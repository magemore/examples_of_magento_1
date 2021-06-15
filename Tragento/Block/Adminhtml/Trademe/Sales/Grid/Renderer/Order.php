<?php

/**
 * Order Renderer for Tragento Sales Grid
 *
 * @category    Magemore
 * @package     Magemore_Tragento
 * @author      Alexandr Martynov <alex@magemore.com>
 */
class Magemore_Tragento_Block_Adminhtml_Trademe_Sales_Grid_Renderer_Order extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract {

   /**
    * Renderer of link to order
    *
    * @param Varien_Object $row
    * @return string
    */
    public function render(Varien_Object $row) {
        $id = $row->getOrderId();
        if (!$id) {
            return '';
            //return '<pre>'.var_export($row->getData(),true).'</pre>';;
        }
        else {
            $url = $this->getUrl('adminhtml/sales_order/view/', array('order_id' => $id));
            $str = '<a target="_blank" href="' . $url . '">' . $id . '</a>';
            return $str; 
        }
    }

}
