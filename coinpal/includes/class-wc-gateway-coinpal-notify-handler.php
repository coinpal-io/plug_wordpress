<?php

if ( ! defined( "ABSPATH" ) ) {
	exit; // Exit if accessed directly
}

include_once( "class-wc-gateway-coinpal-response.php" );

/**
 * Handles responses from Coinpal Notify
 */
class WC_Gateway_Coinpal_Notify_Handler extends WC_Gateway_Coinpal_Response {

	/**
	 * Constructor
	 */
	public function __construct( $sandbox = false) {
		add_action( "woocommerce_api_wc_gateway_coinpal", array( $this, "check_response" ) );
		add_action( "valid-coinpal-notify", array( $this, "valid_response" ) );

		$this->sandbox        = $sandbox;
		
	}

	/**
	 * Check for Coinpal Notify Response
	 */
	public function check_response() {
		
		wc_clear_notices();
		
		header('HTTP/1.1 200 OK');
		
		if ( ! empty( $_POST ) && $this->validate_notify() ) {
			$posted = array_map(
				function ( $value ) {
					if ( is_array( $value ) ) {
						return array_map( 'sanitize_text_field', $value );
					}
					return sanitize_text_field( $value );
				},
				wp_unslash( $_POST )
			);
			do_action( "valid-coinpal-notify", $posted );
			exit;
		}

		wp_die( "Coinpal Notify failed", "Coinpal Notify", array( "response" => 400 ) );
	}

	/**
	 * There was a valid response
	 * @param  array $posted Post data after wp_unslash
	 */
	public function valid_response( $posted ) {
		$REQ_INVOICE=empty($posted["orderNo"])?"":$posted["orderNo"];
		$REQ_INVOICE=substr($REQ_INVOICE,3);
		if ( ! empty($REQ_INVOICE) && ( $order = $this->get_coinpal_order($REQ_INVOICE,$REQ_INVOICE) ) ) {

			WC_Gateway_Coinpal::log( "Found order #" .$order->get_id()." s:".$REQ_INVOICE."  notify:".json_encode($posted));
			
			$this->validate_amount_currency( $order, $posted );

			if ( method_exists( __CLASS__, "payment_status_" . $posted["status"] ) ) {
				call_user_func( array( __CLASS__, "payment_status_" . $posted["status"] ), $order, $posted );
				die ("OK");
			}
		} else {
			WC_Gateway_Coinpal::log( "order not found , order:".$REQ_INVOICE);
			die ("order not found ");
		}
	}
	
	protected function isCoinpal($payment_method) {
		return substr($payment_method, 0, strlen("coinpal")) === "coinpal";
	}

	/**
	 *   
	 * Check Coinpal notify validity
	 */
	public function validate_notify() {
		WC_Gateway_Coinpal::log( "Checking Notify response is valid" );
		$coinpal = new WC_Gateway_Coinpal();
		$apiKey = $coinpal->get_option("api_key");
		$secretKey = $coinpal->get_option("secret_key");
		
		$check_array = array(
				"apiKey"=>$apiKey,
				"secretKey"=>$secretKey
		);

		$cleaned_post = [];
		$cleaned_post['requestId']     = isset($_POST['requestId']) ? sanitize_text_field($_POST['requestId']) : '';
		$cleaned_post['merchantNo']    = isset($_POST['merchantNo']) ? sanitize_text_field($_POST['merchantNo']) : '';
		$cleaned_post['orderNo']       = isset($_POST['orderNo']) ? sanitize_text_field($_POST['orderNo']) : '';
		$cleaned_post['orderAmount']   = isset($_POST['orderAmount']) ? $_POST['orderAmount'] : 0;
		$cleaned_post['orderCurrency'] = isset($_POST['orderCurrency']) ? sanitize_text_field($_POST['orderCurrency']) : '';
		$cleaned_post['sign']          = isset($_POST['sign']) ? $_POST['sign'] : '';
		$valid = $this->validatePSNSIGN($cleaned_post, $check_array);

		if($valid){
			WC_Gateway_Coinpal::log( "Received valid response from Coinpal , notify:".json_encode($cleaned_post));
			return true;
		} else {
			WC_Gateway_Coinpal::log( "Received invalid response from Coinpal, notify:".json_encode($cleaned_post) );
		}
		
		return false;
	}
	
