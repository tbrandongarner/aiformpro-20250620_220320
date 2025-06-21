<?php
if ( ! class_exists( 'AIFormPro_Widget_Handler' ) ) {
    class AIFormPro_Widget_Handler {
        public function __construct() {
            add_action( 'widgets_init', array( $this, 'register_widget' ) );
            add_shortcode( 'aiformpro', array( $this, 'shortcode' ) );
        }

        public function register_widget() {
            register_widget( 'AIFormPro_Widget' );
        }

        public function shortcode( $atts ) {
            $atts = shortcode_atts( array(
                'id' => '',
            ), $atts, 'aiformpro' );
            $form_id = sanitize_text_field( $atts['id'] );
            if ( empty( $form_id ) ) {
                return '';
            }
            if ( function_exists( 'aiFormPro_render_form' ) ) {
                return aiFormPro_render_form( $form_id );
            }
            return '<div class="aiformpro-form" data-form-id="' . esc_attr( $form_id ) . '"></div>';
        }
    }

    new AIFormPro_Widget_Handler();
}

if ( ! class_exists( 'AIFormPro_Widget' ) ) {
    class AIFormPro_Widget extends WP_Widget {
        public function __construct() {
            parent::__construct(
                'aiformpro_widget',
                __( 'AIFormPro Form', 'aiformpro' ),
                array( 'description' => __( 'Embed an AIFormPro form', 'aiformpro' ) )
            );
        }

        public function widget( $args, $instance ) {
            echo $args['before_widget'];
            if ( ! empty( $instance['title'] ) ) {
                echo $args['before_title']
                    . apply_filters( 'widget_title', $instance['title'] )
                    . $args['after_title'];
            }
            if ( ! empty( $instance['form_id'] ) ) {
                echo do_shortcode( '[aiformpro id="' . esc_attr( $instance['form_id'] ) . '"]' );
            }
            echo $args['after_widget'];
        }

        public function form( $instance ) {
            $title   = ! empty( $instance['title'] ) ? $instance['title'] : '';
            $form_id = ! empty( $instance['form_id'] ) ? $instance['form_id'] : '';
            $forms   = $this->get_forms();
            ?>
            <p>
                <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
                    <?php esc_html_e( 'Title:', 'aiformpro' ); ?>
                </label>
                <input
                    class="widefat"
                    id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
                    name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
                    type="text"
                    value="<?php echo esc_attr( $title ); ?>"
                />
            </p>
            <p>
                <label for="<?php echo esc_attr( $this->get_field_id( 'form_id' ) ); ?>">
                    <?php esc_html_e( 'Select Form:', 'aiformpro' ); ?>
                </label>
                <select
                    class="widefat"
                    id="<?php echo esc_attr( $this->get_field_id( 'form_id' ) ); ?>"
                    name="<?php echo esc_attr( $this->get_field_name( 'form_id' ) ); ?>"
                >
                    <option value=""><?php esc_html_e( '-- Select --', 'aiformpro' ); ?></option>
                    <?php foreach ( $forms as $form ) : ?>
                        <option
                            value="<?php echo esc_attr( $form['id'] ); ?>"
                            <?php selected( $form_id, $form['id'] ); ?>
                        >
                            <?php echo esc_html( $form['title'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            <?php
        }

        public function update( $new_instance, $old_instance ) {
            $instance            = array();
            $instance['title']   = sanitize_text_field( $new_instance['title'] );
            $instance['form_id'] = sanitize_text_field( $new_instance['form_id'] );
            return $instance;
        }

        protected function get_forms() {
            if ( class_exists( 'AIFormPro_Form_Model' ) ) {
                $forms  = AIFormPro_Form_Model::get_all();
                $result = array();
                foreach ( $forms as $form ) {
                    $result[] = array(
                        'id'    => $form->id,
                        'title' => $form->title,
                    );
                }
                return $result;
            }

            if ( function_exists( 'aiFormPro_get_forms_list' ) ) {
                return aiFormPro_get_forms_list();
            }

            return array();
        }
    }
}