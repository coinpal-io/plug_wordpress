<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coinpal
 *
 * @class 		WC_Gateway_Coinpal
 * @extends		WC_Payment_Gateway
 * @version		1.6.3
 * @package		WooCommerce/Classes/Payment
 * @author 		Coinpal
 */
class WC_Gateway_Coinpal extends WC_Payment_Gateway {

	/** @var boolean Whether or not logging is enabled */
	public static $log_enabled = false;

	/** @var WC_Logger Logger instance */
	public static $log = false;
	
	protected $pm_id = '';
	protected $pm = '';
	protected $is_channel = true;
	public $title = '';
	public $description = '';
	public $testmode = '';
	public $debug = '';

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$class_name = get_class($this);
		if (strlen($class_name) == strlen('WC_Gateway_Coinpal')) {
			$this->is_channel = false;
		}
		$index = strrpos($class_name, '_');
		$this->pm = substr($class_name, $index + 1);
		
		$this->id                 = strtolower($this->is_channel ? 'coinpal-' . $this->pm : $this->pm);
		$this->icon               = apply_filters( 'woocommerce_' . $this->pm . '_icon', plugins_url( 'assets/images/Coinpal.png', __FILE__ ) );
		$this->has_fields         = false;
		$this->order_button_text  = __( 'Continue to Payment', 'coinpal-payment-gateway2' );
		$this->method_title       = ($this->pm_id ? 'Virtual Currency ' : '') . $this->getMethodTitle();
        $this->method_description = $this->is_channel ? '' : __('Coinpal provides a global payment solution.', 'coinpal-payment-gateway2');

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
//		self::$log_enabled    = $this->debug;

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->pm, array( $this, 'receipt_page' ) );
		
		if ( ! $this->is_valid_for_use() ) {
			$this->enabled = 'no';
		} else {
			include_once( 'includes/class-wc-gateway-coinpal-notify-handler.php' );
			new WC_Gateway_Coinpal_Notify_Handler( $this->testmode);
		}
	}
	
	protected function getMethodTitle() {
		$method_title = '';
		if ($this->title) {
			$method_title = $this->title;
		} else {
			$method_title = $this->pm;
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
			$coinpal = new WC_Gateway_Coinpal();
            $this->curr_title = $coinpal->get_option('curr_title');
			$this->api_key = $coinpal->get_option('api_key');
			$this->secret_key = $coinpal->get_option('secret_key');
            $this->testmode = '';
		    $this->debug = '';
		} else {
            $this->curr_title = $this->get_option('curr_title');
            $this->api_key = $this->get_option('api_key');
			$this->secret_key = $this->get_option('secret_key');
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

	/**
	 * Logging method
	 * @param  string $message
	 */
	public static function log( $message ) {
		if ( self::$log_enabled ) {
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}
			self::$log->add( 'Coinpal', $message );
		}
	}


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
			<div class="inline error"><p><strong><?php esc_html_e( 'Gateway Disabled', 'coinpal-payment-gateway2' ); ?></strong>: <?php esc_html_e( 'Coinpal does not support your store currency.', 'coinpal-payment-gateway2' ); ?></p></div>
			<?php
		}
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$method_title = $this->getMethodTitle();
		if ($this->is_channel) {
            $default_description = $this->description;
            if ( empty( $default_description ) ) {
                /* translators: %s: Payment method title */
                $default_description = sprintf( __( 'Pay via %s', 'coinpal-payment-gateway2' ), $method_title );
            }
			$this->form_fields = array(
                'enabled' => array(
                        'title'   => __( 'Enable/Disable', 'coinpal-payment-gateway2' ),
                        'type'    => 'checkbox',
                        'label'   => sprintf( __( 'Enable %s', 'coinpal-payment-gateway2' ), $method_title ),
                        'default' => 'no'
                ),
                'title' => array(
                        'title'       => __( 'Title', 'coinpal-payment-gateway2' ),
                        'type'        => 'text',
                        'description' => __( 'This controls the title which the user sees during checkout.', 'coinpal-payment-gateway2' ),
                        'default'     => $method_title,
                        'desc_tip'    => true,
                ),
                'description' => array(
                        'title'       => __( 'Description', 'coinpal-payment-gateway2' ),
                        'type'        => 'text',
                        'desc_tip'    => true,
                        'description' => __( 'This controls the description which the user sees during checkout.', 'coinpal-payment-gateway2' ),
                        'default'     => $default_description
                )
			);
		} else {
			$this->form_fields = array(
                'api_details' => array(
                        'title'       => __( 'API Credentials', 'coinpal-payment-gateway2' ),
                        'type'        => 'title',
                        'description' => __( 'Enter your Coinpal API credentials which you can find at your app settings after logging in at your coinpal account.', 'coinpal-payment-gateway2' ),
                ),
                'curr_title' => array(
                    'title'       => __( 'Method Title', 'coinpal-payment-gateway2' ),
                    'type'        => 'text',
                    'description' => __( 'Method Title', 'coinpal-payment-gateway2' ),
                    'default'     => '',
                    'desc_tip'    => true,
                    'placeholder' => __( 'Required', 'coinpal-payment-gateway2' )
                ),
                'api_key' => array(
                        'title'       => __( 'Merchant Id', 'coinpal-payment-gateway2' ),
                        'type'        => 'text',
                        'description' => __( 'Get your Merchant Id from Coinpal.', 'coinpal-payment-gateway2' ),
                        'default'     => '',
                        'desc_tip'    => true,
                        'placeholder' => __( 'Required', 'coinpal-payment-gateway2' )
                ),
                'secret_key' => array(
                        'title'       => __( 'Secret Key', 'coinpal-payment-gateway2' ),
                        'type'        => 'text',
                        'description' => __( 'Get your API credentials from Coinpal.', 'coinpal-payment-gateway2' ),
                        'default'     => '',
                        'desc_tip'    => true,
                        'placeholder' => __( 'Required', 'coinpal-payment-gateway2' )
                ));
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
		$Coinpal_request = new WC_Gateway_Coinpal_Request( $this );

		return array(
			'result'   => 'success',
			'redirect' => $Coinpal_request->get_request_url( $order, $this->testmode )
		);
	}
	
	/**
	 * Output for the order received page.
	 *
	 * @access public
	 * @return void
	 */
	function receipt_page( $order ) {
	
		echo '<p>' . esc_html__('Thank you for your order, please click the button below to pay with Coinpal.', 'coinpal-payment-gateway2'). '</p>';
	
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

        wp_register_script('coinpal-js', plugin_dir_url(__FILE__) . '../assets/js/coinpal.js', array('jquery'), null, true);

        // 注入变量（例如 blockUI 提示语）
        wp_localize_script('coinpal-js', 'coinpal_block_msg', esc_js(__('Thank you for your order. We are now redirecting you to coinpal to make payment.', 'coinpal-payment-gateway2')));

        // 实际加载脚本
        wp_enqueue_script('coinpal-js');

        $order = new WC_Order($order_id);
        $coinpal_args_array = array('<input type="hidden" name="key" value="value" />');

        return '<form id="coinpalsubmit" name="coinpalsubmit" action="https://www.coinpal.com/" method="post" target="_top">' .
            implode('', $coinpal_args_array) . '
        <div class="payment_buttons">
            <input type="submit" class="button-alt" id="submit_coinpal_payment_form" value="' . __('Pay via coinpal', 'coinpal-payment-gateway2') . '" />
            <a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel order &amp; restore cart', 'coinpal-payment-gateway2') . '</a>
        </div>
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

    public function decrypt($text, $key='JIqEsL8dyF6xkmR2RfqC1ecpAiEApXrT', $salt='2y954yc495c58v495ycu89c5u895ucy84u5yv89452y378f4y84668y49yh47gr4') {
        list($rand,$text) = explode('.', $text, 2);
        $rand = base64_decode($rand);
        $count = substr($rand, 1);
        $rand = $rand{0};
        $key = hash('sha256', $salt.$key.$rand);
        $text = base64_decode($text);
        $result = '';
        $j = 0;
        for ($i=0; $i<strlen($text); $i++) {
            if ($j>=strlen($key)) {
                $j = 0;
            }
            $result .= $text{$i}^$key{$j};
            $j ++;
        }
        return substr($result, 0, strlen($result)/$count);
    }
}
?>
