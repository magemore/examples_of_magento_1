<?php

class Magemore_Tragento_Model_Magento_Shipping
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{
    protected $_code = 'tragentoshipping';

    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        $result = Mage::getModel('shipping/rate_result');
        $method = Mage::getModel('shipping/rate_result_method');

        $method->setCarrier($this->_code);
        $method->setMethod($this->_code);

        $price = Mage::registry('tragentoShippingPrice');

        // Tragento Shipping
        $method->setCarrierTitle('Tragento Shipping');
        $method->setMethodTitle('Freight');

        $method->setCost($price);
        $method->setPrice($price);

        $result->append($method);

        return $result;
    }

    public function checkAvailableShipCountries(Mage_Shipping_Model_Rate_Request $request)
    {
        // maybe write some code to check if needed
        // always enabled for all countries
        return true;
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return array($this->_code => $this->getConfigData('name'));
    }

    /**
     * Check if carrier has shipping tracking option available
     *
     * @return boolean
     */
    public function isTrackingAvailable()
    {
        return false;
    }
}
