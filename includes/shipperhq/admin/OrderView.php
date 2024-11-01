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


class ShipperHQ_OrderView
{
    /** @var ShipperHQ_OrderHelper */
    private $orderHelper;

    public function __construct()
    {
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_listing_manager'));

        require(plugin_dir_path(__FILE__) . './RestApi/ListingManager.php');
        new ShipperHQ_ListingManager(); // Registers the routes

        require(plugin_dir_path(__FILE__) . '../helper/OrderHelper.php');
        $this->orderHelper = new ShipperHQ_OrderHelper();
    }

    private function get_options($order)
    {
        $nonce = wp_create_nonce('wp_rest');
        $options = [
            'order_number' => $order->get_id(),
            'items' => $this->get_unlisted_items($order),
            'create_listing' => [
                'endpoint' => $this->get_create_listing_endpoint(),
                'api_key' => $nonce,
            ],
            'fetch_updated_rate' => [
                'endpoint' => $this->get_fetch_rate_endpoint(),
                'api_key' => $nonce,
            ],
            'existing_rate' => $this->fetch_selected_rate($order)
        ];

        return json_encode($options);
    }

    private function get_fetch_rate_endpoint()
    {
        return rest_url('shq/v1/listing/fetch');
    }

    private function get_create_listing_endpoint()
    {
        return rest_url('shq/v1/listing/create');
    }

    /**
     * @param WC_Order $order
     * @return array
     */
    private function fetch_selected_rate($order)
    {
        $this->orderHelper->setOrder($order);
        $type = $this->orderHelper->extractShippingMetaDataByKey('carrier_type');
        $shippingRate = $this->orderHelper->getSelectedShippingMethod();

        if ($type) { // if carrier_type is set this is an SHQ shipping method
            list( $carrier_title, $method_title ) = explode(' - ', $shippingRate->get_method_title());
            return [
                'carrier_title' => $carrier_title,
                'method_title' => $method_title,
                'price' => $shippingRate->get_total()
            ];
        } else {
            return [
                'carrier_title' => 'WC',
                'method_title' => $shippingRate->get_method_title(),
                'price' => $shippingRate->get_total()
            ];
        }
    }

    private function get_unlisted_items($order)
    {
        $result = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $result[] = [
                'qty' => $item->get_quantity(),
                'id' => $item->get_product_id(),
                'name' => $product->get_formatted_name(),
                'img' => $product->get_image('thumbnail'),
                'listingId' => null,
            ];
        }

        return $result;
    }

    /**
     * @param $order WC_Order
     */
    public function display_listing_manager($order)
    {
        $this->orderHelper->setOrder($order);

        $settings = get_option('woocommerce_shipperhq_settings');
        $usingListing = array_key_exists('create_listing', $settings) && $settings['create_listing'] !== 'NONE';

        $shipping_address = implode('', array_values($order->get_address('shipping')));
        $shipping_methods = $order->get_shipping_methods();
        if(empty($shipping_address) || empty($shipping_methods)) {
            // display nothing on create new order form
            return;
        }

        $listing_id = get_post_meta($order->get_id(), "listing_id", true);
        $listing_status = get_post_meta($order->get_id(), "listing_status", true);

        if ($listing_status && !empty($listing_id)) {
            ?>
            <div id="shq-listing-status">
                <div><b>uShip Listing Creation was</b> <?php echo $listing_status ?></div>
                <div><b>uShip Listing id:</b> <a target="_blank" href="https://uship.com/shipment/Furniture-Listing/<?php echo $listing_id ?>"><?php echo $listing_id ?></a></div>
            </div>
            <?php
        } elseif ($this->showManualListingWidget($order, $usingListing, $settings, $listing_status, $listing_id)) {
            $rootRelPath = str_ireplace(get_home_path(), '', plugin_dir_path(__FILE__));
            wp_enqueue_script('listingWidget', "/$rootRelPath../../../assets/js/admin/labelWidget.js");
            ?>
            <div id="shq-create-listing"></div>
            <script>
                window.addEventListener('load', function () {
                    var attach = (window.shqListing && window.shqListing.attach) ? window.shqListing.attach : false;
                    if (attach) {
                        var options = <?= $this->get_options($order) ?>;
                        attach(document.getElementById('shq-create-listing'), options);
                    } else {
                        console.error('Error loading SHQ listing bundle');
                    }
                });
            </script>
            <?php
        }
    }

    /**
     * @param $order
     * @param $usingListing
     * @param $settings
     * @param $listing_status
     * @param $listing_id
     * @return bool
     */
    private function showManualListingWidget($order, $usingListing, $settings, $listing_status, $listing_id)
    {
        $this->orderHelper->setOrder($order);
        return $usingListing && ($settings['create_listing'] === 'MANUAL'
                || ($this->orderHelper->shippingMethodIsUship() && $listing_status && empty($listing_id)));
    }
}



