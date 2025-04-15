<?php
defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Coinpal Blocks integration
 *
 * @since 1.0
 */
final class CoinpalGatewayBlock extends AbstractPaymentMethodType {

	/**
	 * The gateway instance.
	 *
	 * @var WC_Gateway_Coinpal
	 */
	private $gateway;

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'coinpal';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_coinpal_settings', [] );
		$this->gateway  = new WC_Gateway_Coinpal();
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {

		$script_path       = 'assets/blocks/frontend/blocks.js';
		$script_asset_path = CPWC_PLUGIN_DIR_PATH . '/assets/blocks/frontend/blocks.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require( $script_asset_path )
			: array(
				'dependencies' => array(),
				'version'      => CPWC_VERSION
			);
		$script_url        = CPWC_PLUGIN_URL . $script_path;

		wp_register_script(
			'coinpal-checkout-block',
			$script_url,
			$script_asset[ 'dependencies' ],
			$script_asset[ 'version' ],
			true
		);

		return [ 'coinpal-checkout-block' ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return [
			'title'       => $this->get_setting( 'curr_title' ),
			'description' => $this->get_setting( 'description' ),
            'plugin_url'  => plugin_dir_url(__FILE__) . '../assets/images/Coinpal.png',
            'supports'    => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] )
		];
	}
}