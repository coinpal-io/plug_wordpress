<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * coinpal 
 *
 * @class 		WC_Gateway_coinpal
 * @extends		WC_Payment_Gateway
 * @version		1.2
 * @package		WooCommerce/Classes/Payment
 * @author 		coinpal
 */
class WC_Gateway_coinpal extends WC_Payment_Gateway {

	/** @var boolean Whether or not logging is enabled */
	public static $log_enabled = false;

	/** @var WC_Logger Logger instance */
	public static $log = false;
	
	protected $pm_id = '';
	protected $pm = '';
	protected $is_channel = true;
	public $title = '';
	public $description = '';

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$class_name = get_class($this);
		if (strlen($class_name) == strlen('WC_Gateway_coinpal')) {
			$this->is_channel = false;
		}
		$index = strrpos($class_name, '_');
		$this->pm = substr($class_name, $index + 1);
		
		$this->id                 = strtolower($this->is_channel ? 'coinpal-' . $this->pm : $this->pm);
		$this->icon               = apply_filters( 'woocommerce_' . $this->pm . '_icon', plugins_url( 'assets/images/Coinpal.png', __FILE__ ) );
		$this->has_fields         = false;
		$this->order_button_text  = __( 'Continue to Payment', 'woocommerce' );
		$this->method_title       = ($this->pm_id ? 'Virtual Currency ' : '') . $this->getMethodTitle();
		$this->method_description = __( $this->is_channel ? '' : 'Coinpal provides a global payment solution.', 'woocommerce' );
		$this->supports           = array(
			'products'
		);

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();
		
		$this->init_coinpal_setting();

		// Define user set variables
        $title = $this->get_option( 'curr_title' );
        if(empty($title)) {
            $this->title='Crypto-Currency';
        } else {
            $this->title = $title;
        }

		$this->description    = $this->get_option( 'description' );
		$this->enabled        = $this->get_option( 'enabled' );
		self::$log_enabled    = $this->debug;

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->pm, array( $this, 'receipt_page' ) );
		
		if ( ! $this->is_valid_for_use() ) {
			$this->enabled = 'no';
		} else {
			include_once( 'includes/class-wc-gateway-coinpal-notify-handler.php' );
			new WC_Gateway_coinpal_Notify_Handler( $this->testmode);
		}
	}
	
	protected function getMethodTitle() {
		$method_title = '';
		if ($this->title) {
			$method_title = $this->title;
		} else {
			$method_title = __( $this->pm, 'woocommerce' );
			$index = strrpos($this->pm_id, '_');
			if ($index && substr($this->pm_id, $index + 1) == substr($method_title, strlen($method_title) - 2)) {
				$method_title = substr($method_title, 0, strlen($method_title) - 2);
			}
		}
		
		return $method_title;
	}

    protected $curr_title;
	protected $api_key;
	protected $secret_key;
	protected $paymenturl;
	protected $BIL_CC3DS;
	protected $BIL_METHOD;
	protected $REQ_APPID;
	protected function init_coinpal_setting() {
		if ($this->is_channel) {
			$coinpal = new WC_Gateway_coinpal();
            $this->curr_title = $coinpal->get_option('curr_title');
			$this->api_key = $coinpal->get_option('api_key');
			$this->secret_key = $coinpal->get_option('secret_key');
//			$this->paymenturl = $coinpal->get_option('paymenturl');
//			$this->BIL_CC3DS = 'yes' === $coinpal->get_option( 'BIL_CC3DS', 'no' );
//			$this->BIL_METHOD = $coinpal->get_option('BIL_METHOD');
//            $this->REQ_APPID = $coinpal->get_option('REQ_APPID');
            $this->testmode = '';
		    $this->debug = '';
		} else {
            $this->curr_title = $this->get_option('curr_title');
            $this->api_key = $this->get_option('api_key');
			$this->secret_key = $this->get_option('secret_key');
//			$this->paymenturl = $this->get_option('paymenturl');
//			$this->BIL_CC3DS = 'yes' === $this->get_option( 'BIL_CC3DS', 'no' );
//			$this->BIL_METHOD = $this->get_option('BIL_METHOD');
//            $this->REQ_APPID = $this->get_option('REQ_APPID');
            $this->testmode = '';
			$this->debug = '';
		}
	}

    public function get_currtitle() {
        return $this->curr_title;
    }
	
	public function get_apikey() {
		return $this->api_key;
	}
	
	public function get_secretkey() {
		return $this->secret_key;
	}
	
