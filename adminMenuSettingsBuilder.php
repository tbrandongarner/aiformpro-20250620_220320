* @var string
     */
    private $page_slug = 'aiformpro';

    /**
     * Settings option group.
     *
     * @var string
     */
    private $option_group = 'aiformpro';

    /**
     * Settings option name.
     *
     * @var string
     */
    private $option_name = 'aiformpro_options';

    /**
     * Default option values.
     *
     * @var array
     */
    private $default_options = array(
        'api_key'            => '',
        'default_ai_model'   => 'gpt-3.5-turbo',
    );

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'settings_init' ] );
    }

    /**
     * Add the top-level admin menu item.
     */
    public function add_admin_menu() {
        add_menu_page(
            esc_html__( 'AIFormPro', 'aiformpro' ),
            esc_html__( 'AIFormPro', 'aiformpro' ),
            'manage_options',
            $this->page_slug,
            [ $this, 'render_admin_page' ],
            'dashicons-feedback',
            65
        );
    }

    /**
     * Initialize settings: register setting, add sections and fields.
     */
    public function settings_init() {
        register_setting(
            $this->option_group,
            $this->option_name,
            [
                'sanitize_callback' => [ $this, 'sanitize_options' ],
            ]
        );

        add_settings_section(
            'aiformpro_general_section',
            esc_html__( 'General Settings', 'aiformpro' ),
            [ $this, 'general_section_callback' ],
            $this->page_slug
        );

        add_settings_field(
            'api_key',
            esc_html__( 'API Key', 'aiformpro' ),
            [ $this, 'render_api_key_field' ],
            $this->page_slug,
            'aiformpro_general_section',
            [ 'label_for' => 'api_key' ]
        );

        add_settings_field(
            'default_ai_model',
            esc_html__( 'Default AI Model', 'aiformpro' ),
            [ $this, 'render_default_model_field' ],
            $this->page_slug,
            'aiformpro_general_section',
            [ 'label_for' => 'default_ai_model' ]
        );
    }

    /**
     * Callback to display section description.
     */
    public function general_section_callback() {
        echo '<p>' . esc_html__( 'Configure AIFormPro core settings.', 'aiformpro' ) . '</p>';
    }

    /**
     * Sanitize and validate input.
     *
     * @param array $input Submitted input.
     * @return array Sanitized values merged with defaults.
     */
    public function sanitize_options( $input ) {
        $new = [];

        if ( isset( $input['api_key'] ) ) {
            $new['api_key'] = sanitize_text_field( $input['api_key'] );
        }

        $allowed_models = [ 'gpt-3.5-turbo', 'gpt-4' ];
        if ( isset( $input['default_ai_model'] ) && in_array( $input['default_ai_model'], $allowed_models, true ) ) {
            $new['default_ai_model'] = $input['default_ai_model'];
        }

        // Merge with defaults to ensure all keys exist.
        return wp_parse_args( $new, $this->default_options );
    }

    /**
     * Render the API Key input field.
     */
    public function render_api_key_field() {
        $options = get_option( $this->option_name, $this->default_options );
        ?>
        <label for="api_key">
            <input
                type="text"
                id="api_key"
                name="<?php echo esc_attr( $this->option_name ); ?>[api_key]"
                value="<?php echo esc_attr( $options['api_key'] ); ?>"
                class="regular-text"
            />
        </label>
        <?php
    }

    /**
     * Render the Default AI Model select field.
     */
    public function render_default_model_field() {
        $options = get_option( $this->option_name, $this->default_options );
        $models  = [
            'gpt-3.5-turbo' => esc_html__( 'GPT-3.5 Turbo', 'aiformpro' ),
            'gpt-4'         => esc_html__( 'GPT-4', 'aiformpro' ),
        ];
        ?>
        <label for="default_ai_model">
            <select
                id="default_ai_model"
                name="<?php echo esc_attr( $this->option_name ); ?>[default_ai_model]"
            >
                <?php foreach ( $models as $value => $label ) : ?>
                    <option value="<?php echo esc_attr( $value ); ?>"
                        <?php selected( $options['default_ai_model'], $value ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php
    }

    /**
     * Render the admin settings page.
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'AIFormPro Settings', 'aiformpro' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( $this->option_group );
                do_settings_sections( $this->page_slug );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}

new AIFormPro_Settings_Page();