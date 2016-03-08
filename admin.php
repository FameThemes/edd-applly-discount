<?php

/**
 * @see edd_store_discount
 */

add_action( 'edd_add_discount_form_before_use_once', 'edd_aad_add_discount_settings' );
add_action( 'edd_edit_discount_form_before_use_once', 'edd_aad_add_discount_settings', 20, 2 );

function edd_aad_add_discount_settings( $discount_id = null, $discount =  null ){
    $is_auto  =  ( $discount_id ) ?  get_post_meta( $discount_id, '_edd_discount_auto_apply', true ) : 0 ;
    ?>
    <tr>
        <th scope="row" valign="top">
            <label for="edd-auto-apply"><?php _e( 'Auto apply', 'easy-digital-downloads' ); ?></label>
        </th>
        <td>
            <input type="checkbox" id="edd-auto-apply" name="auto_apply" value="1"<?php checked( true, $is_auto ); ?>/>
            <span class="description"><?php _e( 'Limit this discount to a single-use per customer?', 'easy-digital-downloads' ); ?></span>
        </td>
    </tr>
<?php
}

add_filter( 'edd_insert_discount', 'edd_aad_add_discount_meta' );
add_filter( 'edd_update_discount', 'edd_aad_add_discount_meta' );

function edd_aad_add_discount_meta( $meta , $discount_id =  null ){
    $is_auto_apply = 0;
    if ( isset ( $_REQUEST['auto_apply'] ) ) {
        if ( $_REQUEST['auto_apply'] == 1 ) {
            $is_auto_apply = 1;
        }
    }
    $meta['auto_apply'] = $is_auto_apply;
    return $meta;
}

function edd_aad_discount_custom_title( $title, $post_id = '' ){
    if ( get_post_type( $post_id ) !== 'edd_discount' ){
        return $title;
    }

    $is_auto  =  ( $post_id ) ?  get_post_meta( $post_id, '_edd_discount_auto_apply', true ) : 0 ;
    return $is_auto ? __( '<span title="This discount is auto apply" class="dashicons dashicons-awards"></span>', 'easy-digital-downloads' ).' '.$title : $title;
}

function edd_aad_discount_add_custom_title( ){
    $screen = get_current_screen( null );
    if ($screen->id = 'download_page_edd-discounts' ) {
        if ( ! isset( $_REQUEST['edd-action'] )  || 'edit_discount' != $_REQUEST['edd-action']  ) {
            add_filter( 'the_title', 'edd_aad_discount_custom_title', 35, 2 );
        }
    }
}
add_action( 'load-download_page_edd-discounts', 'edd_aad_discount_add_custom_title' );
