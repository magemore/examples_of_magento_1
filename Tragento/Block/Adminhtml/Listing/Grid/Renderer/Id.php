<?php

/**
 * Product ID and link Renderer for Tragento Listing Grid
 *
 * @category    Magemore
 * @package     Magemore_Tragento
 * @author      Alexandr Martynov <alex@magemore.com>
 */
class Magemore_Tragento_Block_Adminhtml_Listing_Grid_Renderer_Id extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract {

   /**
    * Renderer of id and image to product
    *
    * @param Varien_Object $row
    * @return string
    */
    public function render(Varien_Object $row) {
        $id = $row->getProductId();
        if (!$id) return '';
        $url = $this->getUrl('adminhtml/catalog_product/edit/', array('id' => $id));
        $str = '<a target="_blank" href="'.$url.'">'.$id .'</a>';
        $product = Mage::getModel('catalog/product')->load($id);
        $img = '';
        try {
            $img = $this->helper('catalog/image')->init($product, 'small_image')->resize(80);
            $str.='<div><a target="_blank" href="'.$url.'"><img border=0 src="'.$img.'" /></a></div>';
        } catch (Exception $e) {
            // do nothing
            $str.='<div style="clear:both"><div style="height:80px; width:80px; background-color:#fff; text-align:center; float:right;">[no image]</div></div>';
        }
        //$img = $product->getImage();
        //$str = '<pre>'.var_export($row->getData(),true).'</pre>';
        return $str; 
    }

}
