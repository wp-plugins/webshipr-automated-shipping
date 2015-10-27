<?php
/*

Plugin Name: Webshipr for WooCommerce
Plugin URI: http://www.webshipr.com
Description: Automated shipping for WooCommerce
Author: webshipr.com
Author URI: http://www.webshipr.com
Version: 2.2.6

*/

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ){
    
    // Which environemnt to connect to
    define("API_RESOURCE", 'https://portal.webshipr.com');

    // Load webshipr library
    require_once('webshipr.php');
    
    // Load webshipr woocommere class
    require_once('class.webshipr-wc.php');

    // Load shipping calculator
    require_once('class.webshipr-rates.php'); 

}
?>
