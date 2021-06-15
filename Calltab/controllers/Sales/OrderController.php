<?php

// it can be used by calltab to display specially formated order for calltab

class Magemore_Calltab_Sales_OrderController extends Mage_Adminhtml_Controller_Action
{

    public function indexAction()
    {
        $this->_redirect('admincalltab/index/manager/');
    }


    public function viewAction() {
        // right now it just redirects to magento order
        $this->_redirect('adminhtml/sales_order/view',array('order_id'=>$this->getRequest()->getParam('order_id')));
    }


}
