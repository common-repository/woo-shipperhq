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


class ShipperHQ_CreateListing_Request_Builder
{
    private $orderId;
    private $order;
    private $items;
    private $forItems;
    private $forRate;
    /** @var WC_Logger */
    private $logger;

    /**
     * ShipperHQ_CreateListing_Request_Builder constructor.
     * @param $orderId
     * @param WC_Logger $logger
     */
    public function __construct($orderId, WC_Logger $logger)
    {
        $this->orderId = $orderId;
        $this->logger = $logger;
    }


    /**
     * @return mixed
     */
    public function getOrderId()
    {
        return $this->orderId;
    }

    /**
     * @param $items
     * @return $this
     */
    public function forItems($items) {
        $this->forItems = $items;
        return $this;
    }

    /**
     * @param $rate
     * @return $this
     */
    public function forRate($rate) {
        $this->forRate = $rate;
        return $this;
    }

    /**
     * @return \ShipperHQ\GraphQL\Types\Input\ListingInfo|false
     */
    public function build() {
        if ($this->isNotSHQShipmentMethod()) {
            return false;
        }

        $request = new \ShipperHQ\GraphQL\Types\Input\ListingInfo(
            $this->buildCarrier(),
            $this->buildSender(),
            $this->buildAddress(),
            $this->buildListings(),
            $this->buildSiteDetails()
        );

        return $request;
    }

    private function getOrder() {
        if (!$this->order) {
            $this->order = wc_get_order($this->getOrderId());
        }
        return $this->order;
    }

    private function getItems() {
        if (!$this->items) {
            $this->items = $this->getOrder()->get_items();
            if ($this->forItems) {
                $forItems = $this->forItems;
                $this->items = array_reduce($this->items, function($carry, $item) use ($forItems) {
                    /** @var WC_Order_Item_Product $item */
                    foreach($forItems as $fi) {
                        if ($fi['itemId'] === $item->get_product_id()) {
                            $item->set_quantity(min($item->get_quantity(), $fi['qty']));
                            $carry[] = $item;
                        }
                    }
                    return $carry;
                }, []);
            }
        }
        return $this->items;
    }

    /**
     * Gets meta data from first shipping method
     *
     * todo: should store this instead of repeatedly getting it
     *
     * @return mixed
     */
    private function getMethodMetaData()
    {
        $order = $this->getOrder();
        $shippingMethods = $order->get_shipping_methods();
        $firstShippingMethod = array_shift($shippingMethods);
        $methodMetaData = $firstShippingMethod->get_meta_data();

        return $methodMetaData;
    }

    private function extractMetaDataByKey($key)
    {
        $methodMetaData = $this->getMethodMetaData();
        $value = "";

        foreach ($methodMetaData as $metaData) {
            $data = $metaData->get_data();
            if ($data['key'] == $key) {
                $value = $data['value'];
                break;
            }
        }
        return $value;
    }

    /**
     * @return \ShipperHQ\GraphQL\Types\Input\Carrier
     */
    private function buildCarrier()
    {
        if ($this->forRate) {
            $carrierCode = $this->forRate['carrier_code'];
            $carrierType = $this->forRate['carrier_type'];
        } else {
            $carrierCode = $this->extractMetaDataByKey('carrier_code');
            $carrierType = $this->extractMetaDataByKey('carrier_type');
        }

        return new \ShipperHQ\GraphQL\Types\Input\Carrier($carrierType, $carrierCode);
    }

    /**
     * @return \ShipperHQ\GraphQL\Types\Input\Sender
     */
    private function buildSender()
    {
        $carriergroupDetail = $this->extractMetaDataByKey("carriergroup_detail");
        $originName = $carriergroupDetail['name'];

        return new \ShipperHQ\GraphQL\Types\Input\Sender($originName);
    }

    /**
     * @return \ShipperHQ\GraphQL\Types\Input\Address
     */
    private function buildAddress()
    {
        return new \ShipperHQ\GraphQL\Types\Input\Address(
            $this->getOrder()->get_shipping_country(),
            $this->getOrder()->get_shipping_state(),
            $this->getOrder()->get_shipping_city(),
            $this->getOrder()->get_shipping_address_1(),
            $this->getOrder()->get_shipping_address_2(),
            $this->getOrder()->get_shipping_postcode()
        );
    }

