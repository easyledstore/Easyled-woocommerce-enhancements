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

		$chosen_payment_method = $this->get_chosen_payment_method();

		if ( 'cod' !== $chosen_payment_method ) {
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
	}

	/**
	 * Keep the chosen payment method in session during checkout refreshes.
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
	}

	/**
	 * Hide alternative payment gateways when cash on delivery is selected.
	 *
	 * @param array $gateways Available gateways.
	 * @return array
	 */
	public function filter_gateways_by_payment_method( $gateways ) {
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return $gateways;
		}

		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->session ) {
			return $gateways;
		}

		if ( ! isset( $gateways['cod'] ) ) {
			return $gateways;
		}

		if ( 'cod' !== $this->get_chosen_payment_method() ) {
			return $gateways;
		}

		return array(
			'cod' => $gateways['cod'],
		);
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
	 * Render a visible notice for the COD surcharge on checkout.
	 *
	 * @return void
	 */
	public function output_cod_fee_notice() {
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) {
			return;
		}

		$chosen_payment_method = $this->get_chosen_payment_method();
		if ( 'cod' !== $chosen_payment_method ) {
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
			__( 'Supplemento pagamento alla consegna', 'easyled-woocommerce-enhancements' ),
			$carrier,
			$selected_shipping_methods
		);

		if ( '' !== $carrier ) {
			$labels_by_carrier = (array) apply_filters( 'easyled_woocommerce_enhancements_cod_fee_labels_by_carrier', array(), $selected_shipping_methods );
			if ( isset( $labels_by_carrier[ $carrier ] ) && '' !== trim( (string) $labels_by_carrier[ $carrier ] ) ) {
				$label = (string) $labels_by_carrier[ $carrier ];
			} else {
				$label = trim( $label . ' ' . $this->get_carrier_display_name( $carrier ) );
			}
		}

		return $label;
	}

	/**
	 * Resolve the selected payment method from the current request or session.
	 *
	 * @return string
	 */
	private function get_chosen_payment_method() {
		if ( isset( $_POST['payment_method'] ) ) {
			return sanitize_text_field( wp_unslash( $_POST['payment_method'] ) );
		}

		if ( isset( $_POST['post_data'] ) ) {
			$post_data = wp_unslash( $_POST['post_data'] );
			parse_str( $post_data, $data );

			if ( ! empty( $data['payment_method'] ) ) {
				return sanitize_text_field( wp_unslash( $data['payment_method'] ) );
			}
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() && function_exists( 'WC' ) && WC() && WC()->session ) {
			return (string) WC()->session->get( 'chosen_payment_method' );
		}

		return '';
	}

	/**
	 * Return the selected shipping methods from the current request or session.
	 *
	 * @return array
	 */
	private function get_selected_shipping_methods() {
		$selected_shipping_methods = array();

		if ( isset( $_POST['shipping_method'] ) ) {
			$selected_shipping_methods = wp_unslash( $_POST['shipping_method'] );
		} elseif ( isset( $_POST['post_data'] ) ) {
			$post_data = wp_unslash( $_POST['post_data'] );
			parse_str( $post_data, $data );

			if ( isset( $data['shipping_method'] ) ) {
				$selected_shipping_methods = $data['shipping_method'];
			}
		} elseif ( function_exists( 'WC' ) && WC() && WC()->session ) {
			$selected_shipping_methods = WC()->session->get( 'chosen_shipping_methods', array() );
		}

		if ( ! is_array( $selected_shipping_methods ) ) {
			$selected_shipping_methods = array( $selected_shipping_methods );
		}

		return array_values(
			array_filter(
				array_map(
					static function ( $method_id ) {
						return sanitize_text_field( (string) $method_id );
					},
					$selected_shipping_methods
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
		$selected_shipping_methods = $this->get_selected_shipping_methods();

		if ( empty( $selected_shipping_methods ) || ! function_exists( 'WC' ) || ! WC() || ! WC()->shipping() ) {
			return '';
		}

		$packages = WC()->shipping()->get_packages();

		foreach ( $packages as $package_index => $package ) {
			if ( empty( $selected_shipping_methods[ $package_index ] ) || empty( $package['rates'] ) || ! is_array( $package['rates'] ) ) {
				continue;
			}

			$selected_rate_id = (string) $selected_shipping_methods[ $package_index ];
			if ( empty( $package['rates'][ $selected_rate_id ] ) || ! is_object( $package['rates'][ $selected_rate_id ] ) ) {
				continue;
			}

			$carrier = $this->detect_carrier( $package['rates'][ $selected_rate_id ]->get_label(), $package['rates'][ $selected_rate_id ] );
			if ( '' !== $carrier ) {
				return $carrier;
			}
		}

		return '';
	}

	/**
	 * Convert a carrier slug into a readable name.
	 *
	 * @param string $carrier Carrier slug.
	 * @return string
	 */
	private function get_carrier_display_name( $carrier ) {
		$carrier_labels = (array) apply_filters(
			'easyled_woocommerce_enhancements_carrier_labels',
			array(
				'bartolini' => 'Bartolini',
				'brt'       => 'BRT',
			),
			$carrier
		);

		if ( isset( $carrier_labels[ $carrier ] ) && '' !== trim( (string) $carrier_labels[ $carrier ] ) ) {
			return (string) $carrier_labels[ $carrier ];
		}

		$carrier = str_replace( array( '-', '_' ), ' ', (string) $carrier );
		return ucwords( strtolower( $carrier ) );
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
