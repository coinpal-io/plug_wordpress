<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Gateway_coinpal_Order {
	private $order;
	
	public function __construct($order) {
	    $this->order = $order;
	}
	
	public function getOrginOrder() {
		return $this->order;
	}
	
	public function __get($key) {
		if (property_exists($this->order, $key)) {
			return $this->order->$key;
		} else {
			if (!method_exists($this->order, "get_$key")) {
				$order_prefix = 'order_';
				if (substr($key, 0, strlen($order_prefix)) === $order_prefix) {
					$key = substr($key, strlen($order_prefix));
				}
			}

			if (method_exists($this->order, "get_$key")) {
				return $this->order->{"get_$key"}();
			}
		}
	}
	
	public function __call($method, $parameters) {
		if($method=="update_status"){
			//The status can not be changed and may be wrong, so add conditions
			$s=empty($parameters[0])?"":$parameters[0];
			$n=empty($parameters[1])?"":$parameters[1];
			$m=empty($parameters[2])?FALSE:TRUE;
				
			return $this->order->$method($s,$n,$m);
		}
		else{
			return $this->order->$method($parameters);
		}
	}
}
?>