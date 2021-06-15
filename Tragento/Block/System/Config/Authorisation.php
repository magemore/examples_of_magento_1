<?php

/**
 * Magemore - Tragento
 */

/**
 * @category Magemore
 * @package Magemore_Tragento
 * @author Alexandr Martynov <alex@magemore.com>
 */
class Magemore_Tragento_Block_System_Config_Authorisation extends Magemore_Tragento_Block_System_Config_Abstractbutton  {
    public function getButtonData($buttonBlock) {
        $params = array(
            'website' => $buttonBlock->getRequest()->getParam('website')
        );
        $url = Mage::helper('adminhtml')->getUrl('tragento/adminhtml_trademe_connect/connect', $params);
        $data = array(
            'label'     => Mage::helper('adminhtml')->__('Begin Authorisation'),
            'onclick'   => 'setLocation(\''.$url.'\')',
            'class'     => '',
        );
        return $data;
    }
}