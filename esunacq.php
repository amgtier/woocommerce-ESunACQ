<?php
/**
 * Plugin Name: WooCommerce - ESun ACQ
 * Plugin URI: https://github.com/amgtier/woocommerce-ESunACQ
 * Text Domain: esunacq
 * Author: Tzu-Hsiang Chao
 * Author URI: https://github.com/amgtier
 * Description: ESun ACQ for WooCommerce
 * Version: 1.0
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
    require_once 'includes/UnionPaySettings.php';
    require_once 'includes/ESunACQRequestBuilder.php';
    require_once 'includes/TxnType.php';
    require_once 'includes/Endpoint.php';
    require_once 'includes/ReturnMesg.php';

    new WC_ESunACQ();

    function esunacq_load_textdomain() {
        load_plugin_textdomain( 'esunacq', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }


    function order_actions ( $arr ) {
        $arr[ 'esunacq_query_status' ] = __( 'Query Order Status', 'esunacq' );
        return $arr;
    }

    add_action( 'plugins_loaded', 'esunacq_load_textdomain' );
    add_filter( 'woocommerce_thankyou_order_received_text', 'esunacq_order_received_text', 10, 2 );
    add_filter( 'woocommerce_order_actions', 'order_actions', 10, 2 );
}
