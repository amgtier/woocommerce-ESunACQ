<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Gateway_ESunACQBase extends WC_Payment_Gateway {
    
    public static $log_enabled = false;
    public static $log = false;
    public static $order_recv_text;
    protected $len_ono_prefix = 16; # AWYYYYMMDDHHMMSS

    public function __construct() {
        add_filter( 'https_ssl_verify', '__return_false' );
        add_action( 'woocommerce_order_action_esunacq_query_status', array( $this, 'order_action_esunacq_query_status' ) );
    }

    public function process_payment( $order_id ) {
        global $woocommerce, $ESunHtml;

        $order = new WC_Order( $order_id );
        $order -> update_status( 'pending', sprintf( __( 'Awaiting %s Payment', 'esunacq' ), $this -> method_title ) );

        return array(
            'result' => 'success',
            'redirect' => get_site_url() . '/wc-api/' . $this -> get_order_from_data . '/?order_id=' . $order_id,
        );
    }
    
    public static function log($message, $level = 'info', $prefix=null, $identifier=null)
    {
        $log_path = plugin_dir_path( __DIR__ ) . "LogESunACQ.txt";
        if ( $level = 'debug' ){
            if ( $prefix ) {
                $prefix = "[" . $prefix . "]";
            }
            if ( $identifier ) {
                $identifier = "[" . $identifier. "]";
            }
            if ( is_array( $message ) ){
                error_log( sprintf( "[%s]%s%s: %s\n", date("Y/m/d H:i:s", time()), $prefix, $identifier, http_build_query( $message ) ), 3, $log_path);
            } else {
                error_log( sprintf( "[%s]%s%s: %s\n", date("Y/m/d H:i:s", time()), $prefix, $identifier, $message ), 3, $log_path);
            }
        }
        if (self::$log_enabled) {
            if (empty(self::$log)) {
                self::$log = wc_get_logger();
            }
            self::$log -> log($level, $message, array('source' => 'esunacq'));
        }
    }

    protected function check_mac( $order, $urlparam, $data, $mfkey ) {
        if ( !array_key_exists( $mfkey, $urlparam ) ){
            $order -> update_status( 'failed' );
            wc_add_notice( $mfkey . __( ' Not Found.', 'esunacq' ), 'error' );
            wp_redirect( $order -> get_cancel_order_url() );
            exit;
        }
        if ( !$this -> request_builder -> check_hash( $data, $urlparam[ $mfkey ] ) ){
            $order -> update_status( 'failed' );
            wc_add_notice( __( 'Inconsistent ', 'esunacq' ) . $mfkey, 'error' );
            $this -> log( __( 'Inconsistent ', 'esunacq' ) . $mfkey );
            $this -> log( $data );
            wp_redirect( $order -> get_cancel_order_url() );
            exit;
        }
    }

    protected function parse_returned_param( $from ){
        preg_match_all('/(?<key>\w+)=(?<value>\w+),*/', $from, $match);

        $res = [];
        for ($i = 0; $i < count($match[ "key" ]); $i++){
            $res[ $match[ "key" ][ $i ] ] = $match[ "value" ][ $i ];
        }

        return $res;
    }

    protected function check_RC_MID_ONO( $data ) {
        if (!array_key_exists('RC', $data)){
            wc_add_notice( __( 'Return Code Not Found', 'esunacq' ), 'error' );
            wp_redirect( '/' );
            exit;
        }
        if (!array_key_exists('MID', $data) || $data['MID'] != $this -> request_builder -> store_id ){
            wc_add_notice( __( 'Store ID Incorrect', 'esunacq' ), 'error' );
            $this -> log( sprintf( __( 'Store ID Incorrect, got: %s', 'esunacq' ), $data['MID'] ) );
            $this -> log( sprintf( $data ) );
            wp_redirect( '/' );
            exit;
        }
        if (!array_key_exists( 'ONO', $data )){
            wc_add_notice( __( 'Order No. Not Found', 'esunacq' ) , 'error' );
            $this -> log( sprintf( __( 'Order No. Not Found', 'esunacq' ) ) );
            $this -> log( sprintf( $data ) );
            wp_redirect( '/' );
            exit;
        }
    }

    // protected function exist_or_add_notice( $data, $field_name, $notice_name=null ){
    //     $notice_name = $notice_name == null ? $field_name : $notice_name;
    //     if (!array_key_exists( $field_name, $data )){
    //         wc_add_notice( $notice_name . ' Not Found.' , 'error' );
    //         $this -> log( $notice_name . ' Not Found' );
    //         $this -> log( sprintf( $data ) );
    //         return false;
    //     }
    //     return true;
    // }

    protected function check_MID_ONO( $data, $order, $ono ){
        if ( $data[ 'MID' ] != $this -> store_id || $data[ 'ONO' ] != $ono ){
            $refund_note .= sprintf( __( 'MID(%s) or ONO(%s) Error. Refund failed.<br>', 'esunacq' ), $txnData[ 'MID' ], $txnData[ 'ONO' ] );
            $order -> add_order_note( $refund_note, true );
            return false;
        }
        return true;
    }

    protected function get_api_DATA( $res ){
        return json_decode(substr( $res, 5 ), true);
    }

    protected function order_failed( $order, $DATA ){
        $order -> update_status( 'failed' );
        wc_add_notice( sprintf( '%s', ReturnMesg::CODE[ $DATA[ 'RC' ] ] ), 'error' );
        $this -> log( sprintf( '%s', ReturnMesg::CODE[ $DATA[ 'RC' ] ] ) );
        $this -> log( $DATA );
        // exit;
    }
}

?>
