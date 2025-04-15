<?php

// Register a custom order status
add_action('init', 'register_custom_order_statuses_coinpal');
function register_custom_order_statuses_coinpal() {
//    register_post_status('wc-paidcoming', array(
//        'label' => __( 'Paid confirming', 'woocommerce' ),
//        'public' => true,
//        'exclude_from_search' => false,
//        'show_in_admin_all_list' => true,
//        'show_in_admin_status_list' => true,
//        'label_count' => _n_noop('Paid confirming <span class="count">(%s)</span>', 'Paid confirming <span class="count">(%s)</span>')
//    ));
//
//	register_post_status('wc-partialcoming', array(
//        'label' => __( 'Partial paid confirming', 'woocommerce' ),
//        'public' => true,
//        'exclude_from_search' => false,
//        'show_in_admin_all_list' => true,
//        'show_in_admin_status_list' => true,
//        'label_count' => _n_noop('Partial paid confirming <span class="count">(%s)</span>', 'Partial paid confirming <span class="count">(%s)</span>')
//    ));
	
	register_post_status('wc-partialpaid', array(
        'label' => __( 'Partial paid', 'woocommerce' ),
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Partial paid <span class="count">(%s)</span>', 'Partial paid <span class="count">(%s)</span>')
    ));

//    register_post_status('wc-shipped', array(
//        'label' => __( 'Shipped', 'woocommerce' ),
//        'public' => true,
//        'exclude_from_search' => false,
//        'show_in_admin_all_list' => true,
//        'show_in_admin_status_list' => true,
//        'label_count' => _n_noop('Shipped <span class="count">(%s)</span>', 'Shipped <span class="count">(%s)</span>')
//    ));
}


// Add a custom order status to list of WC Order statuses
add_filter('wc_order_statuses', 'add_custom_order_statuses_coinpal');
function add_custom_order_statuses_coinpal($order_statuses) {
    $new_order_statuses = array();

    // add new order status before processing
    foreach ($order_statuses as $key => $status) {
        $new_order_statuses[$key] = $status;
        if ('wc-processing' === $key) {
//            $new_order_statuses['wc-paidcoming'] = __('Paid confirming', 'woocommerce' );
//			$new_order_statuses['wc-partialcoming'] = __('Partial paid confirming', 'woocommerce' );
			$new_order_statuses['wc-partialpaid'] = __('Partial paid', 'woocommerce' );
//            $new_order_statuses['wc-shipped'] = __('Shipped', 'woocommerce' );
        }
    }
    return $new_order_statuses;
}


// Adding custom status 'awaiting-delivery' to admin order list bulk dropdown
add_filter( 'bulk_actions-edit-shop_order', 'custom_dropdown_bulk_actions_shop_order_coinpal', 50, 1 );
function custom_dropdown_bulk_actions_shop_order_coinpal( $actions ) {
    $new_actions = array();

    // add new order status before processing
    foreach ($actions as $key => $action) {
        if ('mark_processing' === $key){
//            $new_actions['mark_paidcoming'] = __( 'Change status to Paid confirming', 'woocommerce' );
//			$new_actions['mark_partialcoming'] = __( 'Change status to Partial paid confirming', 'woocommerce' );
			$new_actions['mark_partialpaid'] = __( 'Change status to Partial paid', 'woocommerce' );
//            $new_actions['mark_shipped'] = __( 'Change status to Shipped', 'woocommerce' );
		}

        $new_actions[$key] = $action;
    }
    return $new_actions;
}


// Add a custom order status action button (for orders with "processing" status)
add_filter( 'woocommerce_admin_order_actions', 'add_custom_order_status_actions_button_coinpal', 100, 2 );
function add_custom_order_status_actions_button_coinpal( $actions, $order ) {
    // Display the button for all orders that have a 'partialpaid', 'paidcoming' or 'partialcoming' status
    if ( $order->has_status( array( 'partialpaid','paidcoming','partialcoming') ) ) {
        $action_slug = 'processing';
		$actions[$action_slug] = array(
            'url'       => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status='.$action_slug.'&order_id='.$order->get_id() ), 'woocommerce-mark-order-status' ),
            'name'      => __( 'Processing', 'woocommerce' ),
            'action'    => $action_slug,
        );

        $action_slug = 'complete';
		$actions[$action_slug] = array(
            'url'       => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=completed&order_id='.$order->get_id() ), 'woocommerce-mark-order-status' ),
            'name'      => __( 'Complete', 'woocommerce' ),
            'action'    => $action_slug,
        );
    }
    if ( $order->has_status(array('shipped') ) ) {
        $action_slug = 'complete';
		$actions[$action_slug] = array(
            'url'       => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=completed&order_id='.$order->get_id() ), 'woocommerce-mark-order-status' ),
            'name'      => __( 'Complete', 'woocommerce' ),
            'action'    => $action_slug,
        );
    }

    return $actions;
}

