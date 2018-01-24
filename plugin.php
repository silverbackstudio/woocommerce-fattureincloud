<?php
/**
 * Adds the custom fields to the registration form and profile editor
 *
 * @package woocommerce-fattureincloud
 * @author Brando Meniconi <b.meniconi@silverbackstudio.it>
 */

/*
Plugin Name: WooCommerce - Fatture In Cloud
Description: Integrates WooCommerce with FattureInCloud
Author: Silverback Studio
Version: 1.0
Author URI: http://www.silverbackstudio.it/
Text Domain: woocommerce-fattureincloud
*/

namespace Svbk\WP\Plugins\WooCommerce\FattureInCloud;

use Svbk\WP\Helpers\Lists\Utils;
use Svbk\FattureInCloud;
use Svbk\FattureInCloud\Struct\DocNuovoArticolo as Articolo;
use Svbk\FattureInCloud\Struct\DocNuovoRequest as Fattura;
use Svbk\FattureInCloud\Struct\DocNuovoPagamento as Pagamento;

/**
 * Loads textdomain and main initializes main class
 *
 * @return void
 */
function init() {
	load_plugin_textdomain( 'woocommerce-fattureincloud', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	
	if ( !class_exists( '\WC_Integration'  ) ) { 
		return;
	}

	if ( ! class_exists( __NAMESPACE__ . '\\WC_Integration_FattureInCloud' ) ) {
		include_once 'includes/class-wc-integration-fattureincloud.php';
	}

	add_filter( 'woocommerce_integrations', __NAMESPACE__ . '\\add_integration'  );	
	
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );

/**
 * Add a new integration to WooCommerce.
 */
function add_integration( $integrations ) {
	$integrations[] = __NAMESPACE__ . '\\WC_Integration_FattureInCloud';
	return $integrations;
}


