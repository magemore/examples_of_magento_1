<?php

/**
 * Product SKU and link Renderer for Tragento Listing Grid
 *
 * @category    Magemore
 * @package     Magemore_Tragento
 * @author      Alexandr Martynov <alex@magemore.com>
 */
class Magemore_Tragento_Block_Adminhtml_Listing_Grid_Renderer_Sku extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract {

   /**
    * Renderer of link to product
    *
    * @param Varien_Object $row
    * @return string
    */
    public function render(Varien_Object $row) {
        $id = $row->getProductId();
        if (!$id) return '';
        $sku = $row->getSku();
        $title = $row->getValue();
        $str = '<span>'.$title.'</span>';
        $url = $this->getUrl('adminhtml/catalog_product/edit/', array('id' => $id));
        $str.= '<div><strong>SKU: <a target="_blank" href="' . $url . '">' . $sku . '</a></strong></div>';
        return $str;
    }

}
