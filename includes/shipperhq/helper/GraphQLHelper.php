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


class ShipperHQ_GraphQLHelper
{
    private $_testUrl = "http://www.localhost.com:8080/shipperhq-ws/v2/";
    private $_liveUrl = "https://api.shipperhq.com/v2/";

    private $_testPostorderUrl = "http://www.localhost.com:8081/label-ws/v3/";
    private $_livePostorderUrl = "https://postapi.shipperhq.com/v3/";

    private $_sandboxMode = false;
    private $_wsTimeout;

    public function __construct()
    {
        $shipperHQSettings = get_option('woocommerce_shipperhq_settings');

        $this->_sandboxMode = array_key_exists('sandbox_mode', $shipperHQSettings) ? $shipperHQSettings['sandbox_mode'] : null;
        $this->_wsTimeout = array_key_exists('ws_timeout', $shipperHQSettings) ? $shipperHQSettings['ws_timeout'] : null;
    }

    /**
     * Returns url to use - live if present, otherwise test
     * @return String
     */
    protected function _getGatewayUrl()
    {
        return $this->_sandboxMode == "yes" ? $this->_testUrl : $this->_liveUrl;
    }

    /**
     * Returns url to use for postorder - live if present, otherwise test
     * @return String
     */
    protected function _getPostorderGatewayUrl()
    {
        return $this->_sandboxMode == "yes" ? $this->_testPostorderUrl : $this->_livePostorderUrl;
    }

    /**
     * Retrieve url for getting allowed methods
     * @return string
     */
    public function getAllowedMethodGatewayUrl()
    {
        return $this->_getGatewayUrl().'allowed_methods';
    }

    /**
     * Retrieve url for getting shipping rates
     * @return string
     */
    public function getRateGatewayUrl()
    {
        return  $this->_getGatewayUrl().'graphql';
    }

    /**
     * Retrieve url for creating listings
     * @return string
     */
    public function getPostorderGatewayUrl()
    {
        return $this->_getPostorderGatewayUrl().'graphql';
    }

    public function getListingGatewayUrl()
    {
        return $this->getPostorderGatewayUrl().'/label';
    }

    /*
     * Retrieve url for retrieving attributes
     * todo: is this correct?
     */
    public function getAttributeGatewayUrl()
    {
        return $this->_getGatewayUrl().'attributes/get';
    }

    /*
     * Retrieve url for retrieving attributes
     * todo: is this correct?
     */
    public function getCheckSynchronizedUrl()
    {
        return $this->_getGatewayUrl().'attributes/check';
    }

    /*
     * Retrieve configured timeout for webservice
     */
    public function getWebserviceTimeout()
    {
        if ($this->_wsTimeout == NULL) {
            $this->_wsTimeout = 30;
        }

        return $this->_wsTimeout;
    }
}
