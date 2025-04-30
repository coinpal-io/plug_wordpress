<?php

if (!defined('ABSPATH')) {
    exit; // 防止直接访问
}

function coinpal_admin_assets($hook) {
    // 确保只在插件的后台页面加载资源
    if ($hook !== 'toplevel_page_coinpal-dashboard') {
        return;
    }
    // 引入 Bootstrap CSS
    wp_enqueue_style('bootstrap-css', plugin_dir_url(__FILE__) . '../../assets/css/bootstrap.min.css');

    // 引入自定义样式（可选）
    wp_enqueue_style('coinapl-payment-plugin-admin-css', plugin_dir_url(__FILE__) . 'admin-style.css');

    // 引入 jQuery（WordPress 已内置，无需手动引入）
    // 引入 Bootstrap JS
    wp_enqueue_script('bootstrap-js', plugin_dir_url(__FILE__) . '../../assets/js/bootstrap.bundle.min.js', array('jquery'), null, true);
}
add_action('admin_enqueue_scripts', 'coinpal_admin_assets');


add_action('admin_menu', 'coinpal_plugin_add_admin_menu');
function coinpal_plugin_add_admin_menu() {
    require_once COINPAL_CPWC_PLUGIN_INCLUDE_PATH . 'admin/dashboard.php';
    add_menu_page(
        'Coinpal Payment',       // 页面标题
        'Coinpal Payment',          // 菜单标题
        'manage_options',    // 权限要求
        'coinpal-dashboard', // slug（URL 后缀）
        'coinpal_plugin_admin_page', // 回调函数，渲染页面
        'dashicons-admin-generic',  // 菜单图标（可选）
        50  // 菜单位置（可选）
    );
    require_once COINPAL_CPWC_PLUGIN_INCLUDE_PATH . 'admin/callback.php';
    add_submenu_page(
        null,   // 不挂载到任何菜单上
        'Coinpal Auth',   // 页面标题
        '',   // 为空，不显示菜单项
        'manage_options',  // 权限
        'coinpal-callback',  // slug
        'coinpal_plugin_admin_callback_page' // 回调函数
    );
}


