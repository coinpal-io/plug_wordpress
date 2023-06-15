<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

include_once( 'class-wc-gateway-coinpal-order.php' );

/**
 * Generates requests to send to coinpal
 */
class WC_Gateway_coinpal_Request {

	/**
	 * Stores line items to send to coinpal
	 * @var array
	 */
	protected $line_items = array();

	/**
	 * Pointer to gateway making the request
	 * @var WC_Gateway_coinpal
	 */
	protected $gateway;

	/**
	 * Endpoint for requests from coinpal
	 * @var string
	 */
	protected $notify_url;

	/**
	 * Constructor
	 * @param WC_Gateway_coinpal $gateway
	 */
	public function __construct( $gateway ) {
		$this->gateway    = $gateway;
		$this->notify_url = WC()->api_request_url( 'WC_Gateway_coinpal' );
	}

	/**
	 * Get the coinpal request URL for an order
	 * @param  WC_Order  $order
	 * @param  boolean $sandbox
	 * @return string
	 */
	public function get_request_url( $order, $sandbox = false ) {
		$coinpal_args =  $this->get_coinpal_args( $order,$sandbox );

		return $coinpal_args;
	}

	/**
	 * Get coinpal Args for passing to PP
	 *
	 * @param WC_Order $order
	 * @return array
	 */
	protected function get_coinpal_args( $order,$sandbox=false ) {
        $goodsName = $this->get_order_item_names($order);
        $order = new WC_Gateway_coinpal_Order($order);
		WC_Gateway_coinpal::log( 'Generating payment form for order ' . $order->get_order_number() . ' ,order_id:'.$order->id.'. Notify URL: ' . $this->notify_url );

		$order_total = number_format($order->order_total, 2, '.', '');
		$coinpal = new WC_Gateway_coinpal();

        $param= array(
            'version'=>'2',
            'requestId'=>"Q".time().rand(1000,9999),
            'merchantNo'=> $this->gateway->get_apikey(),
            'orderNo'=> "WOO".$order->id,
            'orderCurrencyType'=>'fiat',
            'orderAmount'=>number_format($order->order_total, 2, '.', ''),
            'orderCurrency'=>get_woocommerce_currency(),
            'payerEmail'=>$order->billing_email,
            'payerIP'=>$_SERVER['REMOTE_ADDR'],
            'successUrl'=>esc_url($this->gateway->get_return_url($order)),
            'redirectURL'=>esc_url($this->gateway->get_return_url($order)),
            'cancelURL'=>(get_permalink(function_exists('woocommerce_get_page_id') ? woocommerce_get_page_id('myaccount') : wc_get_page_id('myaccount'))),
            'notifyURL'=>$this->notify_url,
        );
		
		//Return or error prompt
		//wc_add_notice(__( 'Order Failed or Cancel. If you think this is an error, you can contact our', 'woocommerce' ) , 'notice');


        $param['sign'] = hash("sha256",
            $this->gateway->get_secretkey().
            $param['requestId'].
            $param['merchantNo'].
            $param['orderNo'].
            $param['orderAmount'].
            $param['orderCurrency']
        );
		
		$gatewayUrl="https://pay.coinpal.io/gateway/pay/checkout";

		
		WC_Gateway_coinpal::log( '#'.$order->id.' payment_url:'.$gatewayUrl.' Request: ' .json_encode($param));
		
		$httpCode = $this->paycurl($gatewayUrl, http_build_query($param), $result);
		
		WC_Gateway_coinpal::log( '#'.$order->id.' payment_response:'.$result);
		
		$datas = json_decode($result, true);
		$action="";
		if ($httpCode!=200 || empty($datas['nextStepContent'])) {
			// 请求失败
			$action=(get_permalink(function_exists('woocommerce_get_page_id') ? woocommerce_get_page_id('myaccount') : wc_get_page_id('myaccount')));
			
			wc_clear_notices();
			//Return or error prompt
			//wc_add_notice(__( 'There was a problem processing your payment and the order did not complete.Possible reasons are:1、Insufficient funds. 2、Verification failed.', 'woocommerce' ) , 'error');
			
			wc_add_notice(__( 'There was a problem processing your payment and the order did not complete.Possible reasons are: '.$datas['respMessage'], 'woocommerce' ) , 'error');
			
		}
		else{
			
			
			//$datas['URL_PAYMENT']=str_replace("https","http",$datas['URL_PAYMENT']); //测试
			
			$action=$datas['nextStepContent'];
		}
		

		return apply_filters( 'woocommerce_coinpal_args', $action, $order->getOrginOrder());
	}
	
