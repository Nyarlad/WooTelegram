<?php

/*
Plugin Name: WooCommerce -> Telegram
Plugin URI: https://speedwings.ru
Description: Плагин интеграции  WooCommerce c Telegram.
Version: 4.0
Author: Nar-Marratuk
License: GPL2
*/
if ( ! defined('ABSPATH')) {
    exit;
}

if ( ! defined('WPINC')) {
    die;
}

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    function wc_telegram_add_integration($integrations) {
        global $woocommerce;

        if (is_object($woocommerce)) {
            include_once( 'includes/telegram.php' );
            $integrations[] = 'Telegram';
        }
        return $integrations;
    }

    add_filter('woocommerce_integrations', 'wc_telegram_add_integration', 10);
}