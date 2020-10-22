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
    private $len_ono_prefix = 16; # AWYYYYMMDDHHMMSS

    public function __construct() {
        require_once 'Endpoint.php';
        require_once 'ReturnMesg.php';
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

    public function thankyou_order_received_text( $args ) {
        return "<h1>Thank you page.</h1>";
    }

    public function process_payment( $order_id ) {
        global $woocommerce, $ESunHtml;

        $order = new WC_Order( $order_id );
        $order -> update_status( 'pending', __( 'Awaiting ESun Payment', 'esunacq' ) );

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
        // $this -> len_ono_prefix = 14;
        $new_order_id = 'AW' . date('YmdHis') . $order -> get_order_number();
        $amount = ceil( $order -> get_total() );
        $res = $this -> request_builder -> json_order( $new_order_id, $amount, 'http://nuan.vatroc.net/wc-api/wc_gateway_esunacq/' );

        echo sprintf("
            <form id='esunacq' method='post' action='%s'>
                <input type='text' hidden name='data' value='%s' />
                <input type='text' hidden name='mac' value='%s' />
                <input type='text' hidden name='ksn' value='1' />
            </form>
            <script>
                var esunacq_form = document.getElementById('esunacq');
                esunacq_form.submit();
            </script>
            ",
            $this -> request_builder -> get_endpoint(),
            // Endpoint_Test::PC_AUTHREQ,
            $res['data'],
            $res['mac']
        );
        exit;
    }

    public function handle_response( $args ){
        global $woocommerce;
        if ( !array_key_exists('DATA', $_GET) ){
            throw new Exception( 'Param DATA not found.' );
            exit;
        }

        preg_match_all('/(?<key>\w+)=(?<value>\w+),*/', $_GET['DATA'], $match);

        $DATA = [];
        for ($i = 0; $i < count($match["key"]); $i++){
            $DATA[$match["key"][$i]] = $match["value"][$i];
        }

        if (!array_key_exists('RC', $DATA)){

            throw new Exception( sprintf('Return Code: <%s>', $DATA['RC']) );
            exit;
        }
        if (!array_key_exists('MID', $DATA) || $DATA['MID'] != $this -> request_builder -> store_id){
            throw new Exception( sprintf('Store ID incorrect. Got: %s', $DATA['MID']) );
            exit;
        }
        if (!array_key_exists( 'ONO', $DATA )){
            throw new Exception( sprintf( 'Order No Not Found.') );
            exit;
        }

        $order_id = substr( $DATA['ONO'], $this -> len_ono_prefix );
        $order = new WC_Order( $order_id );


        if ($DATA['RC'] != "00"){
            $order->update_status('failed');
            wc_add_notice( sprintf( '%s', ReturnMesg::CODE[ $DATA[ 'RC' ] ] ), 'error' );
            wp_redirect( $order -> get_cancel_order_url() );
            // throw new Exception( sprintf( "Error: %s", ReturnMesg::CODE[ $DATA[ 'RC' ] ] ) );
            exit;
        }
        else{
            if ( !array_key_exists('MACD', $_GET) ){
                throw new Exception( 'MACD not found.' );
                exit;
            }

            // if ( !$this -> request_builder -> check_hash( $_GET['DATA'], $_GET['MACD'] ));
        }

        foreach ([
            'RRN' => 'RRN',
            'AIR' => 'AIR',
            'AN' => 'AN',
        ] as $key => $name ){
            if (!array_key_exists( $key, $DATA )){
                throw new Exception( sprintf( '%s No Not Found.', $name) );
                exit;
            }
        }
        wc_reduce_stock_levels( $order_id );
        // $woocommerce -> cart -> empty_cart();

        $pay_type_note = '信用卡 付款（一次付清）';
        $pay_type_note .= sprintf('<br>末四碼：%s', $DATA['AN']);
        $pay_type_note .= sprintf('<br>RRN：%s', $DATA['RRN']);

        $order->add_order_note($pay_type_note, true);
        $order->update_status('processing');
        $order->payment_complete();
        wp_redirect( $order -> get_checkout_order_received_url() );
        exit;
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
        // if ( $this -> log_enabled ) {
        //     if ( empty( $this -> logging ) ) {
        //         $this -> logging = wc_get_logger();
        //     }
        //     $this -> log( 
        //         $level, 
        //         $message, 
        //         [ 'source' => 'esunacq' ] 
        //     );
        // }
        error_log( $message );
    }
}

?>