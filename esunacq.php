<?php
/**
 * Plugin Name: WooCommerce - ESun ACQ
 * Text Domain: esunacq
 * Author: Tzu-Hsiang Chao
 * Author URI: https://github.com/amgtier
 * Description: ESun ACQ for WooCommerce
 * Version: 0.1
 * Text Domain: esunacq
 * Domain Path: /languages/
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

add_action('plugins_loaded', 'esunacq_gateway_init', 0);
function esunacq_gateway_init(){
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    require_once 'includes/ESunACQ.php';
    require_once 'includes/ESunACQBase.php';
    require_once 'includes/ESunACQGateway.php';
    require_once 'includes/UnionPayGateway.php';
    require_once 'includes/ESunACQSettings.php';
    require_once 'includes/ESunACQRequestBuilder.php';

    new WC_ESunACQ();

    function esunacq_load_textdomain() {
        load_plugin_textdomain( 'esunacq', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    function esunacq_order_received_text($text, $order)
    {
        return WC_Gateway_ESunACQ::$customize_order_received_text;
    }

    function order_actions ( $arr ) {
        $arr[ 'esunacq_query_status' ] = __( 'Query Order Status' );
        return $arr;
    }

    add_action( 'plugins_loaded', 'esunacq_load_textdomain' );
    add_filter('woocommerce_thankyou_order_received_text', 'esunacq_order_received_text', 10, 2);
    add_filter( 'woocommerce_order_actions', 'order_actions', 10, 2);
}
