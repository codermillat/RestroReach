<?php
/**
 * Delivery Agent Login Page
 *
 * @package RestaurantDeliveryManager
 * @subpackage Mobile
 * @since 1.0.0
 */
if (!defined('ABSPATH')) exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title><?php esc_html_e('Agent Login', 'restaurant-delivery-manager'); ?></title>
    <?php wp_head(); ?>
</head>
<body class="rrm-mobile-login">
    <div class="rrm-login-container">
        <h1 class="rrm-login-title"><?php esc_html_e('Delivery Agent Login', 'restaurant-delivery-manager'); ?></h1>
        <!-- Login Form -->
        <form id="rrm-agent-login-form" autocomplete="off">
            <?php wp_nonce_field('rdm_agent_mobile', 'nonce'); ?>
            <label for="rrm-login-username"><?php esc_html_e('Username or Email', 'restaurant-delivery-manager'); ?></label>
            <input type="text" id="rrm-login-username" name="username" required autocomplete="username">
            <label for="rrm-login-password"><?php esc_html_e('Password', 'restaurant-delivery-manager'); ?></label>
            <input type="password" id="rrm-login-password" name="password" required autocomplete="current-password">
            <button type="submit" class="rrm-btn rrm-btn-primary"><?php esc_html_e('Login', 'restaurant-delivery-manager'); ?></button>
        </form>
        <!-- Error Message Area -->
        <div id="rrm-login-error" class="rrm-error-message" style="display:none;"></div>
    </div>
    <?php wp_footer(); ?>
</body>
</html> 