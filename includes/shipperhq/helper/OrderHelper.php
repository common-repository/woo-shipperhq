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


class ShipperHQ_OrderHelper
{
    /** @var mixed */
    private $orderId;

    /** @var WC_Order */
    private $order;

    /** @var WC_Order_Item_Shipping */
    private $selectedShippingMethod;

    /** @var array */
    private $shippingMetaData;

    /**
     * ShipperHQ_OrderHelper constructor.
     * @param mixed|null $orderId
     * @param WC_Order|null $order
     */
    public function __construct($orderId = null, $order = null)
    {
        if ($order) {
            $this->setOrder($order);
            return;
        } elseif ($orderId) {
            $this->setOrderId($orderId);
            return;
        }
    }

    /**
     * @param mixed $orderId
     * @return $this
     */
    public function setOrderId($orderId) {
        $this->orderId = $orderId;
        $this->order = wc_get_order($orderId);
        $this->resetMemoizedData();
        return $this;
    }

    /**
     * @param WC_Order $order
     * @return $this
     */
    public function setOrder($order) {
        $this->order = $order;
        $this->orderId = $order->get_id();
        $this->resetMemoizedData();
        return $this;
    }

    /**
     * @return bool
     */
    public function shippingMethodIsUship()
    {
        // SHQ18-2977 - Virtual product only shipments will not have a shipping line item
        if (!$this->getSelectedShippingMethod()) {
            return false;
        }

        $type = $this->extractShippingMetaDataByKey('carrier_type');

        return stripos($type, 'uship') !== false;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function extractShippingMetaDataByKey($key) {
        return $this->extractMetaDataByKey($this->getShippingMetaData(), $key);
    }

    /**
     * @return mixed|WC_Order_Item_Shipping
     */
    public function getSelectedShippingMethod()
    {
        if (!$this->selectedShippingMethod) {
            $shippingMethods = $this->order->get_shipping_methods();
            $this->selectedShippingMethod = array_shift($shippingMethods);
        }
        return $this->selectedShippingMethod;
    }

    /**
     *
     */
    private function resetMemoizedData() {
        $this->selectedShippingMethod = null;
        $this->shippingMetaData = null;
    }

    /**
     * @return array
     */
    private function getShippingMetaData()
    {
        if (!$this->shippingMetaData) {
            $this->shippingMetaData = $this->getSelectedShippingMethod()->get_meta_data();
        }
        return $this->shippingMetaData;
    }

    /**
     * @param array $methodMetaData
     * @param string $key
     * @return mixed
     */
    private function extractMetaDataByKey($methodMetaData, $key)
    {
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

}
