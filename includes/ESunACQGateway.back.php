<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Gateway_ESunACQ extends WC_Payment_Gateway {

    public $store_id;
    public $mac_key;
    public $test_mode;
    public $log_enabled;
    public $logging;
    public $card_last_digits;
    public $request_builder;

    public function __construct() {
        $this -> init();
        if (empty($this -> store_id) || empty($this -> mac_key) ){
            $this -> enabled = 'no';
        }
        else {
            $this -> request_builder = new ESunACQRequestBuilder(
                $this -> store_id,
                $this -> mac_key,
                $this -> test_mode
            );
        }

        add_action( 'woocommerce_receipt_' . $this->id, array(&$this, 'receipt_page') );
        add_action( 'woocommerce_review_order_after_submit' . $this->id, array( $this, 'hello_world' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'handle_response' ) );
        add_filter( 'https_ssl_verify', '__return_false' );
    }

    public function receipt_page( $args ){
        echo "<h1>" . $args . "</h1>";
        echo "<h1>A Whole New World</h1>";
    }

    public function init() {
        $this -> id = 'esunacq';
        $this -> icon = apply_filters( 'woocommerce_' . $this -> id . '_icon', plugins_url('images/esun_logo.jpeg', dirname( __FILE__ ) ) );
        $this -> has_fields = false;
        $this -> method_title = __( 'ESun ACQ', 'esunacq' );
        $this -> method_description = __( 'Credit Card Payment with ESun Bank.', 'esunacq' );
        $this -> supports = array( 'products', 'refunds' );

        $this -> form_fields = WC_Gateway_ESunACQ_Settings::form_fields();

        $this -> enabled = $this -> get_option( 'enabled' );
        $this -> title = $this -> get_option( 'title' );
        $this -> description = $this -> get_option( 'description' );
        $this -> store_id = $this -> get_option( 'store_id' );
        $this -> mac_key = $this -> get_option( 'mac_key' );
        $this -> test_mode = ( $this -> get_option( 'test_mode' ) ) === 'yes' ? true : false;
        $this -> log_enabled = ( $this -> get_option( 'logging' ) ) === 'yes' ? true : false;
        $this -> logging = null;
        $this -> card_last_digits = ( $this -> get_option( 'card_last_digits' ) === 'yes' ) ? true : false;
    }

    public function thankyou_order_received_text() {
        return $this -> get_option( 'thankyou_order_received_text' );
    }

    public function process_payment( $order_id ) {
        global $woocommerce;

        $order = new WC_Order( $order_id );

        $new_order_id = 'AW' . date('Ymd') . $order -> get_order_number();
        $amount = ceil( $order -> get_total() );
        $return_url = $this -> get_return_url( $order );

        // $res = $this -> request_builder -> place_order( $new_order_id, $amount, $return_url );

        // error_log("RRRRRR");
        // error_log(date('Y-m-d H:i:s'));
        // error_log("RRRRRR");
        // print($res);
        // wc_reduce_stock_levels( $order_id );
        // $woocommerce -> cart -> empty_cart();

        // add_post_meta( $order_id, '_pchomepay_orderid', $order_id );
        // $order -> update_status( 'pending', __( 'Awaiting ESun Payment', 'esunacq' ) );
        // $order -> add_order_note( sprintf( __( 'Order No %s:' ), $order_id ) );

        // return array(
        //     'result' => 'success',
        //     'redirect' => $order -> get_checkout_payment_url(true)
        // );

        return array(
            'result' => 'success',
            // 'redirect' => $return_url,
            // 'redirect' => '#',
            'redirect' => get_site_url() . '/wc-api/esunacq_get_order_form_data/?order_id=' . $order_id,
        );
    }

    public function handle_response( $args ){
        error_log('#######################');
        error_log('handle_response' . $args);
        error_log(WP_REST_Request::get_header());
        error_log('#######################');
    }

    // public function RENDER_ESUN_buffer_start() {
    //     add_action( 'shutdown', 'RENDER_ESUN_buffer_stop', PHP_INT_MAX );
    //     ob_start( 'RENDER_ESUN_content' );
    // }

    // public function RENDER_ESUN_buffer_stop() {
    //     ob_end_flush();
    // }

    // public function RENDER_ESUN_content(  ) {
    //     return $this -> result;
    // }

    public function log( $message, $level='info' ) {
        if ( $this -> log_enabled ) {
            if ( empty( $this -> logging ) ) {
                $this -> logging = wc_get_logger();
            }
            $this -> log( 
                $level, 
                $message, 
                [ 'source' => 'esunacq' ] 
            );
        }
    }
}

?>