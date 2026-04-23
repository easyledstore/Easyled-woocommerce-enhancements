<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://acquistasitoweb.com
 * @since      1.0.0
 *
 * @package    Easyled_Woocommerce_Enhancements
 * @subpackage Easyled_Woocommerce_Enhancements/admin
 */

class Easyled_Woocommerce_Enhancements_Admin {

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
	 * Order column keys used by this plugin.
	 */
	const PAYMENT_COLUMN_KEY  = 'easyled_payment_method';
	const SHIPPING_COLUMN_KEY  = 'easyled_shipping_info';
	const PACKING_COLUMN_KEY   = 'easyled_packing_list';
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
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/easyled-woocommerce-enhancements-admin.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/easyled-woocommerce-enhancements-admin.js',
			array( 'jquery' ),
			$this->version,
			false
		);
	}

	/**
	 * Add custom columns to the legacy orders table.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_order_columns_classic( $columns ) {
		return $this->add_order_columns( $columns );
	}

	/**
	 * Add custom columns to the HPOS orders table.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_order_columns_hpos( $columns ) {
		return $this->add_order_columns( $columns );
	}

	/**
	 * Render custom columns for the legacy orders table.
	 *
	 * @param string $column Column key.
	 * @param int    $post_id Order post ID.
	 * @return void
	 */
	public function render_order_columns_classic( $column, $post_id ) {
		if ( ! $this->is_custom_column_key( $column ) ) {
			return;
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( $post_id );
		if ( ! $order ) {
			return;
		}

		echo wp_kses_post( $this->render_order_column_value( $column, $order ) );
	}

	/**
	 * Render custom columns for the HPOS orders table.
	 *
	 * @param string|int|object $column Column key or order object, depending on the hook.
	 * @param mixed            $order  Order object or order ID.
	 * @return void
	 */
	public function render_order_columns_hpos( $column, $order ) {
		if ( ! $this->is_custom_column_key( $column ) ) {
			return;
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		if ( is_numeric( $order ) ) {
			$order = wc_get_order( absint( $order ) );
		}

		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		echo wp_kses_post( $this->render_order_column_value( $column, $order ) );
	}

	/**
	 * Register the secure handler used to print the packing list.
	 *
	 * @return void
	 */
	public function handle_print_packing_list() {
		if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Non autorizzato.', 'easyled-woocommerce-enhancements' ) );
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			wp_die( esc_html__( 'WooCommerce non e disponibile.', 'easyled-woocommerce-enhancements' ) );
		}

		$order_id = absint( $_GET['order_id'] ?? 0 );
		if ( ! $order_id ) {
			wp_die( esc_html__( 'ID ordine non valido.', 'easyled-woocommerce-enhancements' ) );
		}

		check_admin_referer( 'easyled_print_packing_list_' . $order_id );

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Ordine non trovato.', 'easyled-woocommerce-enhancements' ) );
		}

		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );

		$items          = $order->get_items( 'line_item' );
		$order_number   = $order->get_order_number();
		$payment_method = $order->get_payment_method_title();
		$total_weight   = $this->get_order_total_weight( $order );
		$billing        = $this->format_address_for_print( $order->get_formatted_billing_address() );
		$shipping       = $this->format_address_for_print( $order->get_formatted_shipping_address() );
		$weight_unit    = get_option( 'woocommerce_weight_unit', 'kg' );
		?>
		<!DOCTYPE html>
		<html lang="it">
		<head>
			<meta charset="UTF-8">
			<title><?php echo esc_html( sprintf( 'Packing List - Ordine #%s', $order_number ) ); ?></title>
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
			<style>
				body{
					font-family: Arial, Helvetica, sans-serif;
					padding: 28px;
					margin: 0;
					color: #111;
					background: #fff;
				}
				.wrap{
					max-width: 1100px;
					margin: 0 auto;
				}
				.top-actions{
					margin-bottom: 20px;
				}
				.top-actions .button{
					display: inline-flex;
					align-items: center;
					gap: 8px;
					padding: 10px 16px;
					text-decoration: none;
					border: 1px solid #111;
					color: #111;
					background: #fff;
					margin-right: 8px;
					font-size: 14px;
				}
				.header{
					border-bottom: 2px solid #111;
					padding-bottom: 14px;
					margin-bottom: 22px;
				}
				.header h1{
					margin: 0 0 8px;
					font-size: 28px;
				}
				.header-meta{
					display: grid;
					grid-template-columns: repeat(3, 1fr);
					gap: 14px;
					font-size: 14px;
					line-height: 1.6;
				}
				.boxes{
					display: grid;
					grid-template-columns: 1fr 1fr;
					gap: 18px;
					margin-bottom: 22px;
				}
				.box{
					border: 1px solid #111;
					padding: 14px;
					min-height: 120px;
				}
				.box h3{
					margin: 0 0 10px;
					font-size: 16px;
				}
				.address{
					font-size: 14px;
					line-height: 1.6;
				}
				table{
					width: 100%;
					border-collapse: collapse;
					margin-top: 10px;
				}
				th, td{
					border: 1px solid #111;
					padding: 10px;
					text-align: left;
					vertical-align: middle;
				}
				th{
					background: #f3f3f3;
					font-size: 14px;
				}
				td{
					font-size: 14px;
				}
				.col-product{
					width: 38%;
				}
				.col-sku{
					width: 18%;
				}
				.col-barcode{
					width: 26%;
					text-align: center;
				}
				.col-qty{
					width: 10%;
					text-align: center;
					font-weight: bold;
					font-size: 16px;
				}
				.product-title{
					font-size: 15px;
					font-weight: 700;
					line-height: 1.4;
				}
				.sku-text{
					font-size: 16px;
					font-weight: 700;
					letter-spacing: 0.3px;
				}
				.barcode-svg{
					width: 220px;
					height: 64px;
				}
				.note{
					margin-top: 18px;
					font-size: 13px;
				}
				@media print{
					.top-actions{
						display: none;
					}
					body{
						padding: 0;
					}
					.wrap{
						max-width: 100%;
					}
					@page{
						size: auto;
						margin: 12mm;
					}
				}
			</style>
		</head>
		<body>
			<div class="wrap">
				<div class="top-actions">
					<a href="#" class="button" onclick="window.print(); return false;" rel="noopener noreferrer">
						<?php echo $this->get_printer_icon_svg(); ?>
						<span><?php echo esc_html__( 'Stampa', 'easyled-woocommerce-enhancements' ); ?></span>
					</a>
				</div>

				<div class="header">
					<h1><?php echo esc_html__( 'Packing List', 'easyled-woocommerce-enhancements' ); ?></h1>
					<div class="header-meta">
						<div><strong><?php echo esc_html__( 'Ordine', 'easyled-woocommerce-enhancements' ); ?>:</strong> #<?php echo esc_html( $order_number ); ?></div>
						<div><strong><?php echo esc_html__( 'Metodo di pagamento', 'easyled-woocommerce-enhancements' ); ?>:</strong> <?php echo esc_html( $payment_method ?: __( 'Non disponibile', 'easyled-woocommerce-enhancements' ) ); ?></div>
						<div><strong><?php echo esc_html__( 'Peso totale prodotti', 'easyled-woocommerce-enhancements' ); ?>:</strong> <?php echo esc_html( wc_format_weight( $total_weight ) ); ?></div>
						<div><strong><?php echo esc_html__( 'Unit di peso', 'easyled-woocommerce-enhancements' ); ?>:</strong> <?php echo esc_html( $weight_unit ); ?></div>
						<div><strong><?php echo esc_html__( 'Codice Acquistasitoweb', 'easyled-woocommerce-enhancements' ); ?>:</strong> <?php echo esc_html( $this->get_acquistasitoweb_transaction_code() ); ?></div>
					</div>
				</div>

				<div class="boxes">
					<div class="box">
						<h3><?php echo esc_html__( 'Indirizzo di fatturazione', 'easyled-woocommerce-enhancements' ); ?></h3>
						<div class="address"><?php echo wp_kses_post( $billing ); ?></div>
					</div>

					<div class="box">
						<h3><?php echo esc_html__( 'Indirizzo di spedizione', 'easyled-woocommerce-enhancements' ); ?></h3>
						<div class="address"><?php echo wp_kses_post( $shipping ); ?></div>
					</div>
				</div>

				<table>
					<thead>
						<tr>
							<th class="col-product"><?php echo esc_html__( 'Prodotto', 'easyled-woocommerce-enhancements' ); ?></th>
							<th class="col-sku"><?php echo esc_html__( 'SKU', 'easyled-woocommerce-enhancements' ); ?></th>
							<th class="col-barcode"><?php echo esc_html__( 'Barcode 128', 'easyled-woocommerce-enhancements' ); ?></th>
							<th class="col-qty"><?php echo esc_html__( 'Qta', 'easyled-woocommerce-enhancements' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $items as $item_id => $item ) : ?>
							<?php
							$product       = $item->get_product();
							$product_name  = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $item->get_name() ) ) );
							$qty           = (int) $item->get_quantity();
							$sku           = $product ? (string) $product->get_sku() : '';
							$sku           = '' !== $sku ? $sku : 'N/D';
							$barcode_value = 'N/D' !== $sku ? $sku : 'ID-' . absint( $item->get_product_id() );
							?>
							<tr>
								<td class="col-product">
									<div class="product-title"><?php echo esc_html( $product_name ); ?></div>
								</td>
								<td class="col-sku">
									<div class="sku-text"><?php echo esc_html( $sku ); ?></div>
								</td>
								<td class="col-barcode">
									<svg
										class="barcode-svg"
										jsbarcode-format="CODE128"
										jsbarcode-value="<?php echo esc_attr( $barcode_value ); ?>"
										jsbarcode-textmargin="4"
										jsbarcode-fontoptions="bold"
									></svg>
								</td>
								<td class="col-qty"><?php echo esc_html( $qty ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<div class="note">
					<strong><?php echo esc_html__( 'Nota', 'easyled-woocommerce-enhancements' ); ?>:</strong>
					<?php echo esc_html__( 'Il barcode viene generato usando lo SKU quando disponibile; in mancanza dello SKU viene usato un codice interno.', 'easyled-woocommerce-enhancements' ); ?>
				</div>
			</div>

			<script>
				document.addEventListener('DOMContentLoaded', function() {
					if (window.JsBarcode) {
						JsBarcode(".barcode-svg").init();
					}
				});
			</script>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * Output admin styles for the custom order columns.
	 *
	 * @return void
	 */
	public function output_admin_styles() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}

		if ( 'shop_order' !== $screen->id && 'woocommerce_page_wc-orders' !== $screen->id ) {
			return;
		}
		?>
		<style>
			.column-<?php echo esc_attr( self::PAYMENT_COLUMN_KEY ); ?>,
			.column-<?php echo esc_attr( self::SHIPPING_COLUMN_KEY ); ?>,
			.column-<?php echo esc_attr( self::PACKING_COLUMN_KEY ); ?> {
				vertical-align: top;
			}

			.column-<?php echo esc_attr( self::PAYMENT_COLUMN_KEY ); ?> {
				width: 180px;
			}

			.column-<?php echo esc_attr( self::SHIPPING_COLUMN_KEY ); ?> {
				width: 220px;
			}

			.column-<?php echo esc_attr( self::PACKING_COLUMN_KEY ); ?> {
				width: 140px;
			}

			.easyled-pay-badge {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				padding: 6px 10px;
				border-radius: 999px;
				font-size: 12px;
				font-weight: 600;
				line-height: 1;
				white-space: nowrap;
				border: 1px solid transparent;
			}

			.easyled-pay-icon .dashicons {
				font-size: 16px;
				width: 16px;
				height: 16px;
				line-height: 16px;
			}

			.easyled-pay-default {
				background: #f1f1f1;
				color: #444;
				border-color: #dcdcde;
			}

			.easyled-pay-bacs {
				background: #e8f1ff;
				color: #135e96;
				border-color: #b6d4fe;
			}

			.easyled-pay-cod {
				background: #fff4e5;
				color: #9a4d00;
				border-color: #f3c98b;
			}

			.easyled-pay-paypal {
				background: #eef7ff;
				color: #003087;
				border-color: #b9dbff;
			}

			.easyled-pay-card {
				background: #f3e8ff;
				color: #6b21a8;
				border-color: #d8b4fe;
			}

			.easyled-pay-none {
				background: #ffeaea;
				color: #a30000;
				border-color: #ffb3b3;
			}

			.easyled-shipping-icon {
				width: 24px;
				height: auto;
				margin-right: 6px;
				vertical-align: middle;
			}

			.easyled-packing-list-button {
				display: inline-flex !important;
				align-items: center;
				gap: 6px;
			}
		</style>
		<?php
	}

	/**
	 * Insert all order columns in a stable order.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	private function add_order_columns( $columns ) {
		$columns = $this->insert_column_after( $columns, 'order_number', self::PAYMENT_COLUMN_KEY, __( 'Pagamento', 'easyled-woocommerce-enhancements' ) );
		$columns = $this->insert_column_after( $columns, self::PAYMENT_COLUMN_KEY, self::SHIPPING_COLUMN_KEY, __( 'Spedizione', 'easyled-woocommerce-enhancements' ) );
		$columns = $this->insert_column_after( $columns, 'order_total', self::PACKING_COLUMN_KEY, __( 'Packing', 'easyled-woocommerce-enhancements' ) );

		if ( ! isset( $columns[ self::PAYMENT_COLUMN_KEY ] ) ) {
			$columns[ self::PAYMENT_COLUMN_KEY ] = __( 'Pagamento', 'easyled-woocommerce-enhancements' );
		}

		if ( ! isset( $columns[ self::SHIPPING_COLUMN_KEY ] ) ) {
			$columns[ self::SHIPPING_COLUMN_KEY ] = __( 'Spedizione', 'easyled-woocommerce-enhancements' );
		}

		if ( ! isset( $columns[ self::PACKING_COLUMN_KEY ] ) ) {
			$columns[ self::PACKING_COLUMN_KEY ] = __( 'Packing', 'easyled-woocommerce-enhancements' );
		}

		return $columns;
	}

	/**
	 * Render the value for each custom order column.
	 *
	 * @param string   $column Column key.
	 * @param WC_Order $order  The order object.
	 * @return string
	 */
	private function render_order_column_value( $column, $order ) {
		switch ( $column ) {
			case self::PAYMENT_COLUMN_KEY:
				return $this->get_payment_badge_html( $order );

			case self::SHIPPING_COLUMN_KEY:
				return $this->get_shipping_info_html( $order );

			case self::PACKING_COLUMN_KEY:
				return $this->get_packing_list_button_html( $order->get_id() );
		}

		return '';
	}

	/**
	 * Generate a payment badge for the admin list table.
	 *
	 * @param WC_Order $order The order object.
	 * @return string
	 */
	private function get_payment_badge_html( $order ) {
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return '&mdash;';
		}

		$method       = (string) $order->get_payment_method();
		$method_title = (string) $order->get_payment_method_title();

		if ( '' === $method ) {
			return '<span class="easyled-pay-badge easyled-pay-none">'
				. '<span class="easyled-pay-icon"><span class="dashicons dashicons-warning"></span></span> '
				. '<span class="easyled-pay-text">' . esc_html__( 'Non impostato', 'easyled-woocommerce-enhancements' ) . '</span>'
				. '</span>';
		}

		$icon_class = 'dashicons-money-alt';
		$badge_class = 'easyled-pay-default';

		switch ( $method ) {
			case 'bacs':
				$icon_class  = 'dashicons-bank';
				$badge_class = 'easyled-pay-bacs';
				break;

			case 'cod':
				$icon_class  = 'dashicons-money';
				$badge_class = 'easyled-pay-cod';
				break;

			case 'paypal':
				$icon_class  = 'dashicons-external';
				$badge_class = 'easyled-pay-paypal';
				break;

			case 'stripe':
			case 'woocommerce_payments':
				$icon_class  = 'dashicons-credit-card';
				$badge_class = 'easyled-pay-card';
				break;
		}

		return '<span class="easyled-pay-badge ' . esc_attr( $badge_class ) . '">'
			. '<span class="easyled-pay-icon"><span class="dashicons ' . esc_attr( $icon_class ) . '"></span></span> '
			. '<span class="easyled-pay-text">' . esc_html( $method_title ) . '</span>'
			. '</span>';
	}

	/**
	 * Render shipping info and total for the admin list table.
	 *
	 * @param WC_Order $order The order object.
	 * @return string
	 */
	private function get_shipping_info_html( $order ) {
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return '&mdash;';
		}

		$methods      = $order->get_shipping_methods();
		$shipping_name = array();
		$shipping_total = 0.0;

		foreach ( $methods as $method ) {
			$shipping_name[] = $method->get_name();
			$shipping_total  += (float) $method->get_total();
		}

		if ( empty( $shipping_name ) ) {
			return '&mdash;';
		}

		$cod_fee_total = $this->get_cod_fee_total( $order );
		$display_total  = $shipping_total + $cod_fee_total;
		$label         = implode( ', ', array_filter( array_map( 'sanitize_text_field', $shipping_name ) ) );

		return esc_html( $label ) . ' (' . wp_kses_post( wc_price( $display_total ) ) . ')';
	}

	/**
	 * Build the packing list button HTML.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	private function get_packing_list_button_html( $order_id ) {
		return sprintf(
			'<a class="button easyled-packing-list-button" href="%1$s" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-printer"></span> <span>%2$s</span></a>',
			esc_url( $this->get_packing_list_url( $order_id ) ),
			esc_html__( 'Stampa', 'easyled-woocommerce-enhancements' )
		);
	}

	/**
	 * Build the secure URL used by the packing list button.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	private function get_packing_list_url( $order_id ) {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=easyled_print_packing_list&order_id=' . absint( $order_id ) ),
			'easyled_print_packing_list_' . absint( $order_id )
		);
	}

	/**
	 * SVG printer icon.
	 *
	 * @return string
	 */
	private function get_printer_icon_svg() {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>';
	}

	/**
	 * Helper that returns the total weight of the ordered products.
	 *
	 * @param WC_Order $order The order object.
	 * @return float
	 */
	private function get_order_total_weight( $order ) {
		$total_weight = 0.0;

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			$weight = (float) $product->get_weight();
			$qty    = (float) $item->get_quantity();

			if ( $weight > 0 && $qty > 0 ) {
				$total_weight += $weight * $qty;
			}
		}

		return $total_weight;
	}

	/**
	 * Convert the WooCommerce formatted address into safe printable HTML.
	 *
	 * @param string $address_html Address HTML.
	 * @return string
	 */
	private function format_address_for_print( $address_html ) {
		$address_html = wp_kses_post( $address_html );

		if ( empty( trim( wp_strip_all_tags( $address_html ) ) ) ) {
			return '<em>' . esc_html__( 'Nessun indirizzo disponibile', 'easyled-woocommerce-enhancements' ) . '</em>';
		}

		return $address_html;
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
	 * Get the total COD fee already added to the order.
	 *
	 * @param WC_Order $order The order object.
	 * @return float
	 */
	private function get_cod_fee_total( $order ) {
		$cod_fee_total = 0.0;
		$fee_label     = $this->get_cod_fee_label();

		foreach ( $order->get_fees() as $fee ) {
			$fee_name = (string) $fee->get_name();
			if ( '' !== $fee_label && false !== stripos( $fee_name, $fee_label ) ) {
				$cod_fee_total += (float) $fee->get_total();
			}
		}

		return $cod_fee_total;
	}

	/**
	 * Insert a column after the requested key.
	 *
	 * @param array  $columns  Existing columns.
	 * @param string $target   Key after which the new column should be inserted.
	 * @param string $new_key  New column key.
	 * @param string $label    Column label.
	 * @return array
	 */
	private function insert_column_after( $columns, $target, $new_key, $label ) {
		if ( isset( $columns[ $new_key ] ) ) {
			return $columns;
		}

		$new_columns = array();
		$inserted    = false;

		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;

			if ( ! $inserted && $target === $key ) {
				$new_columns[ $new_key ] = $label;
				$inserted                = true;
			}
		}

		if ( ! $inserted ) {
			$new_columns[ $new_key ] = $label;
		}

		return $new_columns;
	}

	/**
	 * Check whether a column belongs to this plugin.
	 *
	 * @param string $column Column key.
	 * @return bool
	 */
	private function is_custom_column_key( $column ) {
		return in_array(
			$column,
			array(
				self::PAYMENT_COLUMN_KEY,
				self::SHIPPING_COLUMN_KEY,
				self::PACKING_COLUMN_KEY,
			),
			true
		);
	}

	/**
	 * Return the Acquistasitoweb transaction code as a filterable value.
	 *
	 * @return string
	 */
	private function get_acquistasitoweb_transaction_code() {
		return (string) apply_filters( 'easyled_woocommerce_enhancements_acquistasitoweb_transaction_code', 'PDS.5982-5587-6952-14579' );
	}
}
