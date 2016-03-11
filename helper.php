<?php
/**
 * Checks to see if the required products are in the cart
 *
 * @since 1.5
 * @param int $code_id Discount ID
 * @return bool $ret Are required products in the cart?
 */
function aad_edd_discount_product_reqs_met( $code_id = null, $download_id =  null ) {
    $product_reqs = edd_get_discount_product_reqs( $code_id );
    $condition    = edd_get_discount_product_condition( $code_id );
    $excluded_ps  = edd_get_discount_excluded_products( $code_id );
    $ret          = false;

    if ( empty( $product_reqs ) && empty( $excluded_ps ) ) {
        $ret = true;
    }

    // Normalize our data for product requiremetns, exlusions and cart data
    // First absint the items, then sort, and reset the array keys
    $product_reqs = array_map( 'absint', $product_reqs );
    asort( $product_reqs );
    $product_reqs = array_values( $product_reqs );

    $excluded_ps  = array_map( 'absint', $excluded_ps );
    asort( $excluded_ps );
    $excluded_ps  = array_values( $excluded_ps );

    // Ensure we have requirements before proceeding
    if ( ! $ret && ! empty( $product_reqs ) ) {
        $ret = in_array( $download_id,  $product_reqs );
    } else {
        $ret = true;
    }

    if( ! empty( $excluded_ps ) ) {
        // Check that there are products other than excluded ones in the cart
        $ret =  in_array( $download_id, $excluded_ps ) ? false : true;
    }

    return (bool) $ret;
}

/*
 * Get a price for a download
 *
 * @since 1.0
 * @param int $download_id ID of the download price to show
 * @param int $price_id Optional price id for variable pricing
 * @return void
 */
function aad_get_edd_price( $download_id = 0, $price_id = false ) {

    if( empty( $download_id ) ) {
        $download_id = get_the_ID();
    }

    if ( edd_has_variable_prices( $download_id ) ) {

        $prices = edd_get_variable_prices( $download_id );

        if ( false !== $price_id && isset( $prices[$price_id] ) ) {

            $price = edd_get_price_option_amount( $download_id, $price_id );

        } elseif( $default = edd_get_default_variable_price( $download_id ) ) {

            $price = edd_get_price_option_amount( $download_id, $default );

        } else {

            $price = edd_get_lowest_price_option( $download_id );

        }

        $price = edd_sanitize_amount( $price );

    } else {

        $price = edd_get_download_price( $download_id );

    }

    return $price;
}


