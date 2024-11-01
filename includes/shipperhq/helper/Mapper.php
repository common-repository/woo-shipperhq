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

use ShipperHQ\WS\Rate\Request\RateRequest;

class ShipperHQ_Mapper
{

    /**
     * Holds the settings
     *
     * @var mixed|void
     */
    private $_settings;


    /**
     * All attributes - not currently implemented
     * @var array
     */
    //    protected static $_allAttributeNames = [
    //        'shipperhq_shipping_group', 'shipperhq_post_shipping_group',
    //        'shipperhq_shipping_qty',
    //        'shipperhq_shipping_fee', 'shipperhq_additional_price', 'freight_class',
    //        'shipperhq_nmfc_class', 'shipperhq_nmfc_sub', 'shipperhq_handling_fee', 'shipperhq_carrier_code',
    //        'shipperhq_volume_weight', 'shipperhq_declared_value', 'ship_separately',
    //        'shipperhq_dim_group', 'shipperhq_poss_boxes', 'ship_box_tolerance', 'must_ship_freight', 'packing_section_name',
    //        'shipperhq_volume_weight',
    //        'height', 'width', 'length', 'shipperhq_availability_date'
    //    ];

    /**
     * Custom Product Attributes
     * @var array
     */
    protected static $_customAttributeNames = [
        'shipperhq_shipping_group', 'freight_class', 'ship_separately',
        'shipperhq_dim_group', 'must_ship_freight', 'shipperhq_warehouse', 'shipperhq_hs_code'
    ];

    /**
     * Woocommerce attributes (excludes height/width/length as is in meta data
     *
     * @var array
     */
    protected static $_stdAttributeNames = [
        'height', 'width', 'length'
    ];


    protected static $origin = 'shipperhq_warehouse';
    protected static $location = 'shipperhq_location';

    protected static $useDefault = 'Use Default';

    /**
     * Options on the delivery type
     * @var array
     */
    protected static $shippingOptions = ['liftgate_required', 'notify_required', 'inside_delivery', 'destination_type'];


    /**
     * Woocommerce_Shipperhq_API constructor.
     */
    public function __construct()
    {
        $this->_settings = get_option( 'woocommerce_shipperhq_settings' );
    }

	/**
	 * Puts together a ShipperHQ request
	 *
	 * @param $package
	 *
	 * @return null|RateRequest
	 */
    public function create_request($package)
    {
		if (!$this->hasCredentialsEntered()) {
			return null;
		}

        $shipperHQRequest = new RateRequest();
        $shipperHQRequest->cart = $this->getCartDetails($package);
        $shipperHQRequest->destination = $this->getDestination($package);;
        $shipperHQRequest->customerDetails = $this->getCustomerGroupDetails($package);
        $shipperHQRequest->cartType = $this->getCartType($package);

        //        if ($shipDetails = $this->getShipDetails($package)) {
        //            $shipperHQRequest->shipDetails($package);
        //        }
        //        if ($carrierGroupId = $this->getCarrierGroupId($package)) {
        //            $shipperHQRequest->setCarrierGroupId($package);
        //        }

        //        if ($carrierId = $this->getCarrierId($package)) {
        //            $shipperHQRequest->setCarrierId($carrierId);
        //        }

        $shipperHQRequest->siteDetails = ($this->getSiteDetails());
        $shipperHQRequest->credentials = ($this->getCredentials());

		return $shipperHQRequest;
    }

    /**
     * Return credentials for ShipperHQ login
     * @return \ShipperHQ\WS\Shared\Credentials
     */
    public function getCredentials()
    {
        $credentials = new \ShipperHQ\WS\Shared\Credentials();
        $credentials->apiKey = $this->_settings['api_key'];
        $credentials->password = $this->_settings['authentication_code'];
        return $credentials;
    }

	/**
	 * SHQ18-1158 Ensure credentials are entered before calling SHQ API
	 *
	 * @return bool
	 */
	private function hasCredentialsEntered ()
	{
		$credentials = $this->getCredentials();

		if (!empty($credentials->getApiKey()) && !empty($credentials->getPassword())) {
			return true;
		}

		return false;
	}

	/**
	 * Format cart for from shipper for Magento
	 *
	 * @param $package
	 *
	 * @return \ShipperHQ\WS\Rate\Request\Checkout\Cart
	 */
    public function getCartDetails($package)
    {
        $cartDetails = new \ShipperHQ\WS\Rate\Request\Checkout\Cart();
        $cartDetails->declaredValue = $package['contents_cost'];
        $cartDetails->items = $this->getFormattedItems($package['contents']);
        return $cartDetails;
    }

