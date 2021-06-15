<?php

class Magemore_Tragento_Model_System_Config_Source_Environment
{

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => 'sandbox', 'label'=>Mage::helper('adminhtml')->__('Sandbox')),
            array('value' => 'production', 'label'=>Mage::helper('adminhtml')->__('Production')),
        );
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            'sandbox' => Mage::helper('adminhtml')->__('Sandbox'),
            'production' => Mage::helper('adminhtml')->__('Production'),
        );
    }

}
 
