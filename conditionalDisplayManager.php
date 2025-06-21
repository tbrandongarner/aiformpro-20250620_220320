private function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_filter( 'aiformpro_render_form', array( $this, 'set_rules' ), 10, 2 );
        add_filter( 'aiformpro_process_submission', array( $this, 'filter_submission' ), 10, 2 );
    }

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function set_rules( $form_html, $form_data ) {
        $raw       = isset( $form_data['conditional_rules'] ) && is_array( $form_data['conditional_rules'] ) ? $form_data['conditional_rules'] : array();
        $sanitized = array();
        foreach ( $raw as $rule ) {
            if ( ! isset( $rule['trigger_field'], $rule['target_field'], $rule['operator'], $rule['action'], $rule['value'] ) ) {
                continue;
            }
            $trigger = sanitize_text_field( $rule['trigger_field'] );
            $target  = sanitize_text_field( $rule['target_field'] );
            $op      = sanitize_text_field( $rule['operator'] );
            $action  = sanitize_text_field( $rule['action'] );
            $value   = $rule['value'];

            $valid_ops = array( 'equals', 'not_equals', 'in', 'not_in', 'greater_than', 'less_than' );
            if ( ! in_array( $op, $valid_ops, true ) ) {
                continue;
            }
            if ( ! in_array( $action, array( 'show', 'hide' ), true ) ) {
                continue;
            }

            if ( in_array( $op, array( 'in', 'not_in' ), true ) ) {
                if ( ! is_array( $value ) ) {
                    $value = array( sanitize_text_field( strval( $value ) ) );
                } else {
                    $value = array_map( 'sanitize_text_field', $value );
                }
            } elseif ( in_array( $op, array( 'greater_than', 'less_than' ), true ) ) {
                $value = floatval( $value );
            } else {
                $value = sanitize_text_field( strval( $value ) );
            }

            $sanitized[] = array(
                'trigger_field' => $trigger,
                'target_field'  => $target,
                'operator'      => $op,
                'action'        => $action,
                'value'         => $value,
            );
        }
        $this->rules = $sanitized;
        return $form_html;
    }

    public function enqueue_assets() {
        $handle = 'aiformpro-conditional-display';
        $src    = plugin_dir_url( __FILE__ ) . 'assets/js/conditional-display.js';
        wp_register_script( $handle, $src, array( 'jquery' ), '1.0', true );
        wp_localize_script( $handle, 'CDM_RULES', $this->rules );
        wp_enqueue_script( $handle );
    }

    public function filter_submission( $submission, $form_data ) {
        $responses = isset( $submission['responses'] ) && is_array( $submission['responses'] ) ? $submission['responses'] : array();
        $rules     = isset( $form_data['conditional_rules'] ) && is_array( $form_data['conditional_rules'] ) ? $form_data['conditional_rules'] : array();
        $visible   = $this->evaluate_rules( $responses, $rules );
        $filtered  = array();
        foreach ( $responses as $field => $value ) {
            if ( ( isset( $visible[ $field ] ) && $visible[ $field ] ) || ! isset( $visible[ $field ] ) ) {
                $filtered[ $field ] = $value;
            }
        }
        $submission['responses'] = $filtered;
        return $submission;
    }

    private function evaluate_rules( $responses, $rules ) {
        $visible = array();
        foreach ( $rules as $rule ) {
            if ( ! isset( $rule['trigger_field'], $rule['target_field'], $rule['operator'], $rule['action'], $rule['value'] ) ) {
                continue;
            }
            $tgt   = $rule['target_field'];
            $match = $this->condition_matches( $responses, $rule );
            if ( 'show' === $rule['action'] ) {
                $visible[ $tgt ] = $match;
            } elseif ( 'hide' === $rule['action'] ) {
                $visible[ $tgt ] = ! $match;
            }
        }
        return $visible;
    }

    private function condition_matches( $responses, $rule ) {
        $src = $rule['trigger_field'];
        if ( ! isset( $responses[ $src ] ) ) {
            return false;
        }
        $val = $responses[ $src ];
        $cmp = $rule['value'];
        switch ( $rule['operator'] ) {
            case 'equals':
                return $val == $cmp;
            case 'not_equals':
                return $val != $cmp;
            case 'in':
                return is_array( $cmp ) && in_array( $val, $cmp );
            case 'not_in':
                return is_array( $cmp ) && ! in_array( $val, $cmp );
            case 'greater_than':
                return is_numeric( $val ) && floatval( $val ) > $cmp;
            case 'less_than':
                return is_numeric( $val ) && floatval( $val ) < $cmp;
            default:
                return false;
        }
    }
}

ConditionalDisplayManager::get_instance();