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


class ShipperHQ_RestHelper
{
    private $_testUrl = "http://www.localhost.com:8080/shipperhq-ws/v1/";
    private $_liveUrl = "http://api.shipperhq.com/v1/";

    private $_sandboxMode = false;

    public function __construct($sandboxMode)
    {
        $this->_sandboxMode = $sandboxMode;
    }


        /**
     * Returns url to use - live if present, otherwise dev
     * @return array
     */
    protected function _getGatewayUrl()
    {
        return $this->_sandboxMode=="yes"  ? $this->_testUrl : $this->_liveUrl;
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
        return  $this->_getGatewayUrl().'rates';

    }

    /*
     * *Retrieve url for retrieving attributes
     */
    public function getAttributeGatewayUrl()
    {
        return $this->_getGatewayUrl().'attributes/get';
    }

    /*
     * *Retrieve url for retrieving attributes
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

        if (self::$wsTimeout==NULL) {
            $timeout =  $this->getConfigValue('carriers/shipper/ws_timeout');
            if(!is_numeric($timeout) || $timeout < 120) {
                $timeout = 120;
            }
            self::$wsTimeout = $timeout;
        }
        return self::$wsTimeout;
    }




}