//add custom order status in woocommerce_valid_order_statuses
add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', 'coinpal_custom_status_valid_for_payment', 10, 2 );
function coinpal_custom_status_valid_for_payment( $statuses, $order ) {
    // Registering the custom status as valid for payment
    $statuses[] = 'paidcoming';
    $statuses[] = 'partialcoming';
    $statuses[] = 'partialpaid';
    $statuses[] = 'shipped';

    return $statuses;
}

//Custom fields for order details display for admin
add_action( 'woocommerce_admin_order_data_after_order_details', 'brain_display_order_data_in_admin_coinpal' );
function brain_display_order_data_in_admin_coinpal( $order ){  
	$coinpal_field=$order->get_meta('coinpal_field');
	$coinpal_field=json_decode($coinpal_field,true);
	if(!empty($coinpal_field)){
		?>
		<div class="order_data_column" style="width: 100%;">
			<h4><?php _e( 'Extra Order Details', 'woocommerce' ); ?></h4>
			<table>
		<?php
		foreach($coinpal_field as $key=>$item){
			if($key=="paidAddress" && !empty($item) && is_array($item)){
				echo '<tr>';
				echo '<td><strong>'.__( 'paid address' ).':</strong></td>';
				echo '<td>';
				foreach($item as $info){
					echo '<p>'.$info["address"].'&nbsp;-&nbsp;'.$info["paidCurrency"].'&nbsp;-&nbsp;'.$info["paidAmount"].'</p>';
				}
				echo '</td></tr>';
			}
		}
		?>
			</table>
		</div>
		<?php
	}
}

//when payment_complete then order send email
// add_action( 'woocommerce_payment_complete', 'order_completed_coinpal' );
// function order_completed_coinpal($order_id){ 
//     WC_Gateway_Coinpal::log( "#".$order_id." send processing email when completed");
//     WC()->mailer()->emails['WC_Email_Customer_Processing_Order']->trigger($order_id);
// }

//when admin payment_complete then order send email
add_action('woocommerce_order_status_changed', 'order_status_changed_coinpal', 99, 4);
function order_status_changed_coinpal($order_id, $old_status, $new_status, $order_object)
{
    if($new_status=="processing"){
        $order = wc_get_order($order_id);
        if ( $order->get_payment_method() == "coinpal" ) {
            WC_Gateway_Coinpal::log( "#".$order_id." send processing email when status change");
            WC()->mailer()->emails['WC_Email_Customer_Processing_Order']->trigger($order_id);
        }
    }
}


