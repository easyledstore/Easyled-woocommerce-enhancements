<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://acquistasitoweb.com
 * @since             1.0.0
 * @package           Easyled_Woocommerce_Enhancements
 *
 * @wordpress-plugin
 * Plugin Name:       Easyled woocommerce Enhancment
 * Plugin URI:        https://acquistasitoweb.com/easyledwe
 * Description:       Questo plugin serve per estendere le funzioni di woocommerce per permettere una gestione migliore sia lato admin che lato user
 * Version:           1.5.5
 * Author:            Acquistasitoweb
 * Author URI:        https://acquistasitoweb.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       easyled-woocommerce-enhancements
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'EASYLED_WOOCOMMERCE_ENHANCEMENTS_VERSION', '1.5.5' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-easyled-woocommerce-enhancements-activator.php
 */
function activate_easyled_woocommerce_enhancements() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-easyled-woocommerce-enhancements-activator.php';
	Easyled_Woocommerce_Enhancements_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-easyled-woocommerce-enhancements-deactivator.php
 */
function deactivate_easyled_woocommerce_enhancements() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-easyled-woocommerce-enhancements-deactivator.php';
	Easyled_Woocommerce_Enhancements_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_easyled_woocommerce_enhancements' );
register_deactivation_hook( __FILE__, 'deactivate_easyled_woocommerce_enhancements' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-easyled-woocommerce-enhancements.php';

/**
 * Check whether WooCommerce is available.
 *
 * @return bool
 */
function easyled_woocommerce_enhancements_is_woocommerce_active() {
	return class_exists( 'WooCommerce' ) && function_exists( 'WC' );
}

/**
 * Display a notice when WooCommerce is missing.
 *
 * @return void
 */
function easyled_woocommerce_enhancements_missing_wc_notice() {
	?>
	<div class="notice notice-warning">
		<p><?php echo esc_html__( 'Easyled WooCommerce Enhancements richiede WooCommerce attivo.', 'easyled-woocommerce-enhancements' ); ?></p>
	</div>
	<?php
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_easyled_woocommerce_enhancements() {
	if ( ! easyled_woocommerce_enhancements_is_woocommerce_active() ) {
		if ( is_admin() ) {
			add_action( 'admin_notices', 'easyled_woocommerce_enhancements_missing_wc_notice' );
		}

		return;
	}

	$plugin = new Easyled_Woocommerce_Enhancements();
	$plugin->run();

}
add_action( 'plugins_loaded', 'run_easyled_woocommerce_enhancements', 20 );
