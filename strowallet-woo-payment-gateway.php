<?php

/**
 * Plugin Name: Payment Gateway for strowallet on Woo
 * Plugin URI: https://strowallet.com/services
 * Author: strowallet
 * Author URI: https://strowallet.com/
 * Description: Woo payment gateway for strowallet
 * Version: 1.0.0
 * License: 1.0.0
 * License URL: http://www.gnu.org/licenses/gpl-2.0.txt
 * text-domain: strowallet-woo-payment-gateway
*/
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    if(!in_array("woocommerce/woocommerce.php", apply_filters("active_plugins", get_option("active_plugins")))) return;

    define("WC_STROWALLET_VERSION", "1.0.0");
    define( 'WC_STROWALLET_MAIN_FILE', __FILE__ );
    define( 'WC_STROWALLET_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );

    add_action("plugins_loaded", "strowallet_method_init", 11);
    //Notice user
    add_action( 'admin_notices', 'ade_wc_strowallet_testmode_notice' );
    //Admin URL
    add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ade_woo_strowallet_plugin_action_links' );
    //Methods
    function strowallet_method_init()
    {
        //Init  class
	    require_once dirname( __FILE__ ) . '/includes/class-wc-gateway-strowallet.php';
    }

    add_filter("woocommerce_payment_gateways", "strowallet_method_init_payment_gateway");

    function strowallet_method_init_payment_gateway($gateways)
    {
    $gateways[] = "WC_strowallet_Payment_Gateway";
    return $gateways;
    }

    /**
    * Display the test mode notice.
    **/
    function ade_wc_strowallet_testmode_notice() {

    $strowallet_settings = get_option( 'woocommerce_strowallet_settings' );
    $test_mode = isset( $strowallet_settings['testmode'] ) ? $strowallet_settings['testmode'] : '';

    if ( 'yes' === $test_mode ) {
    echo '<div class="error">
        <p>' . sprintf( __( 'strowallet Payment test mode is still enabled, Click <strong><a
                    href="%s">here</a></strong> to
            disable it when you want to start accepting live payment on your site.', 'strowallet-woo-payment-gateway' ), esc_url(
            admin_url( 'admin.php?page=wc-settings&tab=checkout&section=strowallet' ) ) ) . '</p>
    </div>';
    }
    }

    /**
    * Add Settings link to the plugin entry in the plugins menu.
    *
    * @param array $links Plugin action links.
    *
    * @return array
    **/
    function ade_woo_strowallet_plugin_action_links( $links ) {

    $settings_link = array(
    'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=strowallet' ) . '"
        title="' . __( 'View strowallet WooCommerce Settings', 'strowallet-woo-payment-gateway' ) . '">' . __( 'Settings',
        'strowallet-woo-payment-gateway' ) . '</a>',
    );

    return array_merge( $settings_link, $links );

    }