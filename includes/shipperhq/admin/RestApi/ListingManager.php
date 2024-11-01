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

/* WooCommerce ShipperHQ
 *
 * Shipping method
 *
 * @author 		ShipperHQ
 * @category 	Shipping
 * @package 	woocommerce-shipperhq/admin
 */

class ShipperHQ_ListingManager extends WP_REST_Controller
{

    public function __construct()
    {
        $this->namespace = 'shq/v1';
        $this->rest_base = 'listing';
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Called automatically on `rest_api_init()`.
     */
    public function register_routes()
    {
        register_rest_route(
            $this->namespace,
            $this->rest_base . '/create',
            array(
                array(
                    'methods' => 'POST',
                    'callback' => array($this, 'create'),
                    'permission_callback' => array($this, 'get_status_permission_check'),
                    'args'                => array(
                        'order_number'    => array(
                            'type'     => 'number',
                            'required' => true,
                        ),
                    ),
                ),
            )
        );
        register_rest_route(
            $this->namespace,
            $this->rest_base . '/fetch',
            array(
                array(
                    'methods' => 'POST',
                    'callback' => array($this, 'fetch'),
                    'permission_callback' => array($this, 'get_status_permission_check'),
                    'args'                => array(
                        'order_number'    => array(
                            'type'     => 'number',
                            'required' => true,
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Ensure the user has proper permissions
     *
     * @return boolean
     */
    public function get_status_permission_check()
    {
        return current_user_can('edit_posts');
    }

    /**
     * @param object $request - request passed from WP.
     * @return WP_REST_Response|WP_Error
     */
    public function create($request)
    {
        $items = $request['items'];
        $rate = $request['rate'];
        $order_number = $request['order_number'];

        require_once plugin_dir_path( __FILE__ ) . '../../ListingManager.php';

        $listing_manager = new ShipperHQ_Listing_Manager();
        $listing_id = $listing_manager->create_listing_for_order($order_number, $items, $rate);

        $response = new WP_REST_Response($listing_id, 200);

        return $response;
    }

    /**
     * @param object $request - request passed from WP.
     * @return array|WP_Error
     */
    public function fetch($request)
    {
        $items = $request['items'];
        $carrier_code_pattern = $request['carrierCodePattern'];
        $order_number = $request['order_number'];

        $order = wc_get_order($order_number);
        $rates = $this->fetchRates($order, $items);

        $rates = array_filter($rates, function ($rate) use ($carrier_code_pattern) {
            return array_key_exists('carrier_title', $rate)
                && stripos($rate['carrier_title'], $carrier_code_pattern) !== false;
        });

        $response = new WP_REST_Response($rates);

        $response->set_status(200);

        return $response;
    }

    private function fetchRates($order, $items) {
        $result = [];

        $contents = [];
        /** @var WC_Order_Item_Product $item */
        foreach($items as $item) {
            $product = wc_get_product($item['itemId']);
            $contents []= [
                'data' => $product,
                'quantity' => $item['qty'],
            ];
        }

        $wc_shipping        = WC_Shipping::instance();
        foreach ( $wc_shipping->get_shipping_methods() as $method_id => $method ) {
            $rates = $method->get_rates_for_package([
                'contents' => $contents,
                'destination' => [
                    'country'   => $order->get_shipping_country(),
                    'state'     => $order->get_shipping_state(),
                    'postcode'  => $order->get_shipping_postcode(),
                    'city'      => $order->get_shipping_city(),
                    'address_1' => $order->get_shipping_address_1(),
                    'address_2' => $order->get_shipping_address_2(),
                ]
            ]);

            foreach($rates as $rate) {
                $result[] = $this->formatMeta($rate->get_meta_data());
            }
        }

        return $result;
    }

    private function formatMeta($meta) {
        $carrier_group = $meta['carriergroup_detail'];

        return [
            'carrier_title' => $carrier_group['carrierTitle'],
            'carrier_code' => $carrier_group['carrier_code'],
            'carrier_type' => $meta['carrier_type'],
            'method_code' => $meta['method_code'],
            'method_title' => $carrier_group['methodTitle'],
            'price' => $carrier_group['price'],
            'nyp_amount' => $carrier_group['rate_cost'] ?: 0
        ];
    }
}
