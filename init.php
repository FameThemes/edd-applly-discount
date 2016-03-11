<?php
/**
 * Plugin Name: 	EDD Auto Apply Discount
 * Plugin URI:		http://famethemes.com
 * Description:		Auto apply discount code for checkout
 * Version: 		1.0.0
 * Author:			FameThemes
 * Author URI: 		http://www.famethemes.com/
 * Text Domain: 	edd-auto-apply-discount
 */

if ( is_admin() ) {
    require_once dirname( __FILE__ ).'/admin.php';
} else {
    require_once dirname( __FILE__ ).'/helper.php';
    global $edd_aad_discount_codes,
           $aad_applied_download;
           $aad_applied_discount_download;

    $aad_get_discount_code =  false;
    $aad_applied_download = array();
    $aad_applied_discount_download = array();


    function aad_maybe_apply_discount_code( $download_id, $price_id = null  ){
        global $aad_applied_download, $aad_applied_discount_download ;
        if ( $price_id ) {
            $check =  isset( $aad_applied_download[$download_id] ) && isset( $aad_applied_download[$download_id][ $price_id ] ) ?  false : true;
        } else {
            $check =  isset( $aad_applied_download[$download_id] ) ?  false : true;
        }
        if (  $check ) {
            $codes = edd_aad_get_discount_codes();
            if ( is_array( $codes ) ) {
                foreach ($codes as $discount) {
                    if (
                        edd_is_discount_active($discount->ID) &&
                        edd_is_discount_started($discount->ID) &&
                        edd_discount_is_min_met($discount->ID) &&
                        aad_edd_discount_product_reqs_met($discount->ID, $download_id)
                    ) {
                        $type = edd_get_discount_type($discount->ID);
                        $amount = edd_get_discount_amount($discount->ID);
                        $price = aad_get_edd_price($download_id, $price_id);
                        if ($type == 'flat') {
                            $price = $price - $amount;
                        } else {
                            $price = $price - $price * ($amount / 100);
                        }
                        edd_unset_error( 'edd-discount-error' );

                        // Get best coupon discount
                        if  ( $price_id ) {
                            if ( ! isset( $aad_applied_download[$download_id] ) ) {
                                $aad_applied_download[$download_id] = array();
                            }
                            $_p = isset( $aad_applied_download[$download_id][$price_id] ) ? $aad_applied_download[$download_id][$price_id] : 0;
                            $aad_applied_download[$download_id][$price_id] = ( $price > $_p ) ? $price : $_p;
                        } else {
                            $_p = isset( $aad_applied_download[$download_id] ) ? $aad_applied_download[$download_id] : 0;
                            $aad_applied_download[$download_id] = ( $price > $_p ) ? $price : $_p;
                        }
                        $aad_applied_discount_download[ $download_id ] = $discount->ID;
                        return $price;
                    }
                }
            }
        } else {
            edd_unset_error( 'edd-discount-error' );
            if ( ! isset( $aad_applied_download[$download_id] ) ) {
                return false;
            } else if ( ! is_array( $aad_applied_download[$download_id] ) ) {
                return $aad_applied_download[$download_id];
            } else {
                return current( $aad_applied_download[$download_id] );
            }
        }
        return false;
    }

    /**
     * @see edd_price
     */
    function aad_edd_price( $formatted_price, $download_id, $price, $price_id ){
        $discount_price = aad_maybe_apply_discount_code( $download_id , $price_id );
        if ( $discount_price ) {
            $price =  aad_get_edd_price( $download_id , $price_id );
            $discount_price = apply_filters( 'edd_download_price', edd_sanitize_amount( $discount_price ) );
            $price = apply_filters( 'edd_download_price', edd_sanitize_amount( $price ) );

            // _edd_discount_auto_apply_title
            global $aad_applied_discount_download;
            $custom_title = '';
            if ( isset( $aad_applied_discount_download[ $download_id ] ) ){
                $custom_title = get_post_meta( $aad_applied_discount_download[ $download_id ], '_edd_discount_auto_apply_title', true );
                if ( $custom_title != '' ) {
                    $custom_title  ='<span class="price-custom-title">'.wp_kses_post( $custom_title ).'</span>';
                }
            }

            $formatted_price = '<span class="edd_price" id="edd_price_' . $download_id . '"><del class="price-del">' . $price . '</del><span class="price-sep">-</span><ins class="price-ins">'.$discount_price.'</ins>'.$custom_title.'</span>';
            return $formatted_price;
        }
        return $formatted_price;
    }
    add_filter( 'edd_download_price_after_html', 'aad_edd_price', 35, 4 );


    function aad_edd_cart_price( $price_label, $item_id = 0, $options = array( ) ) {
        $price_id = isset( $options['price_id'] ) ? $options['price_id'] : false;
        $discount_price = aad_maybe_apply_discount_code( $item_id, $price_id );
        if ( ! $discount_price ) {
            return $price_label;
        }

        $price = edd_get_cart_item_price( $item_id, $options );
        $label = '';

        if ( ! edd_is_free_download( $item_id, $price_id ) && ! edd_download_is_tax_exclusive( $item_id ) ) {

            if( edd_prices_show_tax_on_checkout() && ! edd_prices_include_tax() ) {

                $price += edd_get_cart_item_tax( $item_id, $options, $price );

            } if( ! edd_prices_show_tax_on_checkout() && edd_prices_include_tax() ) {

                $price -= edd_get_cart_item_tax( $item_id, $options, $price );

            }

            if( edd_display_tax_rate() ) {

                $label = '&nbsp;&ndash;&nbsp;';

                if( edd_prices_show_tax_on_checkout() ) {
                    $label .= sprintf( __( 'includes %s tax', 'easy-digital-downloads' ), edd_get_formatted_tax_rate() );
                } else {
                    $label .= sprintf( __( 'excludes %s tax', 'easy-digital-downloads' ), edd_get_formatted_tax_rate() );
                }

                $label = apply_filters( 'edd_cart_item_tax_description', $label, $item_id, $options );

            }
        }
        wp_reset_postdata();
        return '<del class="price-del">'.edd_currency_filter( edd_format_amount( $price ) ).'</del><span class="price-sep">-</span><ins class="price-ins">'.edd_currency_filter( edd_format_amount( $discount_price ) ).'</ins>' . $label;
    }
    add_filter( 'edd_cart_item_price_label', 'aad_edd_cart_price', 35, 4  );



    /**
     * Get all auto apply discount codes
     *
     * @return array
     */
    function edd_aad_get_discount_codes(){
        global $edd_aad_discount_codes;
        if ( empty( $edd_aad_discount_codes ) ) {
            $args = array(
                'posts_per_page'   => -1,
                'meta_key'         => '_edd_discount_auto_apply',
                'meta_value'       => '1',
                'post_type'        => 'edd_discount',
                'post_status'      => 'active',
                'suppress_filters' => true
            );
            $posts_array = get_posts( $args );
            wp_reset_postdata();
            $edd_aad_discount_codes =  $posts_array;
        }
        return $edd_aad_discount_codes;
    }



    /**
     * Has Active Discounts
     *
     * Checks if there is any active discounts, returns a boolean.
     *
     * @since 1.0
     * @return bool
     */
    function edd_aad_get_active_discounts() {
        $_codes = EDD()->session->get( 'cart_discounts' );
        if ( is_string( $_codes ) ) {
            $codes = explode( '|', $_codes );
        }
        if ( is_array( $codes ) ) {
            $codes = array_filter( $codes );
        }

        if ( ! empty( $codes ) ) {
            return $codes;
        } else {
            return false;
        }
    }


    /**
     * Renders the Discount Code field which allows users to enter a discount code.
     * This field is only displayed if there are any active discounts on the site else
     * it's not displayed.
     *
     * @since 1.2.2
     * @return void
     */
    function edd_aad_discount_field() {

        if( isset( $_GET['payment-mode'] ) && edd_is_ajax_disabled() ) {
            return; // Only show before a payment method has been selected if ajax is disabled
        }

        if( ! edd_is_checkout() ) {
            return;
        }

        if ( edd_has_active_discounts() && edd_get_cart_total() ) :

            $color = edd_get_option( 'checkout_color', 'blue' );
            $color = ( $color == 'inherit' ) ? '' : $color;
            $style = edd_get_option( 'button_style', 'button' );

            $codes =  edd_aad_get_active_discounts();
            $code = ( $codes ) ? esc_attr( $codes[0] ) : '';
            ?>
            <fieldset id="edd_discount_code">
                <?php if ( $code ) { ?>
                <?php } ?>
                <p id="edd_show_discount" style="<?php echo $code ? 'display:none' : ''; ?>;">
                    <?php _e( 'Have a discount code?', 'easy-digital-downloads' ); ?> <a href="#" class="edd_discount_link"><?php echo _x( 'Click to enter it', 'Entering a discount code', 'easy-digital-downloads' ); ?></a>
                </p>
                <p id="edd-discount-code-wrap" class="edd-cart-adjustment">
                    <label class="edd-label" for="edd-discount">
                        <?php _e( 'Discount', 'easy-digital-downloads' ); ?>
                        <img src="<?php echo EDD_PLUGIN_URL; ?>assets/images/loading.gif" id="edd-discount-loader" style="display:none;"/>
                    </label>
                    <span class="edd-description"><?php _e( 'Enter a coupon code if you have one.', 'easy-digital-downloads' ); ?></span>
                    <input class="edd-input" type="text" id="edd-discount" name="edd-discount" value="<?php echo esc_attr( $code ); ?>" placeholder="<?php  esc_attr_e( 'Enter discount', 'easy-digital-downloads' ); ?>"/>
                    <input type="submit" class="edd-apply-discount edd-submit button <?php echo $color . ' ' . $style; ?>" value="<?php echo _x( 'Apply', 'Apply discount at checkout', 'easy-digital-downloads' ); ?>"/>
                    <?php if ( $code ) { ?>
                    <span class="edd-discount-info"></i> You have already selected the best coupon - <?php echo esc_html( $code ); ?>!</span>
                    <?php } ?>

                    <span id="edd-discount-error-wrap" class="edd_error edd-alert edd-alert-error" style="display:none;"></span>
                </p>
            </fieldset>
        <?php
        endif;
    }
    remove_action( 'edd_checkout_form_top', 'edd_discount_field', -1 );
    add_action( 'edd_checkout_form_top', 'edd_aad_discount_field', -1 );



    //add_action( 'edd_post_add_to_cart', 'edd_aad_auto_apply_discount', 60 , 2 );
    //add_action( 'edd_before_purchase_form', 'edd_aad_auto_apply_discount' );
    /**
     * @see edd_ajax_apply_discount
     *
     * @return bool
     */
    function edd_aad_auto_apply_discount( ){

        $codes = edd_aad_get_discount_codes();

        $customer = EDD()->session->get( 'customer' );
        $customer = wp_parse_args( $customer, array( 'first_name' => '', 'last_name' => '', 'email' => '' ) );

        if( is_user_logged_in() ) {
            $user_data = get_userdata( get_current_user_id() );
            foreach( $customer as $key => $field ) {
                if ( 'email' == $key && empty( $field ) ) {
                    $customer[ $key ] = $user_data->user_email;
                } elseif ( empty( $field ) ) {
                    $customer[ $key ] = $user_data->$key;
                }
            }
        }
        $customer = array_map( 'sanitize_text_field', $customer );
        $user = $customer['email'];
        $is_multiple_discounts_allowed =  edd_multiple_discounts_allowed();
        $set = false;

        foreach ( $codes as $discount ) {
            $discount_code = edd_get_discount_code( $discount->ID );
            if ( edd_is_discount_valid( $discount_code, $user ) ) {
                $discounts = edd_set_cart_discount( $discount_code );
                $set = true;
                if ( ! $is_multiple_discounts_allowed ) {
                    edd_unset_error( 'edd-discount-error' );
                    return true;
                }
            }
        }
        edd_unset_error( 'edd-discount-error' );

        return $set;
    }


    /**
     * Variable price output
     *
     * Outputs variable pricing options for each download or a specified downloads in a list.
     * The output generated can be overridden by the filters provided or by removing
     * the action and adding your own custom action.
     *
     * @since 1.2.3
     * @param int $download_id Download ID
     * @return void
     */
    function aad_edd_purchase_variable_pricing( $download_id = 0, $args = array() ) {
        $variable_pricing = edd_has_variable_prices( $download_id );

        if ( ! $variable_pricing ) {
            return;
        }

        $prices = apply_filters( 'edd_purchase_variable_prices', edd_get_variable_prices( $download_id ), $download_id );

        // If the price_id passed is found in the variable prices, do not display all variable prices.
        if ( false !== $args['price_id'] && isset( $prices[ $args['price_id'] ] ) ) {
            return;
        }

        $type   = edd_single_price_option_mode( $download_id ) ? 'checkbox' : 'radio';
        $mode   = edd_single_price_option_mode( $download_id ) ? 'multi' : 'single';
        $schema = edd_add_schema_microdata() ? ' itemprop="offers" itemscope itemtype="http://schema.org/Offer"' : '';

        if ( edd_item_in_cart( $download_id ) && ! edd_single_price_option_mode( $download_id ) ) {
            return;
        }

        do_action( 'edd_before_price_options', $download_id );
        // maybe- can apply discount
        ?>
        <div class="edd_price_options edd_<?php echo esc_attr( $mode ); ?>_mode">
            <ul>
                <?php
                if ( $prices ) :
                    $checked_key = isset( $_GET['price_option'] ) ? absint( $_GET['price_option'] ) : edd_get_default_variable_price( $download_id );
                    foreach ( $prices as $key => $price ) :

                        $discount_price = aad_maybe_apply_discount_code( $download_id, $key );

                        echo '<li id="edd_price_option_' . $download_id . '_' . sanitize_key( $price['name'] ) . '"' . $schema . '>';
                        echo '<label for="'	. esc_attr( 'edd_price_option_' . $download_id . '_' . $key ) . '">';
                        echo '<input type="' . $type . '" ' . checked( apply_filters( 'edd_price_option_checked', $checked_key, $download_id, $key ), $key, false ) . ' name="edd_options[price_id][]" id="' . esc_attr( 'edd_price_option_' . $download_id . '_' . $key ) . '" class="' . esc_attr( 'edd_price_option_' . $download_id ) . '" value="' . esc_attr( $key ) . '" data-price="' . edd_get_price_option_amount( $download_id, $key ) .'"/>&nbsp;';
                        echo '<span class="edd_price_option_name" itemprop="description">' . esc_html( $price['name'] ) . '</span><span class="edd_price_option_sep">&nbsp;&ndash;&nbsp;</span>';
                        if ( $discount_price ) {
                            echo '<span class="edd_price_option_price" itemprop="price" xmlns="http://www.w3.org/1999/html"><del class="price-del">'.edd_currency_filter( edd_format_amount( $price['amount'])).'</del><span class="price-sep">-</span><ins class="price-ins">'.edd_currency_filter( $discount_price ).'</ins></span>';
                        } else {
                            echo '<span class="edd_price_option_price" itemprop="price">' . edd_currency_filter( edd_format_amount( $price['amount'] ) ) . '</span>';
                        }

                        echo '</label>';
                        do_action( 'edd_after_price_option', $key, $price, $download_id );
                        echo '</li>';
                    endforeach;
                endif;
                do_action( 'edd_after_price_options_list', $download_id, $prices, $type );
                ?>
            </ul>
        </div><!--end .edd_price_options-->
        <?php
        wp_reset_postdata();
        do_action( 'edd_after_price_options', $download_id );
    }
    remove_action( 'edd_purchase_link_top', 'edd_purchase_variable_pricing', 10, 2 );
    add_action( 'edd_purchase_link_top', 'aad_edd_purchase_variable_pricing', 35, 2 );





    /**
     * Auto add discount code if vaild
     */
    function edd_aad_add_checkout_discount(){
        if ( is_singular() ) {
            $purchase_page = edd_get_option( 'purchase_page', false );
            if ( $purchase_page  && is_page( $purchase_page ) ) {
                $codes =  edd_aad_get_active_discounts();
                if ( ! $codes  ) {
                    edd_aad_auto_apply_discount();
                }

            }
        }

    }
    add_action('wp', 'edd_aad_add_checkout_discount');



} // end if not is admin pages