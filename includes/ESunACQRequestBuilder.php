<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ESunACQRequestBuilder {

    private $stord_id;
    private $mac_key;
    private $endpoint;

    public function __construct( $store_id, $mac_key, $test_mode=false ) {
        require_once 'TxnType.php';
        require_once 'Endpoint.php';

        $this -> store_id = $store_id;
        $this -> mac_key = $mac_key;
        $this -> endpoint = !$test_mode ? new Endpoint() : new Endpoint_Test();
    }

    public function place_order( $ONO, $TA, $U, $IC=null, $BPF=null ) {
        return $this -> type4_auth( $ONO, $TA, $U, $IC, $BPF );
    }

    public function json_order( $ONO, $TA, $U, $IC=null, $BPF=null ) {
        return $this -> type4_auth( $ONO, $TA, $U, $IC, $BPF, true );
    }

    private function type4_auth( $ONO, $TA, $U, $IC, $BPF, $return_data=false ) {
        $U = 'http://nuan.vatroc.net/wc-api/wc_gateway_esunacq/';
        $data = [
            'data' => json_encode( $this -> pack( $ONO, $TA, $U, $IC, $BPF ) )
        ];
        $data['mac'] = $this -> packmd5( $data['data'] );
        $data['ksn'] = 1;
        if ( $return_data ){
            return $data;
        }

        $res = $this -> post_request( Endpoint_Test::PC_AUTHREQ, $data  );
        return $res;
    }

    private function pack( $ONO, $TA, $U, $IC, $BPF ) {
        $data = [
            'MID' => $this -> store_id,
            'TID' => TxnType::GENERAL,
            'ONO' => $ONO,
            'TA'  => $TA,
            'U'   => $U
        ];
        if ( $IC != null ){
            $data[ 'IC' ] = $IC;
            $data[ 'TID' ] = TxnType::INSTALLMENT;
        }
        if ( $BPF != null ) {
            $data[ 'BPF' ] = $BPF;
        }
        return $data;
    }

    private function packmd5( $data ) {
        // return md5( $data . $this -> mac_key );
        return hash( 'sha256', $data . $this -> mac_key );
    }

    private function post_request( $endpoint, $data ) {
        $res = wp_remote_post( $endpoint, [
            'headers' => [ 
                'Content-type' => 'application/json',
                'User-agent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:47.0) Gecko/20100101 Firefox/47.0' 
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