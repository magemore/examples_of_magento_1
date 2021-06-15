<?php

/**
 * Order Renderer for Tragento Sales Grid
 *
 * @category    Magemore
 * @package     Magemore_Tragento
 * @author      Alexandr Martynov <alex@magemore.com>
 */
class Magemore_Tragento_Block_Adminhtml_Trademe_Sales_Grid_Renderer_Ordercreate extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract {

   /**
    * Renderer of link to order
    *
    * @param Varien_Object $row
    * @return string
    */
    public function render(Varien_Object $row) {
        $id = $row->getOrderId();
        if (!$id) {
            // show create order button
            $pid = $row->getPurchaseId();
            $url = Mage::helper('adminhtml')->getUrl('tragento/adminhtml_trademe_sales/createorder/',array('purchase_id'=>$pid));
            $s = '<button style="display:none" id="createOrder'.$pid.'" onclick="window.location=\''.$url.'\'"><span><span><span>Create Order</span></span></span></button>';
            // it's possible to make same thing with css :hower. hovewer at the moment was easier with js
            $s .= "<script>$('createOrder{$pid}').up().up().observe('mouseover', function() { $('createOrder{$pid}').show(); })</script>";
            $s .= "<script>$('createOrder{$pid}').up().up().observe('mouseout', function() { $('createOrder{$pid}').hide(); })</script>";
            return $s;
            //return '<pre>'.var_export($row->getData(),true).'</pre>';;
        }
        else {
            $url = $this->getUrl('adminhtml/sales_order/view/', array('order_id' => $id));
            $s = '<a target="_blank" href="' . $url . '">' . $id . '</a>';
            $order = Mage::getModel('sales/order')->load($id);
            $date = $order->getCreatedAt();
            $s .= '<br />'.$this->formatDate($date);
            return $s; 
        }
    }

}
