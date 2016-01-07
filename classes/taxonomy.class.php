<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.
/**
 *
 * Metabox Class
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
class CSFramework_Taxonomy extends CSFramework_Abstract{

  /**
   *
   * metabox options
   * @access public
   * @var array
   *
   */
  public $options = array();

  /**
   *
   * instance
   * @access private
   * @var class
   *
   */
  private static $instance = null;

  // run metabox construct
  public function __construct( $options ){

    $this->options = apply_filters( 'cs_taxonomy_options', $options );

    if( ! empty( $this->options ) ) {

      //add_action( 'category_add_form_fields', 'pippin_taxonomy_add_new_meta_field', 10, 2 );

      $this->addAction( 'admin_init', 'add_meta_box', 10000, 2 );
      
    }

  }

  // instance
  public static function instance( $options = array() ){
    
    if ( is_null( self::$instance ) && CS_ACTIVE_METABOX ) {
      self::$instance = new self( $options );
    }
    return self::$instance;
  }

  // add metabox
  public function add_meta_box( $post_type ) {

    $priority = 11;
    

    foreach ( $this->options as $value ) {


      if( $taxonomy = $value['taxonomy'] ) {
        
        // Assign the current section array to object property, so we can access within callback
        //$this->sections = $value;

        $this->addAction( $taxonomy.'_edit_form_fields', 'render_meta_box_content', $priority, 2 );

        $this->addAction( 'edited_'.$taxonomy, 'save_post', $priority, 2 );

      }

      $priority++;
    }

  }

  // metabox render content
  public function render_meta_box_content( $term, $taxonomy ) {

    
    wp_nonce_field( 'cs-framework-taxonomy', 'cs-framework-taxonomy-nonce' );

    foreach($this->options as $value ) {

        $thisTax = isset( $value['taxonomy'] ) ? $value['taxonomy'] : '';
        
        //Check if current taxonomy match with loop taxonomy
        if( $thisTax !== $taxonomy ) continue;

        $sections = $value['sections'];
        $unique = $value['id'];

        $meta_value = get_term_meta( $term->term_id, $unique, true );
        
        echo '<tr class="form-field term-description-wrap">';

          echo '<td colspan="2">';

            echo '<div class="cs-framework cs-metabox-framework">';

              echo '<div class="cs-body">';

                echo '<div class="cs-content" style="margin-left:0">';

                  echo ( isset( $value['title'] ) ) ? '<div class="cs-main-section-title" style="display:block;margin-bottom:20px;"><h3>'. $value['title'] .'</h3></div>' : '';

                  echo '<div class="cs-sections">';
              
                      foreach( $sections as $val ) {

                        if( isset( $val['fields'] ) ) {

                          $icon = ( isset($val['icon']) ) ? '<i class="'.$val['icon'].'"></i> ' : '';
                          echo ( isset( $val['title'] ) ) ? '<div class="cs-section-title" style="display:block;"><h3>'. $icon.$val['title'] .'</h3></div>' : '';

                          foreach ( $val['fields'] as $field_key => $field ) {

                            $default    = ( isset( $field['default'] ) ) ? $field['default'] : '';
                            $elem_id    = ( isset( $field['id'] ) ) ? $field['id'] : '';
                            $elem_value = ( is_array( $meta_value ) && isset( $meta_value[$elem_id] ) ) ? $meta_value[$elem_id] : $default;
                            echo cs_add_element( $field, $elem_value, $unique );

                          }
                          //echo '</div>';

                        }
                      }
              
                  echo '</div>';

                  echo '<div class="clear"></div>';

                echo '</div>';

              echo '</div>';

            echo '</div>';

          echo '</td>';

        echo '</tr>';
    }

  }

  // save metabox options
  public function save_post( $term_id, $term ) {

    //print_r($_POST);exit;

    $nonce = ( isset( $_POST['cs-framework-taxonomy-nonce'] ) ) ? $_POST['cs-framework-taxonomy-nonce'] : '';

    if ( ! wp_verify_nonce( $nonce, 'cs-framework-taxonomy' ) ) { return $post_id; }

    $errors = array();
    $taxonomy = ( isset( $_POST['taxonomy'] ) ) ? $_POST['taxonomy'] : '';

    /*if ( 'page' == $post_type ) {
      if ( ! current_user_can( 'edit_page', $post_id ) ) { return $post_id; }
    } else {
      if ( ! current_user_can( 'edit_post', $post_id ) ) { return $post_id; }
    }*/

    foreach ( $this->options as $request_value ) {

      if( $taxonomy == $request_value['taxonomy'] ) {

        $request_key = $request_value['id'];
        $meta_value  = get_term_meta( $term_id, $request_key, true );
        $request     = ( isset( $_POST[$request_key] ) ) ? $_POST[$request_key] : array();

        // ignore _nonce
        if( isset( $request['_nonce'] ) ) {
          unset( $request['_nonce'] );
        }

        foreach( $request_value['sections'] as $key => $section ) {

          if( isset( $section['fields'] ) ) {

            foreach( $section['fields'] as $field ) {

              if( isset( $field['type'] ) && isset( $field['id'] ) ) {

                $field_value = ( isset( $_POST[$request_key][$field['id']] ) ) ? $_POST[$request_key][$field['id']] : '';

                // sanitize options
                if( isset( $field['sanitize'] ) && $field['sanitize'] !== false ) {
                  $sanitize_type = $field['sanitize'];
                } else if ( ! isset( $field['sanitize'] ) ) {
                  $sanitize_type = $field['type'];
                }

                if( has_filter( 'cs_sanitize_'. $sanitize_type ) ) {
                  $request[$field['id']] = apply_filters( 'cs_sanitize_' . $sanitize_type, $field_value, $field, $section['fields'] );
                }

                // validate options
                if ( isset( $field['validate'] ) && has_filter( 'cs_validate_'. $field['validate'] ) ) {

                  $validate = apply_filters( 'cs_validate_' . $field['validate'], $field_value, $field, $section['fields'] );

                  if( ! empty( $validate ) ) {

                    $errors[$field['id']] = array( 'code' => $field['id'], 'message' => $validate, 'type' => 'error' );
                    $default_value = isset( $field['default'] ) ? $field['default'] : '';
                    $request[$field['id']] = ( isset( $meta_value[$field['id']] ) ) ? $meta_value[$field['id']] : $default_value;

                  }

                }

              }

            }

          }

        }

        $request = apply_filters( 'cs_save_taxonomy', $request, $request_key, $meta_value, $this );

        if( empty( $request ) ) {

          delete_term_meta( $term_id, $request_key );

        } else {

          if( get_term_meta( $term_id, $request_key, true ) ) {

            update_term_meta( $term_id, $request_key, $request );

          } else {

            add_term_meta( $term_id, $request_key, $request );

          }

        }

        $transient['ids'][$request_key] = $_POST['cs_section_id'][$request_key];
        $transient['errors'] = $errors;

      }

    }

    set_transient( 'cs-taxonomy-transient', $transient, 10 );

  }

}
