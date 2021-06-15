<?php

/**
 * Tragento settings main observer 
 *
 * @category    Magemore
 * @package     Magemore_Tragento
 */
class Magemore_Tragento_Model_Observer
{
    /**
     * Save system config event 
     *
     * @param Varien_Object $observer
     */
    public function saveSystemConfig($observer)
    {
        // check git logs for example or other observers with same method
        // it maybe needed if process complex post with different options
        return;
    }
}
