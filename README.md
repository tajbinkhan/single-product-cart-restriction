# Single Product Cart Restriction

Single Product Cart Restriction is a WooCommerce plugin that lets store owners enforce a one-product cart policy.

## What The Plugin Does

The plugin can enforce that only one product line item exists in the cart at a time.

It supports two behaviors:

- Block mode: Prevents adding a different product when one restricted product is already in the cart.
- Replace mode: Removes the current restricted cart item and keeps the newly added product.

The plugin also supports:

- Optional quantity cap of 1.
- Excluding specific products.
- Excluding specific categories.
- Restricting only selected categories.
- Optional bypass for administrators and shop managers.
- Custom customer notices.
- Optional debug logging.

## Important Behavior Notes

- One product means one cart line item, not quantity.
- In Block mode, adding the same product again is allowed when force quantity is disabled.
- If force quantity is enabled, quantity is capped at 1 during add-to-cart and cart updates.
- Exclusions take precedence over restrict-only category targeting.

## Where To Configure

In WordPress admin, go to:

WooCommerce > Settings > Products > Single Product Restriction

## Technical Summary

The plugin integrates with WooCommerce hooks to enforce rules for both normal and AJAX add-to-cart flows.

Primary enforcement includes:

- Add-to-cart validation.
- Post add-to-cart handling.
- Cart quantity update validation.
- Cart normalization before totals calculation.

## Plugin Details

- Slug: single-product-cart-restriction
- Text domain: single-product-cart-restriction
- Author: webphics
- Author website: https://www.webphics.com/

## Notes For Developers

The plugin exposes filters to customize logic:

- spcr_should_apply_restriction
- spcr_restriction_decision
- spcr_notice_message

Use these filters to override when restrictions apply, how decisions are made, and which notice message is shown.
