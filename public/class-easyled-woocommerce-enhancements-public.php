<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://acquistasitoweb.com
 * @since      1.0.0
 *
 * @package    Easyled_Woocommerce_Enhancements
 * @subpackage Easyled_Woocommerce_Enhancements/public
 */

class Easyled_Woocommerce_Enhancements_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Default COD fee label.
	 */
	const COD_FEE_LABEL_FILTER = 'easyled_woocommerce_enhancements_cod_fee_label';

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since      1.0.0
	 * @param      string    $plugin_name    The name of this plugin.
	 * @param      string    $version        The current version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/easyled-woocommerce-enhancements-public.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/easyled-woocommerce-enhancements-public.js',
			array( 'jquery' ),
			$this->version,
			false
		);
	}

	/**
	 * Add a fee for cash on delivery orders.
	 *
	 * @param WC_Cart $cart The cart object.
	 * @return void
	 */
	public function add_cod_fee( $cart ) {
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->session || ! $cart ) {
			return;
		}

		$chosen_payment_method = WC()->session->get( 'chosen_payment_method' );
		if ( 'cod' !== $chosen_payment_method ) {
			return;
		}

		$amount  = (float) apply_filters( 'easyled_woocommerce_enhancements_cod_fee_amount', 9.90, $cart );
		$taxable = (bool) apply_filters( 'easyled_woocommerce_enhancements_cod_fee_taxable', true, $cart );
		$label   = $this->get_cod_fee_label();

		if ( $amount <= 0 ) {
			return;
		}

		$cart->add_fee( $label, $amount, $taxable );
	}

	/**
	 * Optionally hide payment gateways based on the selected shipping method.
	 *
	 * The default behavior keeps all gateways available. Site owners can hook into the
	 * filter and return arrays of allowed or blocked shipping method IDs.
	 *
	 * @param array $gateways Available gateways.
	 * @return array
	 */
	public function filter_gateways_by_shipping( $gateways ) {
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return $gateways;
		}

		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->session ) {
			return $gateways;
		}

		if ( ! isset( $gateways['cod'] ) ) {
			return $gateways;
		}

		$chosen_shipping_methods = (array) WC()->session->get( 'chosen_shipping_methods', array() );
		if ( empty( $chosen_shipping_methods ) ) {
			return $gateways;
		}

		$normalized_methods = array_filter(
			array_map(
				static function ( $method_id ) {
					return strtolower( sanitize_text_field( (string) $method_id ) );
				},
				$chosen_shipping_methods
			)
		);

		$allowed_shipping_methods = array_filter(
			array_map(
				static function ( $method_id ) {
					return strtolower( sanitize_text_field( (string) $method_id ) );
				},
				(array) apply_filters( 'easyled_woocommerce_enhancements_cod_allowed_shipping_methods', array(), $chosen_shipping_methods )
			)
		);

		$blocked_shipping_methods = array_filter(
			array_map(
				static function ( $method_id ) {
					return strtolower( sanitize_text_field( (string) $method_id ) );
				},
				(array) apply_filters( 'easyled_woocommerce_enhancements_cod_blocked_shipping_methods', array(), $chosen_shipping_methods )
			)
		);

		$disable_cod = false;

		if ( ! empty( $allowed_shipping_methods ) && empty( array_intersect( $normalized_methods, $allowed_shipping_methods ) ) ) {
			$disable_cod = true;
		}

		if ( ! empty( $blocked_shipping_methods ) && array_intersect( $normalized_methods, $blocked_shipping_methods ) ) {
			$disable_cod = true;
		}

		if ( $disable_cod ) {
			unset( $gateways['cod'] );
		}

		return $gateways;
	}

	/**
	 * Prefix shipping labels with carrier icons when a known carrier is detected.
	 *
	 * @param string              $label  Shipping rate label.
	 * @param WC_Shipping_Rate|mixed $method Shipping rate object.
	 * @return string
	 */
	public function add_shipping_icons( $label, $method ) {
		$carrier = $this->detect_carrier( $label, $method );
		if ( ! $carrier ) {
			return $label;
		}

		$icons = apply_filters(
			'easyled_woocommerce_enhancements_shipping_icons',
			array(
				'bartolini' => array(
					'label' => 'Bartolini',
					'url'   => 'https://www.brt.it/wp-content/uploads/sites/275/2021/07/BRT_logo_cropped.png',
				),
				'brt'       => array(
					'label' => 'BRT',
					'url'   => 'https://www.brt.it/wp-content/uploads/sites/275/2021/07/BRT_logo_cropped.png',
				),
			),
			$method,
			$label
		);

		if ( empty( $icons[ $carrier ]['url'] ) ) {
			return $label;
		}

		$alt_text = ! empty( $icons[ $carrier ]['label'] ) ? $icons[ $carrier ]['label'] : strtoupper( $carrier );

		$icon_html = sprintf(
			'<img class="easyled-shipping-icon easyled-shipping-icon--%1$s" src="%2$s" alt="%3$s" loading="lazy" decoding="async">',
			esc_attr( $carrier ),
			esc_url( $icons[ $carrier ]['url'] ),
			esc_attr( $alt_text )
		);

		return $icon_html . ' ' . $label;
	}

	/**
	 * Refresh checkout totals when the payment method changes.
	 *
	 * @return void
	 */
	public function refresh_checkout_on_payment_change() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url() ) {
			return;
		}
		?>
		<script>
			jQuery(function($){
				$(document.body).on('change', 'input[name="payment_method"]', function() {
					$(document.body).trigger('update_checkout');
				});
			});
		</script>
		<?php
	}

	/**
	 * Return the configured COD fee label.
	 *
	 * @return string
	 */
	private function get_cod_fee_label() {
		return (string) apply_filters(
			self::COD_FEE_LABEL_FILTER,
			__( 'Supplemento pagamento alla consegna', 'easyled-woocommerce-enhancements' )
		);
	}

	/**
	 * Detect a carrier name from the shipping label or shipping rate object.
	 *
	 * @param string              $label  Shipping rate label.
	 * @param WC_Shipping_Rate|mixed $method Shipping rate object.
	 * @return string
	 */
	private function detect_carrier( $label, $method ) {
		$search_space = strtolower( wp_strip_all_tags( (string) $label ) );

		if ( is_object( $method ) ) {
			if ( method_exists( $method, 'get_method_id' ) ) {
				$search_space .= ' ' . strtolower( (string) $method->get_method_id() );
			}

			if ( method_exists( $method, 'get_instance_id' ) ) {
				$search_space .= ' ' . strtolower( (string) $method->get_instance_id() );
			}
		}

		if ( false !== strpos( $search_space, 'bartolini' ) ) {
			return 'bartolini';
		}

		if ( false !== strpos( $search_space, 'brt' ) ) {
			return 'brt';
		}

		return '';
	}
}
