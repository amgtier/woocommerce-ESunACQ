<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Gateway_ESunACQ extends WC_Payment_Gateway {

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
        add_filter( 'wocommerce_thankyou_order_received_text',  array( $this, 'thankyou_order_received_text' ) );
    }

    public function init() {
        $this -> id = 'esunacq';
        $this -> icon = apply_filters( 'woocommerce_' . $this -> id . '_icon', plugins_url('images/esun_logo.png', dirname( __FILE__ ) ) );
        $this -> has_fields = false;
        $this -> method_title = __( 'ESun ACQ', 'esunacq' );
        $this -> method_description = __( 'Credit Card Payment with ESun Bank.', 'esunacq' );
        $this -> supports = array( 'products', 'refunds' );

        $this -> form_fields = WC_Gateway_ESunACQ_Settings::form_fields();

        $this -> enabled        = $this -> get_option( 'enabled' );
        $this -> title          = $this -> get_option( 'title' );
        $this -> description    = $this -> get_option( 'description' );
        $this -> store_id       = $this -> get_option( 'store_id' );
        $this -> mac_key        = $this -> get_option( 'mac_key' );
        $this -> test_mode      = ( $this -> get_option( 'test_mode' ) ) === 'yes' ? true : false;
        $this -> store_card_digits = ( $this -> get_option( 'store_card_digits' ) === 'yes' ) ? true : false;
        self::$log_enabled      = ( $this -> get_option( 'logging' ) ) === 'yes' ? true : false;
        self::$order_recv_text = $this -> get_option( 'thankyou_order_received_text' );
    }

    public static function thankyou_order_received_text( $args ) {
        // error_log( $this -> order_recv_text );
        // if ( strlen( $this -> order_recv_text ) > 0 ){
            return sprintf( "<p>AAAMY ORDER RECEIVED TEXT</p><p>%s</p>", $this -> get_option( 'thankyou_order_received_text' ) );
        // }
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

    public function process_refund( $order_id, $amount = null, $reason = '' ) {

        $esun_order_id = get_post_meta( $order_id, '_esunacq_orderid', true );
        $order = new WC_Order( $order_id );

        $res = $this -> request_builder -> request_refund( $esun_order_id );
        $DATA = $this -> get_api_DATA( $res );

        $refund_note = '';
        if ( $DATA[ 'returnCode' ] == '00' ){
            $txnData = $DATA[ 'txnData' ];
            if ( !$this -> check_MID_ONO( $txnData, $order, $esun_order_id, '退款' ) ){
                return false;
            }
            if ( $txnData[ 'RC' ] == "00" ){
                $refund_note .= sprintf( '訂單交易日期: %s<br>', $txnData[ 'LTD' ]);
                $refund_note .= sprintf( '訂單交易時間: %s<br>', $txnData[ 'LTT' ]);
                $refund_note .= sprintf( '簽單序號: %s<br>', $txnData[ 'RRN' ]);
                $refund_note .= sprintf( '授權碼: %s<br>', $txnData[ 'AIR' ]);
                $order -> add_order_note( $refund_note, true );
            }
            else{
                $refund_note .= sprintf( '退款失敗：%s<br>', ReturnMesg::CODE[ $DATA[ 'returnCode' ] ] );
                $order->add_order_note( $refund_note, true );
                return false;
            }
        }
        else if ( $DATA[ 'returnCode' ] == 'GF' ){
            $refund_note .= sprintf( '退款失敗：%s<br>', ReturnMesg::CODE[ $DATA[ 'returnCode' ] ] );

            $Qres = $this -> request_builder -> request_query( $esun_order_id );
            $QDATA = $this -> get_api_DATA( $Qres );
            if ($QDATA[ 'returnCode' ] == '00' ){
                $QtxnData = $QDATA[ 'txnData' ];
                if ( !$this -> check_MID_ONO( $QtxnData, $order, $esun_order_id, '查詢' ) ){
                    return false;
                }
                if ( $QtxnData[ 'RC' ] == '49' ){
                    $order -> update_status( 'refunded' );
                    $refund_note .= '已退款<br>';
                    $order->add_order_note( $refund_note, true );
                    return false;
                }
            }
            else{
                $refund_note .= sprintf( '查詢失敗：%s', ReturnMesg::CODE[ $QDATA[ 'returnCode' ] ] );
                $order->add_order_note( $refund_note, true );
                return false;
            }
            $refund_note .= sprintf( '退款失敗：%s', ReturnMesg::CODE[ $DATA[ 'returnCode' ] ] );
            $order->add_order_note( $refund_note, true );
            return false;
        }
        else{
            $refund_note .= sprintf( '退款失敗：%s', ReturnMesg::CODE[ $DATA[ 'returnCode' ] ] );
            $order->add_order_note( $refund_note, true );
            return false;
        }
        $order -> update_status( 'refunded' );
        return true;
    }

    public function make_order_form_data() {
        if (array_key_exists('order_id', $_GET)){
            $order_id = $_GET['order_id'];
        }
        else{
            exit;
        }

        $order = new WC_Order( $order_id );
        $new_order_id = 'AW' . date('YmdHis') . $order -> get_order_number();
        $amount = ceil( $order -> get_total() );
        $res = $this -> request_builder -> json_order( $new_order_id, $amount, 'http://nuan.vatroc.net/wc-api/wc_gateway_esunacq/' );

        echo sprintf( "thankyou_order_received_text: %s<br>", $this -> get_option( 'thankyou_order_received_text' ) );

        echo sprintf( "
            <form id='esunacq' method='post' action='%s'>
                <input type='text' hidden name='data' value='%s' />
                <input type='text' hidden name='mac' value='%s' />
                <input type='text' hidden name='ksn' value='1' />
                <button>submit</button>
            </form>
            <script>
                var esunacq_form = document.getElementById('esunacq');
                // esunacq_form.submit();
            </script>
            ",
            $this -> request_builder -> get_endpoint( 'PC_AUTHREQ' ),
            $res['data'],
            $res['mac']
        );
        exit;
    }

    public function handle_response( $args ){
        global $woocommerce;

        if ( !array_key_exists('DATA', $_GET) ){
            wc_add_notice( 'Data Not Found' , 'error' );
            wp_redirect( '/' );
            exit;
        }

        $DATA = $this -> parse_returned_param( $_GET['DATA'] );
        $DATA = $this -> parse_returned_param( $_GET['DATA'] );
        $this -> check_RC_MID_NON( $DATA );

        $order_id = substr( $DATA['ONO'], $this -> len_ono_prefix );
        $order = new WC_Order( $order_id );

        if ($DATA['RC'] != "00"){
            $order->update_status('failed');
            wc_add_notice( sprintf( '%s', ReturnMesg::CODE[ $DATA[ 'RC' ] ] ), 'error' );
            wp_redirect( $order -> get_cancel_order_url() );
            $this -> log( sprintf( '%s', ReturnMesg::CODE[ $DATA[ 'RC' ] ] ) );
            $this -> log( $DATA );
            wp_redirect( '/' );
            exit;
        }
        else{
            if ( !array_key_exists('MACD', $_GET) ){
                $order->update_status('failed');
                wc_add_notice( 'MACD Not Found.', 'error' );
                wp_redirect( $order -> get_cancel_order_url() );
                wp_redirect( '/' );
                exit;
            }

            if ( false && !$this -> request_builder -> check_hash( $_GET['DATA'], $_GET['MACD'] ) ){
                $order->update_status('failed');
                wc_add_notice( 'Inconsistent MACD.', 'error' );
                $this -> log( 'Inconsistent MACD' );
                $this -> log( $DATA );
                wp_redirect( '/' );
                exit;
            }
        }

        foreach ([
            'RRN' => 'RRN',
            'AIR' => 'AIR',
            'AN' => 'AN',
        ] as $key => $name ){
            if (!array_key_exists( $key, $DATA )){
                $order->update_status('failed');
                wc_add_notice( sprintf( '%s No Not Found.', $name), 'error' );
                $this -> log( sprintf( '%s No Not Found.', $name) );
                $this -> log( $DATA );
                wp_redirect( '/' );
                exit;
            }
        }
        wc_reduce_stock_levels( $order_id );

        $pay_type_note = '信用卡 付款（一次付清）';
        $pay_type_note .= sprintf('<br>RRN：%s', $DATA['RRN']);
        if ( $this -> store_card_digits ){
            $pay_type_note .= sprintf('<br>末四碼：%s', $DATA['AN']);
        }

        add_post_meta( $order_id, '_esunacq_orderid', $DATA['ONO'] );
        $order -> add_order_note( $pay_type_note, true );
        $order -> update_status( 'processing' );
        $order -> payment_complete();
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
        if (self::$log_enabled) {
            if (empty(self::$log)) {
                self::$log = wc_get_logger();
            }
            self::$log->log($level, $message, array('source' => 'esunacq'));
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

    private function check_RC_MID_NON( $data ) {
        if (!array_key_exists('RC', $data)){
            wc_add_notice( 'Return Code Not Found' , 'error' );
            wp_redirect( '/' );
            exit;
        }
        if (!array_key_exists('MID', $data) || $data['MID'] != $this -> request_builder -> store_id ){
            wc_add_notice( 'Store ID Incorrect' , 'error' );
            $this -> log( sprintf( 'Store ID Incorrect, get: %s', $data['MID'] ) );
            $this -> log( sprintf( $data ) );
            wp_redirect( '/' );
            exit;
        }
        if (!array_key_exists( 'ONO', $data )){
            wc_add_notice( 'Order No. Not Found.' , 'error' );
            $this -> log( sprintf( 'Order No. Not Found, get: %s', $data['ONO'] ) );
            $this -> log( sprintf( $data ) );
            wp_redirect( '/' );
            exit;
        }
    }

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
}

?>