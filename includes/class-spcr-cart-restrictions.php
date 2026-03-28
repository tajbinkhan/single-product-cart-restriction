<?php
/**
 * Cart restriction engine.
 *
 * @package SingleProductCartRestriction
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enforces cart restrictions.
 */
class SPCR_Cart_Restrictions {
	/**
	 * Prevents recursion while we adjust quantities programmatically.
	 *
	 * @var bool
	 */
	private $is_adjusting_quantity = false;

	/**
	 * Tracks force-quantity notice emission per request.
	 *
	 * @var bool
	 */
	private $force_quantity_notice_added = false;

	/**
	 * Tracks cleanup notice emission per request.
	 *
	 * @var bool
	 */
	private $cleanup_notice_added = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 20, 6 );
		add_action( 'woocommerce_add_to_cart', array( $this, 'after_add_to_cart' ), 20, 6 );
		add_filter( 'woocommerce_update_cart_validation', array( $this, 'validate_cart_update' ), 20, 4 );
		add_action( 'woocommerce_cart_item_set_quantity', array( $this, 'enforce_quantity_when_set' ), 20, 3 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'enforce_cart_state' ), 20 );
		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'enforce_cart_state' ), 20 );
	}

	/**
	 * Validates add-to-cart requests for block/replace behavior.
	 *
	 * @param bool                   $passed Existing validation state.
	 * @param int                    $product_id Product ID.
	 * @param int                    $quantity Requested quantity.
	 * @param int                    $variation_id Variation ID.
	 * @param array<string, string>  $variation Selected attributes.
	 * @param array<string, mixed>   $cart_item_data Extra item data.
	 *
	 * @return bool
	 */
	public function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variation = array(), $cart_item_data = array() ): bool {
		unset( $variation, $cart_item_data );

		if ( ! $passed || ! $this->has_cart() ) {
			return (bool) $passed;
		}

		$quantity     = max( 1, absint( $quantity ) );
		$product_id   = absint( $product_id );
		$variation_id = absint( $variation_id );

		$decision_context = array(
			'product_id'           => $product_id,
			'variation_id'         => $variation_id,
			'quantity'             => $quantity,
			'mode'                 => $this->get_mode(),
			'force_quantity_one'   => $this->is_force_quantity_one_enabled(),
			'scoped_item_count'    => count( $this->get_scoped_cart_items( WC()->cart ) ),
			'request_context'      => 'add_to_cart',
		);

		$decision = $this->build_add_to_cart_decision( $product_id, $variation_id );
		$decision = apply_filters( 'spcr_restriction_decision', $decision, $decision_context, $this );
		$action   = isset( $decision['action'] ) ? sanitize_key( (string) $decision['action'] ) : 'allow';

		if ( 'block' === $action ) {
			$this->add_notice(
				$this->resolve_notice_message(
					$this->get_default_block_message(),
					array(
						'reason'         => 'block',
						'product_id'     => $product_id,
						'variation_id'   => $variation_id,
						'request_context' => 'add_to_cart',
					),
					true,
					isset( $decision['notice_message'] ) ? (string) $decision['notice_message'] : ''
				),
				isset( $decision['notice_type'] ) ? (string) $decision['notice_type'] : 'error'
			);

			$this->log( 'info', 'Blocked add-to-cart by restriction.', $decision_context );

			return false;
		}

		if ( 'replace' === $action ) {
			WC()->cart->empty_cart();

			$this->add_notice(
				$this->resolve_notice_message(
					$this->get_default_replace_message(),
					array(
						'reason'         => 'replace',
						'product_id'     => $product_id,
						'variation_id'   => $variation_id,
						'request_context' => 'add_to_cart',
					),
					true,
					isset( $decision['notice_message'] ) ? (string) $decision['notice_message'] : ''
				),
				isset( $decision['notice_type'] ) ? (string) $decision['notice_type'] : 'success'
			);

			$this->log( 'info', 'Replaced existing cart items before add-to-cart.', $decision_context );
		}

		return true;
	}

	/**
	 * Handles post-add adjustments (quantity cap).
	 *
	 * @param string                 $cart_item_key Cart item key.
	 * @param int                    $product_id Product ID.
	 * @param int                    $quantity Requested quantity.
	 * @param int                    $variation_id Variation ID.
	 * @param array<string, string>  $variation Selected attributes.
	 * @param array<string, mixed>   $cart_item_data Extra item data.
	 */
	public function after_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ): void {
		unset( $variation, $cart_item_data );

		if ( ! $this->has_cart() || ! $this->is_force_quantity_one_enabled() ) {
			return;
		}

		if ( ! isset( WC()->cart->cart_contents[ $cart_item_key ] ) ) {
			return;
		}

		$product_id   = absint( $product_id );
		$variation_id = absint( $variation_id );

		if ( ! $this->should_apply_to_product( $product_id, $variation_id, 'after_add_to_cart' ) ) {
			return;
		}

		$cart_item = WC()->cart->cart_contents[ $cart_item_key ];
		$current   = isset( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : absint( $quantity );

		if ( $current > 1 ) {
			$this->set_cart_item_quantity( WC()->cart, (string) $cart_item_key, 1 );
			$this->maybe_add_force_quantity_notice();
		}
	}

	/**
	 * Intercepts manual cart updates.
	 *
	 * @param bool                 $passed Existing validation state.
	 * @param string               $cart_item_key Cart item key.
	 * @param array<string, mixed> $values Cart item values.
	 * @param int                  $quantity Requested quantity.
	 *
	 * @return bool
	 */
	public function validate_cart_update( $passed, $cart_item_key, $values, $quantity ): bool {
		unset( $cart_item_key );

		if ( ! $passed || ! $this->is_force_quantity_one_enabled() || ! is_array( $values ) ) {
			return (bool) $passed;
		}

		$product_id   = isset( $values['product_id'] ) ? absint( $values['product_id'] ) : 0;
		$variation_id = isset( $values['variation_id'] ) ? absint( $values['variation_id'] ) : 0;

		if ( $quantity > 1 && $this->should_apply_to_product( $product_id, $variation_id, 'update_cart_validation' ) ) {
			$this->maybe_add_force_quantity_notice();
		}

		return (bool) $passed;
	}

	/**
	 * Ensures quantity remains 1 when force mode is active.
	 *
	 * @param string  $cart_item_key Cart item key.
	 * @param int     $quantity Updated quantity.
	 * @param WC_Cart $cart Cart object.
	 */
	public function enforce_quantity_when_set( $cart_item_key, $quantity, $cart ): void {
		if ( $this->is_adjusting_quantity || ! $this->is_force_quantity_one_enabled() ) {
			return;
		}

		if ( ! $cart instanceof WC_Cart || $quantity <= 1 ) {
			return;
		}

		$cart_items = $cart->get_cart();
		if ( ! isset( $cart_items[ $cart_item_key ] ) ) {
			return;
		}

		$cart_item    = $cart_items[ $cart_item_key ];
		$product_id   = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
		$variation_id = isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0;

		if ( ! $this->should_apply_to_product( $product_id, $variation_id, 'set_quantity' ) ) {
			return;
		}

		$this->set_cart_item_quantity( $cart, (string) $cart_item_key, 1 );
		$this->maybe_add_force_quantity_notice();
	}

	/**
	 * Enforces single-line-item integrity before totals calculation.
	 *
	 * @param WC_Cart $cart Cart object.
	 */
	public function enforce_cart_state( $cart ): void {
		if ( ! $cart instanceof WC_Cart || ! $this->is_enabled() || $this->current_user_can_bypass() ) {
			return;
		}

		$scoped_items = $this->get_scoped_cart_items( $cart );

		if ( count( $scoped_items ) > 1 ) {
			$this->reduce_scoped_items_to_one( $cart, $scoped_items );
		}

		if ( ! $this->is_force_quantity_one_enabled() ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$product_id   = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
			$variation_id = isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0;
			$quantity     = isset( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : 0;

			if ( $quantity <= 1 || ! $this->should_apply_to_product( $product_id, $variation_id, 'before_calculate_totals' ) ) {
				continue;
			}

			$this->set_cart_item_quantity( $cart, (string) $cart_item_key, 1 );
			$this->maybe_add_force_quantity_notice();
		}
	}

	/**
	 * Reduces restricted items to one cart line when bypass attempts happen.
	 *
	 * @param WC_Cart              $cart Cart object.
	 * @param array<string, array> $scoped_items Restricted cart items.
	 */
	private function reduce_scoped_items_to_one( WC_Cart $cart, array $scoped_items ): void {
		$keys_to_keep = array_keys( $scoped_items );
		$mode         = $this->get_mode();

		$keep_key = 'replace' === $mode ? end( $keys_to_keep ) : reset( $keys_to_keep );

		foreach ( array_keys( $scoped_items ) as $cart_item_key ) {
			if ( $cart_item_key === $keep_key ) {
				continue;
			}

			$cart->remove_cart_item( $cart_item_key );
		}

		$this->maybe_add_cleanup_notice( $mode );
		$this->log(
			'warning',
			'Removed extra restricted cart lines to enforce one-product policy.',
			array(
				'mode'              => $mode,
				'kept_cart_item_key' => (string) $keep_key,
			)
		);
	}

	/**
	 * Builds default decision for add-to-cart requests.
	 *
	 * @param int $product_id Product ID.
	 * @param int $variation_id Variation ID.
	 *
	 * @return array<string, mixed>
	 */
	private function build_add_to_cart_decision( int $product_id, int $variation_id ): array {
		$decision = array(
			'action'         => 'allow',
			'reason'         => 'not_applicable',
			'notice_type'    => 'notice',
			'notice_message' => '',
		);

		if ( ! $this->should_apply_to_product( $product_id, $variation_id, 'add_to_cart' ) ) {
			return $decision;
		}

		$scoped_items = $this->get_scoped_cart_items( WC()->cart );
		if ( empty( $scoped_items ) ) {
			$decision['reason'] = 'cart_empty';
			return $decision;
		}

		$incoming_identity = $this->get_identity( $product_id, $variation_id );
		$has_different     = false;

		foreach ( $scoped_items as $cart_item ) {
			$existing_identity = $this->get_identity(
				isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0,
				isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0
			);

			if ( $existing_identity !== $incoming_identity ) {
				$has_different = true;
				break;
			}
		}

		if ( ! $has_different ) {
			$decision['reason'] = 'same_product';
			return $decision;
		}

		if ( 'replace' === $this->get_mode() ) {
			$decision['action']         = 'replace';
			$decision['reason']         = 'different_product';
			$decision['notice_type']    = 'success';
			$decision['notice_message'] = $this->get_default_replace_message();
			return $decision;
		}

		$decision['action']         = 'block';
		$decision['reason']         = 'different_product';
		$decision['notice_type']    = 'error';
		$decision['notice_message'] = $this->get_default_block_message();

		return $decision;
	}

	/**
	 * Returns cart items that are in restriction scope.
	 *
	 * @param WC_Cart $cart Cart object.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_scoped_cart_items( WC_Cart $cart ): array {
		$items = array();

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$product_id   = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
			$variation_id = isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0;

			if ( $this->should_apply_to_product( $product_id, $variation_id, 'cart_item' ) ) {
				$items[ $cart_item_key ] = $cart_item;
			}
		}

		return $items;
	}

	/**
	 * Determines whether restriction applies to a product in the current request context.
	 *
	 * @param int    $product_id Product ID.
	 * @param int    $variation_id Variation ID.
	 * @param string $request_context Context name.
	 */
	private function should_apply_to_product( int $product_id, int $variation_id, string $request_context ): bool {
		$base_decision = true;

		if ( ! $this->is_enabled() || $this->current_user_can_bypass() ) {
			$base_decision = false;
		}

		if ( $base_decision && $this->is_product_excluded( $product_id, $variation_id ) ) {
			$base_decision = false;
		}

		if ( $base_decision && ! $this->is_product_in_restrict_scope( $product_id, $variation_id ) ) {
			$base_decision = false;
		}

		return (bool) apply_filters(
			'spcr_should_apply_restriction',
			$base_decision,
			array(
				'product_id'       => $product_id,
				'variation_id'     => $variation_id,
				'mode'             => $this->get_mode(),
				'request_context'  => $request_context,
				'force_quantity_1' => $this->is_force_quantity_one_enabled(),
			),
			$this
		);
	}

	/**
	 * Checks whether product is excluded by ID/category settings.
	 */
	private function is_product_excluded( int $product_id, int $variation_id ): bool {
		$excluded_products = spcr_normalize_ids( spcr_get_option( 'spcr_excluded_products' ) );
		$excluded_terms    = spcr_normalize_ids( spcr_get_option( 'spcr_excluded_categories' ) );

		$product_targets = array_filter(
			array(
				$product_id,
				$variation_id,
				$variation_id > 0 ? absint( wp_get_post_parent_id( $variation_id ) ) : 0,
			)
		);

		if ( array_intersect( $excluded_products, $product_targets ) ) {
			return true;
		}

		if ( empty( $excluded_terms ) ) {
			return false;
		}

		$product_terms = $this->get_product_category_ids( $product_id, $variation_id );

		return ! empty( array_intersect( $excluded_terms, $product_terms ) );
	}

	/**
	 * Checks whether product falls inside restrict-only category scope.
	 */
	private function is_product_in_restrict_scope( int $product_id, int $variation_id ): bool {
		$restrict_only_terms = spcr_normalize_ids( spcr_get_option( 'spcr_restrict_only_categories' ) );

		if ( empty( $restrict_only_terms ) ) {
			return true;
		}

		$product_terms = $this->get_product_category_ids( $product_id, $variation_id );

		return ! empty( array_intersect( $restrict_only_terms, $product_terms ) );
	}

	/**
	 * Returns category IDs for a product/variation.
	 *
	 * @param int $product_id Product ID.
	 * @param int $variation_id Variation ID.
	 *
	 * @return int[]
	 */
	private function get_product_category_ids( int $product_id, int $variation_id ): array {
		$term_source_id = $product_id;

		if ( $variation_id > 0 ) {
			$parent_id = absint( wp_get_post_parent_id( $variation_id ) );
			if ( $parent_id > 0 ) {
				$term_source_id = $parent_id;
			}
		}

		if ( $term_source_id <= 0 ) {
			return array();
		}

		if ( function_exists( 'wc_get_product_term_ids' ) ) {
			return array_map( 'absint', wc_get_product_term_ids( $term_source_id, 'product_cat' ) );
		}

		$terms = wp_get_post_terms( $term_source_id, 'product_cat', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return array_map( 'absint', $terms );
	}

	/**
	 * Sets a cart item quantity safely.
	 *
	 * @param WC_Cart $cart Cart object.
	 * @param string  $cart_item_key Cart item key.
	 * @param int     $quantity Quantity.
	 */
	private function set_cart_item_quantity( WC_Cart $cart, string $cart_item_key, int $quantity ): void {
		if ( $this->is_adjusting_quantity ) {
			return;
		}

		$this->is_adjusting_quantity = true;

		try {
			$cart->set_quantity( $cart_item_key, $quantity, false );
		} finally {
			$this->is_adjusting_quantity = false;
		}
	}

	/**
	 * Adds force-quantity notice once per request.
	 */
	private function maybe_add_force_quantity_notice(): void {
		if ( $this->force_quantity_notice_added ) {
			return;
		}

		$this->add_notice(
			$this->resolve_notice_message(
				esc_html__( 'Quantity has been limited to 1 by store cart policy.', 'single-product-cart-restriction' ),
				array(
					'reason'         => 'force_quantity',
					'request_context' => 'cart_update',
				),
				false,
				''
			),
			'notice'
		);

		$this->force_quantity_notice_added = true;
	}

	/**
	 * Adds cleanup notice once per request.
	 *
	 * @param string $mode Restriction mode.
	 */
	private function maybe_add_cleanup_notice( string $mode ): void {
		if ( $this->cleanup_notice_added ) {
			return;
		}

		$default_message = 'replace' === $mode
			? esc_html__( 'The cart was normalized to keep only the most recent restricted product.', 'single-product-cart-restriction' )
			: esc_html__( 'The cart was normalized to keep only one restricted product.', 'single-product-cart-restriction' );

		$this->add_notice(
			$this->resolve_notice_message(
				$default_message,
				array(
					'reason'         => 'cart_cleanup',
					'mode'           => $mode,
					'request_context' => 'before_calculate_totals',
				),
				false,
				''
			),
			'notice'
		);

		$this->cleanup_notice_added = true;
	}

	/**
	 * Resolves final notice text.
	 *
	 * @param string $default_message Default notice.
	 * @param array  $context Notice context.
	 * @param bool   $allow_custom_option Whether custom option can override default.
	 * @param string $decision_message Decision-provided message.
	 */
	private function resolve_notice_message( string $default_message, array $context, bool $allow_custom_option, string $decision_message ): string {
		$message = $default_message;

		if ( '' !== trim( $decision_message ) ) {
			$message = sanitize_text_field( $decision_message );
		} elseif ( $allow_custom_option ) {
			$custom_message = trim( (string) spcr_get_option( 'spcr_custom_notice' ) );
			if ( '' !== $custom_message ) {
				$message = $custom_message;
			}
		}

		$message = (string) apply_filters( 'spcr_notice_message', $message, $context, $this );
		$message = trim( $message );

		return '' !== $message ? $message : $default_message;
	}

	/**
	 * Adds a WooCommerce notice.
	 *
	 * @param string $message Notice message.
	 * @param string $type Notice type.
	 */
	private function add_notice( string $message, string $type ): void {
		if ( '' === trim( $message ) || ! function_exists( 'wc_add_notice' ) ) {
			return;
		}

		$allowed_types = array( 'error', 'success', 'notice' );
		if ( ! in_array( $type, $allowed_types, true ) ) {
			$type = 'notice';
		}

		wc_add_notice( $message, $type );
	}

	/**
	 * Writes a debug log entry when debug mode is enabled.
	 *
	 * @param string               $level Log level.
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Extra context.
	 */
	private function log( string $level, string $message, array $context = array() ): void {
		if ( ! $this->is_debug_mode_enabled() ) {
			return;
		}

		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			$logger->log(
				$level,
				$message,
				array_merge(
					$context,
					array(
						'source' => 'single-product-cart-restriction',
					)
				)
			);
		}
	}

	/**
	 * Whether WooCommerce cart object is available.
	 */
	private function has_cart(): bool {
		return function_exists( 'WC' ) && WC() && WC()->cart instanceof WC_Cart;
	}

	/**
	 * Returns unique identity for product comparison.
	 */
	private function get_identity( int $product_id, int $variation_id ): string {
		$identity = $variation_id > 0 ? $variation_id : $product_id;
		return (string) absint( $identity );
	}

	/**
	 * Whether plugin restriction is enabled.
	 */
	private function is_enabled(): bool {
		return spcr_is_yes_option( 'spcr_enabled' );
	}

	/**
	 * Whether force-quantity option is enabled.
	 */
	private function is_force_quantity_one_enabled(): bool {
		return spcr_is_yes_option( 'spcr_force_quantity_one' );
	}

	/**
	 * Whether debug mode is enabled.
	 */
	private function is_debug_mode_enabled(): bool {
		return spcr_is_yes_option( 'spcr_debug_mode' );
	}

	/**
	 * Whether current user can bypass restrictions.
	 */
	private function current_user_can_bypass(): bool {
		if ( ! spcr_is_yes_option( 'spcr_bypass_admin_shop_manager' ) ) {
			return false;
		}

		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Returns configured mode.
	 */
	private function get_mode(): string {
		$mode = sanitize_key( (string) spcr_get_option( 'spcr_mode' ) );

		if ( ! in_array( $mode, array( 'block', 'replace' ), true ) ) {
			return 'block';
		}

		return $mode;
	}

	/**
	 * Default block notice.
	 */
	private function get_default_block_message(): string {
		return esc_html__( 'You can only keep one product in your cart at a time.', 'single-product-cart-restriction' );
	}

	/**
	 * Default replace notice.
	 */
	private function get_default_replace_message(): string {
		return esc_html__( 'Your previous cart item was replaced with the newly selected product.', 'single-product-cart-restriction' );
	}
}
