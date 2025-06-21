* @var AiFormPro_PluginBootstrapManager
     */
    private static $instance = null;

    /**
     * @var string
     */
    private $pluginFile;

    private function __construct( $pluginFile ) {
        $this->pluginFile = $pluginFile;

        // Load text domain for translations.
        add_action( 'plugins_loaded', [ $this, 'loadTextdomain' ] );

        register_activation_hook( $this->pluginFile, [ $this, 'onActivate' ] );
        register_deactivation_hook( $this->pluginFile, [ $this, 'onDeactivate' ] );

        add_action( 'init', [ $this, 'initCPTsAndTax' ] );
        add_action( 'admin_menu', [ $this, 'registerSettingsPage' ] );
        add_action( 'admin_init', [ $this, 'registerSettings' ] );
    }

    /**
     * Singleton runner.
     *
     * @param string $pluginFile
     * @return AiFormPro_PluginBootstrapManager
     */
    public static function run( $pluginFile ) {
        if ( null === self::$instance ) {
            self::$instance = new self( $pluginFile );
        }
        return self::$instance;
    }

    /**
     * Activation callback.
     */
    public function onActivate() {
        // Ensure CPTs and taxonomies are registered before flushing.
        $this->initCPTsAndTax();

        $this->createDbTables();
        $this->setDefaultOptions();
        flush_rewrite_rules();
    }

    /**
     * Deactivation callback.
     */
    public function onDeactivate() {
        flush_rewrite_rules();
    }

    /**
     * Load plugin textdomain.
     */
    public function loadTextdomain() {
        load_plugin_textdomain(
            'ai-form-pro',
            false,
            dirname( plugin_basename( $this->pluginFile ) ) . '/languages'
        );
    }

    /**
     * Register custom post type and taxonomy.
     */
    public function initCPTsAndTax() {
        register_post_type( 'aiformpro_form', [
            'labels'             => [
                'name'          => __( 'AI Forms', 'ai-form-pro' ),
                'singular_name' => __( 'AI Form', 'ai-form-pro' ),
            ],
            'public'             => true,
            'has_archive'        => true,
            'show_in_rest'       => true,
            'supports'           => [ 'title', 'editor', 'custom-fields' ],
            'rewrite'            => [ 'slug' => 'ai-forms' ],
        ] );

        register_taxonomy( 'aiformpro_category', 'aiformpro_form', [
            'labels'       => [
                'name'          => __( 'Categories', 'ai-form-pro' ),
                'singular_name' => __( 'Category', 'ai-form-pro' ),
            ],
            'hierarchical' => true,
            'show_in_rest' => true,
            'rewrite'      => [ 'slug' => 'ai-form-categories' ],
        ] );
    }

    /**
     * Register settings page under Settings menu.
     */
    public function registerSettingsPage() {
        add_options_page(
            __( 'AI Form Pro Settings', 'ai-form-pro' ),
            __( 'AI Form Pro', 'ai-form-pro' ),
            'manage_options',
            'ai-form-pro',
            [ $this, 'settingsPageCallback' ]
        );
    }

    /**
     * Register plugin settings, sections and fields.
     */
    public function registerSettings() {
        register_setting(
            'aiformpro_settings_group',
            'aiformpro_options',
            [
                'sanitize_callback' => [ $this, 'sanitizeOptions' ],
                'default'           => [],
            ]
        );

        add_settings_section(
            'aiformpro_general_section',
            __( 'General Settings', 'ai-form-pro' ),
            '__return_false',
            'ai-form-pro'
        );

        add_settings_field(
            'api_key',
            __( 'API Key', 'ai-form-pro' ),
            [ $this, 'fieldApiKey' ],
            'ai-form-pro',
            'aiformpro_general_section'
        );
    }

    /**
     * Sanitize plugin options.
     *
     * @param array $input
     * @return array
     */
    public function sanitizeOptions( $input ) {
        $output = [];
        if ( isset( $input['api_key'] ) ) {
            $output['api_key'] = sanitize_text_field( $input['api_key'] );
        }
        return $output;
    }

    /**
     * Render API Key field.
     */
    public function fieldApiKey() {
        $options = get_option( 'aiformpro_options', [] );
        $value   = isset( $options['api_key'] ) ? esc_attr( $options['api_key'] ) : '';
        printf(
            '<input type="text" id="api_key" name="aiformpro_options[api_key]" value="%s" class="regular-text" />',
            $value
        );
    }

    /**
     * Settings page HTML.
     */
    public function settingsPageCallback() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'AI Form Pro Settings', 'ai-form-pro' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'aiformpro_settings_group' );
                do_settings_sections( 'ai-form-pro' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Create plugin database tables.
     */
    private function createDbTables() {
        global $wpdb;
        $table_name      = $wpdb->prefix . 'aiformpro_entries';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id BIGINT UNSIGNED NOT NULL,
            entry_data LONGTEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Set default options on activation.
     */
    private function setDefaultOptions() {
        $defaults = [
            'api_key' => '',
        ];
        add_option( 'aiformpro_options', $defaults );
    }

    /**
     * Uninstall callback: remove data and options.
     */
    public static function onUninstall() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiformpro_entries';
        $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
        delete_option( 'aiformpro_options' );
    }
}

// Initialize plugin.
AiFormPro_PluginBootstrapManager::run( __FILE__ );

// Uninstall hook.
register_uninstall_hook( __FILE__, [ 'AiFormPro_PluginBootstrapManager', 'onUninstall' ] );