add_action( 'woocommerce_order_details_after_order_table', 'add_payment_info_for_partial_coinpal' );
function add_payment_info_for_partial_coinpal( $order ) {
	//gm 2023-5-10 当为部分付款时，显示已经付款的信息 
	if($order->get_status() === 'partialpaid' and $order->get_payment_method() === 'coinpal'){
		echo '<h2 class="woocommerce-column__title">Paid Information</h2>
		<table class="woocommerce-table woocommerce-table--order-details shop_table order_details partialpaid_table">
			<thead>
				<tr>
                    <th class="product-time">Time</th>
					<th class="product-quantity">Qty</th>
					<th class="product-currency">Currency</th>
				</tr>
			</thead>
			<tbody>';
				
		$coinpal_field=$order->get_meta('coinpal_field');
		$coinpal_field=json_decode($coinpal_field,true);
		if(!empty($coinpal_field)){
			foreach($coinpal_field as $key=>$item){
                if($key=="paidAddress" && !empty($item) && is_array($item)){
                    foreach($item as $info){
                        if(empty($info["paidAmount"]) || $info["paidAmount"]==0){
                            continue;
                        }
                        echo '<tr>';
                        echo '<td class="product-time">'.(empty($info["confirmedTime"])?"-":date("Y-m-d H:i:s",$info["confirmedTime"])).'</td>';
                        echo '<td class="product-quantity">'.(empty($info["paidAmount"])?"-":$info["paidAmount"]).'</td>';
                        echo '<td class="product-subtotal">'.(empty($info["paidCurrency"])?"-":$info["paidCurrency"]).'</td>';
                        echo '</td>';
                        echo '</tr>';
                    }
                }
			}
		}
		
		echo '
			</tbody>
			<tfoot>
				<tr><td scope="row" colspan="3"></td></tr>
				<tr>';
					
		global $wpdb;
		$table_name = $wpdb->prefix . 'coinpal_notify_log';
		$results = $wpdb->get_results(
			$wpdb->prepare("SELECT * FROM {$table_name} WHERE order_id = ".$order->get_id()." and status like 'partial_paid' ")
		);
		foreach ($results as $result) {
			$info=$result->info;
			$info=json_decode($info,true);
			//因为1usd=1usdt,而且coinpal只返回usdt已付款数，所以用usdt作为usd的已付款数
			$balance=$balance+$info["paidUsdt"];
		}

		$order_total = number_format($order->order_total, 8, '.', '');
		$order_total=$order_total-$balance;
		$order_total = number_format($order_total, 2, '.', '');
					
		echo '<td class="product-balance" colspan="3"><span>Amount unpaid</span>:&nbsp;&nbsp;&nbsp;&nbsp;$'.$order_total.'</td>
				</tr>
			</tfoot>
		</table>';
	}
}

//Display the Pay Again button when partiallypaid
//add_action( 'woocommerce_order_details_after_customer_details', 'add_payment_button_for_partial_order_iceriver2' );
function add_payment_button_for_partial_order_iceriver2( $order ) {
    if( $order->get_status() === 'partialpaid' ) { // Check if the order is in a 'partialpaid' state
		$order_id = $order->get_id();
		$order_key = $order->get_order_key();
		$nonce = wp_create_nonce( 'woocommerce-pay' );
		$url="/checkout/order-pay/".$order_id."/?pay_for_order=true&key=".$order_key;
		
		echo '
		<form id="order_review" method="post" novalidate="novalidate" action="'.$url.'">
			<input id="payment_method_coinpal" type="hidden" class="input-radio" name="payment_method" value="coinpal" data-order_button_text="Proceed to Coinpal">
			<input type="hidden" name="woocommerce_pay" value="1">
			<button type="submit" class="button alt wp-element-button" id="place_order" value="Pay for order" data-value="Pay for order">Repayment</button>
			<input type="hidden" id="woocommerce-pay-nonce" name="woocommerce-pay-nonce" value="'.$nonce.'">
			<input style="display:none;" type="checkbox" name="orderpoliciescheck" checked>
			<input type="hidden" name="_wp_http_referer" value="'.$url.'">
		</form>
		';
		
    }
}
//Add partialpaid to allow payment
function custom_order_needs_payment_coinpal( $needs_payment, $order ) {
    // Get the valid order statuses for payment
    $valid_statuses = apply_filters( 'woocommerce_valid_order_statuses_for_payment', array( 'pending', 'failed' ,'partialpaid'), $order );

    // Check if the order status is valid for payment
    if ( in_array( $order->get_status(), $valid_statuses ) ) {
        $needs_payment = true;
    }

    return $needs_payment;
}
add_filter( 'woocommerce_order_needs_payment', 'custom_order_needs_payment_coinpal', 10, 2 );

//Modify payment button name during partialpaid
add_filter('woocommerce_my_account_my_orders_actions', 'custom_order_button_text', 10, 2);
function custom_order_button_text($actions, $order) {
	if($order->get_status() === 'partialpaid' and $order->get_payment_method() === 'coinpal'){
		foreach ($actions as $key => $action) {
			if ($key == 'pay') {
				$actions['pay']['name'] = __('Continue to pay', 'woocommerce');
			}
		}
	}
	return $actions;
}

add_filter( 'woocommerce_order_is_pending_statuses', 'custom_pending_order_status_coinpal', 10, 1 );
function custom_pending_order_status_coinpal($statuses) {
    //Ensure order items are still stocked if paying for a failed order. Pending orders do not need this check because stock is held.
    // Add Custom State
    $statuses[] = 'partialpaid';
    return $statuses;
}

require_once CPWC_PLUGIN_INCLUDE_PATH . 'admin/index.php';


