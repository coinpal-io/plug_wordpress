<?php
if (!defined('ABSPATH')) {
    exit; // 防止直接访问
}

function coinpal_plugin_admin_callback_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $coinpal = new WC_Gateway_Coinpal();
    if (!empty($_GET['key'])) {
        $keyJson = $coinpal->decrypt($_GET['key']);
        $keyDec = json_decode($keyJson, true);
        if (!empty($keyDec)) {
            $options = get_option('woocommerce_coinpal_settings', []);
            $options['enabled'] = 'yes';
            update_option('woocommerce_coinpal_settings', $options);
        }
        if (!empty($keyDec['merId'])) {
            $coinpal->update_option("api_key", $keyDec['merId']);
        }
        if (!empty($keyDec['key'])) {
            $coinpal->update_option("secret_key", $keyDec['key']);
        }
        $currTitle = $coinpal->get_option("curr_title");
        if (empty($currTitle)) {
            $coinpal->update_option("curr_title", 'Pay Crypto with CoinPal');
        }
    }


    $redirect_url = admin_url('admin.php?page=coinpal-dashboard');
    wp_redirect($redirect_url);
}


