<?php
namespace AIFormPro;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AIFormPro {
    const VERSION = '1.0.0';
    private static $instance = null;

    private function __construct() {
        $this->define_constants();
        $this->load_includes();
        $this->init_hooks();
    }

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function define_constants() {
        define( 'AIFORMPRO_VERSION', self::VERSION );
        define( 'AIFORMPRO_PLUGIN_FILE', __FILE__ );
        define( 'AIFORMPRO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
        define( 'AIFORMPRO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
        define( 'AIFORMPRO_BUILD_DIR', AIFORMPRO_PLUGIN_DIR . 'build/' );
    }

    private function load_includes() {
        $dir = AIFORMPRO_PLUGIN_DIR . 'includes/';

        require_once $dir . 'class-cpt.php';
        require_once $dir . 'class-rest-api.php';
        require_once $dir . 'class-admin.php';
        require_once $dir . 'class-frontend.php';
        require_once $dir . 'class-shortcode.php';
        require_once $dir . 'class-blocks.php';
        require_once $dir . 'class-payments.php';
    }

    private function init_hooks() {
        add_action( 'init', [ 'AIFormPro\CPT',      'register_post_types' ] );
        add_action( 'init', [ 'AIFormPro\Blocks',   'register_blocks'     ] );
        add_action( 'rest_api_init', [ 'AIFormPro\REST_API', 'register_routes' ] );
        add_action( 'admin_menu', [ 'AIFormPro\Admin',    'register_menu'      ] );
        add_action( 'admin_enqueue_scripts', [ 'AIFormPro\Admin',  'enqueue_assets' ] );
        add_action( 'wp_enqueue_scripts',    [ 'AIFormPro\Frontend','enqueue_assets' ] );
        add_shortcode( 'ai_form', [ 'AIFormPro\Shortcode','render' ] );
        load_plugin_textdomain( 'aiformpro', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    public static function activate() {
        self::get_instance();
        CPT::register_post_types();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }
}

register_activation_hook( __FILE__, [ 'AIFormPro', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'AIFormPro', 'deactivate' ] );

AIFormPro::get_instance();