	/**
	 * Return site specific information
	 * @return \ShipperHQ\WS\Shared\SiteDetails
	 */
    public function getSiteDetails()
    {
        $siteDetails = new \ShipperHQ\WS\Shared\SiteDetails();
        $siteDetails->ecommerceCart = "Woocommerce";
        $siteDetails->ecommerceVersion = WC()->version;
        $siteDetails->websiteUrl = get_site_url();
        $siteDetails->environmentScope = "LIVE";
        $siteDetails->appVersion = $this->plugin_name_get_version();
        return $siteDetails;
    }

    private function plugin_name_get_version() {
        $plugin_data = get_plugin_data( WC_SHIPPERHQ_ROOT_FILE );
        $plugin_version = $plugin_data['Version'];
        return $plugin_version;
    }

	/**
	 * Get values for items
	 *
	 * @param      $items
	 * @param bool $useChild
	 *
	 * @return array
	 */
    private function getFormattedItems($items, $useChild = false)
    {
        $formattedItems = [];

        $counter = 0;

        foreach ( $items as $item_id => $product ) {
        	/*
        	 * SHQ18-1079 Skip over product add-ons.
        	 * We will get any additional prices lower down from the parent product
        	 */
            if (array_key_exists("addon_parent_id", $product)) {
            	continue;
			}

        	$productData  = $product['data'];

            $counter++;
            $warehouseDetails = $this->getWarehouseDetails($productData);
            $pickupLocationDetails = $this->getPickupLocationDetails($productData);
            // Check if product has variation. - isn't that the child item?
            // SHQ16-2339 remove use of get_variation_id
            if ($useChild &&  $productData->is_type( 'variation' ) ) {
                //support for v3.0 of Woo
                if(function_exists("wc_get_product")) {
                    $productA = wc_get_product($productData->get_id());
                } else {
                    $productA = new WC_Product($productData->get_id());
                }
            } else {
                //SHQ16-2202 - retrieve correct product id for parent and child
                $productId = $this->getProductId($productData, $useChild);
                //support for v3.0 of Woo
                if(function_exists("wc_get_product")) {
                    $productA = wc_get_product($productId);
                } else {
                    $productA = new WC_Product($productId);
                }
            }
            // Get SKU
            $sku = $productData->get_sku();

            $id = $counter;
            $productType = $productData->get_type() == 'variation' && !$useChild ? "configurable" : "simple";

            $weight = $productData->get_weight();
            if(is_null($weight)) {
                $weight = 0;
            }

            $qty = $product['quantity'] ? floatval($product['quantity']) : 0;
            if ($productData->get_type() == 'variation' && $useChild) {
                $qty = 1;
            }

			$addOnPrice = array_key_exists('addons', $product) ? $this->getAddonProductsPrice($product) : 0;

            $itemPrice = $productData->get_price() + $addOnPrice;
            $discountedPrice = $productData->get_price() + $addOnPrice;
            $currency = "USD";
            $formattedItem = new \ShipperHQ\WS\Rate\Request\Checkout\Item();

            $formattedItem->id = $id;
            $formattedItem->sku = $sku;
            $formattedItem->storePrice = $itemPrice;
            $formattedItem->weight = $weight;
            $formattedItem->qty = $qty;
            $formattedItem->type = $productType;
            $formattedItem->items = []; // child items
            $formattedItem->basePrice = $itemPrice;
            $formattedItem->taxInclBasePrice = $itemPrice;
            $formattedItem->taxInclStorePrice = $itemPrice;
            $formattedItem->rowTotal = $itemPrice*$qty;
            $formattedItem->baseRowTotal = $itemPrice*$qty;
            $formattedItem->discountPercent = 0; //TODO
            $formattedItem->discountedBasePrice = $discountedPrice;
            $formattedItem->discountedStorePrice = $discountedPrice;
            $formattedItem->discountedTaxInclBasePrice = $discountedPrice ;
            $formattedItem->discountedTaxInclStorePrice = $discountedPrice;
            $formattedItem->attributes = $this->populateAttributes($productData, $productA);
            $formattedItem->baseCurrency = $currency;
            $formattedItem->packageCurrency = $currency;
            $formattedItem->storeBaseCurrency = $currency;
            $formattedItem->storeCurrentCurrency = $currency;
            $formattedItem->taxPercentage = 0.00; // TODO;
            $formattedItem->freeShipping = false; // TODO
            $formattedItem->fixedPrice = false;
            $formattedItem->fixedWeight = false;
            $formattedItem->warehouseDetails            = $warehouseDetails;
            $formattedItem->pickupLocationDetails       = $pickupLocationDetails;

            if ($productData->get_type() == 'variation' && !$useChild) {
                $formattedItem->setItems($this->getFormattedItems(
                    array($item_id => $product), true));
            }

			// RIV-1302 Add hook to enable merchants to support custom products
            $formattedItems[] = apply_filters('shipperhq_formatted_item', $formattedItem, $product);
        }

        return $formattedItems;
    }

