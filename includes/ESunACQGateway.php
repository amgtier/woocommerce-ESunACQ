<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Gateway_ESunACQ extends WC_Gateway_ESunACQBase {
    
    public function __construct() {
        parent::__construct();
        
        add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'handle_response' ) );

        $this -> init();
        if ( empty( $this -> store_id ) && ( empty( $this -> store_id_test ) ) || empty( $this -> mac_key ) && empty( $this -> mac_key_test ) ){
            $this -> enabled = 'no';
        }
        else {
            $this -> request_builder = new ESunACQRequestBuilder(
                $this -> store_id,
                $this -> store_id_test,
                $this -> mac_key,
                $this -> mac_key_test,
                $this -> test_mode
            );
        }
        if ( $this -> enabled == 'yes' && ( empty( $this -> store_id) || empty( $this -> mac_key ) ) ) {
            $user = wp_get_current_user();
            if ( !in_array( 'administrator', (array) $user->roles ) ){
                $this -> enabled = 'no';
            }
            $this -> test_mode = true;
            $this -> title .= ' test';
        }
    }

    public function init() {
        $this -> id             = 'esunacq';
        $this -> icon           = apply_filters( 'woocommerce_' . $this -> id . '_icon', plugins_url('images/esun_logo.png', dirname( __FILE__ ) ) );
        $this -> has_fields     = false;
        $this -> method_title   = __( 'ESun ACQ', 'esunacq' );
        $this -> method_description = __( 'Credit Card Payment with ESun Bank.', 'esunacq' );
        $this -> supports       = array( 'products', 'refunds' );

        $this -> form_fields    = WC_Gateway_ESunACQ_Settings::form_fields();

        $this -> enabled        = $this -> get_option( 'enabled' );
        $this -> title          = $this -> get_option( 'title' );
        $this -> description    = $this -> get_option( 'description' );
        $this -> store_id       = $this -> get_option( 'store_id' );
        $this -> store_id_test  = $this -> get_option( 'store_id_test' );
        $this -> mac_key        = $this -> get_option( 'mac_key' );
        $this -> mac_key_test   = $this -> get_option( 'mac_key_test' );
        $this -> test_mode      = ( $this -> get_option( 'test_mode' ) ) === 'yes' ? true : false;
        $this -> store_card_digits = ( $this -> get_option( 'store_card_digits' ) === 'yes' ) ? true : false;
        self::$log_enabled      = ( $this -> get_option( 'logging' ) ) === 'yes' ? true : false;
        $this -> get_order_from_data = $this -> id .'_get_order_form_data';
        add_action( 'woocommerce_api_' . $this -> get_order_from_data , array( $this, 'make_order_form_data' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this -> id, array( $this, 'process_admin_options' ) );
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
            $refund_note = sprintf( __( 'Refund Failed: %s', 'esunacq' ), ReturnMesg::CODE[ $DATA[ 'returnCode' ] ] );
            $order->add_order_note( $refund_note, true );
            return false;
        }
        return false;
    }

    public function order_action_esunacq_query_status ( $order ) {
        if ( $order -> get_payment_method() == $this -> id ){
            $esun_order_id = get_post_meta( $order -> id, '_esunacq_orderid', true );
            $Qres = $this -> request_builder -> request_query( $esun_order_id );
            $QDATA = $this -> get_api_DATA( $Qres );
            if ($QDATA[ 'returnCode' ] == '00' ){
                $QtxnData = $QDATA[ 'txnData' ];
                if ( !$this -> check_MID_ONO( $QtxnData, $order, $esun_order_id ) ){
                    $order_note = sprintf( __( 'Query Failed: %s', 'esunacq' ), ReturnMesg::CODE[ $QtxnData[ 'RC' ] ] );
                    $order -> add_order_note( $order_note, true );
                    return;
                }
                else {
                    switch ($QtxnData[ 'RC' ]) {
                        case "00":
                            $order -> update_status( 'processing' );
                            break;
                        case "49":
                            $order -> update_status( 'refunded' );
                            break;
                    }
                    $order_note = sprintf( __( 'Query Result: %s', 'esunacq' ), ReturnMesg::CODE[ $QtxnData[ 'RC' ] ] );
                    $order -> add_order_note( $order_note, true );
                    return;
                }
            }
            else{
                $order_note = sprintf( __( 'Query Failed: %s', 'esunacq' ), ReturnMesg::CODE[ $QDATA[ 'returnCode' ] ] );
                $order -> add_order_note( $order_note, true );
                return;
            }
        }
        return;
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
        $res = $this -> request_builder -> json_order( $new_order_id, $amount, get_home_url() . '/wc-api/wc_gateway_esunacq/' );

        echo sprintf( "
            <p>%s</p>
            <form id='esunacq' method='post' action='%s'>
                <input type='text' hidden name='data' value='%s' />
                <input type='text' hidden name='mac' value='%s' />
                <input type='text' hidden name='ksn' value='1' />
                <!-- <button>submit</button> -->
            </form>
            <script>
                var esunacq_form = document.getElementById('esunacq');
                esunacq_form.submit();
            </script>
            ",
            __( 'Redirecting to ESun Bank. Do not refresh or close the window.', 'esunacq' ),
            $this -> request_builder -> get_endpoint( 'PC_AUTHREQ' ),
            $res['data'],
            $res['mac']
        );
        exit;
    }

    public function handle_response( $args ){
        global $woocommerce;

        if ( !array_key_exists('DATA', $_GET) ){
            wc_add_notice( __( 'Data Not Found', 'esunacq' ) , 'error' );
            wp_redirect( '/cart' );
            exit;
        }

        $DATA = $this -> parse_returned_param( $_GET[ 'DATA' ] );
        $this -> check_RC_MID_ONO( $DATA );

        $order_id = substr( $DATA[ 'ONO' ], $this -> len_ono_prefix );
        $order = new WC_Order( $order_id );

        if ( $DATA['RC'] != "00" ){
            $this -> order_failed( $order, $DATA );
            wp_redirect( '/cart' );
            exit;
        }
        else {
            $this -> check_mac( $order, $_GET, $_GET[ 'DATA' ], 'MACD' );
        }

        foreach ([
            'RRN' => 'RRN',
            'AIR' => 'AIR',
            'AN' => 'AN',
        ] as $key => $name ){
            if ( !array_key_exists( $key, $DATA ) ){
                $order -> update_status( 'failed' );
                wc_add_notice( sprintf( __( '%s No Not Found.', 'esunacq' ), $name), 'error' );
                $this -> log( sprintf( __( '%s No Not Found.', 'esunacq' ), $name) );
                $this -> log( $DATA );
                wp_redirect( $order -> get_cancel_order_url() );
                exit;
            }
        }
        wc_reduce_stock_levels( $order_id );

        $pay_type_note = __( 'Pay by credit card. (At once)', 'esunacq' );
        $pay_type_note .= sprintf( '<br>RRN：%s', $DATA[ 'RRN' ] );
        if ( $this -> store_card_digits ){
            $pay_type_note .= sprintf( __( '<br>Digits: %s', 'esunacq' ), $DATA[ 'AN' ] );
        }

        add_post_meta( $order_id, '_esunacq_orderid', $DATA['ONO'] );

        $order -> add_order_note( $pay_type_note, true );
        $order -> update_status( 'processing' );
        $order -> payment_complete();

        wp_redirect( $order -> get_checkout_order_received_url() );
        exit;
    }

    private function refund_success( $order, $DATA, $esun_order_id ){
        $txnData = $DATA[ 'txnData' ];
        if ( !$this -> check_MID_ONO( $txnData, $order, $esun_order_id ) ){
            return false;
        }
        if ( $txnData[ 'RC' ] == "00" ){
            $refund_note  = sprintf( __( 'Transaction Date: %s<br>', 'esunacq' ), $txnData[ 'LTD' ]);
            $refund_note .= sprintf( __( 'Transaction Time: %s<br>', 'esunacq' ), $txnData[ 'LTT' ]);
            $refund_note .= sprintf( __( 'Bill Serial: %s<br>', 'esunacq' ), $txnData[ 'RRN' ]);
            $refund_note .= sprintf( __( 'Authorization Code: %s<br>', 'esunacq' ), $txnData[ 'AIR' ]);
            $order -> add_order_note( $refund_note, true );
            $order -> update_status( 'refunded' );
            return true;
        }
        else{
            $refund_note .= sprintf( __( 'Refund Failed: %s<br>', 'esunacq' ), ReturnMesg::CODE[ $DATA[ 'RC' ] ] );
            $order -> add_order_note( $refund_note, true );
            return false;
        }
    }

    private function refund_failed_query( $order, $DATA, $esun_order_id ){
        $refund_note= sprintf( __( 'Refund Failed: %s<br>', 'esunacq' ), ReturnMesg::CODE[ $DATA[ 'returnCode' ] ] );

        $Qres = $this -> request_builder -> request_query( $esun_order_id );
        $QDATA = $this -> get_api_DATA( $Qres );
        if ($QDATA[ 'returnCode' ] == '00' ){
            $QtxnData = $QDATA[ 'txnData' ];
            if ( !$this -> check_MID_ONO( $QtxnData, $order, $esun_order_id ) ){
                return false;
            }
            if ( $QtxnData[ 'RC' ] == '49' ){
                $order -> update_status( 'refunded' );
                $refund_note .= __( 'Refunded<br>', 'esunacq' );
                $order -> add_order_note( $refund_note, true );
                return true;
            }
        }
        else{
            $refund_note .= sprintf( __( 'Query Failed: %s', 'esunacq' ), ReturnMesg::CODE[ $QDATA[ 'returnCode' ] ] );
            $order -> add_order_note( $refund_note, true );
            return false;
        }

        $order -> add_order_note( $refund_note, true );
        return false;
    }
}

?>
