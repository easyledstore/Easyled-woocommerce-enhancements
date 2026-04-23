<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://acquistasitoweb.com
 * @since      1.0.0
 *
 * @package    Easyled_Woocommerce_Enhancements
 * @subpackage Easyled_Woocommerce_Enhancements/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Easyled_Woocommerce_Enhancements
 * @subpackage Easyled_Woocommerce_Enhancements/includes
 * @author     Acquistasitoweb <info@acquistasitoweb.com>
 */
class Easyled_Woocommerce_Enhancements {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Easyled_Woocommerce_Enhancements_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'EASYLED_WOOCOMMERCE_ENHANCEMENTS_VERSION' ) ) {
			$this->version = EASYLED_WOOCOMMERCE_ENHANCEMENTS_VERSION;
		} else {
			$this->version = '1.5.1';
		}
		$this->plugin_name = 'easyled-woocommerce-enhancements';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_update_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Easyled_Woocommerce_Enhancements_Loader. Orchestrates the hooks of the plugin.
	 * - Easyled_Woocommerce_Enhancements_i18n. Defines internationalization functionality.
	 * - Easyled_Woocommerce_Enhancements_Admin. Defines all hooks for the admin area.
	 * - Easyled_Woocommerce_Enhancements_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-easyled-woocommerce-enhancements-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-easyled-woocommerce-enhancements-i18n.php';

		/**
		 * The class responsible for handling plugin updates.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-easyled-woocommerce-enhancements-updater.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-easyled-woocommerce-enhancements-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-easyled-woocommerce-enhancements-public.php';

		$this->loader = new Easyled_Woocommerce_Enhancements_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Easyled_Woocommerce_Enhancements_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Easyled_Woocommerce_Enhancements_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Easyled_Woocommerce_Enhancements_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_filter( 'manage_edit-shop_order_columns', $plugin_admin, 'add_order_columns_classic', 20 );
		$this->loader->add_filter( 'manage_woocommerce_page_wc-orders_columns', $plugin_admin, 'add_order_columns_hpos', 20 );
		$this->loader->add_action( 'manage_shop_order_posts_custom_column', $plugin_admin, 'render_order_columns_classic', 20, 2 );
		$this->loader->add_action( 'manage_woocommerce_page_wc-orders_custom_column', $plugin_admin, 'render_order_columns_hpos', 20, 2 );
		$this->loader->add_action( 'admin_post_easyled_print_packing_list', $plugin_admin, 'handle_print_packing_list' );
		$this->loader->add_action( 'admin_head', $plugin_admin, 'output_admin_styles' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Easyled_Woocommerce_Enhancements_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
		$this->loader->add_action( 'woocommerce_cart_calculate_fees', $plugin_public, 'add_cod_fee', 20, 1 );
		$this->loader->add_action( 'woocommerce_checkout_update_order_review', $plugin_public, 'sync_payment_method_from_checkout', 10, 1 );
		$this->loader->add_filter( 'woocommerce_available_payment_gateways', $plugin_public, 'filter_gateways_by_shipping', 20, 1 );
		$this->loader->add_filter( 'woocommerce_available_payment_gateways', $plugin_public, 'filter_gateways_by_payment_method', 25, 1 );
		$this->loader->add_action( 'woocommerce_review_order_after_payment', $plugin_public, 'output_cod_fee_notice', 20 );
		$this->loader->add_filter( 'woocommerce_cart_shipping_method_full_label', $plugin_public, 'add_shipping_icons', 20, 2 );

	}

	/**
	 * Register hooks used by the GitHub release updater.
	 *
	 * @since    1.5.1
	 * @access   private
	 */
	private function define_update_hooks() {
		$repository = function_exists( 'easyled_woocommerce_enhancements_get_github_repository' )
			? easyled_woocommerce_enhancements_get_github_repository()
			: ( defined( 'EASYLED_WOOCOMMERCE_ENHANCEMENTS_GITHUB_REPOSITORY' ) ? EASYLED_WOOCOMMERCE_ENHANCEMENTS_GITHUB_REPOSITORY : '' );

		if ( '' === $repository || ! defined( 'EASYLED_WOOCOMMERCE_ENHANCEMENTS_FILE' ) ) {
			return;
		}

		new Easyled_Woocommerce_Enhancements_Updater(
			EASYLED_WOOCOMMERCE_ENHANCEMENTS_FILE,
			$this->get_version(),
			$repository
		);
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Easyled_Woocommerce_Enhancements_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