//	public function get_paymenturl() {
//		return $this->paymenturl;
//	}
//
//	public function get_BIL_CC3DS() {
//		return $this->BIL_CC3DS;
//	}
//
//	public function get_BIL_METHOD() {
//		return $this->BIL_METHOD;
//	}
//
//    public function get_REQ_APPID() {
//        return $this->REQ_APPID;
//    }

	/**
	 * Logging method
	 * @param  string $message
	 */
	public static function log( $message ) {
		if ( self::$log_enabled ) {
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}
			self::$log->add( 'coinpal', $message );
		}
	}

	/**
	 * get_icon function.
	 *
	 * @return string
	 */
// 	public function get_icon() {
// 		return apply_filters('woocommerce_coinpal_icon',  plugins_url('assets/images/coinpal.png', __FILE__));
// 	}

	/**
	 * Check if this gateway is enabled and available in the user's country
	 *
	 * @return bool
	 */
	public function is_valid_for_use() {
		return true;
	}

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		if ( $this->is_valid_for_use() ) {
			parent::admin_options();
		} else {
			?>
			<div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woocommerce' ); ?></strong>: <?php _e( 'coinpal does not support your store currency.', 'woocommerce' ); ?></p></div>
			<?php
		}
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$method_title = $this->getMethodTitle();
		if ($this->is_channel) {
			$this->form_fields = array(
					'enabled' => array(
							'title'   => __( 'Enable/Disable', 'woocommerce' ),
							'type'    => 'checkbox',
							'label'   => __( 'Enable ' . $method_title, 'woocommerce' ),
							'default' => 'no'
					),
					'title' => array(
							'title'       => __( 'Title', 'woocommerce' ),
							'type'        => 'text',
							'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
							'default'     => $method_title,
							'desc_tip'    => true,
					),
					'description' => array(
							'title'       => __( 'Description', 'woocommerce' ),
							'type'        => 'text',
							'desc_tip'    => true,
							'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
							'default'     => __( $this->description ? $this->description : ('Pay via ' . $method_title), 'woocommerce' )
					)
			);
		} else {
			$this->form_fields = array(
//					'testmode' => array(
//							'title'       => __( 'coinpal Sandbox', 'woocommerce' ),
//							'type'        => 'checkbox',
//							'label'       => __( 'Enable coinpal sandbox', 'woocommerce' ),
//							'default'     => 'no',
//							'description' => sprintf( __( 'coinpal sandbox can be used to test payments. Sign up for a developer account <a href="%s">here</a>.', 'woocommerce' ), 'https://www.coinpal.com' ),
//					),
//					'debug' => array(
//							'title'       => __( 'Debug Log', 'woocommerce' ),
//							'type'        => 'checkbox',
//							'label'       => __( 'Enable logging', 'woocommerce' ),
//							'default'     => 'no',
//							'description' => sprintf( __( 'Log coinpal events, inside <code>%s</code>', 'woocommerce' ), wc_get_log_file_path( 'coinpal' ) )
//					),
					'invoice_prefix' => array(
							'title'       => __( 'Invoice Prefix', 'woocommerce' ),
							'type'        => 'text',
							'description' => __( 'Please enter a prefix for your invoice numbers. If you use your coinpal account for multiple stores ensure this prefix is unique as coinpal will not allow orders with the same invoice number.', 'woocommerce' ),
							'default'     => 'WC-',
							'desc_tip'    => true,
					),
					'api_details' => array(
							'title'       => __( 'API Credentials', 'woocommerce' ),
							'type'        => 'title',
							'description' => __( 'Enter your Coinpal API credentials which you can find at your app settings after logging in at your coinpal account.', 'woocommerce' ),
					),
                    'curr_title' => array(
                        'title'       => __( 'Method Title', 'woocommerce' ),
                        'type'        => 'text',
                        'description' => __( 'Method Title', 'woocommerce' ),
                        'default'     => '',
                        'desc_tip'    => true,
                        'placeholder' => __( 'Required', 'woocommerce' )
                    ),
					'api_key' => array(
							'title'       => __( 'Merchant Id', 'woocommerce' ),
							'type'        => 'text',
							'description' => __( 'Get your Merchant Id from Coinpal.', 'woocommerce' ),
							'default'     => '',
							'desc_tip'    => true,
							'placeholder' => __( 'Required', 'woocommerce' )
					),
					'secret_key' => array(
							'title'       => __( 'Secret Key', 'woocommerce' ),
							'type'        => 'text',
							'description' => __( 'Get your API credentials from Coinpal.', 'woocommerce' ),
							'default'     => '',
							'desc_tip'    => true,
							'placeholder' => __( 'Required', 'woocommerce' )
					));
//                    'REQ_APPID' => array(
//                        'title'       => __( 'Application ID', 'woocommerce' ),
//                        'type'        => 'text',
//                        'description' => __( 'Application ID', 'woocommerce' ),
//                        'default'     => '',
//                        'desc_tip'    => true,
//                        'placeholder' => __( 'Required', 'woocommerce' )
//                    ),
//					'paymenturl' => array(
//							'title'       => __( 'Terminal', 'woocommerce' ),
//							'type'        => 'select',
//							'description' => __( 'Terminal', 'woocommerce' ),
//							'default'     => 'coinpalpayment',
//							'desc_tip'    => true,
//							'placeholder' => __( 'Required', 'woocommerce' ),
//							'options' => array(
//                                'coinpalpayment' => 'coinpal Payment',
//                                'coinpal' => 'coinpal',
//                            ),
//					),
//					'BIL_CC3DS' => array(
//							'title'       => __( 'Enable 3DS', 'woocommerce' ),
//							'type'        => 'checkbox',
//							'label'       => __( 'Enable 3DS', 'woocommerce' ),
//							'description' => __( 'Enable 3D－Secure', 'woocommerce' ),
//							'default'     => 'no'
//					),
//					'BIL_METHOD' => array(
//							'title'       => __( 'Payment Method', 'woocommerce' ),
//							'type'        => 'text',
//							'description' => __( 'Method', 'woocommerce' ),
//							'default'     => 'C01',
//							'desc_tip'    => true,
//							'placeholder' => __( 'Required', 'woocommerce' )
//					));
		}
	}

	/**
	 * Get the transaction URL.
	 *
	 * @param  WC_Order $order
	 *
	 * @return string
	 */
	public function get_transaction_url( $order ) {
        $this->view_transaction_url = 'https://pay.coinpal.io/gateway/pay/checkout';
		return parent::get_transaction_url( $order );
	}
	
	public function get_pmid() {
		return $this->pm_id;
	}

	/**
	 * Process the payment and return the result
	 *
	 * @param int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {
		include_once('includes/class-wc-gateway-coinpal-request.php');

		$order          = wc_get_order( $order_id );
		$coinpal_request = new WC_Gateway_coinpal_Request( $this );

		return array(
			'result'   => 'success',
			'redirect' => $coinpal_request->get_request_url( $order, $this->testmode )
		);
	}
	
	/**
	 * Output for the order received page.
	 *
	 * @access public
	 * @return void
	 */
	function receipt_page( $order ) {
	
		echo '<p>' . __('Thank you for your order, please click the button below to pay with coinpal.', 'coinpal') . '</p>';
	
		echo $this->generate_coinpal_form( $order );
	}
	
	/**
	 * Generate the coinpal button link (POST method)
	 *
	 * @access public
	 * @param mixed $order_id
	 * @return string
	 */
	function generate_coinpal_form( $order_id ) {
	
		$order = new WC_Order($order_id);
		$coinpal_args_array = array('<input type="hidden" name="' . 'key' . '" value="' . 'value' . '" />');
	
		wc_enqueue_js( '
				$.blockUI({
				message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to coinpal to make payment.', 'coinpal' ) ) . '",
				baseZ: 99999,
				overlayCSS:
				{
				background: "#fff",
				opacity: 0.6
	},
				css: {
				padding:        "20px",
				zindex:         "9999999",
				textAlign:      "center",
				color:          "#555",
				border:         "3px solid #aaa",
				backgroundColor:"#fff",
				cursor:         "wait",
				lineHeight:     "24px",
	}
	});
				jQuery("#submit_coinpal_payment_form").click();
				' );
	
		return '<form id="coinpalsubmit" name="coinpalsubmit" action="www.coinpal.com/' . '" method="post" target="_top">' . implode('', $coinpal_args_array) . '
		<!-- Button Fallback -->
		<div class="payment_buttons">
		<input type="submit" class="button-alt" id="submit_coinpal_payment_form" value="' . __('Pay via coinpal', 'coinpal') . '" /> <a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel order &amp; restore cart', 'coinpal') . '</a>
		</div>
		<script type="text/javascript">
		jQuery(".payment_buttons").hide();
		</script>
		</form>';
	}

	/**
	 * Process a refund if supported
	 * @param  int $order_id
	 * @param  float $amount
	 * @param  string $reason
	 * @return  boolean True or false based on success, or a WP_Error object
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$this->log( 'Refund Failed: You have to log in at coinpal in order to process refund' );
		return false;
	}
}
?>