	/**
	 * 支付curl提交
	 * @param $url
	 * @param $postData
	 * @param $result
	 * akirametero
	 */
	private function paycurl( $url, $postData, &$result ){
		$options = array();
		if (!empty($postData)) {
			$options[CURLOPT_CUSTOMREQUEST] = 'POST';
			$options[CURLOPT_POSTFIELDS] = $postData;
		}
		$options[CURLOPT_USERAGENT] = 'coinpal/v2.*/CURL';
		$options[CURLOPT_ENCODING] = 'gzip,deflate';
		$options[CURLOPT_HTTPHEADER] = [
		'Accept: text/html,application/xhtml+xml,application/xml',
		'Accept-Language: en-US,en',
		'Pragma: no-cache',
		'Cache-Control: no-cache'
				];
		$options[CURLOPT_RETURNTRANSFER] = 1;
		$options[CURLOPT_HEADER] = 0;
		if (substr($url,0,5)=='https') {
			$options[CURLOPT_SSL_VERIFYPEER] = false;
		}
		$ch = curl_init($url);
		curl_setopt_array($ch, $options);
		$result = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return $httpCode;
	}

	/**
	 * Get phone number args for coinpal request
	 * @param  WC_Order $order
	 * @return array
	 */
	protected function get_phone_number_args( $order ) {
		$order = new WC_Gateway_coinpal_Order($order);
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
	 * Get shipping args for coinpal request
	 * @param  WC_Order $order
	 * @return array
	 */
	protected function get_shipping_args( $order ) {
		$order = new WC_Gateway_coinpal_Order($order);
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
			$shipping_args['state']            = $this->get_coinpal_state( $order->shipping_country, $order->shipping_state );
			$shipping_args['country']          = $order->shipping_country;
			$shipping_args['zip']              = $order->shipping_postcode;
		} else {
			$shipping_args['no_shipping']      = 1;
		}

		return $shipping_args;
	}

	/**
	 * Get line item args for coinpal request
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
		 * For shipping, we longer use shipping_1 because coinpal ignores it if *any* shipping rules are within coinpal, and coinpal ignores anything over 5 digits (999.99 is the max)
		 */
		} else {

			$this->delete_line_items();

			$this->add_line_item( $this->get_order_item_names( $order ), 1, number_format( $order->get_total() - round( $order->get_total_shipping() + $order->get_shipping_tax(), 2 ), 2, '.', '' ), $order->get_order_number() );
			$this->add_line_item( sprintf( __( 'Shipping via %s', 'woocommerce' ), ucwords( $order->get_shipping_method() ) ), 1, number_format( $order->get_total_shipping() + $order->get_shipping_tax(), 2, '.', '' ) );

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
	 * Get line items to send to coinpal
	 *
	 * @param  WC_Order $order
	 * @return bool
	 */
	protected function prepare_line_items( $order ) {
		$order = new WC_Gateway_coinpal_Order($order);
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

		// Shipping Cost item - coinpal only allows shipping per item, we want to send shipping for the order
		if ( $order->get_total_shipping() > 0 && ! $this->add_line_item( sprintf( __( 'Shipping via %s', 'woocommerce' ), $order->get_shipping_method() ), 1, round( $order->get_total_shipping(), 2 ) ) ) {
			return false;
		}

		// Check for mismatched totals
		if ( wc_format_decimal( $calculated_total + $order->get_total_tax() + round( $order->get_total_shipping(), 2 ) - round( $order->get_total_discount(), 2 ), 2 ) != wc_format_decimal( $order->get_total(), 2 ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Add coinpal Line Item
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
	 * Get the state to send to coinpal
	 * @param  string $cc
	 * @param  string $state
	 * @return string
	 */
	protected function get_coinpal_state( $cc, $state ) {
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