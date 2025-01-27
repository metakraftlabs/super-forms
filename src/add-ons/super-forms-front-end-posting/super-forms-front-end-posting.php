<?php
/**
 * Super Forms - Front-end Posting
 *
 * @package   Super Forms - Front-end Posting
 * @author    feeling4design
 * @link      http://codecanyon.net/item/super-forms-drag-drop-form-builder/13979866
 * @copyright 2019 by feeling4design
 *
 * @wordpress-plugin
 * Plugin Name: Super Forms - Front-end Posting
 * Plugin URI:  http://codecanyon.net/item/super-forms-drag-drop-form-builder/13979866
 * Description: Let visitors create posts from your front-end website
 * Version:     1.3.10
 * Author:      feeling4design
 * Author URI:  http://codecanyon.net/user/feeling4design
 * Text Domain: super-forms
 * Domain Path: /i18n/languages/
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if(!class_exists('SUPER_Frontend_Posting')) :


    /**
     * Main SUPER_Frontend_Posting Class
     *
     * @class SUPER_Frontend_Posting
     */
    final class SUPER_Frontend_Posting {
    
        
        /**
         * @var string
         *
         *	@since		1.0.0
        */
        public $version = '1.3.10';

        
        /**
         * @var string
         *
         *  @since      1.1.0
        */
        public $add_on_slug = 'front-end-posting';
        public $add_on_name = 'Front-end Posting';


        /**
         * @var SUPER_Frontend_Posting The single instance of the class
         *
         *	@since		1.0.0
        */
        protected static $_instance = null;

        
        /**
         * Main SUPER_Frontend_Posting Instance
         *
         * Ensures only one instance of SUPER_Frontend_Posting is loaded or can be loaded.
         *
         * @static
         * @see SUPER_Frontend_Posting()
         * @return SUPER_Frontend_Posting - Main instance
         *
         *	@since		1.0.0
        */
        public static function instance() {
            if(is_null( self::$_instance)){
                self::$_instance = new self();
            }
            return self::$_instance;
        }

        
        /**
         * SUPER_Frontend_Posting Constructor.
         *
         *	@since		1.0.0
        */
        public function __construct(){
            $this->init_hooks();
            do_action('SUPER_Frontend_Posting_loaded');
        }

        
        /**
         * Define constant if not already set
         *
         * @param  string $name
         * @param  string|bool $value
         *
         *	@since		1.0.0
        */
        private function define($name, $value){
            if(!defined($name)){
                define($name, $value);
            }
        }

        
        /**
         * What type of request is this?
         *
         * string $type ajax, frontend or admin
         * @return bool
         *
         *	@since		1.0.0
        */
        private function is_request($type){
            switch ($type){
                case 'admin' :
                    return is_admin();
                case 'ajax' :
                    return defined( 'DOING_AJAX' );
                case 'cron' :
                    return defined( 'DOING_CRON' );
                case 'frontend' :
                    return (!is_admin() || defined('DOING_AJAX')) && ! defined('DOING_CRON');
            }
        }

        
        /**
         * Hook into actions and filters
         *
         *	@since		1.0.0
        */
        private function init_hooks() {
            
            add_action( 'init', array( $this, 'load_plugin_textdomain' ), 0 );
            
            // Filters since 1.2.2
            add_filter( 'super_redirect_url_filter', array( $this, 'redirect_to_post' ), 10, 2 );
            
            if ( $this->is_request( 'admin' ) ) {
                
                // Filters since 1.0.0
                add_filter( 'super_settings_after_smtp_server_filter', array( $this, 'add_settings' ), 10, 2 );
                add_filter( 'super_shortcodes_after_form_elements_filter', array( $this, 'add_text_field_settings' ), 10, 2 );
                
                // Actions since 1.1.0
                add_action( 'init', array( $this, 'update_plugin' ) );

                // Actions since 1.1.4
                add_action( 'all_admin_notices', array( $this, 'display_activation_msg' ) );   

            }
            
            if ( $this->is_request( 'ajax' ) ) {

                // Actions since 1.0.0
                add_action( 'super_before_email_success_msg_action', array( $this, 'before_email_success_msg' ) );

            }
            
        }


        /**
         * Load Localisation files.
         * Note: the first-loaded translation file overrides any following ones if the same translation is present.
         */
        public function load_plugin_textdomain() {
            $locale = apply_filters( 'plugin_locale', get_locale(), 'super-forms' );

            load_textdomain( 'super-forms', WP_LANG_DIR . '/super-forms-' . $this->add_on_slug . '/super-forms-' . $this->add_on_slug . '-' . $locale . '.mo' );
            load_plugin_textdomain( 'super-forms', false, plugin_basename( dirname( __FILE__ ) ) . '/i18n/languages' );
        }
        

        /**
         * Display activation message for automatic updates
        */
        public function display_activation_msg() {
            if( !class_exists('SUPER_Forms') ) {
                echo '<div class="notice notice-error">'; // notice-success
                    echo '<p>';
                    echo sprintf( 
                        esc_html__( '%sPlease note:%s You must install and activate %4$s%1$sSuper Forms%2$s%5$s in order to be able to use %1$s%s%2$s!', 'super_forms' ), 
                        '<strong>', 
                        '</strong>', 
                        'Super Forms - ' . $this->add_on_name, 
                        '<a target="_blank" href="https://codecanyon.net/item/super-forms-drag-drop-form-builder/13979866">', 
                        '</a>' 
                    );
                    echo '</p>';
                echo '</div>';
            }
        }

        
        /**
         * Automatically update plugin from the repository
        */
        public function update_plugin() {
            if( defined('SUPER_PLUGIN_DIR') ) {
                if(include( SUPER_PLUGIN_DIR . '/includes/admin/plugin-update-checker/plugin-update-checker.php')){
                    $MyUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
                        'http://f4d.nl/@super-forms-updates/?action=get_metadata&slug=super-forms-' . $this->add_on_slug,  //Metadata URL
                        __FILE__, //Full path to the main plugin file.
                        'super-forms-' . $this->add_on_slug //Plugin slug. Usually it's the same as the name of the directory.
                    );
                }
            }
        }


        /**
         * Redirect to newly created Post
         * 
         * @since       1.2.2
        */
        public function redirect_to_post( $url, $attr ) {

            // Only check for URL in the session if setting was enabled
            // Check if option to redirect to created post is enabled in form settings
            if( !empty($attr['settings']['frontend_posting_redirect'] ) ) {
           
                // If setting was enabled, let's check if we can find the Post ID in the stored session
                $post_id = SUPER_Forms()->session->get( 'super_forms_frontend_posting_created_post' );
                $url = get_permalink( $post_id );
                
                // Make sure to reset the session to clear it from the database, and so that we won't have a redirect conflict with other possible forms
                SUPER_Forms()->session->set( 'super_forms_frontend_posting_created_post', false );
            }
            return $url;
        }


        /**
         * Hook into settings and add Text field settings
         *
         *  @since      1.0.0
        */
        public static function add_text_field_settings( $array, $attributes ) {
            
            // Make sure that older Super Forms versions also have the 
            // filter attribute set to true for the name setting field for text field element:
            $array['form_elements']['shortcodes']['text']['atts']['general']['fields']['name']['filter'] = true;

            // Now add the taxonomy settings field
            $fields_array = $array['form_elements']['shortcodes']['text']['atts']['general']['fields'];
            $res = array_slice($fields_array, 0, 1, true);
            $taxonomy['tag_taxonomy'] = array(
                'name' => esc_html__( 'The tag taxonomy name (e.g: post_tag or product_tag)', 'super-forms' ),
                'desc' => esc_html__( 'Required to connect the post to tags (if found)', 'super-forms' ),
                'default'=> ( !isset( $attributes['tag_taxonomy'] ) ? '' : $attributes['tag_taxonomy'] ),
                'filter' => true,
                'parent' => 'name',
                'filter_value' => 'tags_input',
                'required' => true
            );
            $taxonomy['cat_taxonomy'] = array(
                'name' => esc_html__( 'The cat taxonomy name (e.g: category or product_cat)', 'super-forms' ),
                'desc' => esc_html__( 'Required to connect the post to categories (if found)', 'super-forms' ),
                'default'=> ( !isset( $attributes['cat_taxonomy'] ) ? '' : $attributes['cat_taxonomy'] ),
                'filter' => true,
                'parent' => 'name',
                'filter_value' => 'tax_input',
                'required' => true
            );
            $res = $res + $taxonomy + array_slice($fields_array, 1, count($fields_array) - 1, true);

            $array['form_elements']['shortcodes']['text']['atts']['general']['fields'] = $res;
            return $array;

        }


        /**
         * Hook into before sending email and check if we need to create or update a post or taxonomy
         *
         *  @since      1.0.0
        */
        public static function before_email_success_msg( $atts ) {

            $settings = $atts['settings'];
            if( isset( $atts['data'] ) ) {
                $data = $atts['data'];
            }else{
                if( $settings['save_contact_entry']=='yes' ) {
                    $data = get_post_meta( $atts['entry_id'], '_super_contact_entry_data', true );
                }else{
                    $data = $atts['post']['data'];
                }
            }

            if( !isset( $settings['frontend_posting_action'] ) ) return true;
            if( $settings['frontend_posting_action']=='none' ) return true;

            // Create a new post
            if( $settings['frontend_posting_action']=='create_post' ) {
                
                // Lets check if post type exists
                if( $settings['frontend_posting_post_type']=='' ) $settings['frontend_posting_post_type'] = 'page';
                
                // post_title and post_content are required so let's check if these are both set
                if( (!isset( $data['post_title'])) || (!isset($data['post_content'])) ) {
                    $msg = sprintf( esc_html__( 'We couldn\'t find the %1$spost_title%2$s and %1$spost_content%2$s fields which are required in order to create a new post. Please %3$sedit%4$sedit your form and try again', 'super-forms' ), '<strong>', '</strong>', '<a href="' . get_admin_url() . 'admin.php?page=super_create_form&id=' . absint( $atts['post']['form_id'] ) . '">', '</a>' );
                    SUPER_Common::output_error(
                        $error = true,
                        $msg = $msg,
                        $redirect = null
                    );
                }

                // Lets check if post type exists
                if( $settings['frontend_posting_post_type']=='' ) $settings['frontend_posting_post_type'] = 'page';
                if ( !post_type_exists( $settings['frontend_posting_post_type'] ) ) {
                    $msg = sprintf( esc_html__( 'The post type %1$s doesn\'t seem to exist. Please %2$sedit%3$s your form and try again ', 'super-forms' ), '<strong>' . $settings['frontend_posting_post_type'] . '</strong>', '<a href="' . get_admin_url() . 'admin.php?page=super_create_form&id=' . absint( $atts['post']['form_id'] ) . '">', '</a>' );
                    SUPER_Common::output_error(
                        $error = true,
                        $msg = $msg,
                        $redirect = null
                    );
                }

                $postarr = array();
                
                // Default values from the form settings
                $postarr['post_type'] = sanitize_text_field( $settings['frontend_posting_post_type'] );
                $postarr['post_status'] = sanitize_text_field( $settings['frontend_posting_status'] );
                $postarr['post_parent'] = absint( $settings['frontend_posting_post_parent'] );
                $postarr['comment_status'] = sanitize_text_field( $settings['frontend_posting_comment_status'] );
                $postarr['ping_status'] = sanitize_text_field( $settings['frontend_posting_ping_status'] );
                $postarr['menu_order'] = absint( $settings['frontend_posting_menu_order'] );
                $postarr['post_password'] = $settings['frontend_posting_post_password'];
                if($settings['frontend_posting_author']!='') {
                    $postarr['post_author'] = absint( $settings['frontend_posting_author'] );
                }else{
                    $user_id = get_current_user_id();
                    if( $user_id!=0 ) {
                        $postarr['post_author'] = $user_id;
                    }
                }
                $post_format = sanitize_text_field( $settings['frontend_posting_post_format'] );
                $tax_input = sanitize_text_field( $settings['frontend_posting_tax_input'] );
                $tags_input = sanitize_text_field( $settings['frontend_posting_tags_input'] );
                $tag_taxonomy = sanitize_text_field( $settings['frontend_posting_post_tag_taxonomy'] );
                $cat_taxonomy = sanitize_text_field( $settings['frontend_posting_post_cat_taxonomy'] );

                // Override default values for form field values
                $postarr['post_title'] = $data['post_title']['value'];
                if( isset( $data['post_content'] ) ) $postarr['post_content'] = $data['post_content']['value'];
                if( isset( $data['post_excerpt'] ) ) $postarr['post_excerpt'] = $data['post_excerpt']['value'];
                if( isset( $data['post_type'] ) ) $postarr['post_type'] = sanitize_text_field( $data['post_type']['value'] );
                if( isset( $data['post_format'] ) ) $post_format = sanitize_text_field( $data['post_format']['value'] );
                if( isset( $data['tax_input'] ) ) $tax_input = sanitize_text_field( $data['tax_input']['value'] );
                if( isset( $data['tags_input'] ) ) $tags_input = sanitize_text_field( $data['tags_input']['value'] );
                if( isset( $data['tag_taxonomy'] ) ) $tag_taxonomy = sanitize_text_field( $data['tag_taxonomy']['value'] );
                if( isset( $data['cat_taxonomy'] ) ) $cat_taxonomy = sanitize_text_field( $data['cat_taxonomy']['value'] );
                if( isset( $data['post_status'] ) ) $postarr['post_status'] = sanitize_text_field( $data['post_status']['value'] );
                if( isset( $data['post_parent'] ) ) $postarr['post_parent'] = absint( $data['post_parent']['value'] );
                if( isset( $data['comment_status'] ) ) $postarr['comment_status'] = sanitize_text_field( $data['comment_status']['value'] );
                if( isset( $data['ping_status'] ) ) $postarr['ping_status'] = sanitize_text_field( $data['ping_status']['value'] );
                if( isset( $data['post_password'] ) ) $postarr['post_password'] = $data['post_password']['value'];
                if( isset( $data['menu_order'] ) ) $postarr['menu_order'] = $data['menu_order']['value'];
                if( isset( $data['post_author'] ) ) $postarr['post_author'] = absint( $data['post_author']['value'] );
                if( (isset( $data['post_date'] )) && (isset( $data['post_time'] )) ) {
                    $postarr['post_time'] = date( 'H:i:s', strtotime($data['post_time']['value'] ) ); // Must be formatted as '18:57:33';
                    $postarr['post_date'] = date( 'Y-m-d', strtotime($data['post_date']['value'] ) ); // Must be formatted as '2010-02-23';
                    $postarr['post_date'] = $postarr['post_date'] . ' ' . $postarr['post_time']; // Must be formatted as '2010-02-23 18:57:33';
                }else{
                    if( isset( $data['post_date'] ) ) {
                        $postarr['post_date'] = date( 'Y-m-d H:i:s', strtotime($data['post_date']['value'] ) ); // Must be formatted as '2010-02-23 18:57:33';
                    }
                }
                if( ($postarr['comment_status']=='open') || ($postarr['comment_status']=='1') || ($postarr['comment_status']=='yes') || ($postarr['comment_status']=='true') ) {
                    $postarr['comment_status'] = 'open';
                }elseif( ($postarr['comment_status']=='closed') || ($postarr['comment_status']=='0') || ($postarr['comment_status']=='no') || ($postarr['comment_status']=='false') ) {
                    $postarr['comment_status'] = 'closed';
                }else{
                    unset($postarr['comment_status']);
                }

                // Lets check if tax_input field exists
                // If so, let's check if the post_taxonomy exists, because this is required in order to connect the categories accordingly to the post.
                if( $tax_input!='' ) {
                    if( $cat_taxonomy=='' ) {
                        $msg = sprintf( esc_html__( 'You have a field called %1$s but you haven\'t set a valid taxonomy name. Please %2$sedit%3$s your form and try again ', 'super-forms' ), '<strong>tax_input</strong>', '<a href="' . get_admin_url() . 'admin.php?page=super_create_form&id=' . absint( $atts['post']['form_id'] ) . '">', '</a>' );
                        SUPER_Common::output_error(
                            $error = true,
                            $msg = $msg,
                            $redirect = null
                        );
                    }else{
                        if ( !taxonomy_exists( $cat_taxonomy ) ) {
                            $msg = sprintf( esc_html__( 'The taxonomy %1$s doesn\'t seem to exist. Please %2$sedit%3$s your form and try again ', 'super-forms' ), '<strong>' . $settings['frontend_posting_post_cat_taxonomy'] . '</strong>', '<a href="' . get_admin_url() . 'admin.php?page=super_create_form&id=' . absint( $atts['post']['form_id'] ) . '">', '</a>' );
                            SUPER_Common::output_error(
                                $error = true,
                                $msg = $msg,
                                $redirect = null
                            );
                        }
                    }
                }

                // Lets check if tags_input field exists
                // If so, let's check if the tag_taxonomy exists, because this is required in order to connect the categories accordingly to the post.
                if( $tags_input!='' ) {
                    if( $tag_taxonomy=='' ) {
                        $msg = sprintf( esc_html__( 'You have a field called %1$s but you haven\'t set a valid taxonomy name. Please %2$sedit%3$s your form and try again ', 'super-forms' ), '<strong>tags_input</strong>', '<a href="' . get_admin_url() . 'admin.php?page=super_create_form&id=' . absint( $atts['post']['form_id'] ) . '">', '</a>' );
                        SUPER_Common::output_error(
                            $error = true,
                            $msg = $msg,
                            $redirect = null
                        );
                    }else{
                        if ( !taxonomy_exists( $tag_taxonomy ) ) {
                            $msg = sprintf( esc_html__( 'The taxonomy %1$s doesn\'t seem to exist. Please %2$sedit%3$s your form and try again ', 'super-forms' ), '<strong>' . $tag_taxonomy . '</strong>', '<a href="' . get_admin_url() . 'admin.php?page=super_create_form&id=' . absint( $atts['post']['form_id'] ) . '">', '</a>' );
                            SUPER_Common::output_error(
                                $error = true,
                                $msg = $msg,
                                $redirect = null
                            );
                        }
                    }
                }

                // @since 1.0.1
                $postarr = apply_filters( 'super_front_end_posting_before_insert_post_filter', $postarr );

                // Get the post ID or return the error(s)
                $result = wp_insert_post( $postarr, true );
                if( isset( $result->errors ) ) {
                    $msg = '';
                    foreach( $result->errors as $v ) {
                        $msg .= '- ' . $v[0] . '<br />';
                    }
                    SUPER_Common::output_error(
                        $error = true,
                        $msg = $msg,
                        $redirect = null
                    );
                }else{

                    $post_id = $result;

                    // Check if we need to make this post sticky
                    if( isset( $data['stick_post'] ) ) {
                        $sticky = sanitize_text_field( $data['stick_post']['value'] );
                        if( ($sticky=='1') || ($sticky=='true') || ($sticky=='yes') ) {
                            stick_post($post_id);
                        }
                    }

                    // BuddyPress functions
                    // Make Topic sticky
                    if( function_exists( 'bbp_stick_topic' ) ) {
                        if( isset( $data['_bbp_topic_type'] ) ) {
                            $stickies = array( $post_id );
                            $stickies = array_values( $stickies );
                            if( $data['_bbp_topic_type']['value']=='super' ) {
                                update_option( '_bbp_super_sticky_topics', $stickies );
                            }
                            if( $data['_bbp_topic_type']['value']=='stick' ) {
                                update_post_meta( $postarr['post_parent'], '_bbp_sticky_topics', $stickies );
                            }
                        }
                    }
                    // Set parent for topics only
                    if( function_exists( 'bbp_get_topic_post_type' ) ) {
                        if( $postarr['post_type']==bbp_get_topic_post_type() ) {
                            update_post_meta( $post_id, '_bbp_author_ip', bbp_current_author_ip() );
                            if( isset( $postarr['post_parent'] ) ) {
                                if( $postarr['post_parent']!=0 ) {
                                    update_post_meta( $post_id, '_bbp_forum_id', $postarr['post_parent'] );
                                }
                            }
                        }
                    }
                    // Subscribe to the Topic
                    if( function_exists( 'bbp_add_user_subscription' ) ) {
                        if( isset( $data['bbp_subscribe'] ) ) {
                            $bbp_subscribe = filter_var( $data['bbp_subscribe']['value'], FILTER_VALIDATE_BOOLEAN );
                            if( $bbp_subscribe===true) {
                                $result = bbp_add_user_subscription( $postarr['post_author'], $post_id );
                            }
                        }
                    }

                    // Collect categories from the field tax_input
                    if( $tax_input!='' ) {
                        $tax_input_array = array();
                        
                        // @since 1.1.4 - replace {tags}
                        $tax_input = SUPER_Common::email_tags( $tax_input, $data, $settings );
                        $cat_taxonomy = SUPER_Common::email_tags( $cat_taxonomy, $data, $settings );

                        $categories = explode( ",", $tax_input );
                        foreach( $categories as $slug ) {
                            $slug = trim($slug);
                            if( !empty( $slug ) ) {
                                $tax_input_array[] = $slug;
                            }
                        }
                        wp_set_object_terms( $post_id, $tax_input_array, $cat_taxonomy );
                    }

                    // Collect tags from the field tags_input
                    if( $tags_input!='' ) {
                        $tags_input_array = array();

                        // @since 1.1.4 - replace {tags}
                        $tags_input = SUPER_Common::email_tags( $tags_input, $data, $settings );
                        $tag_taxonomy = SUPER_Common::email_tags( $tag_taxonomy, $data, $settings );

                        $tags = explode( ",", $tags_input );
                        foreach( $tags as $slug ) {
                            $slug = trim($slug);
                            if( !empty( $slug ) ) {
                                $tags_input_array[] = $slug;
                            }
                        }
                        wp_set_object_terms($post_id, $tags_input_array, $tag_taxonomy );
                    }

                    // Check if we are saving a WooCommerce product
                    if( $postarr['post_type']=='product' ) {

                        // Set the product type (default = simple)
                        $product_type = sanitize_text_field( $settings['frontend_posting_product_type'] );
                        if( isset( $data['product_type'] ) ) $product_type = sanitize_text_field( $data['product_type']['value'] );
                        if( $product_type=='' ) $product_type = 'simple';
                        wp_set_object_terms( $post_id, $product_type, 'product_type' );

                        // Set the shipping class (default = none)
                        $shipping_class = 0;
                        if( isset( $data['product_shipping_class'] ) ) $shipping_class = absint( $data['product_shipping_class']['value'] );
                        if( $shipping_class!=0 ) {
                            wp_set_object_terms( $post_id, absint($shipping_class), 'product_shipping_class' );
                        }

                        // Save all the product meta data
                        $fields = array(
                            'product_downloadable' => '_downloadable',
                            'product_virtual' => '_virtual',
                            'product_visibility' => '_visibility',
                            'product_featured' => '_featured',
                            'product_stock_status' => '_stock_status',
                            'product_manage_stock' => '_manage_stock',
                            'product_stock' => '_stock',
                            'product_backorders' => '_backorders',
                            'product_sold_individually' => '_sold_individually',
                            'product_regular_price' => '_regular_price',
                            'product_sale_price' => '_sale_price',
                            'product_purchase_note' => '_purchase_note',
                            'product_weight' => '_weight',
                            'product_length' => '_length',
                            'product_width' => '_width',
                            'product_height' => '_height',
                            'product_sku' => '_sku',
                            'product_attributes' => '_product_attributes',
                            'product_sale_price_dates_from' => '_sale_price_dates_from',
                            'product_sale_price_dates_to' => '_sale_price_dates_to',
                            'product_price' => '_price',
                            
                            'product_downloadable_files' => '_downloadable_files',
                            'product_download_limit' => '_download_limit',
                            'product_download_expiry' => '_download_expiry',
                            'product_download_type' => '_download_type',

                            'product_url' => '_product_url',
                            'product_button_text' => '_button_text',
                            
                            'product_upsell_ids' => 'upsell_ids',
                            'product_crosssell_ids' => 'crosssell_ids'

                        );

                        $product = wc_get_product( absint( $post_id ) );

                        foreach( $fields as $k => $v ) {

                            if( $product ) {

                                $setting_key = 'frontend_posting_' . $k;

                                // @since 1.2.0 - set featured, stock status, visibility, virtual, downloadable, sold method, backorders, stock, manage stock
                                if( ($k=='product_featured') ||
                                    ($k=='product_stock_status') || 
                                    ($k=='product_visibility') || 
                                    ($k=='product_virtual') ||
                                    ($k=='product_downloadable') ||
                                    ($k=='product_sold_individually') ||
                                    ($k=='product_backorders') ||
                                    ($k=='product_stock') ||
                                    ($k=='product_manage_stock') ||
                                    ($k=='product_sale_price_dates_from') ||
                                    ($k=='product_sale_price_dates_to') ) {
                                        $field_value = '';
                                        if( isset( $settings[$setting_key] ) ) {
                                            $field_value = wc_clean( $settings[$setting_key] );
                                        }
                                        if( !empty($data[$k]) ) {
                                            $field_value = wc_clean( $data[$k]['value'] );
                                        }
                                        switch ( $k ) {
                                            case 'product_featured' :
                                                if( $field_value=='yes' ) {
                                                    $product->set_featured( true );
                                                }
                                                break;
                                            case 'product_stock_status' :
                                                if( $product_type!='external' ) {
                                                    $product->set_stock_status( $field_value );
                                                }
                                                break; 
                                            case 'product_visibility' :
                                                $product->set_catalog_visibility( $field_value );
                                                break; 
                                            case 'product_virtual' :
                                                $product->set_virtual( $field_value );
                                                break; 
                                            case 'product_downloadable' :
                                                $product->set_downloadable( $field_value );
                                                break; 
                                            case 'product_sold_individually' :
                                                $product->set_sold_individually( $field_value );
                                                break; 
                                            case 'product_backorders' :
                                                if( $product_type!='external' ) {
                                                    $product->set_backorders( $field_value );
                                                }
                                                break; 
                                            case 'product_stock' :
                                                $product->set_stock_quantity( $field_value );
                                                break; 
                                            case 'product_manage_stock' :
                                                $product->set_manage_stock( $field_value );
                                                break; 
                                            case 'product_sale_price_dates_from' :
                                                $product->set_date_on_sale_from( strtotime($field_value) );
                                                break; 
                                            case 'product_sale_price_dates_to' :
                                                $product->set_date_on_sale_to( strtotime($field_value) );
                                                break; 
                                        }
                                        $product->save();
                                        continue;
                                }

                            }
                         
                            if( $k=='product_downloadable_files' ) {
                                if( isset( $data['downloadable_files'] ) ) {
                                    $files = array();
                                    $_file_paths = array();
                                    foreach( $data['downloadable_files']['files'] as $v ) {
                                        $name = get_the_title( $v['attachment'] );
                                        $url = $v['url'];
                                        $array = array( 'name'=>$name, 'file' => $url );
                                        $url = md5( $url );
                                        $_file_paths[$url] = $array;
                                    }
                                    update_post_meta( $post_id, '_downloadable_files', $_file_paths);
                                }
                                continue;
                            }


                            if( $k=='product_attributes' ) {
                                    
                                // Lets make sure we loop through all the product attributes in case a column was set to use Add more + feature
                                $_product_attributes = array();
                                foreach( $data as $dk => $dv ) {
                                    if( ( ($dk=='product_attributes') || (strpos($dk, 'product_attributes_') !== false) ) && (strpos($dk, 'product_attributes_name') === false) ) {
                                        $counter = str_replace('product_attributes_', '', $dv['name']);
                                        $counter = absint($counter);
                                        $value = '';
                                        $visible = '1';
                                        $variation = '0';
                                        $taxonomy = '0';
                                        if( $counter==0 ) {
                                            $name = 'Variation 1';
                                            if( isset( $data['product_attributes_name'] ) ) {
                                                $name = sanitize_text_field( $data['product_attributes_name']['value'] );
                                            }
                                            if( isset( $data['product_attributes'] ) ) {
                                                $value = sanitize_text_field( $data['product_attributes']['value'] );
                                            }
                                            if( isset( $data['product_attributes_is_visible'] ) ) {
                                                $visible = sanitize_text_field( $data['product_attributes_is_visible']['value'] );
                                                if( ($visible=='1') || ($visible=='true') || ($visible=='yes') ) {
                                                    $visible = '1';
                                                }
                                            }
                                            if( isset( $data['product_attributes_is_variation'] ) ) {
                                                $variation = sanitize_text_field( $data['product_attributes_is_variation']['value'] );
                                                if( ($variation=='1') || ($variation=='true') || ($variation=='yes') ) {
                                                    $variation = '1';
                                                }
                                            }
                                            if( isset( $data['product_attributes_is_taxonomy'] ) ) {
                                                $taxonomy = sanitize_text_field( $data['product_attributes_is_taxonomy']['value'] );
                                                if( ($taxonomy=='1') || ($taxonomy=='true') || ($taxonomy=='yes') ) {
                                                    $taxonomy = '1';
                                                }
                                            }
                                        }else{
                                            $name = 'Variation ' . $counter;
                                            if( isset( $data['product_attributes_name_' . $counter] ) ) {
                                                $name = sanitize_text_field( $data['product_attributes_name_' . $counter]['value'] );
                                            }
                                            if( isset( $data['product_attributes_' . $counter] ) ) {
                                                $value = sanitize_text_field( $data['product_attributes_' . $counter]['value'] );
                                            }                                            
                                            if( isset( $data['product_attributes_is_visible_' . $counter] ) ) {
                                                $visible = sanitize_text_field( $data['product_attributes_is_visible_' . $counter]['value'] );
                                                if( ($visible=='1') || ($visible=='true') || ($visible=='yes') ) {
                                                    $visible = '1';
                                                }
                                            }
                                            if( isset( $data['product_attributes_is_variation_' . $counter] ) ) {
                                                $variation = sanitize_text_field( $data['product_attributes_is_variation_' . $counter]['value'] );
                                                if( ($variation=='1') || ($variation=='true') || ($variation=='yes') ) {
                                                    $variation = '1';
                                                }
                                            }                                            
                                            if( isset( $data['product_attributes_is_taxonomy_' . $counter] ) ) {
                                                $taxonomy = sanitize_text_field( $data['product_attributes_is_taxonomy_' . $counter]['value'] );
                                                if( ($taxonomy=='1') || ($taxonomy=='true') || ($taxonomy=='yes') ) {
                                                    $taxonomy = '1';
                                                }
                                            }
                                        }
                                        $term_taxonomy_ids = wp_set_object_terms( $post_id, $value, $name, true );
                                        $_product_attributes[$name]['name'] = $name;
                                        $_product_attributes[$name]['value'] = $value;
                                        $_product_attributes[$name]['is_visible'] = $visible;
                                        $_product_attributes[$name]['is_variation'] = $variation;
                                        $_product_attributes[$name]['is_taxonomy'] = $taxonomy;
                                    } 
                                }
                                update_post_meta( $post_id, '_product_attributes', $_product_attributes);
                                continue;
                            }

                            $field_value = '';
                            if( isset( $settings['frontend_posting_'.$k] ) ) $field_value = sanitize_text_field( $settings['frontend_posting_'.$k] );
                            if( isset( $data[$k] ) ) $field_value = sanitize_text_field( $data[$k]['value'] );
                            update_post_meta( $post_id, $v, $field_value );
                        }

                        // Save custom product attributes
                        $attributes = explode( "\n", $settings['frontend_posting_product_attributes'] );
                        foreach( $attributes as $v ) {
                            if(empty($v)) continue;
                            $values = explode( "|", $v );
                            if( (isset($values[0])) && (isset($values[1])) ) {
                                if(!isset($values[2])) $values[2] = '1';
                                if(!isset($values[3])) $values[3] = '1';
                                if(!isset($values[4])) $values[4] = '1';
                                $values[1] = SUPER_Common::email_tags( $values[1], $data, $settings );
                                $values[1] = explode(",", $values[1]);
                                $attribute = $values[0];
                                $attribute_value = $values[1];
                                $is_visible = $values[2];
                                $is_variation = $values[3];
                                $is_taxonomy = $values[4];
                                $term_taxonomy_ids = wp_set_object_terms( $post_id, $attribute_value, 'pa_'.$attribute, true );
                                $_product_attributes['pa_'.$attribute]['name'] = 'pa_'.$attribute;
                                $_product_attributes['pa_'.$attribute]['value'] = $attribute_value;
                                $_product_attributes['pa_'.$attribute]['is_visible'] = $is_visible;
                                $_product_attributes['pa_'.$attribute]['is_variation'] = $is_variation;
                                $_product_attributes['pa_'.$attribute]['is_taxonomy'] = $is_taxonomy;
                                update_post_meta( $post_id, '_product_attributes', $_product_attributes);
                            }
                        }

                        // If we are saving a WooCommerce product check if we need to add images to the gallery
                        if( isset( $data['image_gallery'] ) ) {
                            $files = array();
                            foreach( $data['image_gallery']['files'] as $v ) {
                                $files[] = $v['attachment'];
                            }
                            $files = implode( ',', $files );

                            // @since 1.2.0 - use native function for saving gallery images
                            $product->set_gallery_image_ids( $files );
                        }

                        // Sales and prices
                        if ( in_array( $product_type, array( 'variable', 'grouped' ) ) ) {
                            // Variable and grouped products have no prices
                            $product->set_regular_price('');
                            $product->set_sale_price('');
                            $product->set_date_on_sale_from('');
                            $product->set_date_on_sale_to('');
                            $product->set_price('');
                        }else{
                            // Regular Price
                            if ( isset( $data['product_regular_price'] ) ) {
                                $product->set_regular_price($data['product_regular_price']['value']);
                            }
                            $regular_price = $product->get_regular_price( 'edit' );

                            // Sale Price
                            if ( isset( $data['product_sale_price'] ) ) {
                                $product->set_sale_price($data['product_sale_price']['value']);
                            } else {
                                $sale_price = $product->get_sale_price( 'edit' );
                            }
                            $date_from = $product->get_date_on_sale_from( 'edit' );
                            $date_to = $product->get_date_on_sale_to( 'edit' );

                            
                            if ( $date_to && ! $date_from ) {
                                $date_from = strtotime( 'NOW', current_time( 'timestamp' ) );
                                $product->set_date_on_sale_from($date_from);
                            }

                            // Update price if on sale
                            if ( '' !== $sale_price && '' == $date_to && '' == $date_from ) {
                                $product->set_price($sale_price);
                            } else {
                                $product->set_price($regular_price);
                            }
                            if ( '' !== $sale_price && $date_from && $date_from <= strtotime( 'NOW', current_time( 'timestamp' ) ) ) {
                                $product->set_price($sale_price);
                            }
                            if ( $date_to && $date_to < strtotime( 'NOW', current_time( 'timestamp' ) ) ) {
                                $product->set_price($regular_price);
                                $product->set_date_on_sale_from('');
                                $product->set_date_on_sale_to('');
                            }
                        }
                        $product->save();
                    }

                    // Save custom post meta
                    $meta_data = array();
                    $custom_meta = explode( "\n", $settings['frontend_posting_meta'] );
                    foreach( $custom_meta as $k ) {
                        if(empty($k)) continue;
                        $field = explode( "|", $k );
                        // @since 1.0.3 - first check if a field with the name exists
                        if( isset( $data[$field[0]]['value'] ) ) {
                            $meta_data[$field[1]] = $data[$field[0]]['value'];
                        }else{
                            
                            // @since 1.1.2 - check if type is files
                            if( (!empty($data[$field[0]])) && ( ($data[$field[0]]['type']=='files') && (isset($data[$field[0]]['files'])) ) ) {
                                if( count($data[$field[0]]['files']>1) ) {
                                    foreach( $data[$field[0]]['files'] as $fk => $fv ) {
                                        if($meta_data[$field[1]]==''){
                                            $meta_data[$field[1]] = $fv['attachment'];
                                        }else{
                                            $meta_data[$field[1]] .= ',' . $fv['attachment'];
                                        }
                                    }
                                }elseif( count($data[$field[0]]['files'])==1) {
                                    $meta_data[$field[1]] = absint($data[$field[0]]['files'][0]['attachment']);
                                }else{
                                    $meta_data[$field[1]] = '';
                                }
                                continue;
                            }else{
                                // @since 1.0.3 - if no field exists, just save it as a string
                                $string = SUPER_Common::email_tags( $field[0], $data, $settings );
                                
                                // @since 1.0.3 - check if string is serialized array
                                $unserialize = unserialize($string);
                                if ($unserialize !== false) {
                                    $meta_data[$field[1]] = $unserialize;
                                }else{
                                    $meta_data[$field[1]] = $string;
                                }
                            }
                        }
                    }

                    foreach( $meta_data as $k => $v ) {
                        // @since 1.1.1 - Check for ACF field and check if checkbox, if checkbox save values as Associative Array
                        if (function_exists('get_field_object')) {
                            global $wpdb;
                            $length = strlen($k);

                            // @since 1.1.2 - Because there are major differences between ACF Pro and the regular ACF plugin we have to do different queries
                            if( class_exists('acf_pro') ) {
                                $sql = "SELECT post_name FROM {$wpdb->posts} WHERE post_excerpt = '$k' AND post_type = 'acf-field'";
                            }else{
                                $sql = "SELECT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE 'field_%' AND meta_value LIKE '%\"name\";s:$length:\"$k\";%';";
                            }
                            $acf_field = $wpdb->get_var($sql);

                            // @since 1.2.4 - It might be possible we are using the ACF Gallery Add-on here for the none pro version, in this case we must do a different query
                            if(!$acf_field){
                                $sql = "SELECT post_name FROM {$wpdb->posts} WHERE post_excerpt = '$k' AND post_type = 'acf-field'";
                                $acf_field = $wpdb->get_var($sql);
                            }

                            $acf_field = get_field_object($acf_field);

                            // @since 1.1.3 - save a checkbox or select value
                            if( ($acf_field['type']=='checkbox') || ($acf_field['type']=='select') || ($acf_field['type']=='radio') || ($acf_field['type']=='gallery') ) {
                                $value = explode( ",", $v );
                                update_field( $acf_field['key'], $value, $post_id );
                                continue;
                            }elseif( $acf_field['type']=='google_map' ) {
                                if( isset($data[$k]['geometry']) ) {
                                    $data[$k]['geometry']['location']['address'] = $data[$k]['value'];
                                    $value = $data[$k]['geometry']['location'];
                                }else{
                                    $value = array(
                                        'address' => $data[$k]['value'],
                                        'lat' => '',
                                        'lng' => '',
                                    );
                                }
                                update_field( $acf_field['key'], $value, $post_id );
                                continue;
                            }

                            // @since 1.1.3 - save a repeater field value
                            if($acf_field['type']=='repeater'){
                                $repeater_values = array();
                                foreach($acf_field['sub_fields'] as $sk => $sv){
                                    if( isset($data[$sv['name']]) ) {
                                        $repeater_values[0][$sv['name']] = $this->return_field_value( $data, $sv['name'], $sv['type'], $settings );
                                        $field_counter = 2;
                                        while( isset($data[$sv['name'] . '_' . $field_counter]) ) {
                                            $repeater_values[$field_counter-1][$sv['name']] = $this->return_field_value( $data, $sv['name'] . '_' . $field_counter, $sv['type'], $settings );
                                            $field_counter++;
                                        }
                                    }
                                }
                                update_field( $acf_field['key'], $repeater_values, $post_id );
                                continue;
                            }

                            // save a basic text value
                            update_field( $acf_field['key'], $v, $post_id );
                            continue;

                        }
                        add_post_meta( $post_id, $k, $v );
                    }

                    // Set post format for the post if theme supports it and if it was set by the form settings or by one of the form fields
                    if ( current_theme_supports( 'post-formats' ) ) {
                        $post_formats = get_theme_support( 'post-formats' );
                        if ( is_array( $post_formats[0] ) ) {
                            if ( in_array( $post_format, $post_formats[0] ) ) {
                                set_post_format( $post_id , $post_format);
                            }
                        }
                    }

                    // Set the featured image if a file upload field with the name featured_image was found
                    if( !empty( $data['featured_image'] ) ) {
                        if( !empty( $data['featured_image']['files'] ) ) {
                            set_post_thumbnail( $post_id, $data['featured_image']['files'][0]['attachment'] );
                        }
                    }
                }

                // Store the created post ID into a session, to either alter the redirect URL or for developers to use in their custom code
                // The redirect URL will only be altered if the option to do so was enabled in the form settings.
                SUPER_Forms()->session->set( 'super_forms_frontend_posting_created_post', $post_id );

                // @since 1.0.1
                do_action( 'super_front_end_posting_after_insert_post_action', array( 'post_id'=>$post_id, 'data'=>$data, 'atts'=>$atts ) );
                
            }
        }


        /**
         * Return field value for saving into post meta
         *
         *  @since      1.1.3
        */
        public static function return_field_value( $data, $name, $type, $settings ) {
            $value = '';
            $type = $type;           
            if( ($data[$name]['type']=='files') && (isset($data[$name]['files'])) ) {
                if( count($data[$name]['files']>1) ) {
                    foreach( $data[$name]['files'] as $fk => $fv ) {
                        if($value==''){
                            $value = $fv['attachment'];
                        }else{
                            $value .= ',' . $fv['attachment'];
                        }
                    }
                }elseif( count($data[$name]['files'])==1) {
                    $value = absint($data[$name]['files'][0]['attachment']);
                }else{
                    $value = '';
                }
            }elseif( ($type=='checkbox') || ($type=='select') || ($type=='radio') || ($type=='gallery') ) {
                $value = explode( ",", $data[$name]['value'] );
            }elseif( $type=='google_map' ) {
                if( isset($data[$name]['geometry']) ) {
                    $data[$name]['geometry']['location']['address'] = $data[$name]['value'];
                    $value = $data[$name]['geometry']['location'];
                }else{
                    $value = array(
                        'address' => $data[$name]['value'],
                        'lat' => '',
                        'lng' => '',
                    );
                }
            }else{
                $value = $data[$name]['value'];
            }
            return $value;
        }


        /**
         * Hook into settings and add Front-end Posting settings
         *
         *  @since      1.0.0
        */
        public static function add_settings( $array, $settings ) {
            $array['frontend_posting'] = array(        
                'name' => esc_html__( 'Front-end Posting', 'super-forms' ),
                'label' => esc_html__( 'Front-end Posting Settings', 'super-forms' ),
                'fields' => array(
                    'frontend_posting_action' => array(
                        'name' => esc_html__( 'Actions', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_action', $settings['settings'], 'none' ),
                        'filter' => true,
                        'type' => 'select',
                        'values' => array(
                            'none' => esc_html__( 'None (do nothing)', 'super-forms' ),
                            'create_post' => esc_html__( 'Create new Post', 'super-forms' ), //(post, page, product etc.)
                        ),
                    ),

                    // @since 1.2.2 - option to redirect to the newly created post
                    'frontend_posting_redirect' => array(
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_redirect', $settings['settings'], '' ),
                        'type' => 'checkbox',
                        'values' => array(
                            'true' => esc_html__( 'Redirect to the created Post after form submission', 'super-forms' ),
                        ),
                        'filter' => true,
                        'parent' => 'frontend_posting_action',
                        'filter_value' => 'create_post',
                    ),

                    'frontend_posting_post_type' => array(
                        'name' => esc_html__( 'Post type', 'super-forms' ),
                        'desc' => esc_html__( 'Enter the name of the post type (e.g: post, page, product)', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_post_type', $settings['settings'], 'page' ),
                        'filter' => true,
                        'parent' => 'frontend_posting_action',
                        'filter_value' => 'create_post',
                    ),
                    'frontend_posting_status' => array(
                        'name' => esc_html__( 'Status', 'super-forms' ),
                        'desc' => esc_html__( 'Select what the status should be (publish, future, draft, pending, private, trash, auto-draft)?', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_status', $settings['settings'], 'publish' ),
                        'type' => 'select',
                        'values' => array(
                            'publish' => esc_html__( 'Publish (default)', 'super-forms' ),
                            'future' => esc_html__( 'Future', 'super-forms' ),
                            'draft' => esc_html__( 'Draft', 'super-forms' ),
                            'pending' => esc_html__( 'Pending', 'super-forms' ),
                            'private' => esc_html__( 'Private', 'super-forms' ),
                            'trash' => esc_html__( 'Trash', 'super-forms' ),
                            'auto-draft' => esc_html__( 'Auto-Draft', 'super-forms' ),
                        ),
                        'filter' => true,
                        'parent' => 'frontend_posting_action',
                        'filter_value' => 'create_post',
                    ),
                    'frontend_posting_post_parent' => array(
                        'name' => esc_html__( 'Parent ID (leave blank for none)', 'super-forms' ),
                        'desc' => esc_html__( 'Enter a parent ID if you want the post to have a parent', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_post_parent', $settings['settings'], '' ),
                        'filter' => true,
                        'parent' => 'frontend_posting_action',
                        'filter_value' => 'create_post',
                    ),
                    'frontend_posting_comment_status' => array(
                        'name' => esc_html__( 'Allow comments', 'super-forms' ),
                        'desc' => esc_html__( 'Whether the post can accept comments', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_comment_status', $settings['settings'], '' ),
                        'type' => 'select',
                        'values' => array(
                            '' => esc_html__( 'Default (use the default_comment_status option)', 'super-forms' ),
                            'open' => esc_html__( 'Open (allow comments)', 'super-forms' ),
                            'closed' => esc_html__( 'Closed (disallow comments)', 'super-forms' ),
                        ),
                        'filter' => true,
                        'parent' => 'frontend_posting_action',
                        'filter_value' => 'create_post',
                    ),
                    'frontend_posting_ping_status' => array(
                        'name' => esc_html__( 'Allow pings', 'super-forms' ),
                        'desc' => esc_html__( 'Whether the post can accept pings', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_ping_status', $settings['settings'], '' ),
                        'type' => 'select',
                        'values' => array(
                            '' => esc_html__( 'Default (use the default_ping_status option)', 'super-forms' ),
                            'open' => esc_html__( 'Open (allow pings)', 'super-forms' ),
                            'closed' => esc_html__( 'Closed (disallow pings)', 'super-forms' ),
                        ),
                        'filter' => true,
                        'parent' => 'frontend_posting_action',
                        'filter_value' => 'create_post',
                    ),
                    'frontend_posting_post_password' => array(
                        'name' => esc_html__( 'Password protect (leave blank for none)', 'super-forms' ),
                        'desc' => esc_html__( 'The password to access the post', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_post_password', $settings['settings'], '' ),
                        'filter' => true,
                        'parent' => 'frontend_posting_action',
                        'filter_value' => 'create_post',
                    ),
                    'frontend_posting_menu_order' => array(
                        'name' => esc_html__( 'Menu order (blank = 0)', 'super-forms' ),
                        'desc' => esc_html__( 'The order the post should be displayed in', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_menu_order', $settings['settings'], '' ),
                        'filter' => true,
                        'parent' => 'frontend_posting_action',
                        'filter_value' => 'create_post',
                    ),
                    'frontend_posting_meta' => array(
                        'name' => esc_html__( 'Save custom post meta', 'super-forms' ),
                        'desc' => esc_html__( 'Based on your form fields you can save custom meta for your post', 'super-forms' ),
                        'type' => 'textarea',
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_meta', $settings['settings'], "field_name|meta_key\nfield_name2|meta_key2\nfield_name3|meta_key3" ),
                        'filter' => true,
                        'parent' => 'frontend_posting_action',
                        'filter_value' => 'create_post',
                        'allow_empty' => true,
                    ),
                    'frontend_posting_author' => array(
                        'name' => esc_html__( 'Author ID (default = current user ID if logged in)', 'super-forms' ),
                        'desc' => esc_html__( 'The ID of the user where the post will belong to', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_author', $settings['settings'], '' ),
                        'filter' => true,
                        'parent' => 'frontend_posting_action',
                        'filter_value' => 'create_post',
                    ),
                    'frontend_posting_post_cat_taxonomy' => array(
                        'name' => esc_html__( 'The cat taxonomy name (e.g: category or product_cat)', 'super-forms' ),
                        'desc' => esc_html__( 'Required to connect the post to categories (if found)', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_post_cat_taxonomy', $settings['settings'], '' ),
                        'filter' => true,
                        'parent' => 'frontend_posting_action',
                        'filter_value' => 'create_post',
                    ),
                    'frontend_posting_tax_input' => array(
                        'name' => esc_html__( 'The post categories slug(s) (e.g: books, cars)', 'super-forms' ),
                        'desc' => esc_html__( 'Category slug separated by comma', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_tax_input', $settings['settings'], '' ),
                        'filter' => true,
                        'parent' => 'frontend_posting_action',
                        'filter_value' => 'create_post',
                    ),
                    'frontend_posting_tags_input' => array(
                        'name' => esc_html__( 'The post tags', 'super-forms' ),
                        'desc' => esc_html__( 'Post tags separated by comma', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_tags_input', $settings['settings'], '' ),
                        'filter' => true,
                        'parent' => 'frontend_posting_action',
                        'filter_value' => 'create_post',
                    ),
                    'frontend_posting_post_tag_taxonomy' => array(
                        'name' => esc_html__( 'The tag taxonomy name (e.g: post_tag or product_tag)', 'super-forms' ),
                        'desc' => esc_html__( 'Required to connect the post to categories (if found)', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_post_tag_taxonomy', $settings['settings'], '' ),
                        'filter' => true,
                        'parent' => 'frontend_posting_action',
                        'filter_value' => 'create_post',
                    ),
                    'frontend_posting_post_format' => array(
                        'name' => esc_html__( 'The post format (e.g: quote, gallery, audio etc.)', 'super-forms' ),
                        'desc' => esc_html__( 'Leave blank for no post format', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_post_format', $settings['settings'], '' ),
                        'filter' => true,
                        'parent' => 'frontend_posting_action',
                        'filter_value' => 'create_post',
                    ),
                    'frontend_posting_guid' => array(
                        'name' => esc_html__( 'GUID', 'super-forms' ),
                        'desc' => esc_html__( 'Global Unique ID for referencing the post', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_guid', $settings['settings'], '' ),
                        'filter' => true,
                        'parent' => 'frontend_posting_action',
                        'filter_value' => 'create_post',
                    ),
                    'frontend_posting_product_type' => array(
                        'name' => esc_html__( 'Product Type (e.g: simple, grouped, external, variable)', 'super-forms' ),
                        'desc' => esc_html__( 'Leave blank to use the default product type: simple', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_product_type', $settings['settings'], '' ),
                        'filter' => true,
                        'parent' => 'frontend_posting_post_type',
                        'filter_value' => 'product',
                    ),
                    'frontend_posting_product_attributes' => array(
                        'name' => esc_html__( 'Save product attributes', 'super-forms' ),
                        'label' => sprintf( esc_html__( 'Enter the attributes that needs to be saved for this product%1$sPut each attribute category on a new line separated by pipes \"|\".%1$s%1$sDefine your values like so:%3$s Attribute Slug|Attribute value|Visible|Variation|Taxonomys%1$s%2$sExample with tags:%3$s colors|{color}|1|1|1%1$s%2$sExample without tags:%3$s colors|red,green,yellow|1|1|1%1$s%2$sAllowed values:%3$s string|string|integer|integer|integer', 'super-forms' ), '<br />', '<strong>', '</strong>' ),
                        'desc' => esc_html__( 'Based on your form fields you can save product attributes', 'super-forms' ),
                        'type' => 'textarea',
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_product_attributes', $settings['settings'], "color|{color}|1|1|1" ),
                        'filter' => true,
                        'parent' => 'frontend_posting_post_type',
                        'filter_value' => 'product',
                        'allow_empty' => true,
                    ),
                    'frontend_posting_product_featured' => array(
                        'name' => esc_html__( 'Featured product', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_product_featured', $settings['settings'], 'no' ),
                        'type' => 'select',
                        'values' => array(
                            'no' => esc_html__( 'No (default)', 'super-forms' ),
                            'yes' => esc_html__( 'Yes', 'super-forms' ),
                        ),
                        'filter' => true,
                        'parent' => 'frontend_posting_post_type',
                        'filter_value' => 'product',
                    ),
                    'frontend_posting_product_stock_status' => array(
                        'name' => esc_html__( 'In stock?', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_product_stock_status', $settings['settings'], 'instock' ),
                        'type' => 'select',
                        'values' => array(
                            'instock' => esc_html__( 'In stock (default)', 'super-forms' ),
                            'outofstock' => esc_html__( 'Out of stock', 'super-forms' ),
                        ),
                        'filter' => true,
                        'parent' => 'frontend_posting_post_type',
                        'filter_value' => 'product',
                    ),
                    'frontend_posting_product_manage_stock' => array(
                        'name' => esc_html__( 'Manage stock?', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_product_manage_stock', $settings['settings'], 'no' ),
                        'type' => 'select',
                        'values' => array(
                            'no' => esc_html__( 'No (default)', 'super-forms' ),
                            'yes' => esc_html__( 'Yes', 'super-forms' ),
                        ),
                        'filter' => true,
                        'parent' => 'frontend_posting_post_type',
                        'filter_value' => 'product',
                    ),
                    'frontend_posting_product_stock' => array(
                        'name' => esc_html__( 'Stock Qty', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_product_stock', $settings['settings'], '' ),
                        'type' => 'slider',
                        'min' => 0,
                        'max' => 100,
                        'steps' => 1,
                        'filter' => true,
                        'parent' => 'frontend_posting_product_manage_stock',
                        'filter_value' => 'yes',
                    ),
                    'frontend_posting_product_backorders' => array(
                        'name' => esc_html__( 'Allow Backorders?', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_product_backorders', $settings['settings'], 'no' ),
                        'type' => 'select',
                        'values' => array(
                            'no' => esc_html__( 'Do not allow (default)', 'super-forms' ),
                            'notify' => esc_html__( 'Allow, but notify customer', 'super-forms' ),
                            'yes' => esc_html__( 'Allow', 'super-forms' ),
                        ),
                        'filter' => true,
                        'parent' => 'frontend_posting_product_manage_stock',
                        'filter_value' => 'yes',
                    ),
                    'frontend_posting_product_sold_individually' => array(
                        'name' => esc_html__( 'Sold individually?', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_product_sold_individually', $settings['settings'], 'no' ),
                        'type' => 'select',
                        'values' => array(
                            'no' => esc_html__( 'No (default)', 'super-forms' ),
                            'yes' => esc_html__( 'Yes', 'super-forms' ),
                        ),
                        'filter' => true,
                        'parent' => 'frontend_posting_post_type',
                        'filter_value' => 'product',
                    ),
                    'frontend_posting_product_downloadable' => array(
                        'name' => esc_html__( 'Downloadable product', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_product_downloadable', $settings['settings'], 'no' ),
                        'type' => 'select',
                        'values' => array(
                            'no' => esc_html__( 'No (default)', 'super-forms' ),
                            'yes' => esc_html__( 'Yes', 'super-forms' ),
                        ),
                        'filter' => true,
                        'parent' => 'frontend_posting_post_type',
                        'filter_value' => 'product',
                    ),
                    'frontend_posting_product_virtual' => array(
                        'name' => esc_html__( 'Virtual product', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_product_virtual', $settings['settings'], 'no' ),
                        'type' => 'select',
                        'values' => array(
                            'no' => esc_html__( 'No (default)', 'super-forms' ),
                            'yes' => esc_html__( 'Yes', 'super-forms' ),
                        ),
                        'filter' => true,
                        'parent' => 'frontend_posting_post_type',
                        'filter_value' => 'product',
                    ),
                    'frontend_posting_product_visibility' => array(
                        'name' => esc_html__( 'Product visibility', 'super-forms' ),
                        'default' => SUPER_Settings::get_value( 0, 'frontend_posting_product_visibility', $settings['settings'], 'visible' ),
                        'type' => 'select',
                        'values' => array(
                            'visible' => esc_html__( 'Catalog & search (default)', 'super-forms' ),
                            'catalog' => esc_html__( 'Catalog', 'super-forms' ),
                            'search' => esc_html__( 'Search', 'super-forms' ),
                            'hidden' => esc_html__( 'Hidden', 'super-forms' ),
                        ),
                        'filter' => true,
                        'parent' => 'frontend_posting_post_type',
                        'filter_value' => 'product',
                    ),

                )
            );
            return $array;
        }

    }
        
endif;


/**
 * Returns the main instance of SUPER_Frontend_Posting to prevent the need to use globals.
 *
 * @return SUPER_Frontend_Posting
 */
if(!function_exists('SUPER_Frontend_Posting')){
    function SUPER_Frontend_Posting() {
        return SUPER_Frontend_Posting::instance();
    }
    // Global for backwards compatibility.
    $GLOBALS['SUPER_Frontend_Posting'] = SUPER_Frontend_Posting();
}