public static function init() {
        add_action( 'rest_api_init', function() {
            register_rest_route( 'ai-form-pro/v1', '/create-payment-intent', array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'create_payment_intent_endpoint' ),
                'permission_callback' => array( __CLASS__, 'check_api_permission' ),
            ) );
            register_rest_route( 'ai-form-pro/v1', '/stripe-webhook', array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'handle_webhook_endpoint' ),
                'permission_callback' => '__return_true',
            ) );
        } );
    }

    public static function check_api_permission( \WP_REST_Request $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return false;
        }
        return true;
    }

    public static function createPaymentIntent( $data ) {
        $secret_key = get_option( 'ai_form_pro_stripe_secret_key' );
        if ( empty( $secret_key ) ) {
            return new \WP_Error( 'missing_api_key', 'Stripe API key is not configured.', array( 'status' => 500 ) );
        }

        if ( ! isset( $data['amount'] ) || ! is_numeric( $data['amount'] ) || intval( $data['amount'] ) <= 0 ) {
            return new \WP_Error( 'invalid_amount', 'Amount must be a positive integer.', array( 'status' => 400 ) );
        }

        Stripe::setApiKey( $secret_key );

        $params = array(
            'amount'   => intval( $data['amount'] ),
            'currency' => isset( $data['currency'] ) ? sanitize_text_field( $data['currency'] ) : 'usd',
            'metadata' => array(),
        );

        if ( isset( $data['metadata'] ) && is_array( $data['metadata'] ) ) {
            $sanitized_meta = array();
            foreach ( $data['metadata'] as $key => $value ) {
                $sanitized_key               = sanitize_text_field( $key );
                $sanitized_meta[ $sanitized_key ] = sanitize_text_field( $value );
            }
            $params['metadata'] = $sanitized_meta;
        }

        if ( isset( $data['payment_method_types'] ) && is_array( $data['payment_method_types'] ) ) {
            $params['payment_method_types'] = array_map( 'sanitize_text_field', $data['payment_method_types'] );
        }

        try {
            return PaymentIntent::create( $params );
        } catch ( \Exception $e ) {
            return new \WP_Error( 'stripe_error', $e->getMessage(), array( 'status' => 400 ) );
        }
    }

    public static function handleWebhook() {
        $payload    = @file_get_contents( 'php://input' );
        $sig_header = isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';
        $secret     = get_option( 'ai_form_pro_stripe_webhook_secret' );

        if ( empty( $secret ) ) {
            return new \WP_REST_Response( 'Webhook secret is not configured.', 400 );
        }

        try {
            $event = Webhook::constructEvent( $payload, $sig_header, $secret );
        } catch ( SignatureVerificationException $e ) {
            return new \WP_REST_Response( 'Signature verification failed.', 400 );
        } catch ( UnexpectedValueException $e ) {
            return new \WP_REST_Response( 'Invalid payload.', 400 );
        }

        switch ( $event->type ) {
            case 'payment_intent.succeeded':
            case 'payment_intent.payment_failed':
                $intent = $event->data->object;
                self::update_payment_record( $intent );
                break;
            default:
                break;
        }

        return new \WP_REST_Response( 'Received', 200 );
    }

    private static function update_payment_record( $intent ) {
        global $wpdb;
        $table     = $wpdb->prefix . 'ai_form_pro_payments';
        $status    = sanitize_text_field( $intent->status );
        $intent_id = sanitize_text_field( $intent->id );

        $result = $wpdb->update(
            $table,
            array( 'status' => $status ),
            array( 'payment_intent_id' => $intent_id ),
            array( '%s' ),
            array( '%s' )
        );

        if ( false === $result ) {
            error_log( sprintf( 'AIFormPro PaymentProcessor: Failed to update payment record for Intent ID %s', $intent_id ) );
        }

        return $result;
    }

    public static function create_payment_intent_endpoint( \WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $intent = self::createPaymentIntent( $params );
        if ( is_wp_error( $intent ) ) {
            return rest_ensure_response( $intent );
        }
        $response = array(
            'clientSecret' => isset( $intent->client_secret ) ? $intent->client_secret : '',
            'id'           => isset( $intent->id ) ? $intent->id : '',
        );
        return rest_ensure_response( $response );
    }

    public static function handle_webhook_endpoint( \WP_REST_Request $request ) {
        return self::handleWebhook();
    }
}

AIFormPro_PaymentProcessor::init();