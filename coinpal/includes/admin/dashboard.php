<?php
if (!defined('ABSPATH')) {
    exit; // 防止直接访问
}
// 账户绑定逻辑
function coinpal_plugin_admin_page() {
    // 检查用户权限
    if (!current_user_can('manage_options')) {
        return;
    }

    $coinpal = new WC_Gateway_Coinpal();
    $apiKey = $coinpal->get_option("api_key");
    $secretKey = $coinpal->get_option("secret_key");
    ?>
    <div class="wrap">
        <div class="container-full">
            <h1 class="my-4">CoinPal Dashboard</h1>
            <?php if (!empty($apiKey) && !empty($secretKey)): ?>
                <div class="card">
                    <div class="card-header">
                        welcome back!
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">Your account has been successfully linked.</h5>
                    </div>
                </div>
            <?php else: ?>
            <?php
                $redirect_uri = urlencode(admin_url('admin.php?page=coinpal-callback'));
                $auth_url = "https://portal.coinpal.io/#/authorize?redirect_uri={$redirect_uri}&response_type=code";
                ?>
                <!-- 未关联账户，显示关联按钮 -->
                <div class="jumbotron">
                    <h1 class="display-4">Associate your payment account</h1>
                    <p class="lead">Please click the button below to link your payment account in order to start using our services.</p>
                    <hr class="my-4">
                    <p>After linking your account, you will be able to access more features and services.</p>
                    <a target="_blank" href="<?php echo esc_url($auth_url);?>" class="btn btn-primary btn-lg" role="button">Related accounts</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
