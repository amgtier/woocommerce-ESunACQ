<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Gateway_ESUnionPay extends WC_Gateway_ESunACQBase {

    public $store_id;
    public $mac_key;
    public $test_mode;
    public $card_last_digits;
    public $request_builder;
    public $ESunHtml;
    public static $log_enabled = false;
    public static $log = false;
    public static $customize_order_received_text;

    public function __construct() {
        parent::__construct();

        add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'handle_response' ) );

        $this -> init();
        if ( empty($this -> store_id) || ( empty( $this -> mac_key ) && empty( $this -> mac_key_test ) ) ){
            $this -> enabled = 'no';
        }
        else {
            $this -> request_builder = new ESunACQRequestBuilder(
                $this -> store_id,
                $this -> mac_key,
                $this -> mac_key_test,
                $this -> test_mode
            );
        }
    }

    public function order_action_esunacq_query_status ( $order ) {
        if ( $order -> get_payment_method() == $this -> id ){
            
        }
        return;
    }

    public function init() {
        $this -> id = 'esunionpay';
        $this -> icon = apply_filters( 'woocommerce_' . $this -> id . '_icon', plugins_url('images/unionpay_logo.png', dirname( __FILE__ ) ) );
        $this -> has_fields = false;
        $this -> method_title = __( 'UnionPay', 'esunacq' );
        $this -> method_description = __( 'Credit Card Payment with UnionPay.', 'esunacq' );
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
        self::$customize_order_received_text = $this -> get_option( 'thankyou_order_received_text' );

        $this -> get_order_from_data = $this -> id . '_get_order_form_data';
        add_action( 'woocommerce_api_' . $this -> get_order_from_data , array( $this, 'make_order_form_data' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this -> id, array( $this, 'process_admin_options' ) );
    }

    public function process_refund( $order_id, $amount = null, $reason = '' ) {

        $esun_order_id  = get_post_meta( $order_id, '_' . $this -> id . '_orderid', true );
        $txnno          = get_post_meta( $order_id, '_' . $this -> id . '_txnno', true );
        $order = new WC_Order( $order_id );

        $res = $this -> request_builder -> up_request_refund( $new_order_id, $amount, '', $txnno );
        $DATA = $this -> parse_returned_param( $res );

        if ( $DATA[ 'RC' ] == 'PC' ){
            /* refund on process */
            // wait for 1 sec
            $this -> refund_query( $order, $esun_order_id );
            // return $this -> refund_success( $order, $DATA, $esun_order_id );
            // return false;
        }
        else{
            $refund_note = sprintf( __( 'Refund failed: %s' ), ReturnMesg::UP_CODE[ $DATA[ 'RC' ] ] );
            $order->add_order_note( $refund_note, true );
            return false;
        }            
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
        $res = $this -> request_builder -> up_json_action( 'order', $new_order_id, $amount, '/wc-api/wc_gateway_esunionpay/' );

        echo sprintf( "
            <p>%s</p>
            <form id='esunacq' method='post' action='%s'>
                <input type='text' hidden name='MID' value='%s' />
                <input type='text' hidden name='CID' value='%s' />
                <input type='text' hidden name='ONO' value='%s' />
                <input type='text' hidden name='TA' value='%s' />
                <input type='text' hidden name='TT' value='%s' />
                <input type='text' hidden name='U' value='%s' />
                <input type='text' hidden name='TXNNO' value='%s' />
                <input type='text' hidden name='M' value='%s' />
                <button>submit</button>
            </form>
            <script>
                var esunacq_form = document.getElementById('esunacq');
                // esunacq_form.submit();
            </script>
            ",
            __( 'Redirecting to Esun Bank. Do not refresh or close the window.', 'esunacq' ),
            $this -> request_builder -> get_endpoint( 'UNIONPAY' ),
            $res[ 'MID' ],
            $res[ 'CID' ],
            $res[ 'ONO' ],
            $res[ 'TA' ],
            $res[ 'TT' ],
            $res[ 'U' ],
            $res[ 'TXNNO' ],
            $res[ 'M' ]
        );
        exit;
    }

    public function handle_response( $args ){
        $this -> check_RC_MID_ONO( $_GET );

        $order_id = substr( $_GET[ 'ONO' ], $this -> len_ono_prefix );
        $order = new WC_Order( $order_id );

        if ($_GET['RC'] != "00"){
            $this -> order_failed( $order, $_GET );
        }
        else{
            $this -> check_mac( $order, $_GET, $_GET, 'M' );
        }

        $required_fields = [
            'LTD' => 'LTD',
            'LTT' => 'LTT',
            'TRACENUMBER' => 'TRACENUMBER',
            'TRACETIME' => 'TRACETIME',
            'TXNNO' => 'TXNNO',
        ];
        foreach ( $required_fields as $key => $name ){
            if (!array_key_exists( $key, $_GET )){
                $order -> update_status( 'failed' );
                wc_add_notice( sprintf( __( '%s No Not Found.', 'esunacq' ), $name), 'error' );
                $this -> log( sprintf( __( '%s No Not Found.', 'esunacq' ), $name) );
                $this -> log( $_GET );
                wp_redirect( $order -> get_cancel_order_url() );
                exit;
            }
        }
        wc_reduce_stock_levels( $order_id );

        $pay_type_note = __( 'Pay by UnionPay (At once)', 'esunacq' );
        foreach ( $required_fields as $key => $name ){
            $pay_type_note .= sprintf( '<br>%s：%s', $key, $_GET[ $key ] );
        }

        add_post_meta( $order_id, '_' . $this -> id . '_orderid', $_GET['ONO'] );
        add_post_meta( $order_id, '_' . $this -> id . '_txnno'  , $_GET['TXNNO'] );

        $order -> add_order_note( $pay_type_note, true );
        $order -> update_status( 'processing' );
        $order -> payment_complete();
        
        wp_redirect( $order -> get_checkout_order_received_url() );
        exit;
    }

    private function refund_success( $order, $DATA, $esun_order_id ){
        if ( !$this -> check_MID_ONO( $DATA, $order, $esun_order_id ) ){
            return false;
        }
        if ( $DATA[ 'RC' ] == "00" ){
            $refund_note  = sprintf( __( 'Transaction Serial: %s <br>Refund succeeded.', 'esunacq' ), $DATA[ 'TXNNO' ]);
            $order -> add_order_note( $refund_note, true );
            $order -> update_status( 'refunded' );
            return true;
        }
        else{
            $refund_note .= sprintf( __( 'Refund failed: %s<br>', 'esunacq' ), ReturnMesg::UP_CODE[ $DATA[ 'RC' ] ] );
            $order->add_order_note( $refund_note, true );
            return false;
        }
    }

    private function refund_query( $order, $esun_order_id ){
        $Qres = $this -> request_builder -> request_query( $esun_order_id );
        $QDATA = $this -> parse_returned_param( $Qres );
        if ($QDATA[ 'RC' ] == '00' ){
            if ( !$this -> check_MID_ONO( $QtxnData, $order, $esun_order_id ) ){
                return false;
            }
            $refund_note .= sprintf( __( 'UnionPay Status: %s <br>' ), ReturnMesg::UP_CODE[ $QDATA[ 'RC' ] ]);
            $order->add_order_note( $refund_note, true );
            return false;
        }
        else{
            $refund_note = sprintf( '查詢失敗：%s', ReturnMesg::UP_CODE[ $QDATA[ 'RC' ] ] );
            $order->add_order_note( $refund_note, true );
            return false;
        }

        $order -> add_order_note( $refund_note, true );
        return false;
    }
}

?>