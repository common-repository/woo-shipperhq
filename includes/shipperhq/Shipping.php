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

if (!defined('ABSPATH')) exit;


class ShipperHQ_Shipping
{


    /**
     * Add the ShipperHQ Shipping Method
     */
    public function __construct()
    {
        add_action('woocommerce_shipping_methods', array($this, 'add_shipperhq_methods'));

        // Initialize shipping method class
        add_action('woocommerce_shipping_init', array($this, 'init_shipping_file'));

        // Setup placeOrder handler
        add_action('woocommerce_checkout_update_order_meta', array($this, 'handle_wc_checkout_update_order_meta'));

		// Add CSS selectors to shipping methods and split estimated delivery dates into new line
	    add_action('woocommerce_after_shipping_rate', array($this, 'checkout_shipping_add_css'), 10, 2);

		// Adjust the method name to add back in the estimated delivery date info if required
	    add_action('woocommerce_checkout_create_order_shipping_item', array($this, 'checkout_shipping_adjust_method_name'), 10, 4);

	    // Add CSS to shipping methods
	    add_action('wp_enqueue_scripts', array($this, 'add_shipperhq_styles'));
    }

	/**
	 * Adds the CSS for the cart and checkout page
	 *
	 * @return void
	 */
	public function add_shipperhq_styles()
	{
		wp_enqueue_style( 'shipperhq_styles', plugins_url( 'assets/css/shipperhq-styles.css', WC_SHIPPERHQ_ROOT_FILE));
	}

	/**
	 * RIV-1247 Sets the shipping method name to method name with delivery message appended.
	 * Ensures backward compatibility
	 *
	 * @param WC_Order_Item_Shipping $item
	 * @param int                    $package_key
	 * @param array                  $package
	 * @param WC_Order               $order
	 *
	 * @return void
	 * @throws WC_Data_Exception
	 */
	public function checkout_shipping_adjust_method_name($item, $package_key, $package, $order)
	{
		$methodDescription = $item->get_meta("method_description");

		if (!empty($methodDescription)) {
			$item->set_method_title( $item->get_method_title() . ' ' . __( $methodDescription ) );
		}
	}

	/**
	 * RIV-1247 Adds CSS class to method description which holds the estimated delivery date if present
	 *
	 * @param WC_Shipping_Method $method
	 * @param int $index
	 *
	 * @return void
	 */
	public function checkout_shipping_add_css($method, $index)
	{
		if ($method->get_method_id() == "shipperhq") {
			$metaData = $method->get_meta_data();
			if (array_key_exists("method_description", $metaData)) {
				echo '<span id="shipperhq-method-description" class="shipperhq-method-description">' . $metaData['method_description'] . '</span>';
			}
		}
	}

    public function add_shipperhq_methods($methods)
    {
        $methods[] = 'ShipperHQ_Shipping_Method';
        return $methods;
    }

    public function init_shipping_file()
    {
        /**
         * Shipping method class
         */
        require_once plugin_dir_path(__FILE__) . '/ShippingMethod.php';
    }

    public function handle_wc_checkout_update_order_meta($orderId)
    {
        require_once plugin_dir_path(__FILE__) . '/ListingManager.php';
        require_once plugin_dir_path(__FILE__) . '/helper/OrderHelper.php';

        $orderHelper = new ShipperHQ_OrderHelper($orderId);

        $settings = get_option('woocommerce_shipperhq_settings');
        $autoListingIsEnabled = array_key_exists('create_listing', $settings) && $settings['create_listing'] === 'AUTO';

        // SHQ18-2977 - Check if we even need to create a listing before wasting further effort
        if ($autoListingIsEnabled && $orderHelper->shippingMethodIsUship()) {
            $listingManager = new ShipperHQ_Listing_Manager();
            $listingManager->create_listing_for_order($orderId);
        }
    }
}
