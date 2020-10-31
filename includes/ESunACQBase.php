<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Gateway_ESunACQBase extends WC_Payment_Gateway {

    public $store_id;
    public $mac_key;
    public $test_mode;
    public $card_last_digits;
    public $request_builder;
    public $ESunHtml;
    public static $log_enabled = false;
    public static $log = false;
    public static $order_recv_text;
    private $len_ono_prefix = 16; # AWYYYYMMDDHHMMSS

    // public function __construct() {
    //     require_once 'Endpoint.php';
    //     require_once 'ReturnMesg.php';
    //     $this -> init();
    //     if (empty($this -> store_id) || empty($this -> mac_key) ){
    //         $this -> enabled = 'no';
    //     }
    //     else {
    //         $this -> request_builder = new ESunACQRequestBuilder(
    //             $this -> store_id,
    //             $this -> mac_key,
    //             $this -> test_mode
    //         );
    //     }

    //     add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    //     add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'handle_response' ) );
    //     add_action( 'woocommerce_api_esunacq_get_order_form_data', array( $this, 'make_order_form_data' ) );
    //     add_filter( 'https_ssl_verify', '__return_false' );
    // }

    // public function init() {
    //     $this -> id = 'esunacq';
    //     $this -> icon = apply_filters( 'woocommerce_' . $this -> id . '_icon', plugins_url('images/esun_logo.png', dirname( __FILE__ ) ) );
    //     $this -> has_fields = false;
    //     $this -> method_title = __( 'ESun ACQ', 'esunacq' );
    //     $this -> method_description = __( 'Credit Card Payment with ESun Bank.', 'esunacq' );
    //     $this -> supports = array( 'products', 'refunds' );

    //     $this -> form_fields = WC_Gateway_ESunACQ_Settings::form_fields();

    //     $this -> enabled        = $this -> get_option( 'enabled' );
    //     $this -> title          = $this -> get_option( 'title' );
    //     $this -> description    = $this -> get_option( 'description' );
    //     $this -> store_id       = $this -> get_option( 'store_id' );
    //     $this -> mac_key        = $this -> get_option( 'mac_key' );
    //     $this -> test_mode      = ( $this -> get_option( 'test_mode' ) ) === 'yes' ? true : false;
    //     $this -> store_card_digits = ( $this -> get_option( 'store_card_digits' ) === 'yes' ) ? true : false;
    //     self::$log_enabled      = ( $this -> get_option( 'logging' ) ) === 'yes' ? true : false;
    //     self::$customize_order_received_text = $this -> get_option( 'thankyou_order_received_text' );
    // }

    public function process_payment( $order_id ) {
        global $woocommerce, $ESunHtml;

        $order = new WC_Order( $order_id );
        $order -> update_status( 'pending', __( sprintf( 'Awaiting %s Payment', $this -> method_title ), 'esunacq' ) );

        return array(
            'result' => 'success',
            'redirect' => get_site_url() . '/wc-api/' . $this -> get_order_from_data . '/?order_id=' . $order_id,
        );
    }

    public function process_refund( $order_id, $amount = null, $reason = '' ) {

        $esun_order_id = get_post_meta( $order_id, '_esunacq_orderid', true );
        $order = new WC_Order( $order_id );

        $res = $this -> request_builder -> request_refund( $esun_order_id );
        $DATA = $this -> get_api_DATA( $res );

        if ( $DATA[ 'returnCode' ] == '00' ){
            return $this -> refund_success( $order, $DATA, $esun_order_id );
        }
        else if ( $DATA[ 'returnCode' ] == 'GF' ){
            return $this -> refund_failed_query( $order, $DATA, $esun_order_id );
        }
        else{
            $refund_note = sprintf( '退款失敗：%s', ReturnMesg::CODE[ $DATA[ 'returnCode' ] ] );
            $order->add_order_note( $refund_note, true );
            return false;
        }
        return false;
    }

    // public function log( $message, $level='info' ) {
    //     // if ( $this -> log_enabled ) {
    //     //     if ( empty( $this -> logging ) ) {
    //     //         $this -> logging = wc_get_logger();
    //     //     }
    //     //     $this -> log( 
    //     //         $level, 
    //     //         $message, 
    //     //         [ 'source' => 'esunacq' ] 
    //     //     );
    //     // }
    //     if ( is_string( $message ) ){
    //         error_log( $message );
    //     }
    //     else{
    //         error_log( var_export( $message ) );
    //     }
    // }
    public static function log($message, $level = 'info')
    {
        error_log("log_enabled");
        error_log(self::$log_enabled);
        error_log($message);
        if (self::$log_enabled) {
            if (empty(self::$log)) {
                self::$log = wc_get_logger();
            }
            self::$log->log($level, $message, array('source' => 'esunacq'));
        }
    }

    private function check_mac( $order, $urlparam, $data, $mfkey ) {
        if ( !array_key_exists( $mfkey, $urlparam ) ){
            $order -> update_status('failed');
            wc_add_notice( $mfkey . ' Not Found.', 'error' );
            wp_redirect( $order -> get_cancel_order_url() );
            exit;
        }
        if ( false && !$this -> request_builder -> check_hash( $data, $urlparam[ $mfkey ] ) ){
            $order -> update_status('failed');
            wc_add_notice( 'Inconsistent ' . $mfkey, 'error' );
            $this ->  log( 'Inconsistent ' . $mfkey );
            $this ->  log( $data );
            wp_redirect( $order -> get_cancel_order_url() );
            exit;
        }
    }

    private function parse_returned_param( $from ){
        preg_match_all('/(?<key>\w+)=(?<value>\w+),*/', $from, $match);

        $res = [];
        for ($i = 0; $i < count($match["key"]); $i++){
            $res[$match["key"][$i]] = $match["value"][$i];
        }

        return $res;
    }

    private function check_RC_MID_ONO( $data ) {
        if (!array_key_exists('RC', $data)){
            wc_add_notice( 'Return Code Not Found' , 'error' );
            wp_redirect( '/' );
            exit;
        }
        if (!array_key_exists('MID', $data) || $data['MID'] != $this -> request_builder -> store_id ){
            wc_add_notice( 'Store ID Incorrect' , 'error' );
            $this -> log( sprintf( 'Store ID Incorrect, got: %s', $data['MID'] ) );
            $this -> log( sprintf( $data ) );
            wp_redirect( '/' );
            exit;
        }
        if (!array_key_exists( 'ONO', $data )){
            wc_add_notice( 'Order No. Not Found.' , 'error' );
            $this -> log( sprintf( 'Order No. Not Found' ) );
            $this -> log( sprintf( $data ) );
            wp_redirect( '/' );
            exit;
        }
    }

    // private function exist_or_add_notice( $data, $field_name, $notice_name=null ){
    //     $notice_name = $notice_name == null ? $field_name : $notice_name;
    //     if (!array_key_exists( $field_name, $data )){
    //         wc_add_notice( $notice_name . ' Not Found.' , 'error' );
    //         $this -> log( $notice_name . ' Not Found' );
    //         $this -> log( sprintf( $data ) );
    //         return false;
    //     }
    //     return true;
    // }

    private function check_MID_ONO( $data, $order, $ono, $op ){
        if ( $data[ 'MID' ] != $this -> store_id || $data[ 'ONO' ] != $ono ){
            $refund_note .= sprintf( 'MID(%s) 或 ONO(%s) 錯誤，退款失敗。<br>', $txnData[ 'MID' ], $txnData[ 'ONO' ] );
            $order -> add_order_note( $refund_note, true );
            return false;
        }
        return true;
    }

    private function get_api_DATA( $res ){
        return json_decode(substr( $res, 5 ), true);
    }

    private function order_failed( $order, $DATA ){
        $order -> update_status('failed');

        wc_add_notice( sprintf( '%s', ReturnMesg::CODE[ $DATA[ 'RC' ] ] ), 'error' );

        $this -> log( sprintf( '%s', ReturnMesg::CODE[ $DATA[ 'RC' ] ] ) );
        $this -> log( $DATA );

        wp_redirect( $order -> get_cancel_order_url() );

        exit;
    }
}

?>