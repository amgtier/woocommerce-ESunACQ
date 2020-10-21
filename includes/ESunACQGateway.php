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
    public $ESunHtml;

    public function __construct() {
        require_once 'Endpoint.php';
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

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'handle_response' ) );
        add_action( 'woocommerce_api_esunacq_get_order_form_data', array( $this, 'make_order_form_data' ) );
        add_filter( 'https_ssl_verify', '__return_false' );

    }

    public function init() {
        $this -> id = 'esunacq';
        $this -> icon = apply_filters( 'woocommerce_' . $this -> id . '_icon', plugins_url('images/esun_logo.png', dirname( __FILE__ ) ) );
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
        return "<h1>Thank you page.</h1>";
    }

    public function process_payment( $order_id ) {
        global $woocommerce, $ESunHtml;

        $order = new WC_Order( $order_id );

        $new_order_id = 'AW' . date('Ymd') . $order -> get_order_number();
        $amount = ceil( $order -> get_total() );
        $return_url = $this -> get_return_url( $order );

        $order -> update_status( 'pending', __( 'Awaiting ESun Payment', 'esunacq' ) );
        // $order -> add_order_note( sprintf( '%s %s', __( 'Order No :' ), $order_id ) );

        return array(
            'result' => 'success',
            'redirect' => get_site_url() . '/wc-api/esunacq_get_order_form_data/?order_id=' . $order_id,
        );
    }

    public function make_order_form_data() {
        if (array_key_exists('order_id', $_GET)){
            $order_id = $_GET['order_id'];
        }
        else{
            exit;
        }

        $order = new WC_Order( $order_id );
        $new_order_id = 'AW' . date('Ymd') . $order -> get_order_number();
        $amount = ceil( $order -> get_total() );
        $return_url = $this -> get_return_url( $order );
        $res = $this -> request_builder -> json_order( $new_order_id, $amount, $return_url );

        echo sprintf("
            <form id='esunacq' method='post' action='%s'>
                <input type='text' hidden name='data' value='%s' />
                <input type='text' hidden name='mac' value='%s' />
                <input type='text' hidden name='ksn' value='1' />
            </form>
            <script>
                var esunacq_form = document.getElementById('esunacq');
                esunacq_form.submit();
            </script>",
            Endpoint_Test::PC_AUTHREQ,
            $res['data'],
            $res['mac']
        );
        exit;
    }

    public function handle_response( $args ){
        // global $woocommerce;
        // if (!array_key_exists('DATA', $_GET)){
        //     throw new Exception( 'Param DATA not found.' );
        //     exit;
        // }

        // preg_match_all('/(?<key>\w+)=(?<value>\w+),*/', $_GET['DATA'], $match);

        // $DATA = [];
        // for ($i = 0; $i < count($match); $i++){
        //     $DATA[$match["key"][$i]] = $match["value"][$i];
        // }

        // if (!array_key_exists('RC', $DATA) || $DATA['RC'] != 00){
        //     throw new Exception( sprintf('Return Code: <%s>', $DATA['RC']) );
        //     exit;
        // }
        // if (!array_key_exists('MID', $DATA) || $DATA['MID'] != $this -> request_builder -> store_id){
        //     throw new Exception( sprintf('Store ID incorrect. Got: %s', $DATA['MID']) );
        //     exit;
        // }
        // if (!array_key_exists('ONO', $DATA)){
        //     throw new Exception( sprintf('Order No incorrect. Got: %s', $DATA['ONO']) );
        //     exit;
        // }

        // // wc_reduce_stock_levels( $order_id );
        // // $woocommerce -> cart -> empty_cart();
        return 0;
    }

    public function RENDER_ESUN_buffer_start() {
        add_action( 'shutdown', 'RENDER_ESUN_buffer_stop', PHP_INT_MAX );
        ob_start( 'RENDER_ESUN_content' );
    }

    public function RENDER_ESUN_buffer_stop() {
        ob_end_flush();
    }

    public function RENDER_ESUN_content(  ) {
        return $this -> result;
    }

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