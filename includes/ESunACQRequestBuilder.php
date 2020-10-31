<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ESunACQRequestBuilder {

    private $stord_id;
    private $mac_key;

    public function __construct( $store_id, $mac_key, $test_mode=false ) {
        require_once 'TxnType.php';
        require_once 'Endpoint.php';

        $this -> store_id = $store_id;
        $this -> mac_key = $mac_key;
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
        }
    }

    public function json_order( $ONO, $TA, $U, $IC=null, $BPF=null ) {
        $data = [
            'data' => json_encode( $this -> pack( $ONO, $TA, $U, $IC, $BPF ) )
        ];
        $data['mac'] = $this -> packs_sha256( $data['data'] );
        $data['ksn'] = 1;
        return $data;
    }

    public function request_refund( $ONO ) {
        $data = [
            'data' => json_encode( $this -> pack( $ONO, null, null, null, null ) )
        ];
        $data['mac'] = $this -> packs_sha256( $data['data'] );
        $data['ksn'] = 1;
        $res = $this -> post_request( $this -> get_endpoint( 'REFUNDREQ' ), $data  );
        return $res;
    }

    public function request_query( $ONO ){
        $data = [
            'data' => json_encode( $this -> pack( $ONO, null, null, null, null ) )
        ];
        $data['mac'] = $this -> packs_sha256( $data['data'] );
        $data['ksn'] = 1;
        $res = $this -> post_request( $this -> get_endpoint( 'QUERY' ), $data  );
        return $res;
    }

    public function check_hash( $data, $mac ){
        // error_log("my mac:");
        // error_log($data . ',' . $this -> mac_key);
        // error_log($this -> packs_sha256( $data . ',' . $this -> mac_key ));
        // error_log("their mac:");
        // error_log($mac);
        return $this -> packs_sha256( $data . ',' . $this -> mac_key ) == $mac;
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

    private function packs_sha256( $data ) {
        return hash( 'sha256', $data . $this -> mac_key );
    }

    private function post_request( $endpoint, $data ) {
        $res = wp_remote_post( $endpoint, [
            'headers' => [
                'user-agent' => ''
            ],
            'body' => $data
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