	/**
	 * Get values for destination
	 *
	 * @param $package
	 *
	 * @return \ShipperHQ\WS\Shared\Address
	 */
    private function getDestination($package)
    {
        $destination = new \ShipperHQ\WS\Shared\Address();

        $destination->city = $package[ 'destination' ][ 'city' ];
        $destination->country = $package[ 'destination' ][ 'country' ];
        $destination->region = $package[ 'destination' ][ 'state' ];
        $destination->street = $package[ 'destination' ][ 'address' ];
        $destination->zipcode = $package[ 'destination' ][ 'postcode' ];

        return $destination;
    }

    /**
     * Reads attributes from the item
     *
     * @param $productData
     * @param $product
     * @return array
     */
    protected function populateAttributes($productData, $product)
    {
        $attributes = [];
        if($product) {
            // SHQ16-2202 use the $product as this is the parent or child product for configurable
            $productId = $product->get_id();

            foreach (self::$_stdAttributeNames as $attributeName) {

                $internalAttributeName = "_".$attributeName;
                $customAttributeValue  = get_post_meta($productId, $internalAttributeName, true );

                if (!is_null($customAttributeValue) && !empty($customAttributeValue) &&
                    !strstr($customAttributeValue, 'NONE')) {
                    $customAttributeValue = str_replace(',' , '#', $customAttributeValue);
                    $attributes[] = [
                        'name' => $attributeName,
                        'value' => $customAttributeValue
                    ];
                }
            }
            // SHQ16-2202 pass in correct productId for parent/child correct retrieval
            $this->populateMetaData($productId, $attributes);
        }
        return $attributes;
    }

	/**
	 * Add values from ShipperHQ custom data
	 *
	 * @param $productId
	 * @param $attributes
	 */
    private function populateMetaData($productId, &$attributes)
    {
        foreach (self::$_customAttributeNames as $attributeName) {
            $customAttributeValue  = get_post_meta($productId, $attributeName, true );
            if (!is_null($customAttributeValue) && !empty($customAttributeValue) &&
                !strstr($customAttributeValue, 'NONE')) {

            	// SHQ18-1006
            	if (strtolower($attributeName) == "ship_separately" || strtolower($attributeName) == 'must_ship_freight') {
            		$customAttributeValue = strtolower($customAttributeValue) == 'yes';
				}

                $attributes[] = [
                    'name' => $attributeName,
                    'value' => $customAttributeValue
                ];
            }

        }
    }

    private function getProductId($product, $useChild)
    {
        // SHQ16-2202 retrieve correct product id for parent
        if ( $product->is_type( 'variation' ) && !$useChild) {
			$productId = method_exists($product, 'get_parent_id') ? $product->get_parent_id() : $product->parent_id;

            // SHQ16-2202 Older versions of woo do not support parent_id field and get_id() returns the variation id (child)
			if($productId === '') {
                $productId = $product->id;
            }
        } else {
            $productId = method_exists( $product, 'get_id' ) ? $product->get_id() : $product->id;
        }
        return $productId;
    }

	private function getAddonProductsPrice ($product)
	{
		$runningAddonPrice = 0;

		foreach ($product['addons'] as $addOn) {
			if (array_key_exists('price', $addOn)) {
				$runningAddonPrice += $addOn['price'];
			}
		}

		return $runningAddonPrice;
    }

    public function getWarehouseDetails($item)
    {
        return null;
    }

    public function getPickupLocationDetails($item)
    {
        return null;
    }

    public function getDefaultWarehouseStockDetail($item)
    {
        return null;
    }

    /**
     * Gets the magento order number
     * @param $order
     * @return mixed
     */
    protected function getMagentoOrderNumber($order)
    {
        return null;
    }

    /*
    * Return customer group details
    * Seems WC doesn't have the concept of customer groups yet
    */
    public function getCustomerGroupDetails($request)
    {
        $customerGroup = new \ShipperHQ\WS\Rate\Request\CustomerDetails();

        return $customerGroup;
    }

    /*
    * Return ship Details selected
     * TODO - Pickup
    *
    */
    public function getShipDetails($request)
    {
        return null;
    }

    /*
    * Return cartType String
    *
    */
    public function getCartType($request)
    {
        return "checkout";
    }

    /*
    * Return Delivery Date selected
    *
    */
    public function getDeliveryDateUTC($request)
    {
        return null;
    }

    public function getDeliveryDate($request)
    {
        return null;
    }

    /*
    * Return pickup location selected
    *
    */
    public function getLocation($request)
    {
        return null;

    }

    /*
     * Return selected carrierGroup id
     */
    public function getCarrierGroupId($request)
    {
        return null;

    }

    /*
   * Return selected carrier id
   *
   */
    public function getCarrierId($request)
    {
        return null;

    }
}
