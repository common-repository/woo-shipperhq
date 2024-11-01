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


use ShipperHQ\GraphQL\Client\PostorderClient;
use ShipperHQ\GraphQL\Request\SecureHeaders;
use ShipperHQ\GraphQL\Response\CreateListing;
use ShipperHQ\GraphQL\Response\Data\CreateListingData;
use ShipperHQ\GraphQL\Types\Input\ListingInfo;

if (!defined('ABSPATH')) exit;

if (class_exists('ShipperHQ_Listing_Manager')) return; // Exit if class already exists


class ShipperHQ_Listing_Manager
{

    /**
     * ShipperHQ_Listing_Manager constructor.
     */
    public function __construct()
    {
        $this->include_libs();
    }

    /**
     * @param $orderId
     * @param null|array $items
     * @param null|mixed $rate
     * @return bool|string
     */
    public function create_listing_for_order($orderId, $items = null, $rate = null)
    {
        $loggerInstance = new WC_Logger();

        // Get auth token
        $listingAuth = ShipperHQ_Listing_Authentication_Helper::getInstance($loggerInstance);
        $authToken = $listingAuth->getAuthToken();

        // Map order to request
        $listingRequestBuilder = new ShipperHQ_CreateListing_Request_Builder($orderId, $loggerInstance);
        $request = $listingRequestBuilder->forItems($items)
            ->forRate($rate)
            ->build();

        if ($request === false) {
            $loggerInstance->add('ShipperHQ', "Cannot create listing for order $orderId. Most likely the customer checked out with a non-SHQ rate");
            return false;
        }

        // Build Headers
        $headers = new SecureHeaders($authToken, "LIVE", "None");

        // Send request
        $response = $this->sendCreateListingRequest($orderId, $request, $headers, $loggerInstance);

        return $this->handleCreateListingResponse($orderId, $response, $loggerInstance);
    }

    /**
     * Converts returned status code to text for end user
     *
     * @param $statusCode
     *
     * @return mixed|string
     */
    private function convertStatusCodeToText($statusCode)
    {
        $statusText = "Unknown";

        $codes = [
            0 => "Unsuccessful",
            1 => "Successful"
        ];

        if (array_key_exists($statusCode, $codes)) {
            $statusText = $codes[$statusCode];
        }

        return $statusText;
    }

    /**
     * Include relevant classes
     */
    private function include_libs()
    {
        $this_dir = plugin_dir_path(__FILE__);
        $shq_dir = preg_replace("/(woo(commerce)?-shipperhq)\/.*$/i", "$1", $this_dir);

        try {
            require_once $this_dir . '/helper/Listing/Authentication.php';
            require_once $this_dir . '/helper/GraphQLHelper.php';
            require_once $this_dir . '/helper/Mapper.php';
            require_once $this_dir . '/Builder/CreateListing/Request.php';
            $this->registerJsonMapperLib($shq_dir);
            $this->registerJWTLib($shq_dir);
            $this->registerShipperHQLibs($shq_dir);
        } catch (Exception $e) {
            $logger = new WC_Logger();
            $logger->add('ShipperHQ', $e->getMessage());
        }

    }

    /**
     * @param $shq_dir
     * @throws Exception
     */
    private function registerJsonMapperLib($shq_dir)
    {
        spl_autoload_register(function ($classname) use ($shq_dir) {
            if (stripos($classname, 'jsonmapper') !== false) {
                $classToPathStub = str_replace('_', '/', $classname);
                $path = "$shq_dir/includes/external/lib/jsonmapper/src/$classToPathStub.php";
                if (!file_exists($path)) {
                    throw new Exception("Did not find class $classname at path $path");
                }

                require_once $path;
            }
        });
    }

    /**
     * @param $shq_dir
     * @throws Exception
     */
    private function registerJWTLib($shq_dir)
    {
        spl_autoload_register(function ($classname) use ($shq_dir) {
            if (stripos($classname, 'Lcobucci\\JWT') !== false) {
                $classToPathStub = str_replace('Lcobucci\\JWT\\', '', $classname);
                $classToPathStub = str_replace('\\', '/', $classToPathStub);
                $path = "$shq_dir/includes/external/lib/jwt/src/$classToPathStub.php";
                if (!file_exists($path)) {
                    throw new Exception("Did not find class $classname at path $path");
                }

                require_once $path;
            }
        }, true, true);
    }

    /**
     * @param $shq_dir
     * @throws Exception
     */
    private function registerShipperHQLibs($shq_dir)
    {
        spl_autoload_register(function ($classname) use ($shq_dir) {
            if (stripos($classname, 'ShipperHQ') !== false) {
                $replacements = [
                    'replace' => [
                        "/^ShipperHQ\\\\WS/i",
                        "/^ShipperHQ\\\\Lib/i",
                        "/^ShipperHQ\\\\GraphQL/i",
                    ],
                    'with' => [
                        "library-ws/src",
                        "library-shipper/src",
                        "library-graphql/src",
                    ]
                ];

                $classToPathStub = preg_replace($replacements['replace'], $replacements['with'], $classname);
                $classToPathStub = str_replace('\\', '/', $classToPathStub);
                $path = "$shq_dir/includes/external/lib/$classToPathStub.php";
                if (!file_exists($path)) {
                    throw new Exception("Did not find class $classname at path $path");
                }

                require_once $path;
            }
        }, true, true);
    }

    /**
     * @param $orderId
     * @param ListingInfo $request
     * @param SecureHeaders $headers
     * @param WC_Logger $loggerInstance
     * @return array|null
     */
    private function sendCreateListingRequest($orderId, $request, $headers, $loggerInstance)
    {
        $postorderClient = new PostorderClient();
        $response = null;
        $graphQLHelper = new ShipperHQ_GraphQLHelper();

        $loggerInstance->add('ShipperHQ', "Listing Request: " . print_r($request, true));

        try {
            $response = $postorderClient->createListing(
                $request,
                $graphQLHelper->getListingGatewayUrl(),
                $graphQLHelper->getWebserviceTimeout(),
                $headers,
                false
            );
        } catch (Exception $e) {
            $loggerInstance->add(
                'ShipperHQ',
                "Cannot create listing for order $orderId. Error reading response: " . $e->getMessage());
        }
        return $response;
    }

    /**
     * @param $orderId
     * @param $response
     * @param $loggerInstance
     */
    private function handleCreateListingResponse($orderId, $response, $loggerInstance)
    {
        if ($response != null) {

            if (array_key_exists('result', $response) && $response['result'] instanceof CreateListing) {
                /** @var CreateListingData $result */
                $result = $response['result']->getData();
                $status = 0;
                $listingId = false;

                if (count($result->getCreateListing()->getErrors()) > 0) {
                    foreach ($result->getCreateListing()->getErrors() as $error) {
                        $loggerInstance->add(
                            'ShipperHQ',
                            "Cannot create listing for order $orderId. Error returned from webservice:"
                            . $error->getInternalErrorMessage());
                    }
                } else {
                    $listingId = $result->getCreateListing()->getListingId();
                    $status = $result->getCreateListing()->getResponseSummary()->getStatus();
                }

                update_post_meta($orderId, "listing_status", $this->convertStatusCodeToText($status));
                update_post_meta($orderId, "listing_id", $listingId);

                return $listingId;
            }

            $loggerInstance->add('ShipperHQ', "Listing Response: " . print_r($response['debug'], true));
        }

        return false;
    }
}
