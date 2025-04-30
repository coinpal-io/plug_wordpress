<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

include_once( 'class-wc-gateway-coinpal-order.php' );

/**
 * Generates requests to send to Coinpal
 */
class WC_Gateway_Coinpal_Request {

	/**
	 * Stores line items to send to Coinpal
	 * @var array
	 */
	protected $line_items = array();

	/**
	 * Pointer to gateway making the request
	 * @var WC_Gateway_Coinpal
	 */
	protected $gateway;

	/**
	 * Endpoint for requests from Coinpal
	 * @var string
	 */
	protected $notify_url;

	/**
	 * Constructor
	 * @param WC_Gateway_Coinpal $gateway
	 */
	public function __construct( $gateway ) {
		$this->gateway    = $gateway;
		$this->notify_url = WC()->api_request_url( 'WC_Gateway_Coinpal' );
	}

	/**
	 * Get the Coinpal request URL for an order
	 * @param  WC_Order  $order
	 * @param  boolean $sandbox
	 * @return string
	 */
	public function get_request_url( $order, $sandbox = false ) {
		$Coinpal_args =  $this->get_coinpal_args( $order,$sandbox );

		return $Coinpal_args;
	}

	/**
	 * Get Coinpal Args for passing to PP
	 *
	 * @param WC_Order $order
	 * @return array
	 */
	protected function get_coinpal_args( $order,$sandbox=false ) {
        $goodsName = $this->get_order_item_names($order);
        $order = new WC_Gateway_Coinpal_Order($order);
		WC_Gateway_Coinpal::log( 'Generating payment form for order ' . $order->get_order_number() . ' ,order_id:'.$order->get_id().'. Notify URL: ' . $this->notify_url );

		$order_total = number_format($order->order_total, 2, '.', '');
		$coinpal = new WC_Gateway_Coinpal();

        $param= array(
            'version'=>'2',
            'requestId'=>"WOO".$order->get_id(),
            'merchantNo'=> $this->gateway->get_apikey(),
            'orderNo'=> "WOO".$order->get_id(),
            'orderCurrencyType'=>'fiat',
            'orderAmount'=>number_format($order->order_total, 2, '.', ''),
            'orderCurrency'=>get_woocommerce_currency(),
            'payerEmail'=>$order->billing_email,
            'successUrl'=>esc_url($this->gateway->get_return_url($order)),
            'redirectURL'=>esc_url($this->gateway->get_return_url($order)),
            'cancelURL'=>(get_permalink(function_exists('woocommerce_get_page_id') ? woocommerce_get_page_id('myaccount') : wc_get_page_id('myaccount'))),
            'notifyURL'=>$this->notify_url,
        );


        $param['sign'] = hash("sha256",
            $this->gateway->get_secretkey().
            $param['requestId'].
            $param['merchantNo'].
            $param['orderNo'].
            $param['orderAmount'].
            $param['orderCurrency']
        );
		
		$gatewayUrl="https://pay.coinpal.io/gateway/pay/checkout";

		WC_Gateway_Coinpal::log( '#'.$order->get_id().' payment_url:'.$gatewayUrl.' Request: ' .json_encode($param));

        $response = wp_remote_post($gatewayUrl, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode($param),
            'timeout' => 60,
        ]);
        $body = '';
        $datas = '';
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            // 处理错误
        } else {
            $body = wp_remote_retrieve_body($response);
            $datas = json_decode($body, true);
            $httpCode = $response['response']['code'];
        }

        WC_Gateway_Coinpal::log( '#'.$order->get_id().' payment_response:'.$body);

		$action="";
		if ($httpCode!=200 || empty($datas['nextStepContent'])) {
			// 请求失败
			$action=(get_permalink(function_exists('woocommerce_get_page_id') ? woocommerce_get_page_id('myaccount') : wc_get_page_id('myaccount')));
			
			wc_clear_notices();
			//Return or error prompt
			//wc_add_notice(__( 'There was a problem processing your payment and the order did not complete.Possible reasons are:1、Insufficient funds. 2、Verification failed.', 'woocommerce' ) , 'error');
            $message = sprintf(
                __( 'There was a problem processing your payment and the order did not complete. Possible reasons are: %s', 'coinpal-payment-gateway2' ),
                $datas['respMessage']
            );

            wc_add_notice( $message, 'error' );
		}
		else{

			$action=$datas['nextStepContent'];
		}
		

		return apply_filters( 'coinpal_payment_woocommerce_args', $action, $order->getOrginOrder());
	}


	/**
	 * Get phone number args for Coinpal request
	 * @param  WC_Order $order
	 * @return array
	 */
	protected function get_phone_number_args( $order ) {
		$order = new WC_Gateway_Coinpal_Order($order);
		if ( in_array( $order->billing_country, array( 'US','CA' ) ) ) {
			$phone_number = str_replace( array( '(', '-', ' ', ')', '.' ), '', $order->billing_phone );
			$phone_args   = array(
				'night_phone_a' => substr( $phone_number, 0, 3 ),
				'night_phone_b' => substr( $phone_number, 3, 3 ),
				'night_phone_c' => substr( $phone_number, 6, 4 ),
				'day_phone_a' 	=> substr( $phone_number, 0, 3 ),
				'day_phone_b' 	=> substr( $phone_number, 3, 3 ),
				'day_phone_c' 	=> substr( $phone_number, 6, 4 )
			);
		} else {
			$phone_args = array(
				'night_phone_b' => $order->billing_phone,
				'day_phone_b' 	=> $order->billing_phone
			);
		}
		return $phone_args;
	}

	/**
	 * Get shipping args for Coinpal request
	 * @param  WC_Order $order
	 * @return array
	 */
	protected function get_shipping_args( $order ) {
		$order = new WC_Gateway_Coinpal_Order($order);
		$shipping_args = array();

		if ( 'yes' == $this->gateway->get_option( 'send_shipping' ) ) {
			$shipping_args['address_override'] = $this->gateway->get_option( 'address_override' ) === 'yes' ? 1 : 0;
			$shipping_args['no_shipping']      = 0;

			// If we are sending shipping, send shipping address instead of billing
			$shipping_args['first_name']       = $order->shipping_first_name;
			$shipping_args['last_name']        = $order->shipping_last_name;
			$shipping_args['company']          = $order->shipping_company;
			$shipping_args['address1']         = $order->shipping_address_1;
			$shipping_args['address2']         = $order->shipping_address_2;
			$shipping_args['city']             = $order->shipping_city;
			$shipping_args['state']            = $this->get_Coinpal_state( $order->shipping_country, $order->shipping_state );
			$shipping_args['country']          = $order->shipping_country;
			$shipping_args['zip']              = $order->shipping_postcode;
		} else {
			$shipping_args['no_shipping']      = 1;
		}

		return $shipping_args;
	}

	/**
	 * Get line item args for Coinpal request
	 * @param  WC_Order $order
	 * @return array
	 */
	protected function get_line_item_args( $order ) {
		/**
		 * Try passing a line item per product if supported
		 */
		if ( ( ! wc_tax_enabled() || ! wc_prices_include_tax() ) && $this->prepare_line_items( $order ) ) {

			$line_item_args             = $this->get_line_items();
			$line_item_args['tax_cart'] = $order->get_total_tax();

			if ( $order->get_total_discount() > 0 ) {
				$line_item_args['discount_amount_cart'] = round( $order->get_total_discount(), 2 );
			}

		/**
		 * Send order as a single item
		 *
		 * For shipping, we longer use shipping_1 because Coinpal ignores it if *any* shipping rules are within Coinpal, and Coinpal ignores anything over 5 digits (999.99 is the max)
		 */
		} else {

			$this->delete_line_items();

			$this->add_line_item( $this->get_order_item_names( $order ), 1, number_format( $order->get_total() - round( $order->get_total_shipping() + $order->get_shipping_tax(), 2 ), 2, '.', '' ), $order->get_order_number() );
			$this->add_line_item( sprintf( __( 'Shipping via %s', 'coinpal-payment-gateway2' ), ucwords( $order->get_shipping_method() ) ), 1, number_format( $order->get_total_shipping() + $order->get_shipping_tax(), 2, '.', '' ) );

			$line_item_args = $this->get_line_items();
		}

		return $line_item_args;
	}

	/**
	 * Get order item names as a string
	 * @param  WC_Order $order
	 * @return string
	 */
	protected function get_order_item_names( $order ) {
		$item_names = array();

		foreach ( $order->get_items() as $item ) {
			$item_names[] = $item['name'] . ' x ' . $item['qty'];
		}

		return implode( '; ', $item_names );
	}

	/**
	 * Get order item names as a string
	 * @param  WC_Order $order
	 * @param  array $item
	 * @return string
	 */
	protected function get_order_item_name( $order, $item ) {
		$item_name = $item['name'];
		$item_meta = new WC_Order_Item_Meta( $item['item_meta'] );

		if ( $meta = $item_meta->display( true, true ) ) {
			$item_name .= ' ( ' . $meta . ' )';
		}

		return $item_name;
	}

	/**
	 * Return all line items
	 */
	protected function get_line_items() {
		return $this->line_items;
	}

	/**
	 * Remove all line items
	 */
	protected function delete_line_items() {
		$this->line_items = array();
	}

	/**
	 * Get line items to send to Coinpal
	 *
	 * @param  WC_Order $order
	 * @return bool
	 */
	protected function prepare_line_items( $order ) {
		$order = new WC_Gateway_Coinpal_Order($order);
		$this->delete_line_items();
		$calculated_total = 0;

		// Products
		foreach ( $order->get_items( array( 'line_item', 'fee' ) ) as $item ) {
			if ( 'fee' === $item['type'] ) {
				$line_item        = $this->add_line_item( $item['name'], 1, $item['line_total'] );
				$calculated_total += $item['line_total'];
			} else {
				$product          = $order->get_product_from_item( $item );
				$line_item        = $this->add_line_item( $this->get_order_item_name( $order, $item ), $item['qty'], $order->get_item_subtotal( $item, false ), $product->get_sku() );
				$calculated_total += $order->get_item_subtotal( $item, false ) * $item['qty'];
			}

			if ( ! $line_item ) {
				return false;
			}
		}

		// Shipping Cost item - Coinpal only allows shipping per item, we want to send shipping for the order
		if ( $order->get_total_shipping() > 0 && ! $this->add_line_item( sprintf( __( 'Shipping via %s', 'coinpal-payment-gateway2' ), $order->get_shipping_method() ), 1, round( $order->get_total_shipping(), 2 ) ) ) {
			return false;
		}

		// Check for mismatched totals
		if ( wc_format_decimal( $calculated_total + $order->get_total_tax() + round( $order->get_total_shipping(), 2 ) - round( $order->get_total_discount(), 2 ), 2 ) != wc_format_decimal( $order->get_total(), 2 ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Add Coinpal Line Item
	 * @param string  $item_name
	 * @param integer $quantity
	 * @param integer $amount
	 * @param string  $item_number
	 * @return bool successfully added or not
	 */
	protected function add_line_item( $item_name, $quantity = 1, $amount = 0, $item_number = '' ) {
		$index = ( sizeof( $this->line_items ) / 4 ) + 1;

		if ( ! $item_name || $amount < 0 || $index > 9 ) {
			return false;
		}

		$this->line_items[ 'item_name_' . $index ]   = html_entity_decode( wc_trim_string( $item_name, 127 ), ENT_NOQUOTES, 'UTF-8' );
		$this->line_items[ 'quantity_' . $index ]    = $quantity;
		$this->line_items[ 'amount_' . $index ]      = $amount;
		$this->line_items[ 'item_number_' . $index ] = $item_number;

		return true;
	}

	/**
	 * Get the state to send to Coinpal
	 * @param  string $cc
	 * @param  string $state
	 * @return string
	 */
	protected function get_Coinpal_state( $cc, $state ) {
		if ( 'US' === $cc ) {
			return $state;
		}

		$states = WC()->countries->get_states( $cc );

		if ( isset( $states[ $state ] ) ) {
			return $states[ $state ];
		}

		return $state;
	}
}
?>