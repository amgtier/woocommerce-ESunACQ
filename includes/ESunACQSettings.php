<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Gateway_ESunACQ_Settings {
    
    public function add_settings_link( $links ) {
        $links[] = sprintf( '<a href="%s">%s</a>',
            admin_url('admin.php?page=wc-settings&tab=checkout&section=esunacq'),
            __( 'Settings', 'woocommerce' )
        );
        return $links;
    }

    static public function form_fields() {
        return array(
            'enabled' => array(
                'title' => __( 'Enable/Disable', 'woocommerce' ),
                'type' => 'checkbox',
                'lable' => __( 'Enable', 'esunacq' ),
                'description' => __('It is enabled only until both <code>Store ID</code> and <code>Mac Key</code> are nonempty.', 'esunacq'),
                'default' => 'no'
            ),
            'store_id' => array(
                'title'     => __( 'Store ID', 'esunacq' ),
                'type'      => 'text',
                'description' => __( 'Store ID provided by ESun Bank', 'esunacq' ),
                'default'   => ''
            ),
            'store_id_test' => array(
                'title'     => __( 'Store ID for Test Mode', 'esunacq' ),
                'type'      => 'text',
                'description' => __( 'Store ID for testing provided by ESun Bank', 'esunacq' ),
                'default'   => ''
            ),
            'mac_key' => array(
                'title'     => __( 'Mac Key', 'esunacq' ),
                'type'      => 'text',
                'description' => __( 'Mac Key provided by ESun Bank', 'esunacq' ),
                'default'   => ''
            ),
            'mac_key_test' => array(
                'title'     => __( 'Mac Key for Test Mode', 'esunacq' ),
                'type'      => 'text',
                'description' => __( 'Mac Key for testing provided by ESun Bank', 'esunacq' ),
                'default'   => ''
            ),
            'title' => array(
                'title' => __( 'Title', 'woocommerce' ),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                'default' => __('ESun ACQ', 'esunacq'),
                // 'desc_tip' => true
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
                'default' => __('Pay with Credit Car through ESun Bank.', 'woocommerce')
            ),
            'test_mode' => array(
                'title'     => __( 'Test Mode', 'esunacq' ),
                'label'     => __( 'Enable', 'woocommerce' ),
                'type'      => 'checkbox',
                'description' => __( 'Enable the sandbox mode', 'esunacq' ),
                'default'   => 'no'
            ),
            'logging' => array(
                'title'     => __( 'Save Logs', 'esunacq' ),
                'label'     => __( 'Enable', 'woocommerce' ),
                'type'      => 'checkbox',
                'description' => sprintf( __( 'Enable logging at %1s', 'esunacq' ), '<code>' . WC_Log_Handler_File::get_log_file_path( 'esunacq' ) . '</code>' ),
                'default'   => 'no'
            ),
            'store_card_digits' => array(
                'title'     => __( 'Store Card Digits', 'esunacq' ),
                'label'     => __( 'Enable', 'woocommerce' ),
                'type'      => 'checkbox',
                'description' => __( "Save the first 6 and last 4 digits of buyer's card number at order note.", 'esunacq' ),
                'default'   => 'yes'
            ),
        );
    }
}

?>