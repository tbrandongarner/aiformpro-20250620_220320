<?php
add_action( 'rest_api_init', 'AiFormPro_register_routes' );

function AiFormPro_register_routes() {
    register_rest_route(
        'ai-form-pro/v1',
        '/submit',
        array(
            'methods'             => 'POST',
            'callback'            => 'AiFormPro_SubmitHandler',
            'permission_callback' => '__return_true',
        )
    );
}

function AiFormPro_SubmitHandler( WP_REST_Request $request ) {
    $nonce = $request->get_header( 'X-WP-Nonce' ) ?: $request->get_param( '_wpnonce' );
    if ( ! AiFormPro_verify_nonce( $nonce ) ) {
        return new WP_Error( 'invalid_nonce', __( 'Invalid form submission.', 'aiformpro' ), array( 'status' => 403 ) );
    }

    $raw_data = $request->get_params();
    $data     = AiFormPro_sanitize_data( $raw_data );
    if ( is_wp_error( $data ) ) {
        return $data;
    }

    $evaluation = AiFormPro_evaluate_rules( $data );
    if ( is_wp_error( $evaluation ) ) {
        return $evaluation;
    }
    $data = array_merge( $data, $evaluation );

    if ( ! empty( $data['needs_payment'] ) ) {
        try {
            $payment = AiFormPro_PaymentProcessor::createPaymentIntent( $data );
            return rest_ensure_response( $payment );
        } catch ( Exception $e ) {
            return new WP_Error( 'payment_error', $e->getMessage(), array( 'status' => 500 ) );
        }
    }

    $result = AiFormPro_store_submission( $data );
    if ( is_wp_error( $result ) ) {
        return $result;
    }

    AiFormPro_send_notification( $data );

    return rest_ensure_response( array( 'success' => true ) );
}

function AiFormPro_verify_nonce( $nonce ) {
    if ( empty( $nonce ) ) {
        return false;
    }
    return wp_verify_nonce( $nonce, 'aiformpro_submit' );
}

function AiFormPro_sanitize_data( $data ) {
    if ( empty( $data['form_id'] ) ) {
        return new WP_Error( 'missing_form_id', __( 'Form ID is required.', 'aiformpro' ), array( 'status' => 400 ) );
    }
    $sanitized = array();
    foreach ( $data as $key => $value ) {
        if ( is_array( $value ) ) {
            $sanitized[ $key ] = array_map( 'sanitize_text_field', $value );
        } else {
            $sanitized[ $key ] = sanitize_text_field( $value );
        }
    }
    return $sanitized;
}

function AiFormPro_evaluate_rules( $data ) {
    $result = array(
        'needs_payment' => false,
        'score'         => 0,
    );

    $form_id     = intval( $data['form_id'] );
    $form_config = get_option( "aiformpro_form_{$form_id}" );

    if ( $form_config && ! empty( $form_config['rules'] ) && is_array( $form_config['rules'] ) ) {
        foreach ( $form_config['rules'] as $rule ) {
            $field = isset( $rule['field'] ) ? $rule['field'] : '';
            if ( empty( $field ) || ! isset( $data[ $field ] ) ) {
                continue;
            }
            if ( isset( $rule['condition'], $rule['value'] ) ) {
                $value = sanitize_text_field( $rule['value'] );
                if ( $data[ $field ] === $value ) {
                    if ( ! empty( $rule['set_payment'] ) ) {
                        $result['needs_payment'] = true;
                    }
                    if ( isset( $rule['score'] ) ) {
                        $result['score'] += intval( $rule['score'] );
                    }
                }
            }
        }
    }
    return $result;
}

function AiFormPro_store_submission( $data ) {
    global $wpdb;
    $table  = $wpdb->prefix . 'aiformpro_submissions';
    $insert = array(
        'form_id'         => intval( $data['form_id'] ),
        'submission_data' => maybe_serialize( $data ),
        'score'           => isset( $data['score'] ) ? intval( $data['score'] ) : 0,
        'needs_payment'   => ! empty( $data['needs_payment'] ) ? 1 : 0,
        'created_at'      => current_time( 'mysql' ),
    );
    $format = array( '%d', '%s', '%d', '%d', '%s' );
    $result = $wpdb->insert( $table, $insert, $format );
    if ( false === $result ) {
        return new WP_Error( 'db_insert_error', __( 'Could not store submission.', 'aiformpro' ), array( 'status' => 500 ) );
    }
    return $wpdb->insert_id;
}

function AiFormPro_send_notification( $data ) {
    $form_id    = intval( $data['form_id'] );
    $form_title = get_post_field( 'post_title', $form_id ) ?: __( 'AI Form Submission', 'aiformpro' );
    $to         = get_option( 'admin_email' );
    $subject    = sprintf( __( 'New submission for %s', 'aiformpro' ), $form_title );
    $message    = '';
    foreach ( $data as $key => $value ) {
        if ( '_wpnonce' === $key ) {
            continue;
        }
        if ( is_array( $value ) ) {
            $value = implode( ', ', $value );
        }
        $message .= sprintf( "%s: %s\n", $key, $value );
    }
    wp_mail( $to, $subject, $message );
}

class AiFormPro_PaymentProcessor {
    public static function createPaymentIntent( $data ) {
        if ( empty( $data['payment_amount'] ) || empty( $data['currency'] ) ) {
            throw new Exception( __( 'Payment amount or currency missing.', 'aiformpro' ) );
        }
        if ( ! class_exists( '\\Stripe\\Stripe' ) || ! class_exists( '\\Stripe\\PaymentIntent' ) ) {
            throw new Exception( __( 'Stripe SDK not loaded.', 'aiformpro' ) );
        }
        \Stripe\Stripe::setApiKey( get_option( 'ai_form_pro_stripe_secret' ) );
        $intent = \Stripe\PaymentIntent::create( array(
            'amount'   => intval( $data['payment_amount'] ),
            'currency' => sanitize_text_field( $data['currency'] ),
            'metadata' => array(
                'form_id'    => intval( $data['form_id'] ),
                'user_email' => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '',
            ),
        ) );
        return array(
            'client_secret' => $intent->client_secret,
            'intent_id'     => $intent->id,
        );
    }
}