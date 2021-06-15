<?php

/**
 * Customer Renderer for Tragento Sales Grid
 *
 * @category    Magemore
 * @package     Magemore_Tragento
 * @author      Alexandr Martynov <alex@magemore.com>
 */
class Magemore_Tragento_Block_Adminhtml_Trademe_Sales_Grid_Renderer_Customer extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract {

   /**
    * Renderer of link to customer
    *
    * @param Varien_Object $row
    * @return string
    */
    public function render(Varien_Object $row) {
        $id = $row->getCustomerId();
        if (!$id) return '';
        $url = $this->getUrl('adminhtml/customer/edit/', array('id' => $id));
        $str = '<a target="_blank" href="' . $url . '">' . $id . '</a>';
        return $str; 
    }

}
