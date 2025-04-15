<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

include_once( 'class-wc-gateway-coinpal-order.php' );

/**
 * Handles refunds
 */
abstract class WC_Gateway_Coinpal_Response {

	/** @var bool Sandbox mode */
	protected $sandbox = false;

	/**
	 * Get the order from the Coinpal 'track_id' variable
	 *
	 * @param  string $track_id, $sub_track_id
	 * @return bool|WC_Order object
	 */
	protected function get_coinpal_order($track_id, $sub_track_id) {
		$order = new WC_Gateway_Coinpal_Order(wc_get_order( $track_id ));
		return $order;
	}

	/**
	 * Complete order, add transaction ID and note
	 * @param  WC_Order $order
	 * @param  string $txn_id
	 * @param  string $note
	 */
	protected function payment_complete( $order, $txn_id = '', $note = '' ) {
		$order->add_order_note( $note );
		$order->payment_complete( $txn_id );
	}

	/**
	 * Hold order and add note
	 * @param  WC_Order $order
	 * @param  string $reason
	 */
	protected function payment_on_hold( $order, $reason = '' ) {
		$order->update_status( 'on-hold', $reason );
	}
}
?>