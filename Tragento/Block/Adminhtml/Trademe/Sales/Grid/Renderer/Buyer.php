<?php

/**
 * Buyer Renderer for Tragento Sales Grid
 *
 * @category    Magemore
 * @package     Magemore_Tragento
 * @author      Alexandr Martynov <alex@magemore.com>
 */
class Magemore_Tragento_Block_Adminhtml_Trademe_Sales_Grid_Renderer_Buyer extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract {

   /**
    * Renderer of buyer info Buyer Nickname, Email, Phone
    *
    * @param Varien_Object $row
    * @return string
    */
    public function render(Varien_Object $row) {
        $s = $row->getBuyerNickname().'<br />';
        $s .= $row->getBuyerEmail().'<br />';
        // doesn't return buyer phone so use delivery instead
        $s .= $row->getDeliveryPhonenumber();
        return $s;
        //return '<pre>'.var_export($row->getData(),true).'</pre>';;
        // copied from product id renderer as referance
        //$id = $row->getProductId();
        //if (!$id) return '';
        //$url = $this->getUrl('adminhtml/catalog_product/edit/', array('id' => $id));
        //$str = '<a target="_blank" href="' . $url . '">' . $id . '</a>';
        //return $str; 
    }

}
