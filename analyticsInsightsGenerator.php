<?php

class AnalyticsInsightsGenerator {

    /**
     * WordPress DB instance.
     *
     * @var wpdb
     */
    protected $wpdb;

    /**
     * Analytics table name.
     *
     * @var string
     */
    protected $table_name;

    /**
     * Text domain for translations.
     *
     * @var string
     */
    protected $text_domain = 'ai-form-pro';

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb      = $wpdb;
        $this->table_name = $wpdb->prefix . 'aiformpro_analytics';
    }

    /**
     * Get summary insights for a single form.
     *
     * Consolidates all metrics into a single query for performance.
     *
     * @param int $form_id
     * @return array|WP_Error
     */
    public function getFormInsights( $form_id ) {
        $form_id = intval( $form_id );
        if ( $form_id <= 0 ) {
            return new WP_Error(
                'invalid_form_id',
                __( 'Form ID must be a positive integer.', $this->text_domain )
            );
        }

        $table = $this->wpdb->esc_sql( $this->table_name );
        $sql   = $this->wpdb->prepare(
            "
            SELECT
                COUNT(*) AS views,
                SUM( event_type = 'start' ) AS starts,
                SUM( event_type = 'submit' ) AS submissions,
                SUM( event_type = 'complete' ) AS completions,
                AVG( time_spent ) AS average_time,
                AVG( score ) AS average_score
            FROM {$table}
            WHERE form_id = %d
            ",
            $form_id
        );
        $row = $this->wpdb->get_row( $sql );

        $views       = isset( $row->views ) ? intval( $row->views ) : 0;
        $starts      = isset( $row->starts ) ? intval( $row->starts ) : 0;
        $submissions = isset( $row->submissions ) ? intval( $row->submissions ) : 0;
        $completions = isset( $row->completions ) ? intval( $row->completions ) : 0;
        $avg_time    = $row->average_time !== null ? round( floatval( $row->average_time ), 2 ) : 0.00;
        $avg_score   = $row->average_score !== null ? round( floatval( $row->average_score ), 2 ) : 0.00;
        $conversion  = $views > 0 ? round( ( $completions / $views ) * 100, 2 ) : 0.00;

        return array(
            'form_id'         => $form_id,
            'views'           => $views,
            'starts'          => $starts,
            'submissions'     => $submissions,
            'completions'     => $completions,
            'conversion_rate' => $conversion,
            'average_time'    => $avg_time,
            'average_score'   => $avg_score,
        );
    }

    /**
     * Get insights for multiple forms.
     *
     * @param int $limit
     * @return array
     */
    public function getAllFormsInsights( $limit = 10 ) {
        $limit = intval( $limit );
        $query = new WP_Query(
            array(
                'post_type'      => 'aiformpro_form',
                'posts_per_page' => $limit,
                'fields'         => 'ids',
            )
        );
        $insights = array();
        foreach ( $query->posts as $form_id ) {
            $result = $this->getFormInsights( $form_id );
            if ( ! is_wp_error( $result ) ) {
                $insights[] = $result;
            }
        }
        return $insights;
    }

    /**
     * Count events for a form.
     *
     * @param int    $form_id
     * @param string $event_type
     * @return int
     */
    protected function getEventCount( $form_id, $event_type ) {
        $form_id    = intval( $form_id );
        $event_type = sanitize_text_field( $event_type );
        $table       = $this->wpdb->esc_sql( $this->table_name );
        $sql         = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE form_id = %d AND event_type = %s",
            $form_id,
            $event_type
        );
        return (int) $this->wpdb->get_var( $sql );
    }

    /**
     * Get average value for a numeric column.
     *
     * @param int    $form_id
     * @param string $column
     * @return float
     */
    protected function getAverageValue( $form_id, $column ) {
        $allowed = array( 'time_spent', 'score' );
        if ( ! in_array( $column, $allowed, true ) ) {
            return 0.0;
        }
        $col   = $this->wpdb->esc_sql( $column );
        $table = $this->wpdb->esc_sql( $this->table_name );
        $sql   = $this->wpdb->prepare(
            "SELECT AVG({$col}) FROM {$table} WHERE form_id = %d AND {$col} IS NOT NULL",
            intval( $form_id )
        );
        $value = $this->wpdb->get_var( $sql );
        return $value === null ? 0.0 : round( floatval( $value ), 2 );
    }

    /**
     * Get time-series engagement for a form.
     *
     * @param int    $form_id
     * @param string $period Interval for grouping: DAY, WEEK, MONTH
     * @param int    $points Number of points to retrieve
     * @return array
     */
    public function getEngagementTrend( $form_id, $period = 'DAY', $points = 7 ) {
        $form_id = intval( $form_id );
        $period  = strtoupper( sanitize_key( $period ) );
        if ( ! in_array( $period, array( 'DAY', 'WEEK', 'MONTH' ), true ) ) {
            $period = 'DAY';
        }
        $points = intval( $points );
        if ( $points <= 0 ) {
            $points = 7;
        }

        $table = $this->wpdb->esc_sql( $this->table_name );
        switch ( $period ) {
            case 'WEEK':
                $format   = '%x-%v';
                $group_by = 'YEAR(event_time), WEEK(event_time, 1)';
                break;
            case 'MONTH':
                $format   = '%Y-%m';
                $group_by = 'YEAR(event_time), MONTH(event_time)';
                break;
            case 'DAY':
            default:
                $format   = '%Y-%m-%d';
                $group_by = 'DATE(event_time)';
                break;
        }

        $sql = $this->wpdb->prepare(
            "
            SELECT DATE_FORMAT(event_time, %s) AS period, COUNT(*) AS count
            FROM {$table}
            WHERE form_id = %d
              AND event_time >= DATE_SUB(NOW(), INTERVAL %d {$period})
            GROUP BY {$group_by}
            ORDER BY {$group_by} DESC
            LIMIT %d
            ",
            $format,
            $form_id,
            $points,
            $points
        );

        $results = $this->wpdb->get_results( $sql );
        $trend   = array();
        if ( $results ) {
            foreach ( $results as $row ) {
                $trend[] = array(
                    'period' => $row->period,
                    'count'  => intval( $row->count ),
                );
            }
        }
        return array_reverse( $trend );
    }
}