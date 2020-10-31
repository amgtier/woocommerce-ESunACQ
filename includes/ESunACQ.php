<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_ESunACQ {

    public function __construct() {
        $this -> init_filters();
    }

    private function init_filters() {
        add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway_class' ));
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( 'WC_Gateway_ESunACQ_Settings', 'add_settings_link' ) );
    }

    public function add_gateway_class($method) {
        $method[] = 'WC_Gateway_ESunACQ';
        $method[] = 'WC_Gateway_ESUnionPay';
        return $method;
    }
}
