<?php
/*
Plugin Name: WooCommerce coinpal
Plugin URI: https://www.coinpal.com/
Description: Integrates your coinpal payment getway into your WooCommerce installation.
Version: 1.2
Author: coinpal
Text Domain: coinpal
Author URI: https://www.coinpal.com/
*/
add_action('plugins_loaded', 'init_coinpal_gateway', 0);

function init_coinpal_gateway() {
	if( !class_exists('WC_Payment_Gateway') )  return;
	
	require_once('class-wc-gateway-coinpal.php');
	
	// Add the gateway to WooCommerce
	function add_coinpal_gateway( $methods )
	{
		return array_merge($methods, 
				array(
						'WC_Gateway_coinpal'));
	}
	add_filter('woocommerce_payment_gateways', 'add_coinpal_gateway' );
	
	function wc_coinpal_plugin_edit_link( $links ){
		return array_merge(
				array(
						'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_coinpal') . '">'.__( 'Settings', 'alipay' ).'</a>'
				),
				$links
		);
	}
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_coinpal_plugin_edit_link' );
}
?>
