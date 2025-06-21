<?php

class FormBuilderUI {
    public function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Enqueue admin scripts and styles for the form builder.
     *
     * @param string $hook_suffix The current admin page hook.
     */
    public function enqueue_assets( $hook_suffix ) {
        // Match the actual menu slug: ai_form_pro_builder
        if ( 'toplevel_page_ai_form_pro_builder' !== $hook_suffix ) {
            return;
        }

        // Determine asset version.
        if ( defined( 'AI_FORM_PRO_VERSION' ) ) {
            $version = AI_FORM_PRO_VERSION;
        } else {
            $file    = __DIR__ . '/../assets/js/admin-form-builder.js';
            $version = file_exists( $file ) ? filemtime( $file ) : time();
        }

        $script_handle = 'ai-fbp-builder';
        $script_src    = plugin_dir_url( __FILE__ ) . '../assets/js/admin-form-builder.js';

        wp_register_script(
            $script_handle,
            esc_url( $script_src ),
            [ 'wp-element', 'wp-i18n', 'wp-components', 'wp-data' ],
            $version,
            true
        );

        wp_set_script_translations(
            $script_handle,
            'ai-form-pro',
            __DIR__ . '/../languages'
        );

        $data = [
            'forms'     => function_exists( 'ai_form_pro_get_forms' ) ? ai_form_pro_get_forms() : [],
            'templates' => function_exists( 'ai_form_pro_get_templates' ) ? ai_form_pro_get_templates() : [],
            'restUrl'   => esc_url_raw( rest_url( 'ai-form-pro/v1' ) ),
            'apiNonce'  => wp_create_nonce( 'wp_rest' ),
        ];

        wp_add_inline_script( $script_handle, 'window.AIFPData = ' . wp_json_encode( $data ) . ';', 'before' );
        wp_enqueue_script( $script_handle );

        $style_handle = 'ai-fbp-builder-style';
        $style_src    = plugin_dir_url( __FILE__ ) . '../assets/css/admin-form-builder.css';
        wp_enqueue_style(
            $style_handle,
            esc_url( $style_src ),
            [ 'wp-components' ],
            $version
        );
    }
}

new FormBuilderUI();