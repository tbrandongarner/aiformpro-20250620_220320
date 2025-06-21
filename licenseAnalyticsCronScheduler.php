const CRON_HOOK = 'aiformpro_daily_license_analytics';

    public static function init() {
        add_action( 'init', array( __CLASS__, 'schedule_event' ) );
        add_action( self::CRON_HOOK, array( __CLASS__, 'handle_cron_job' ) );
        if ( defined( 'AIFORMPRO_PLUGIN_FILE' ) ) {
            register_activation_hook( AIFORMPRO_PLUGIN_FILE, array( __CLASS__, 'on_activation' ) );
            register_deactivation_hook( AIFORMPRO_PLUGIN_FILE, array( __CLASS__, 'on_deactivation' ) );
        }
    }

    public static function on_activation() {
        self::schedule_event( true );
    }

    public static function on_deactivation() {
        self::clear_scheduled_event();
    }

    public static function schedule_event( $force = false ) {
        if ( $force || ! wp_next_scheduled( self::CRON_HOOK ) ) {
            // Calculate next local 01:00:00
            $local_timestamp = strtotime( 'tomorrow 01:00:00', current_time( 'timestamp' ) );
            // Convert local to GMT
            $gmt_offset     = get_option( 'gmt_offset', 0 );
            $gmt_timestamp  = intval( $local_timestamp - ( $gmt_offset * HOUR_IN_SECONDS ) );
            wp_schedule_event( $gmt_timestamp, 'daily', self::CRON_HOOK );
        }
    }

    public static function clear_scheduled_event() {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    public static function handle_cron_job() {
        if ( ! class_exists( 'AIFormPro_License_Manager' ) ) {
            return;
        }

        $license_manager = AIFormPro_License_Manager::get_instance();
        $data = array(
            'timestamp'       => current_time( 'mysql' ),
            'total_licenses'  => $license_manager->get_total_licenses(),
            'active_licenses' => $license_manager->get_active_licenses_count(),
            'expired_licenses'=> $license_manager->get_expired_licenses_count(),
            'renewals_due'    => $license_manager->get_licenses_due_for_renewal(),
        );

        $option_key = 'aiformpro_license_analytics_' . date( 'Y_m_d', current_time( 'timestamp' ) );
        if ( false === add_option( $option_key, $data, '', 'no' ) ) {
            update_option( $option_key, $data );
        }
    }
}

LicenseAnalyticsCronScheduler::init();