	// 验证 付款结果/PSN 提交的REQ_SIGN 是否合法
	public function validatePSNSIGN($param,$check_array){
		$sign = hash("sha256",
			$check_array["secretKey"].
			$param['requestId'].
			$param["merchantNo"].
			$param["orderNo"].
			$param["orderAmount"].
			$param["orderCurrency"]
		);

		return $sign==$param['sign'];
	}
	
	private function validate_amount_currency( $order, $posted ) {
		// Validate currency
		$order_amount = number_format( $order->get_total(), 2, ".", "" );
		$order_currency = $order->get_order_currency();
		$currency = $posted["orderCurrency"];
		$amount = $posted["orderAmount"];
		$error = false;
		$error_amount = null;
		$error_currency = null;
		//WC_Gateway_Coinpal::log( "validate_amount_currency：" . json_encode($posted) . " ===".$order_currency."-".$order_amount );
		
		
		if ($order_currency == $currency) {
			if ($order_amount != $amount) {
				WC_Gateway_Coinpal::log( "#".$posted["orderNo"]." amount do not match , gc-amount:".$amount." order-amount:".$order_amount );
				$error=true;
			}
		}
		else{
			WC_Gateway_Coinpal::log( "#".$posted["orderNo"]." currency do not match , gc-currency:".$currency." order-currency:".$order_currency );
		}		
		
		if (true == $error) {
			exit;
		}
	}

	/**
	 * Check currency from Notify matches the order
	 * @param  WC_Order $order
	 * @param  string $currency
	 * 
	 */
	private function validate_currency( $order, $currency ) {
		// Validate currency
		if ( $order->get_order_currency() != $currency ) {
			WC_Gateway_Coinpal::log( "Payment error: Currencies do not match (sent \"" . $order->get_order_currency() . "\" | returned \"" . $currency . "\")" );

			// Put this order on-hold for manual checking
			$order->update_status( "on-hold", sprintf( __( "Validation error: Coinpal currencies do not match (code %s).", "woocommerce" ), $currency ) );
			exit;
		}
	}

	/**
	 * Check payment amount from Notify matches the order
	 * @param  WC_Order $order
	 */
	private function validate_amount( $order, $amount ) {
		if ( number_format( $order->get_total(), 2, ".", "" ) != number_format( $amount, 2, ".", "" ) ) {
			WC_Gateway_Coinpal::log( "Payment error: Amounts do not match (gross " . $amount . ")" );

			// Put this order on-hold for manual checking
			$order->update_status( "on-hold", sprintf( __( "Validation error: Coinpal amounts do not match (gross %s).", "woocommerce" ), $amount ) );
			exit;
		}
	}

	/**
	 * 
		"wc-pending"   
		"wc-processing
		"wc-on-hold"   
		"wc-completed
		"wc-cancelled" 
		"wc-refunded" 
		"wc-failed"
	 */
	
	/**
	 * Handle a unpaid payment
	 * @param  WC_Order $order
	 */
	private function payment_status_unpaid( $order, $posted ) {
		$order->update_status( "pending", sprintf( __( "Payment %s via Coinpal Notify.", "woocommerce" ), wc_clean( $posted["status"] ) ) );
	}

	/**
	 * Handle a partial paid payment
	 * @param  WC_Order $order
	 */
	private function payment_status_partial_paid( $order, $posted ) {
		$order->update_status( "partialpaid", sprintf( __( "Payment %s via Coinpal Notify.", "woocommerce" ), wc_clean( $posted["status"] ) ) );
	}
	
	/**
	 * Handle a completed payment
	 * @param  WC_Order $order
	 */
	private function payment_status_paid( $order, $posted ) {
		if ( $order->has_status( "completed" ) ) {
			WC_Gateway_Coinpal::log( "Aborting, Order #" . $order->get_id() . " is already complete." );
			exit;
		}

		if ( "paid" === $posted["status"] ) {
			WC_Gateway_Coinpal::log( "#".$order->get_id()." 1" );
			$this->payment_complete( $order,"", __( "Coinpal Notify payment completed", "woocommerce" ) );
		} else {
			WC_Gateway_Coinpal::log( "#".$order->get_id()." 2" );
			$this->payment_on_hold( $order, sprintf( __( "Payment pending", "woocommerce" )) );
		}
	}
	