    /**
     * @return \ShipperHQ\GraphQL\Types\Input\RMSSiteDetails
     */
    private function buildSiteDetails()
    {
        $mapper = new ShipperHQ_Mapper;
        $siteDetails = $mapper->getSiteDetails();

        return new \ShipperHQ\GraphQL\Types\Input\RMSSiteDetails(
            $siteDetails->getAppVersion(),
            $siteDetails->getEcommerceCart(),
            $siteDetails->getEcommerceVersion(),
            $siteDetails->getWebsiteUrl(),
            $siteDetails->getIpAddress()
        );
    }

    /**
     * @return \ShipperHQ\GraphQL\Types\Input\Listing[]
     */
    private function buildListings()
    {
        return [$this->buildListing()];
    }

    /**
     * @return \ShipperHQ\GraphQL\Types\Input\Listing
     */
    private function buildListing()
    {
        return new \ShipperHQ\GraphQL\Types\Input\Listing(
            $this->buildListingDetail(),
            $this->buildListingPieces()
        );
    }

    /**
     * @return \ShipperHQ\GraphQL\Types\Input\ListingDetail
     */
    private function buildListingDetail()
    {
        $hashedSiteUrl = $this->getSiteUrlHash();
        $orderNumber = $this->getOrder()->get_order_number();
        $shipmentId = "$hashedSiteUrl-$orderNumber";

        if ($this->forRate) {
            $freightCharges = $this->forRate['nyp_amount'] ? $this->forRate['nyp_amount'] : $this->forRate['price'];
            $shippingMethodCode = $this->forRate['method_code'];
        } else {
            if ($this->extractMetaDataByKey('nyp_amount')) {
                $freightCharges = $this->extractMetaDataByKey('nyp_amount');
            } else {
                $freightCharges = $this->getOrder()->get_shipping_total();
            }
            $shippingMethodCode = $this->extractMetaDataByKey("method_code");
        }

        return new \ShipperHQ\GraphQL\Types\Input\ListingDetail(
            $shipmentId,
            $shippingMethodCode,
            $freightCharges
        );
    }

    /**
     * @return \ShipperHQ\GraphQL\Types\Input\Piece[]
     */
    private function buildListingPieces()
    {
        $items = $this->getItems();
        $pieces = [];
        foreach ($items as $item) {
            $pieces[] = $this->buildListingPiece($item);
        }

        return $pieces;
    }

    /**
     * @param WC_Order_Item_Product $item
     * @return \ShipperHQ\GraphQL\Types\Input\Piece
     */
    private function buildListingPiece($item)
    {
        $product = $item->get_product();
        $piece = new \ShipperHQ\GraphQL\Types\Input\Piece(
            $item->get_product_id(),
            $item->get_name(),
            $item->get_subtotal(),
            $product->get_weight() * $item->get_quantity(),
            $product->get_length(),
            $product->get_width(),
            $product->get_height()
        );
        $piece->setImage($this->getProductImage($product));

        return $piece;
    }

    /**
     * @return bool
     */
    private function isNotSHQShipmentMethod()
    {
        return $this->extractMetaDataByKey('carrier_code') == "";
    }

    /**
     * @return string
     */
    private function getSiteUrlHash()
    {
        $mapper = new ShipperHQ_Mapper;
        $siteDetails = $mapper->getSiteDetails();
        $siteUrl = $siteDetails->getWebsiteUrl();
        $hashedSiteUrl = md5($siteUrl);
        return $hashedSiteUrl;
    }

    /**
     * @param WC_Product $product
     * @return string|null
     */
    private function getProductImage($product)
    {
        $imgUrl = false;
        if ($product->get_image_id()) {
            $imgUrl = wp_get_attachment_thumb_url($product->get_image_id());
        } elseif ($product->get_parent_id()) {
            $parentProduct = wc_get_product($product->get_parent_id());
            return $this->getProductImage($parentProduct);
        }

        if ($imgUrl) {
            $uploads = wp_upload_dir();
            $file_path = str_replace($uploads['baseurl'], $uploads['basedir'], $imgUrl);

            if (file_exists($file_path)) {
                $file_raw_content = file_get_contents($file_path, false);
                return base64_encode($file_raw_content);
            }
        }
        return null;
    }
}
