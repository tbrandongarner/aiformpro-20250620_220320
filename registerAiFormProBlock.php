function aiformpro_register_form_block() {
    $dir = plugin_dir_path( __FILE__ );
    $url = plugin_dir_url( __FILE__ );

    // Register block editor script.
    $script_handle = 'ai-fbp-block-js';
    $asset_file     = $dir . 'build/index.asset.php';
    if ( file_exists( $asset_file ) ) {
        $asset = require $asset_file;
        wp_register_script(
            $script_handle,
            $url . 'build/index.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );
    } else {
        wp_register_script(
            $script_handle,
            $url . 'build/index.js',
            array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-block-editor' ),
            filemtime( $dir . 'build/index.js' ),
            true
        );
    }

    // Enable script translations.
    wp_set_script_translations( $script_handle, 'ai-form-pro', $dir . 'languages' );

    // Register front-end and editor styles if present.
    $style_handle        = 'ai-fbp-block-style';
    $editor_style_handle = 'ai-fbp-block-editor-style';
    $has_style           = file_exists( $dir . 'build/style.css' );
    $has_editor_style    = file_exists( $dir . 'build/editor.css' );

    if ( $has_style ) {
        wp_register_style(
            $style_handle,
            $url . 'build/style.css',
            array(),
            filemtime( $dir . 'build/style.css' )
        );
    }

    if ( $has_editor_style ) {
        wp_register_style(
            $editor_style_handle,
            $url . 'build/editor.css',
            array( $style_handle ),
            filemtime( $dir . 'build/editor.css' )
        );
    }

    // Prepare block registration arguments.
    $block_json = $dir . 'block.json';
    $args       = array(
        'editor_script'   => $script_handle,
        'render_callback' => 'renderAiFormBlock',
    );

    if ( $has_style ) {
        $args['style'] = $style_handle;
    }
    if ( $has_editor_style ) {
        $args['editor_style'] = $editor_style_handle;
    }

    register_block_type( $block_json, $args );
}

add_action( 'init', 'aiformpro_register_form_block' );