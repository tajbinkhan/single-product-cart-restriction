<?php
/**
 * Admin settings controller.
 *
 * @package SingleProductCartRestriction
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles WooCommerce settings integration.
 */
class SPCR_Admin {
	/**
	 * Cached category options.
	 *
	 * @var array<string, string>|null
	 */
	private $category_options;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_get_sections_products', array( $this, 'add_products_section' ) );
		add_filter( 'woocommerce_get_settings_products', array( $this, 'filter_products_settings' ), 30, 2 );
		add_action( 'woocommerce_admin_field_spcr_product_search', array( $this, 'render_product_search_field' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_filter( 'woocommerce_admin_settings_sanitize_option_spcr_enabled', array( $this, 'sanitize_yes_no' ), 10, 3 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_spcr_bypass_cart', array( $this, 'sanitize_yes_no' ), 10, 3 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_spcr_hide_action_messages', array( $this, 'sanitize_yes_no' ), 10, 3 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_spcr_hide_cart_quantity', array( $this, 'sanitize_yes_no' ), 10, 3 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_spcr_force_quantity_one', array( $this, 'sanitize_yes_no' ), 10, 3 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_spcr_bypass_admin_shop_manager', array( $this, 'sanitize_yes_no' ), 10, 3 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_spcr_debug_mode', array( $this, 'sanitize_yes_no' ), 10, 3 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_spcr_mode', array( $this, 'sanitize_mode' ), 10, 3 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_spcr_custom_notice', array( $this, 'sanitize_notice' ), 10, 3 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_spcr_excluded_products', array( $this, 'sanitize_id_list' ), 10, 3 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_spcr_excluded_categories', array( $this, 'sanitize_id_list' ), 10, 3 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_spcr_restrict_only_categories', array( $this, 'sanitize_id_list' ), 10, 3 );
	}

	/**
	 * Adds products subsection.
	 *
	 * @param array<string, string> $sections Existing sections.
	 *
	 * @return array<string, string>
	 */
	public function add_products_section( array $sections ): array {
		$sections['spcr'] = esc_html__( 'Single Product Restriction', 'single-product-cart-restriction' );

		return $sections;
	}

	/**
	 * Injects section fields.
	 *
	 * @param array<int, array<string, mixed>> $settings Existing settings.
	 * @param string                            $current_section Section key.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function filter_products_settings( array $settings, string $current_section ): array {
		if ( 'spcr' !== $current_section ) {
			return $settings;
		}

		return $this->get_settings();
	}

	/**
	 * Enqueues lightweight admin assets on this section only.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'woocommerce_page_wc-settings' !== $hook || ! spcr_is_settings_screen() ) {
			return;
		}

		wp_enqueue_style( 'spcr-admin', SPCR_PLUGIN_URL . 'assets/css/admin.css', array(), SPCR_VERSION );
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_script( 'spcr-admin', SPCR_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'wc-enhanced-select' ), SPCR_VERSION, true );
	}

	/**
	 * Returns section fields.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_settings(): array {
		return array(
			array(
				'title' => esc_html__( 'Single Product Cart Restriction', 'single-product-cart-restriction' ),
				'type'  => 'title',
				'id'    => 'spcr_settings_section',
				'desc'  => esc_html__( 'Control whether customers can keep only one product line item in the WooCommerce cart.', 'single-product-cart-restriction' ),
			),
			array(
				'title'   => esc_html__( 'Enable restriction', 'single-product-cart-restriction' ),
				'id'      => 'spcr_enabled',
				'type'    => 'checkbox',
				'default' => 'no',
				'desc'    => esc_html__( 'Enable single-product cart enforcement.', 'single-product-cart-restriction' ),
			),
			array(
				'title'    => esc_html__( 'Restriction mode', 'single-product-cart-restriction' ),
				'id'       => 'spcr_mode',
				'type'     => 'select',
				'default'  => 'block',
				'class'    => 'wc-enhanced-select',
				'css'      => 'min-width: 260px;',
				'options'  => array(
					'block'   => esc_html__( 'Block additional products', 'single-product-cart-restriction' ),
					'replace' => esc_html__( 'Replace existing cart contents', 'single-product-cart-restriction' ),
				),
				'desc'     => esc_html__( 'Block mode stops adding a second product. Replace mode clears the existing cart item and keeps the newly added item.', 'single-product-cart-restriction' ),
				'desc_tip' => true,
			),
			array(
				'title'   => esc_html__( 'Bypass cart and go to checkout', 'single-product-cart-restriction' ),
				'id'      => 'spcr_bypass_cart',
				'type'    => 'checkbox',
				'default' => 'no',
				'desc'    => esc_html__( 'After a successful add-to-cart for restricted products, send customers to checkout immediately and redirect cart-page visits to the shop page.', 'single-product-cart-restriction' ),
			),
			array(
				'title'   => esc_html__( 'Force quantity to 1', 'single-product-cart-restriction' ),
				'id'      => 'spcr_force_quantity_one',
				'type'    => 'checkbox',
				'default' => 'no',
				'desc'    => esc_html__( 'Cap the cart quantity at 1 for restricted products.', 'single-product-cart-restriction' ),
			),
			array(
				'title'   => esc_html__( 'Hide action messages', 'single-product-cart-restriction' ),
				'id'      => 'spcr_hide_action_messages',
				'type'    => 'checkbox',
				'default' => 'no',
				'desc'    => esc_html__( 'Hide WooCommerce add-to-cart messages and this plugin\'s success/notice messages for restricted products. Restriction errors still display.', 'single-product-cart-restriction' ),
			),
			array(
				'title'   => esc_html__( 'Hide cart quantity column', 'single-product-cart-restriction' ),
				'id'      => 'spcr_hide_cart_quantity',
				'type'    => 'checkbox',
				'default' => 'no',
				'desc'    => esc_html__( 'Hide the cart-page quantity column and Update cart button when the cart contains at least one restricted product.', 'single-product-cart-restriction' ),
			),
			array(
				'title'       => esc_html__( 'Custom notice message', 'single-product-cart-restriction' ),
				'id'          => 'spcr_custom_notice',
				'type'        => 'text',
				'default'     => '',
				'css'         => 'min-width: 400px;',
				'placeholder' => esc_attr__( 'Optional: override default restriction notice.', 'single-product-cart-restriction' ),
				'desc'        => esc_html__( 'Used for block/replace notices. Leave empty to use mode-specific defaults.', 'single-product-cart-restriction' ),
				'desc_tip'    => true,
			),
			array(
				'title'             => esc_html__( 'Exclude specific products', 'single-product-cart-restriction' ),
				'id'                => 'spcr_excluded_products',
				'type'              => 'spcr_product_search',
				'default'           => array(),
				'placeholder'       => esc_attr__( 'Search for a product…', 'single-product-cart-restriction' ),
				'desc'              => esc_html__( 'Excluded products bypass this rule completely.', 'single-product-cart-restriction' ),
				'desc_tip'          => true,
				'custom_attributes' => array(
					'data-allow_clear' => 'true',
				),
			),
			array(
				'title'             => esc_html__( 'Exclude specific categories', 'single-product-cart-restriction' ),
				'id'                => 'spcr_excluded_categories',
				'type'              => 'multiselect',
				'default'           => array(),
				'class'             => 'wc-enhanced-select',
				'css'               => 'min-width: 350px;',
				'options'           => $this->get_category_options(),
				'desc'              => esc_html__( 'Products in excluded categories bypass restriction.', 'single-product-cart-restriction' ),
				'desc_tip'          => true,
				'custom_attributes' => array(
					'data-placeholder' => esc_attr__( 'Select categories…', 'single-product-cart-restriction' ),
				),
			),
			array(
				'title'             => esc_html__( 'Restrict only selected categories', 'single-product-cart-restriction' ),
				'id'                => 'spcr_restrict_only_categories',
				'type'              => 'multiselect',
				'default'           => array(),
				'class'             => 'wc-enhanced-select',
				'css'               => 'min-width: 350px;',
				'options'           => $this->get_category_options(),
				'desc'              => esc_html__( 'If selected, only products in these categories are restricted. Exclusions still win.', 'single-product-cart-restriction' ),
				'desc_tip'          => true,
				'custom_attributes' => array(
					'data-placeholder' => esc_attr__( 'Select categories…', 'single-product-cart-restriction' ),
				),
			),
			array(
				'title'   => esc_html__( 'Allow admin/shop manager bypass', 'single-product-cart-restriction' ),
				'id'      => 'spcr_bypass_admin_shop_manager',
				'type'    => 'checkbox',
				'default' => 'no',
				'desc'    => esc_html__( 'Logged-in users with admin/shop manager permissions bypass the rule.', 'single-product-cart-restriction' ),
			),
			array(
				'title'   => esc_html__( 'Enable debug logging', 'single-product-cart-restriction' ),
				'id'      => 'spcr_debug_mode',
				'type'    => 'checkbox',
				'default' => 'no',
				'desc'    => esc_html__( 'Write decision logs to WooCommerce logs (source: single-product-cart-restriction).', 'single-product-cart-restriction' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'spcr_settings_section',
			),
		);
	}

	/**
	 * Renders product-search multiselect field.
	 *
	 * @param array<string, mixed> $field Field definition.
	 */
	public function render_product_search_field( array $field ): void {
		$field_id          = isset( $field['id'] ) ? (string) $field['id'] : '';
		$placeholder       = isset( $field['placeholder'] ) ? (string) $field['placeholder'] : '';
		$custom_attributes = isset( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) ? $field['custom_attributes'] : array();

		if ( '' === $field_id ) {
			return;
		}

		$current_values    = spcr_normalize_ids( get_option( $field_id, array() ) );
		$field_description = WC_Admin_Settings::get_field_description( $field );

		echo '<tr valign="top">';
		echo '<th scope="row" class="titledesc">';

		if ( ! empty( $field['title'] ) ) {
			echo '<label for="' . esc_attr( $field_id ) . '">' . esc_html( $field['title'] ) . '</label>';
		}

		echo wp_kses_post( $field_description['tooltip_html'] );
		echo '</th>';
		echo '<td class="forminp">';
		echo '<select';
		echo ' id="' . esc_attr( $field_id ) . '"';
		echo ' name="' . esc_attr( $field_id ) . '[]"';
		echo ' class="wc-product-search"';
		echo ' multiple="multiple"';
		echo ' style="min-width:350px;"';
		echo ' data-action="woocommerce_json_search_products_and_variations"';
		echo ' data-placeholder="' . esc_attr( $placeholder ) . '"';

		foreach ( $custom_attributes as $attribute_name => $attribute_value ) {
			echo ' ' . esc_attr( $attribute_name ) . '="' . esc_attr( (string) $attribute_value ) . '"';
		}

		echo '>';

		foreach ( $current_values as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			echo '<option value="' . esc_attr( (string) $product_id ) . '" selected="selected">';
			echo wp_kses_post( $product->get_formatted_name() );
			echo '</option>';
		}

		echo '</select>';

		if ( ! empty( $field_description['description'] ) ) {
			echo '<p class="description">' . wp_kses_post( $field_description['description'] ) . '</p>';
		}

		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Sanitizes yes/no checkboxes.
	 *
	 * @param mixed                $value Sanitized value from WooCommerce.
	 * @param array<string, mixed> $option Option data.
	 * @param mixed                $raw_value Raw posted value.
	 */
	public function sanitize_yes_no( $value, array $option, $raw_value ): string {
		unset( $value, $option );

		return ( 'yes' === $raw_value || '1' === $raw_value ) ? 'yes' : 'no';
	}

	/**
	 * Sanitizes restriction mode.
	 *
	 * @param mixed                $value Sanitized value from WooCommerce.
	 * @param array<string, mixed> $option Option data.
	 * @param mixed                $raw_value Raw posted value.
	 */
	public function sanitize_mode( $value, array $option, $raw_value ): string {
		unset( $value, $option );

		$mode = sanitize_key( (string) $raw_value );

		if ( ! in_array( $mode, array( 'block', 'replace' ), true ) ) {
			$mode = 'block';
		}

		return $mode;
	}

	/**
	 * Sanitizes custom notice text.
	 *
	 * @param mixed                $value Sanitized value from WooCommerce.
	 * @param array<string, mixed> $option Option data.
	 * @param mixed                $raw_value Raw posted value.
	 */
	public function sanitize_notice( $value, array $option, $raw_value ): string {
		unset( $value, $option );

		return sanitize_text_field( (string) $raw_value );
	}

	/**
	 * Sanitizes ID arrays.
	 *
	 * @param mixed                $value Sanitized value from WooCommerce.
	 * @param array<string, mixed> $option Option data.
	 * @param mixed                $raw_value Raw posted value.
	 *
	 * @return int[]
	 */
	public function sanitize_id_list( $value, array $option, $raw_value ): array {
		unset( $value, $option );

		return spcr_normalize_ids( $raw_value );
	}

	/**
	 * Returns available product categories as select options.
	 *
	 * @return array<string, string>
	 */
	private function get_category_options(): array {
		if ( null !== $this->category_options ) {
			return $this->category_options;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			$this->category_options = array();
			return $this->category_options;
		}

		$options = array();

		foreach ( $terms as $term ) {
			$options[ (string) $term->term_id ] = html_entity_decode( $term->name, ENT_QUOTES, get_bloginfo( 'charset' ) );
		}

		$this->category_options = $options;

		return $this->category_options;
	}
}
