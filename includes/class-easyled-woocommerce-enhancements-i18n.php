<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://acquistasitoweb.com
 * @since      1.0.0
 *
 * @package    Easyled_Woocommerce_Enhancements
 * @subpackage Easyled_Woocommerce_Enhancements/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Easyled_Woocommerce_Enhancements
 * @subpackage Easyled_Woocommerce_Enhancements/includes
 * @author     Acquistasitoweb <info@acquistasitoweb.com>
 */
class Easyled_Woocommerce_Enhancements_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'easyled-woocommerce-enhancements',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
