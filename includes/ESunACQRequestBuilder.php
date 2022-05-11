<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ESunACQRequestBuilder {

    private $stord_id;
    private $mac_key;

    public function __construct( $store_id, $store_id_test, $mac_key, $mac_key_test, $test_mode=false ) {

        $this -> store_id = $test_mode ? $store_id_test : $store_id;
        $this -> mac_key = $test_mode ? $mac_key_test : $mac_key;
        $this -> test_mode = $test_mode;
    }

    public function get_endpoint( $name = '' ){
        switch ( $name ){
            case 'PC_AUTHREQ':
                return !$this -> test_mode ? Endpoint::PC_AUTHREQ : Endpoint_Test::PC_AUTHREQ;
            case 'REFUNDREQ':
                return !$this -> test_mode ? Endpoint::REFUNDREQ : Endpoint_Test::REFUNDREQ;
            case 'QUERY':
                return !$this -> test_mode ? Endpoint::QUERY : Endpoint_Test::QUERY;
            case 'UNIONPAY':
                return !$this -> test_mode ? Endpoint::UNIONPAY : Endpoint_Test::UNIONPAY;
        }
    }

    public function json_order( $ONO, $TA, $U, $IC=null, $BPF=null ) {
        $data = [
            'data' => json_encode( $this -> pack( $ONO, strval($TA), $U, $IC, $BPF ) )
        ];
        $data[ 'mac' ] = $this -> packs_esunacq( $data['data'] );
        $data[ 'ksn' ] = 1;
        return $data;
    }

    public function up_json_action( $action, $ONO, $TA, $U, $TXXNO='' ){
        $IC = null;  /* UnionPay does not have such options */
        $BPF = null; /* UnionPay does not have such options */

        $data = $this -> pack( $ONO, $TA, $U, $IC, $BPF );
        $data[ 'CID' ] = '';
        $data[ 'TT' ] = '';
        switch ($action) {
            case 'order':
                $data[ 'TT' ] = '01';
                break;
            case 'refund':
                $data[ 'TT' ] = '04';
                break;
            case 'cancel':
                $data[ 'TT' ] = '31';
                break;
            case 'query':
                $data[ 'TT' ] = '00';
                break;
        }
        $data[ 'TXNNO' ] = $TXXNO;

        $data_to_mac  = sprintf( '%s&', $data[ 'MID' ] );
        $data_to_mac .= sprintf( '%s&', $data[ 'CID' ] );
        $data_to_mac .= sprintf( '%s&', $data[ 'ONO' ] );
        $data_to_mac .= sprintf( '%s&', $data[ 'TA' ] );
        $data_to_mac .= sprintf( '%s&', $data[ 'TT' ] );
        if ( $data[ 'TT' ] != '00' ){
            $data_to_mac .= sprintf( '%s&', $data[ 'U' ] );
        }
        $data_to_mac .= sprintf( '%s&', $data[ 'TXNNO' ] );
        $data_to_mac .= $this -> mac_key;

        $data[ 'M' ] = $this -> packs_esunionpay( $data_to_mac );
        // error_log(date('Y/m/d H:i:s'));
        // error_log($data_to_mac);
        return $data;
    }

    public function request_refund( $ONO ) {
        $data = [
            'data' => json_encode( $this -> pack( $ONO, null, null, null, null ) )
        ];
        $data[ 'mac' ] = $this -> packs_esunacq( $data['data'] );
        $data[ 'ksn' ] = 1;
        $res = $this -> post_request( $this -> get_endpoint( 'REFUNDREQ' ), $data  );
        return $res;
    }

    public function up_request_refund( $new_order_id, $amount, $txnno ) {
        $data = $this -> up_json_action( 'refund', $new_order_id, $amount, '', $txnno );
        $res  = $this -> post_request( $this -> get_endpoint( 'UNIONPAY' ), $data  );
        return $res;
    }

    public function request_query( $ONO ){
        $data = [
            'data' => json_encode( $this -> pack( $ONO, null, null, null, null ) )
        ];
        $data['mac'] = $this -> packs_esunacq( $data['data'] );
        $data['ksn'] = 1;
        $res = $this -> post_request( $this -> get_endpoint( 'QUERY' ), $data  );
        return $res;
    }

    public function up_request_query( $new_order_id, $txnno='' ) {
        $data = $this -> up_json_action( 'query', $new_order_id, '', '', $txnno );
        $res  = $this -> post_request( $this -> get_endpoint( 'UNIONPAY' ), $data  );
        return $res;
    }

    public function check_hash( $data, $mac ){
        return $this -> packs_esunacq( $data, ',' ) == $mac;
    }

    private function pack( $ONO, $TA, $U, $IC, $BPF ) {
        $data = [
            'MID' => $this -> store_id,
            'ONO' => $ONO,
        ];
        if ( $TA != null ){
            $data[ 'TA' ] = $TA;
        }
        if ( $U != null ){
            $data[ 'U' ] = $U;
        }
        if ( $TA != null && $U != null ){
            $data[ 'TID' ] = TxnType::GENERAL;
        }
        if ( $IC != null ){
            $data[ 'IC' ] = $IC;
            $data[ 'TID' ] = TxnType::INSTALLMENT;
        }
        if ( $BPF != null ) {
            $data[ 'BPF' ] = $BPF;
        }
        return $data;
    }

    private function packs_esunacq( $data, $join='' ) {
        return hash( 'sha256', $data . $join . $this -> mac_key );
    }

    private function packs_esunionpay( $data ) {
        return hash( 'md5', $data );
    }

    private function post_request( $endpoint, $data ) {
        $res = wp_remote_post( $endpoint, [
            'headers' => [
                'user-agent' => ''
            ],
            'body' => $data,
            'timeout' => 45,
        ]);
        if (is_wp_error( $res )){
            error_log($res -> get_error_message() );
        }
        else{
            return $res['body'];
        }
    }
}

?>
