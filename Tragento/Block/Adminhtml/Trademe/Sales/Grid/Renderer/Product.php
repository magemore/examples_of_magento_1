<?php

/**
 * Product Renderer for Tragento Sales Grid
 *
 * @category    Magemore
 * @package     Magemore_Tragento
 * @author      Alexandr Martynov <alex@magemore.com>
 */
class Magemore_Tragento_Block_Adminhtml_Trademe_Sales_Grid_Renderer_Product extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract {

   /**
    * Renderer of link to product
    *
    * @param Varien_Object $row
    * @return string
    */
    public function render(Varien_Object $row) {
        $id = $row->getProductId();
        if (!$id) return '';
        $url = $this->getUrl('adminhtml/catalog_product/edit/', array('id' => $id));
        $str = '<a target="_blank" href="' . $url . '">' . $id . '</a>';
        return $str; 
    }

}
