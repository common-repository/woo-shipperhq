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

if (!defined('ABSPATH')) exit;

if (class_exists('ShipperHQ_Shipping_Method')) return; // Exit if class already exists


class ShipperHQ_Shipping_Method extends WC_Shipping_Method
{

    protected $_shipperHQHelper;
    protected $_wsHelper;
    /** @var bool Whether logging is enabled */
    public static $log_enabled = false;

    /** @var WC_Logger Logger instance */
    public static $log = false;


    public function __construct()
    {
        $this->id = 'shipperhq';
        $this->method_title = __('ShipperHQ', 'woocommerce');
        $this->method_description = __('
                    ShipperHQ Official Plugin - The most advanced eCommerce shipping management platform in the world. <br /><br />
                    For documentation and examples, please see the <a href="http://docs.shipperhq.com" target="_blank">ShipperHQ knowledge base</a>.<br /><br />
                    If you have questions about ShipperHQ or need support, visit <a href="http://www.ShipperHQ.com" target="_blank">http://www.ShipperHQ.com</a>.<br />
 				', 'woocommerce');

        // Load the settings.
        $this->init_form_fields();

        $this->include_libs();
        // $this->validate_settings();

        $dateHelper = new \ShipperHQ\Lib\Helper\Date;
        $this->_shipperHQHelper = new \ShipperHQ\Lib\Rate\Helper($dateHelper);
        $this->_wsHelper = new ShipperHQ_RestHelper($this->get_option('sandbox_mode'));

        // Define user set variables
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->sanboxMode = $this->get_option('title');
        $this->debug = 'yes' === $this->get_option('debug', 'no');
        self::$log_enabled = $this->debug;

        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    public function process_admin_options()
    {
        $result = parent::process_admin_options();

        // TODO: Refactor me
        $api_key = $_REQUEST['woocommerce_shipperhq_api_key'];
        $auth_code = $_REQUEST['woocommerce_shipperhq_authentication_code'];

        $restHelper = new ShipperHQ_RestHelper($_REQUEST['debug']);
        $endpoint = $restHelper->getAttributeGatewayUrl();
        $credentials = new ShipperHQ\WS\Shared\Credentials($api_key, $auth_code);
        $siteDetails = (new ShipperHQ_Mapper())->getSiteDetails();
        $request = [
            'siteDetails' => $siteDetails,
            'credentials' => $credentials
        ];
        $args = [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($request),
        ];

        $response = wp_remote_post($endpoint, $args);
        $responseBodyStr = wp_remote_retrieve_body($response) ?: "{}";
        $responseBody = json_decode($responseBodyStr, true);

        $listingData = array_filter($responseBody['attributeTypes'], function($attr) {
            return $attr['code'] === 'carrier_listing_type';
        });
        $listingAttrs = array_shift($listingData)['attributes'];
        $listingSetting = array_reduce($listingAttrs, function ($carry, $item) {
            if ($carry === 'AUTO' || $item['createListing'] === 'AUTO') {
                return 'AUTO';
            } elseif ($carry === 'MANUAL' || $item['createListing'] === 'MANUAL') {
                return 'MANUAL';
            }
            return $carry;
        }, 'NONE');

        $this->settings['create_listing'] = $listingSetting;

        update_option('woocommerce_shipperhq_settings', $this->settings);

        return $result;
    }

    /**
     * Library functions
     *
     */
    private function include_libs()
    {
        $shq_dir = plugin_dir_path(__FILE__);
        require_once($shq_dir . 'helper/Mapper.php');
        require_once($shq_dir . 'helper/RestHelper.php');
        require_once($shq_dir . '/../external/lib/library-shipper/src/Rate/ConfigSettings.php');
        require_once($shq_dir . '/../external/lib/library-shipper/src/Rate/Helper.php');
        require_once($shq_dir . '/../external/lib/library-shipper/src/Helper/Date.php');
	    require_once($shq_dir . '/../external/lib/library-ws/src/Shared/BasicAddress.php');
	    require_once($shq_dir . '/../external/lib/library-ws/src/Shared/Address.php');
	    require_once($shq_dir . '/../external/lib/library-ws/src/Shared/Credentials.php');
        require_once($shq_dir . '/../external/lib/library-ws/src/Shared/SiteDetails.php');
        require_once($shq_dir . '/../external/lib/library-ws/src/Rate/Request/Checkout/Item.php');
        require_once($shq_dir . '/../external/lib/library-ws/src/Rate/Request/Checkout/Cart.php');
        require_once($shq_dir . '/../external/lib/library-ws/src/WebServiceRequestInterface.php');
        require_once($shq_dir . '/../external/lib/library-ws/src/AbstractWebServiceRequest.php');
        require_once($shq_dir . '/../external/lib/library-ws/src/Client/WebServiceClient.php');
        require_once($shq_dir . '/../external/lib/library-ws/src/Rate/Request/RateRequest.php');
        require_once($shq_dir . '/../external/lib/library-ws/src/Rate/Request/CustomerDetails.php');
    }

	/**
	 * @param $package
	 *
	 * @return void
	 */
    public function calculate_shipping($package = array())
    {

        // let's call out to ShipperHQ
        $initVal = microtime(true);

        // create the request
        $shq_request = $this->create_request($package);

        if ($shq_request == null) {
            self::log( 'ShipperHQ_Shipper Couldn\'t create request. Possibly missing API key or Authentication Code' .
                       " or required address fields are missing" );
            return;
        }

        // send the request
        $shq_results = $this->send_request($shq_request);

        // parse results
        $this->parse_shipping_results($shq_request, $shq_results);
        $elapsed = microtime(true) - $initVal;
        self::log('Shipperhq_Shipper Long lapse: ' . $elapsed);

    }

	private function is_valid_address($package): bool
	{
		$valid_address = true;

		$desintationCountry = $package['destination']['country'];
		$destinationState = $package['destination']['state'];
		$destinationPostcode = $package['destination']['postcode'];

		$requiredFieldsByCountry = wc()->countries->get_country_locale();

		// Must have a country specified for us to get rates
		if (empty($desintationCountry)) {
			$valid_address = false;
		} elseif ($this->get_option('ignore_empty_zip') == "yes" &&
		          array_key_exists($desintationCountry, $requiredFieldsByCountry)) {

			$requiredFields = $requiredFieldsByCountry[$desintationCountry];

			// Weirdly, the field required is only present if the field is not required.
			// It's always set to false if present. Added belt and braces check in case it is set to true in future
			if (array_key_exists("state", $requiredFields)
			    && (!array_key_exists("required", $requiredFields["state"])
			        || ($requiredFields["state"]["required"])) && empty($destinationState)) {
				$valid_address = false;
			} else if (array_key_exists("postcode", $requiredFields)
			           && (!array_key_exists("required", $requiredFields["postcode"])
			               || ($requiredFields["postcode"]["required"])) && empty($destinationPostcode)) {
				$valid_address = false;
			}
		}

		return $valid_address;
	}

    /**
     * Create the request to send ShipperHQ
     *
     * @param $package
     *
     * @return RateRequest|null
     */
    private function create_request($package)
    {
        if (!$this->is_valid_address($package)) {
            return null;
        }

        $shq_api = new ShipperHQ_Mapper();

        return $shq_api->create_request($package);
    }

	/**
	 * Sends the JSON request
	 *
	 * @param $shq_request
	 *
	 * @return array|null
	 */
    private function send_request($shq_request)
    {
        $timeout = "30";

        $initVal = microtime(true);
        $web_service_client = new \ShipperHQ\WS\Client\WebServiceClient();
        $result_set = $web_service_client->sendAndReceiveWp($shq_request, $this->_wsHelper->getRateGatewayUrl(), $timeout);
        $elapsed = microtime(true) - $initVal;
        self::log('Shipperhq_Shipper Short lapse: ' . $elapsed);

        if (!$result_set['result']) {
            return null;
        }

        self::log('Rate request and result: ');
        self::log($result_set['debug']);

        return $result_set['result'];
    }

    /**
     * Returns a list of origins for this shipment
     * @param $rate_details
     * @return string
     */
    private function getOriginFromCg($rate_details) {
        if(array_key_exists('carriergroup_detail', $rate_details)) {
            $cgDetail = $rate_details['carriergroup_detail'];
            if(!empty($cgDetail) && array_key_exists(0, $cgDetail) && is_array($cgDetail[0])) {
                $origins = [];
                foreach($cgDetail as $detail) {
                    $origins[] = $detail['name'];
                }

                return implode(', ', array_unique($origins));
            } else {
                return $cgDetail['name'];
            }
        }

        return '';
    }

    /**
     * Parse the results so can be output on frontend
     * @param $shq_request
     * @param $shipper_response
     */
    private function parse_shipping_results($shq_request, $shipper_response)
    {
        $debugRequest = $shq_request;

        $debugData = ['request' => $debugRequest, 'response' => $shipper_response];

        $shipper_response = $this->_shipperHQHelper->object_to_array($shipper_response);

        if (isset($shipper_response['carrierGroups'])) {
            // SHQ16-2350
            $transactionId = $this->_shipperHQHelper->extractTransactionId($shipper_response);
            $carrierRates = $this->processRatesResponse($shipper_response, $transactionId);
        } else {
            $carrierRates = [];
        }

        if (count($carrierRates) == 0) {
            self::log('WARNING: Shipper HQ did not return any carrier rates');
            self::log($debugData);
            return;
        }
        foreach ($carrierRates as $carrierRate) {
            if (isset($carrierRate['error'])) {
                self::log('Shipper HQ ' . $carrierRate['code'] . ' ' . $carrierRate['title'] . ' returned error ' . $carrierRate['error']['internalErrorMessage']);
                continue;
            }
            if (!array_key_exists('rates', $carrierRate)) {
                self::log('WARNING: Shipper HQ did not return any rates for ' . $carrierRate['code'] . ' ' . $carrierRate['title']);
            } else {
                foreach ($carrierRate['rates'] as $rateDetails) {
                    $rate = array(
                        'id' => $rateDetails['carrier_id'] . "_" . $rateDetails['methodcode'],
	                    // RIV-1247 Remove the estimated date text from method title
                        'label' => $this->getMethodLabel($rateDetails),
                        'cost' => $rateDetails['price'],
                        'meta_data' => [
                            'carrier_code' => $carrierRate['code'],
                            'carrier_id' => $rateDetails['carrier_id'],
                            'method_code' => $rateDetails['methodcode'],
                            'carrier_type' => $rateDetails['carrier_type'],
                            'origin' => $this->getOriginFromCg($rateDetails),
                            'carriergroup_detail' => $rateDetails['carriergroup_detail'],
	                        'method_description' => $rateDetails['method_description'] ?? "",
                        ]
                    );

                    if ($rateDetails['carriergroup_detail']['rate_cost']) {
                        $rate['meta_data']['nyp_amount'] = $rateDetails['carriergroup_detail']['rate_cost'];
                    }

                    $this->add_rate($rate);
                }
            }
        }
    }

	/**
	 * Returns a string containing the method title exclusive of any dates
	 *
	 * @param $rateDetails
	 *
	 * @return string
	 */
	private function getMethodLabel($rateDetails): string {
		if (empty($rateDetails['method_description'])) {
			return $rateDetails['method_title'];
		}

		return str_replace($rateDetails['method_description'], "", $rateDetails['method_title']);
	}

    /**
     * Build array of carrier group details for extractShipperHQMergedRates()
     * @param $carrierGroups
     * @param $transactionId
     * @param $configSettings
     * @return array
     */
    protected function populateSplitCarrierGroupDetail($carrierGroups, $transactionId, $configSettings) {
        $splitCarrierGroupDetail = [];

        foreach ($carrierGroups as $carrierGroup) {
            $carrierGroupDetail = $this->_shipperHQHelper->extractCarriergroupDetail($carrierGroup, $transactionId, $configSettings);
            foreach($carrierGroup['carrierRates'] as $carrierRate) {
                $carrierCode = $carrierRate['carrierCode'];
                foreach($carrierRate['rates'] as $rate) {
                    $methodCode = $rate['code'];
                    $splitCarrierGroupDetail[$carrierGroupDetail["carrierGroupId"]][$carrierCode][$methodCode] = $carrierGroupDetail;
                }
            }
        }

        return $splitCarrierGroupDetail;
    }
    /*
    *
    * Build array of rates based on split or merged rates display
    */
    protected function processRatesResponse($shipperResponse, $transactionId)
    {
        $ratesArray = [];

        $configSetttings = $this->getConfigSettings();
        $splitCarrierGroupDetail = [];

        // if merged response lets take that
        if (isset($shipperResponse['mergedRateResponse'])) {
            $splitCarrierGroupDetail = $this->populateSplitCarrierGroupDetail($shipperResponse['carrierGroups'], $transactionId, $configSetttings);

            $mergedRatesArray = [];
            foreach ($shipperResponse['mergedRateResponse']['carrierRates'] as $carrierRate) {
                $mergedResultWithRates = $this->_shipperHQHelper->extractShipperHQMergedRates($carrierRate,
                    $splitCarrierGroupDetail, $configSetttings, $transactionId);
                $mergedRatesArray[] = $mergedResultWithRates;
            }
            $ratesArray = $mergedRatesArray;
        } else {
            $carrierGroups = $shipperResponse['carrierGroups'];

            foreach ($carrierGroups as $carrierGroup) {
                $carrierGroupDetail = $this->_shipperHQHelper->extractCarriergroupDetail($carrierGroup, $transactionId, $configSetttings);

                // Pass off each carrier group to helper to decide best fit to process it.
                // Push result back into our array
                foreach ($carrierGroup['carrierRates'] as $carrierRate) {
                    $carrierResultWithRates = $this->_shipperHQHelper->extractShipperHQRates($carrierRate,
                        $carrierGroupDetail, $configSetttings, $splitCarrierGroupDetail);
                    $ratesArray[] = $carrierResultWithRates;
                }
            }
        }

        return $ratesArray;
    }


    protected function getLocaleInGlobals()
    {
        return 'en-US';
    }

    /**
     * Retrieve debug configuration
     * @return boolean
     */
    public function isTransactionIdEnabled()
    {
//        if (self::$showTransId == NULL) {
//            self::$showTransId = $this->getConfigValue('carriers/shipper/display_transaction');
//        }
        return false;

    }


    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable ShipperHQ', 'woocommerce'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Main Shipping Carrier Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('Name of the main shipping carrier, also used for carrier title if no rates can be
                        found. This is updated dynamically from ShipperHQ', 'woocommerce'),
                'default' => __('Shipping Rates', 'woocommerce'),
            ),
            'api_key' => array(
                'title' => __('API Key', 'woocommerce'),
                'type' => 'text',
                'description' => __('Obtain from under Websites in the ShipperHQ Dashboard'),
            ),
            'authentication_code' => array(
                'title' => __('Authentication Code', 'woocommerce'),
                'type' => 'password',
                'description' => __('Obtain from under Websites in the ShipperHQ Dashboard'),
            ),
            'hide_notify' => array(
                'title' => __('Carrier Notifications at Checkout', 'woocommerce'),
                'label' => __('Hide Notifications', 'woocommerce'),
                'type' => 'checkbox',
                'description' => __('Carriers may include notifications when their live rates have been modified.'),
                'default' => 'no'
            ),
            'ignore_empty_zip' => array(
	            'title' => __('Require Meaningful Address To Request Rates', 'woocommerce'),
	            'type' => 'checkbox',
				'description' => __(
					'Only request shipping rates from ShipperHQ if all of the required address fields in checkout are populated.
					This helps to lower the number of API requests'
				),
	            'label' => __('Will reduce number of requests made to ShipperHQ', 'woocommerce'),
	            'desc_tip'    => true,
	            'default' => 'yes'
            ),
            'sandbox_mode' => array(
                'title' => __('Use Sandbox', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Please leave Unchecked!', 'woocommerce'),
                'default' => 'no'
            ),
            'ws_timeout' => array(
                'title' => __('Connection Timeout (seconds)', 'woocommerce'),
                'type' => 'text',
                'description' => __('', 'woocommerce'),
                'default' => __('30', 'woocommerce'),
            ),
            'debug' => array(
                'title' => __('Debug Log', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable logging', 'woocommerce'),
                'default' => 'no',
                'description' => sprintf(__('Log ShipperHQ shipping rate requests and debug information'))
            ),
        );
    }

    /**
     *
     *
     * @param array $package
     * @return bool
     */
    public function is_available($package)
    {
        //TODO
        return parent::is_available($package);
    }

    /**
     * Logging method.
     * @param string $message
     */
    public static function log($message)
    {
        if (self::$log_enabled) {
            if (empty(self::$log)) {
                self::$log = new WC_Logger();
            }
            self::$log->add('ShipperHQ', print_r($message, true));
        }
    }


    /**
     * @return \ShipperHQ\Lib\Rate\ConfigSettings
     */
    protected function getConfigSettings()
    {
        $configSetttings = new \ShipperHQ\Lib\Rate\ConfigSettings($this->get_option('hide_notify'),
            $this->isTransactionIdEnabled(), $this->getLocaleInGlobals(), "shipperhq", $this->get_option('title'),
            wc_timezone_string());
        $configSetttings->hideNotifications = $this->get_option('hide_notify');
        $configSetttings->transactionIdEnabled = $this->isTransactionIdEnabled();
        $configSetttings->locale = $this->getLocaleInGlobals();
        return $configSetttings;
    }

}
