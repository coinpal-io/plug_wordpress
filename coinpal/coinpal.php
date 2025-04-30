<?php
/*
Plugin Name: Coinpal Payment Gateway
Plugin URI: https://github.com/coinpal-io/plug_wordpress/tree/update-wp
Description: Integrates your Coinpal payment gateway into your WooCommerce installation.
Version: 1.6.6
Author: Coinpal Team
Text Domain: coinpal-payment-gateway2
Author URI: https://www.coinpal.io
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

add_action('plugins_loaded', 'coinpal_init_gateway', 0);

if ( !defined( 'COINPAL_CPWC_PLUGIN_FILE' ) ) {
	define( 'COINPAL_CPWC_PLUGIN_FILE', __FILE__ );
}

if ( !defined( 'COINPAL_CPWC_VERSION' ) ) {
	define( 'COINPAL_CPWC_VERSION', '1.2.4' );
}

if ( !defined( 'COINPAL_CPWC_PLUGIN_URL' ) ) {
	define( 'COINPAL_CPWC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( !defined( 'COINPAL_CPWC_PLUGIN_DIR_PATH' ) ) {
	define( 'COINPAL_CPWC_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );
}

if ( !defined( 'COINPAL_CPWC_PLUGIN_INCLUDE_PATH' ) ) {
	define( 'COINPAL_CPWC_PLUGIN_INCLUDE_PATH', plugin_dir_path( __FILE__ )."includes/" );
}

function coinpal_init_gateway() {
	if( !class_exists('WC_Payment_Gateway') )  return;
	
	require_once('class-wc-gateway-coinpal.php');
	
	// Add the gateway to WooCommerce
	function add_coinpal_gateway( $methods )
	{
		return array_merge($methods, 
				array(
						'WC_Gateway_Coinpal'));
	}
	add_filter('woocommerce_payment_gateways', 'add_coinpal_gateway' );
	
	function wc_coinpal_plugin_edit_link( $links ){
		$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_coinpal' );

		return array_merge(
				array(
						'settings' => '<a href="' .  esc_url($url) . '">'.__( 'Settings', 'coinpal-payment-gateway2' ).'</a>'
				),
				$links
		);
	}
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_coinpal_plugin_edit_link' );


	function declare_cart_checkout_blocks_compatibility() {
		// Check if the required class exists
		if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
			// Declare compatibility for 'cart_checkout_blocks'

			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
		}
	}
	// Hook the custom function to the 'before_woocommerce_init' action
	add_action('before_woocommerce_init', 'declare_cart_checkout_blocks_compatibility');

	require_once('includes/coinpal-gateway-block.php');
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		add_action( 'woocommerce_blocks_payment_method_type_registration', 'register_checkout_block' );
	}

	function register_checkout_block( $payment_method_registry ) {
		$payment_method_registry->register( new CoinpalGatewayBlock );
	}


}
require 'includes/functions_coinpal.php';
?>
