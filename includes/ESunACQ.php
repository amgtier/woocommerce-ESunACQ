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
        add_filter( 'wocommerce_thankyou_order_received_text',  array( 'WC_Gateway_ESunACQ', 'thankyou_order_received_text' ) );
    }

    public function add_gateway_class($method) {
        $method[] = 'WC_Gateway_ESunACQ';
        return $method;
    }
}
