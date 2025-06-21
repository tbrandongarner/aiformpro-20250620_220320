<?php

class ConsentWorkflowManager {
    const CPT                   = 'aiformpro_consent_workflow';
    const META_CONSENT_STEPS    = '_consent_steps';

    /**
     * Singleton instance.
     *
     * @var ConsentWorkflowManager|null
     */
    private static $instance = null;

    /**
     * Allowed step types.
     *
     * @var array
     */
    private $allowed_step_types = [
        'checkbox',
        'radio',
        'text',
        'textarea',
        'select',
        'custom',
    ];

    /**
     * Constructor.
     */
    private function __construct() {
        add_action( 'init', [ $this, 'register_post_type' ] );
        add_action( 'init', [ $this, 'register_meta' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post', [ $this, 'save_post' ], 10, 2 );
        add_filter( 'aiformpro_render_form', [ $this, 'inject_consent_workflow' ], 10, 2 );
    }

    /**
     * Get singleton instance.
     *
     * @return ConsentWorkflowManager
     */
    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register the CPT for consent workflows.
     */
    public function register_post_type() {
        $labels = [
            'name'          => __( 'Consent Workflows', 'aiformpro' ),
            'singular_name' => __( 'Consent Workflow', 'aiformpro' ),
            'add_new_item'  => __( 'Add New Consent Workflow', 'aiformpro' ),
            'edit_item'     => __( 'Edit Consent Workflow', 'aiformpro' ),
            'all_items'     => __( 'All Consent Workflows', 'aiformpro' ),
        ];
        $args = [
            'labels'          => $labels,
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => 'aiformpro',
            'capability_type' => 'post',
            'supports'        => [ 'title' ],
            'has_archive'     => false,
        ];
        register_post_type( self::CPT, $args );
    }

    /**
     * Register post meta for consent steps.
     */
    public function register_meta() {
        register_post_meta(
            self::CPT,
            self::META_CONSENT_STEPS,
            [
                'type'              => 'array',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => [ $this, 'sanitize_consent_steps' ],
                'auth_callback'     => function( $allowed, $meta_key, $post_id ) {
                    return current_user_can( 'edit_post', $post_id );
                },
            ]
        );
    }

    /**
     * Sanitize consent steps data.
     *
     * @param mixed $value
     * @return array
     */
    public function sanitize_consent_steps( $value ) {
        if ( ! is_array( $value ) ) {
            return [];
        }
        $sanitized = [];
        foreach ( $value as $step ) {
            if ( isset( $step['type'], $step['text'] ) ) {
                $type = sanitize_text_field( $step['type'] );
                if ( in_array( $type, $this->allowed_step_types, true ) ) {
                    $sanitized[] = [
                        'type'     => $type,
                        'text'     => wp_kses_post( $step['text'] ),
                        'required' => ! empty( $step['required'] ),
                    ];
                }
            }
        }
        return $sanitized;
    }

    /**
     * Add meta box for consent steps.
     */
    public function add_meta_boxes() {
        add_meta_box(
            'aiformpro_consent_steps',
            __( 'Consent Steps', 'aiformpro' ),
            [ $this, 'render_meta_box' ],
            self::CPT,
            'normal',
            'high'
        );
    }

    /**
     * Render the consent steps meta box.
     *
     * @param WP_Post $post
     */
    public function render_meta_box( $post ) {
        wp_nonce_field( 'aiformpro_save_consent_steps', 'aiformpro_consent_nonce' );
        $steps = get_post_meta( $post->ID, self::META_CONSENT_STEPS, true ) ?: [];
        echo '<div id="aiformpro-consent-steps-app" data-steps="' . esc_attr( wp_json_encode( $steps ) ) . '"></div>';
        wp_enqueue_script( 'aiformpro-consent-workflow-editor' );
    }

    /**
     * Save post handler.
     *
     * @param int     $post_id
     * @param WP_Post $post
     */
    public function save_post( $post_id, $post ) {
        if ( $post->post_type !== self::CPT ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }
        if ( ! isset( $_POST['aiformpro_consent_nonce'] ) || ! wp_verify_nonce( $_POST['aiformpro_consent_nonce'], 'aiformpro_save_consent_steps' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        if ( isset( $_POST['consent_steps'] ) ) {
            $steps = [];
            foreach ( (array) $_POST['consent_steps'] as $step_json ) {
                $decoded = json_decode( wp_unslash( $step_json ), true );
                if ( is_array( $decoded ) ) {
                    $steps[] = $decoded;
                }
            }
            $sanitized = $this->sanitize_consent_steps( $steps );
            update_post_meta( $post_id, self::META_CONSENT_STEPS, $sanitized );
        } else {
            delete_post_meta( $post_id, self::META_CONSENT_STEPS );
        }
    }

    /**
     * Inject consent workflow HTML into form.
     *
     * @param string $form_html
     * @param object $form
     * @return string
     */
    public function inject_consent_workflow( $form_html, $form ) {
        $workflow_id = get_post_meta( $form->ID, '_consent_workflow_id', true );
        if ( ! $workflow_id ) {
            return $form_html;
        }
        $steps = get_post_meta( $workflow_id, self::META_CONSENT_STEPS, true );
        if ( empty( $steps ) ) {
            return $form_html;
        }
        $consent_html = '<div class="aiformpro-consent-workflow">';
        foreach ( $steps as $index => $step ) {
            $consent_html .= '<div class="consent-step">';
            $consent_html .= wp_kses_post( $step['text'] );
            $required_attr = $step['required'] ? ' required' : '';
            $name         = 'consent_step_' . esc_attr( $index );
            $consent_html .= sprintf(
                '<label><input type="checkbox" name="%1$s"%2$s> %3$s</label>',
                $name,
                $required_attr,
                esc_html__( 'I agree', 'aiformpro' )
            );
            $consent_html .= '</div>';
        }
        $consent_html .= '</div>';
        return $consent_html . $form_html;
    }

    /**
     * Get all workflows.
     *
     * @param array $args
     * @return array
     */
    public function get_workflows( $args = [] ) {
        $defaults = [
            'post_type'      => self::CPT,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ];
        return get_posts( wp_parse_args( $args, $defaults ) );
    }

    /**
     * Get a workflow by ID.
     *
     * @param int $id
     * @return array|null
     */
    public function get_workflow_by_id( $id ) {
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== self::CPT ) {
            return null;
        }
        return [
            'id'    => $id,
            'title' => get_the_title( $id ),
            'steps' => get_post_meta( $id, self::META_CONSENT_STEPS, true ) ?: [],
        ];
    }
}

ConsentWorkflowManager::get_instance();