	/**
	 * Handle a pending payment
	 * @param  WC_Order $order
	 */
	private function payment_status_pending( $order, $posted ) {
		if ( $order->has_status( "completed" ) ) {
			WC_Gateway_Coinpal::log( "Aborting, Order #" . $order->get_id() . " is already complete." );
			exit;
		}

		if ( "pending" === $posted["status"] ) {
			$this->payment_on_hold( $order, sprintf( __( "Payment pending", "woocommerce" )) );
		}
	}
	
	/**
	 * Handle a cancelled payment
	 * @param  WC_Order $order
	 */
	private function payment_status_cancelled( $order, $posted ) {
		//$this->payment_status_failed( $order, $posted );
		$order->update_status( "cancelled", sprintf( __( "Payment %s via Coinpal Notify.", "woocommerce" ), wc_clean( $posted["status"] ) ) );
	}
	
	/**
	 * Handle a failed payment
	 * @param  WC_Order $order
	 */
	private function payment_status_failed( $order, $posted ) {
		if ($order->has_status( "pending" )) {
			$order->update_status( "cancelled", sprintf( __( "Payment %s via Coinpal Notify.", "woocommerce" ), wc_clean( $posted["status"] ) ) );
		}
	}
	
	/**
	 * Handle a refunding order
	 * @param  WC_Order $order
	 */
	private function payment_status_refunding( $order, $posted ) {
		$order->update_status( "processing", sprintf( __( "Payment %s via Coinpal Notify.", "woocommerce" ), strtolower( $posted["status"] ) ) );
	
		$this->send_email_notification(
				sprintf( __( "Payment for order #%s refunding", "woocommerce" ), $order->get_order_number() ),
				sprintf( __( "Order %s has been marked as processing due to a refunding", "woocommerce" ), $order->get_order_number())
		);
	}

	/**
	 * Handle a refunded order
	 * @param  WC_Order $order
	 */
	private function payment_status_refunded( $order, $posted ) {
		$order->update_status( "refunded", sprintf( __( "Payment %s via Coinpal Notify.", "woocommerce" ), strtolower( $posted["status"] ) ) );

		$this->send_email_notification(
			sprintf( __( "Payment for order #%s refunded", "woocommerce" ), $order->get_order_number() ),
			sprintf( __( "Order %s has been marked as refunded", "woocommerce" ), $order->get_order_number())
		);
	}
	
	/**
	 * Handle a complaint order
	 * @param  WC_Order $order
	 */
	private function payment_status_complaint( $order, $posted ) {
		$order->update_status( "processing", sprintf( __( "Payment %s via Coinpal Notify.", "woocommerce" ), strtolower( $posted["status"] ) ) );
	
		$this->send_email_notification(
			sprintf( __( "Payment for order #%s complaint", "woocommerce" ), $order->get_order_number() ),
			sprintf( __( "Order %s has been marked processing due to a complaint", "woocommerce" ), $order->get_order_number())
		);
	}

	/**
	 * Handle a chargeback
	 * @param  WC_Order $order
	 */
	private function payment_status_chargeback( $order, $posted ) {
		$order->update_status( "on-hold", sprintf( __( "Payment %s via Coinpal Notify.", "woocommerce" ), wc_clean( $posted["status"] ) ) );

		$this->send_email_notification(
			sprintf( __( "Payment for order #%s reversed", "woocommerce" ), $order->get_order_number() ),
			sprintf( __( "Order %s has been marked on-hold due to a chargeback", "woocommerce" ), $order->get_order_number() )
		);
	}

	/**
	 * Send a notification to the user handling orders.
	 * @param  string $subject
	 * @param  string $message
	 */
	private function send_email_notification( $subject, $message ) {
		$new_order_settings = get_option( "woocommerce_new_order_settings", array() );
		$mailer             = WC()->mailer();
		$message            = $mailer->wrap_message( $subject, $message );

		$mailer->send( ! empty( $new_order_settings["recipient"] ) ? $new_order_settings["recipient"] : get_option( "admin_email" ), $subject, $message );
	}
}
?>
