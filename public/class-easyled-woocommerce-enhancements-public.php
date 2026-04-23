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

		wp_localize_script(
			$this->plugin_name,
			'easyledWooEnhancements',
			array(
				'debug'               => $this->is_debug_enabled(),
				'codPaymentMethod'    => 'cod',
				'codShippingKeywords' => $this->get_cod_shipping_keywords(),
			)
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

		if ( ! $this->is_cod_shipping_selected() ) {
			$this->debug_log(
				'COD fee skipped: selected shipping method is not contrassegno.',
				array(
					'shipping' => $this->get_chosen_shipping_debug_context(),
				)
			);
			return;
		}

		$carrier = $this->get_selected_shipping_carrier();
		$amount  = $this->get_cod_fee_amount( $cart, $carrier );
		$taxable = (bool) apply_filters( 'easyled_woocommerce_enhancements_cod_fee_taxable', true, $cart );
		$label   = $this->get_cod_fee_label( $carrier );

		if ( $amount <= 0 ) {
			return;
		}

		$cart->add_fee( $label, $amount, $taxable );

		$this->debug_log(
			'COD fee added for contrassegno shipping.',
			array(
				'amount'   => $amount,
				'carrier'  => $carrier,
				'taxable'  => $taxable,
				'shipping' => $this->get_chosen_shipping_debug_context(),
			)
		);
	}

	/**
	 * Keep the chosen checkout state visible to this plugin during checkout refreshes.
	 *
	 * @param string $posted_data Serialized checkout form data.
	 * @return void
	 */
	public function sync_payment_method_from_checkout( $posted_data ) {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->session ) {
			return;
		}

		parse_str( $posted_data, $data );

		if ( empty( $data['payment_method'] ) ) {
			WC()->session->set( 'chosen_payment_method', '' );
			return;
		}

		WC()->session->set( 'chosen_payment_method', sanitize_text_field( wp_unslash( $data['payment_method'] ) ) );

		$this->debug_log(
			'Checkout refresh received.',
			array(
				'payment_method'  => sanitize_text_field( wp_unslash( $data['payment_method'] ) ),
				'shipping_method' => isset( $data['shipping_method'] ) ? wc_clean( wp_unslash( $data['shipping_method'] ) ) : array(),
			)
		);
	}

	/**
	 * Limit payment gateways when the selected shipping method is contrassegno.
	 *
	 * @param array $gateways Available gateways.
	 * @return array
	 */
	public function filter_gateways_by_shipping( $gateways ) {
		if ( is_admin() ) {
			return $gateways;
		}

		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->session ) {
			return $gateways;
		}

		$chosen_shipping_methods = $this->get_selected_shipping_methods();
		if ( empty( $chosen_shipping_methods ) ) {
			return $gateways;
		}

		if ( ! $this->is_cod_shipping_selected() ) {
			unset( $gateways['cod'] );

			$this->debug_log(
				'Non-BRT contrassegno shipping selected: COD gateway removed.',
				array(
					'gateways' => array_keys( $gateways ),
					'shipping' => $this->get_chosen_shipping_debug_context(),
				)
			);
			return $gateways;
		}

		if ( ! isset( $gateways['cod'] ) ) {
			$this->debug_log(
				'Contrassegno shipping selected, but COD gateway is not available.',
				array(
					'gateways' => array_keys( $gateways ),
					'shipping' => $this->get_chosen_shipping_debug_context(),
				)
			);
			return $gateways;
		}

		$this->debug_log(
			'Contrassegno shipping selected: limiting gateways to COD.',
			array(
				'gateways_before' => array_keys( $gateways ),
				'gateways_after'  => array( 'cod' ),
				'shipping'        => $this->get_chosen_shipping_debug_context(),
			)
		);

		return array(
			'cod' => $gateways['cod'],
		);
	}

	/**
	 * Auto-select COD when Contrassegno BRT is the chosen shipping method.
	 *
	 * @param string $default Default payment gateway ID.
	 * @return string
	 */
	public function force_cod_for_brt( $default ) {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->session ) {
			return $default;
		}

		if ( $this->is_cod_shipping_selected() ) {
			return 'cod';
		}

		return $default;
	}

	/**
	 * Render a visible notice for the COD surcharge on checkout.
	 *
	 * @return void
	 */
	public function output_cod_fee_notice() {
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart || ! WC()->session ) {
			return;
		}

		if ( ! $this->is_cod_shipping_selected() ) {
			return;
		}

		$carrier = $this->get_selected_shipping_carrier();
		$amount  = $this->get_cod_fee_amount( WC()->cart, $carrier );
		if ( $amount <= 0 ) {
			return;
		}

		printf(
			'<div class="woocommerce-info easyled-cod-fee-notice" role="status" aria-live="polite"><strong>%1$s</strong> %2$s</div>',
			esc_html( $this->get_cod_fee_label( $carrier ) ),
			wp_kses_post( wc_price( $amount ) )
		);
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
			'<span class="easyled-shipping-badge easyled-shipping-badge--%1$s"><img class="easyled-shipping-icon easyled-shipping-icon--%1$s" src="%2$s" alt="%3$s" loading="lazy" decoding="async"><span class="easyled-shipping-text">%4$s</span></span>',
			esc_attr( $carrier ),
			esc_url( $icons[ $carrier ]['url'] ),
			esc_attr( $alt_text ),
			wp_kses_post( $label )
		);

		return $icon_html;
	}

	/**
	 * Return the COD fee amount after filters are applied.
	 *
	 * @param WC_Cart|null $cart The cart object.
	 * @param string       $carrier Selected shipping carrier.
	 * @return float
	 */
	private function get_cod_fee_amount( $cart = null, $carrier = '' ) {
		$selected_shipping_methods = $this->get_selected_shipping_methods();
		$amount                    = (float) apply_filters( 'easyled_woocommerce_enhancements_cod_fee_amount', 9.90, $cart, $carrier, $selected_shipping_methods );

		if ( '' !== $carrier ) {
			$amounts_by_carrier = (array) apply_filters( 'easyled_woocommerce_enhancements_cod_fee_amounts_by_carrier', array(), $cart, $selected_shipping_methods );
			if ( isset( $amounts_by_carrier[ $carrier ] ) ) {
				$amount = (float) $amounts_by_carrier[ $carrier ];
			}

			$amount = (float) apply_filters( 'easyled_woocommerce_enhancements_cod_fee_amount_for_carrier', $amount, $carrier, $cart, $selected_shipping_methods );
		}

		return max( 0, $amount );
	}

	/**
	 * Return the configured COD fee label.
	 *
	 * @param string $carrier Selected shipping carrier.
	 * @return string
	 */
	private function get_cod_fee_label( $carrier = '' ) {
		$selected_shipping_methods = $this->get_selected_shipping_methods();
		$label                     = (string) apply_filters(
			self::COD_FEE_LABEL_FILTER,
			__( 'Supplemento contrassegno', 'easyled-woocommerce-enhancements' ),
			$carrier,
			$selected_shipping_methods
		);

		if ( '' !== $carrier ) {
			$labels_by_carrier = (array) apply_filters( 'easyled_woocommerce_enhancements_cod_fee_labels_by_carrier', array(), $selected_shipping_methods );
			if ( isset( $labels_by_carrier[ $carrier ] ) && '' !== trim( (string) $labels_by_carrier[ $carrier ] ) ) {
				$label = (string) $labels_by_carrier[ $carrier ];
			}
		}

		return $label;
	}

	/**
	 * Keywords used to recognize a cash-on-delivery shipping rate.
	 *
	 * @return array
	 */
	private function get_cod_shipping_keywords() {
		$keywords = (array) apply_filters(
			'easyled_woocommerce_enhancements_cod_shipping_keywords',
			array( 'contrassegno_brt' )
		);

		$keywords = array_map(
			static function ( $keyword ) {
				return strtolower( trim( sanitize_text_field( (string) $keyword ) ) );
			},
			$keywords
		);

		return array_values( array_filter( array_unique( $keywords ) ) );
	}

	/**
	 * Determine whether the selected shipping method is a contrassegno rate.
	 *
	 * @return bool
	 */
	private function is_cod_shipping_selected() {
		foreach ( $this->get_selected_shipping_methods() as $method_id ) {
			if ( $this->is_cod_shipping_method_id( $method_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Read the selected shipping rates from the WooCommerce session/packages.
	 *
	 * @return array
	 */
	private function get_chosen_shipping_rates() {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->session || ! WC()->shipping() ) {
			return array();
		}

		$chosen_methods = (array) WC()->session->get( 'chosen_shipping_methods', array() );
		$packages       = (array) WC()->shipping()->get_packages();
		$rates          = array();

		foreach ( $chosen_methods as $package_index => $chosen_rate_id ) {
			$chosen_rate_id = (string) $chosen_rate_id;
			$package_rates  = isset( $packages[ $package_index ]['rates'] ) ? (array) $packages[ $package_index ]['rates'] : array();

			if ( isset( $package_rates[ $chosen_rate_id ] ) ) {
				$rates[] = array(
					'id'   => $chosen_rate_id,
					'rate' => $package_rates[ $chosen_rate_id ],
				);
				continue;
			}

			$rates[] = array(
				'id'   => $chosen_rate_id,
				'rate' => null,
			);
		}

		return $rates;
	}

	/**
	 * Return the selected shipping method IDs from the current session.
	 *
	 * @return array
	 */
	private function get_selected_shipping_methods() {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->session ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					static function ( $method_id ) {
						return sanitize_text_field( (string) $method_id );
					},
					(array) WC()->session->get( 'chosen_shipping_methods', array() )
				)
			)
		);
	}

	/**
	 * Detect the carrier associated with the selected shipping method.
	 *
	 * @return string
	 */
	private function get_selected_shipping_carrier() {
		foreach ( $this->get_chosen_shipping_rates() as $rate_data ) {
			if ( ! is_object( $rate_data['rate'] ) || ! method_exists( $rate_data['rate'], 'get_label' ) ) {
				continue;
			}

			$carrier = $this->detect_carrier( $rate_data['rate']->get_label(), $rate_data['rate'] );
			if ( '' !== $carrier ) {
				return $carrier;
			}
		}

		return '';
	}

	/**
	 * Determine whether a shipping rate should be treated as contrassegno.
	 *
	 * @param WC_Shipping_Rate|mixed $rate Shipping rate object.
	 * @param string                 $fallback_id Selected rate ID from the session.
	 * @return bool
	 */
	private function is_cod_shipping_rate( $rate, $fallback_id = '' ) {
		$search_space = strtolower( (string) $fallback_id );

		if ( is_object( $rate ) ) {
			if ( method_exists( $rate, 'get_id' ) ) {
				$search_space .= ' ' . strtolower( (string) $rate->get_id() );
			}

			if ( method_exists( $rate, 'get_label' ) ) {
				$search_space .= ' ' . strtolower( wp_strip_all_tags( (string) $rate->get_label() ) );
			}

			if ( method_exists( $rate, 'get_method_id' ) ) {
				$search_space .= ' ' . strtolower( (string) $rate->get_method_id() );
			}

			if ( method_exists( $rate, 'get_instance_id' ) ) {
				$search_space .= ' ' . strtolower( (string) $rate->get_instance_id() );
			}
		}

		foreach ( $this->get_cod_shipping_keywords() as $keyword ) {
			if ( '' !== $keyword && false !== strpos( $search_space, $keyword ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether a shipping method ID is the Contrassegno BRT rate.
	 *
	 * @param string $method_id Selected shipping method ID.
	 * @return bool
	 */
	private function is_cod_shipping_method_id( $method_id ) {
		$method_id = strtolower( sanitize_text_field( (string) $method_id ) );

		foreach ( $this->get_cod_shipping_keywords() as $keyword ) {
			if ( '' !== $keyword && false !== strpos( $method_id, $keyword ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build a compact context array for checkout debugging.
	 *
	 * @return array
	 */
	private function get_chosen_shipping_debug_context() {
		$context = array();

		foreach ( $this->get_chosen_shipping_rates() as $rate_data ) {
			$label = '';

			if ( is_object( $rate_data['rate'] ) && method_exists( $rate_data['rate'], 'get_label' ) ) {
				$label = (string) $rate_data['rate']->get_label();
			}

			$context[] = array(
				'id'              => $rate_data['id'],
				'label'           => $label,
				'is_contrassegno' => $this->is_cod_shipping_method_id( $rate_data['id'] ),
			);
		}

		return $context;
	}

	/**
	 * Check whether checkout debug logging is enabled.
	 *
	 * @return bool
	 */
	private function is_debug_enabled() {
		$enabled = defined( 'EASYLED_WOOCOMMERCE_ENHANCEMENTS_DEBUG' ) && EASYLED_WOOCOMMERCE_ENHANCEMENTS_DEBUG;

		return (bool) apply_filters( 'easyled_woocommerce_enhancements_debug', $enabled );
	}

	/**
	 * Write debug details to the WooCommerce logger when enabled.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	private function debug_log( $message, $context = array() ) {
		if ( ! $this->is_debug_enabled() ) {
			return;
		}

		$context['source'] = 'easyled-woocommerce-enhancements';

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->debug( $message, $context );
			return;
		}

		error_log( '[easyled-woocommerce-enhancements] ' . $message . ' ' . wp_json_encode( $context ) );
	}

	/**
	 * Detect a carrier name from the shipping label or shipping rate object.
	 *
	 * @param string                 $label  Shipping rate label.
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
