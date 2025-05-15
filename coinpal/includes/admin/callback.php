<?php
if (!defined('ABSPATH')) {
    exit; // 防止直接访问
}

function coinpal_plugin_admin_callback_page() {
    // 权限检查
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'coinpal-payment-gateway2' ) );
    }

    // Nonce 校验（确保你在链接或表单中传入了 _wpnonce 参数）
    if (
        ! isset( $_GET['_wpnonce'] ) ||
        ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'coinpal_admin_action' )
    ) {
        wp_die( esc_html__( 'Security check failed.', 'coinpal-payment-gateway2' ) );

    }

    // 仅处理我们关心的字段
    $encrypted_key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';

    if ( ! empty( $encrypted_key ) ) {
        $coinpal = new WC_Gateway_Coinpal();
        $key_json = $coinpal->decrypt( $encrypted_key );
        $key_data = json_decode( $key_json, true );

        if ( is_array( $key_data ) && ! empty( $key_data ) ) {
            // 启用支付网关
            $options = get_option( 'coinpal_payment_woocommerce_settings', [] );
            $options['enabled'] = 'yes';
            update_option( 'coinpal_payment_woocommerce_settings', $options );

            // 更新 API Key 和 Secret
            if ( ! empty( $key_data['merId'] ) ) {
                $coinpal->update_option( 'api_key', sanitize_text_field( $key_data['merId'] ) );
            }
            if ( ! empty( $key_data['key'] ) ) {
                $coinpal->update_option( 'secret_key', sanitize_text_field( $key_data['key'] ) );
            }

            // 默认标题设置
            if ( empty( $coinpal->get_option( 'curr_title' ) ) ) {
                $coinpal->update_option( 'curr_title', 'Pay Crypto with CoinPal' );
            }
        }
    }

    // 重定向后应立即 exit
    wp_redirect( admin_url( 'admin.php?page=coinpal-dashboard' ) );
    exit;
}


