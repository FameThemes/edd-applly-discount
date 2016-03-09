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


    /**
     * Get all auto apply discount codes
     *
     * @return array
     */
    function edd_aad_get_discount_codes(){
        $args = array(
            'posts_per_page'   => -1,
            'meta_key'         => '_edd_discount_auto_apply',
            'meta_value'       => '1',
            'post_type'        => 'edd_discount',
            'post_status'      => 'active',
            'suppress_filters' => true
        );
        $posts_array = get_posts( $args );
        wp_reset_query();
        return $posts_array;
    }


    //  do_action( 'edd_post_add_to_cart', $download_id, $options );
    /**
    * @see edd_ajax_apply_discount
    */


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
        $codes = array_filter( $codes );
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

        foreach (  $codes as $discount ) {
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
     * Auto add discount code if vaild
     */
    function edd_aad_init(){
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
    add_action('wp', 'edd_aad_init');



} // end if not is admin pages