<?php


/*
 * Shipper HQ
 *
 * @category ShipperHQ
 * @package woocommerce-shipperhq
 * @copyright Copyright (c) 2020 Zowta LTD and Zowta LLC (http://www.ShipperHQ.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @author ShipperHQ Team sales@shipperhq.com
 */

/**
 * TODO
 * @return bool
 */
function shq_validate_settings() {
    if ( ( empty( $this->settings[ 'api_key' ] ) && ! isset( $_POST[ 'woocommerce_shipperhq_api_key' ] ) ) || ( isset( $_POST[ 'woocommerce_shipperhq_api_key' ] ) && '' == $_POST[ 'woocommerce_shipperhq_api_key' ] ) )
    {
        WC_Shipperhq::admin_notice( sprintf( __( 'Please enter a <a href="%s">ShipperHQ API Key</a>.', 'woocommerce-shipperhq' ), admin_url( 'admin.php?page=wc-settings&tab=shipping&section=wc_shipperhq_shipping' ) ), 'error' );
        return false;
    }
}
