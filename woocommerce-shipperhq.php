<?php 
/*
Plugin Name: WooCommerce ShipperHQ
Plugin URI: http://www.shipperhq.com
Description: Woocommerce ShipperHQ Official Integration
Version: 1.6.3
Author: ShipperHQ
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define('WC_SHIPPERHQ_ROOT_FILE', __FILE__ );

class WooCommerce_ShipperHQ {

    public $version = '1.6.3';


    private static $instance;

    /**
     * Check if WooCommerce is active
     */
    public function __construct()
    {

        if ( ! function_exists( 'is_plugin_active_for_network' ) ) :
            require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
        endif;

        // is woocommerce active
        $active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
        if ( !in_array( 'woocommerce/woocommerce.php', $active_plugins) ) {
            return;
        }

        $this->include_libs();

        $this->init_shipperHQ();

        // Enqueue scripts
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 20 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 20  );
    }

    /**
     * Include relevant classes
     */
    private function include_libs()
    {
        $shq_dir = plugin_dir_path( __FILE__ ) ;
        require_once($shq_dir . '/includes/shipperhq/Shipping.php');
        require_once($shq_dir . '/includes/shipperhq/admin/ProductData.php');
        require_once($shq_dir . '/includes/shipperhq/admin/OrderView.php');
    }

    /**
     * Singleton
     *
     * @return WooCommerce_ShipperHQ
     */
    public static function get_instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    private function init_shipperHQ()
    {
        $this->product_data = new ShipperHQ_ProductData();
        $this->order_view = new ShipperHQ_OrderView();
        $this->shipping = new ShipperHQ_Shipping();
    }

    /**
     * TODO
     */
    public function enqueue_scripts() {

        if ( is_admin() ) :
//            wp_enqueue_script( 'woocommerce-shipperhq-admin', plugins_url( '/assets/js/woocommerce-shipperhq-admin.js', __FILE__ ), array(
//                'jquery',
//            ), $this->version );
        else :
//            wp_enqueue_script( 'woocommerce-shipperhq', plugins_url( '/assets/js/woocommerce-shipperhq.js', __FILE__ ), array(
//                'jquery',
//                'wc-checkout'
//            ), $this->version );
        endif;

    }

    public function log( $message ) {

        if ( 'yes' == get_option( 'shipperhq_debug', 'yes' ) ) :

            $log_file = plugin_dir_path( __FILE__ ) . '/log.txt';
            if ( is_writeable( $log_file ) ) :
                $log 		= fopen( $log_file, 'a+' );
                $message 	= '[' . date( 'd-m-Y H:i:s' ) . '] ' . $message . PHP_EOL;
                fwrite( $log, $message );
                fclose( $log );
            else :
                error_log( 'log file not writable' );
                error_log( $message );
            endif;

        endif;

    }

}

if ( ! function_exists( 'ShipperHQ' ) ) :

    function ShipperHQ() {
        return WooCommerce_ShipperHQ::get_instance();
    }

endif;

ShipperHQ();
