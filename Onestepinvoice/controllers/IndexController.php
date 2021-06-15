<?php
require_once MAGENTO_ROOT.'/app/code/core/Mage/Adminhtml/controllers/Sales/Order/InvoiceController.php';

class Magemore_Onestepinvoice_IndexController extends Mage_Adminhtml_Sales_Order_InvoiceController
{
    public function printinvoiceAction () {
    	$this->saveAction();
    	Mage::unregister('current_invoice');
    	// finds last order invoice and prints it
        $c = Mage::getModel("sales/order_invoice")->getCollection();
        $c->addFilter('order_id',$this->getRequest()->getParam('order_id'))
				->addOrder('entity_id')
       			->load();
        $invoice = $c->getFirstItem();
        if ($invoice) {
        	$invoice_id = $invoice->getEntityId();
        	$this->getRequest()->setParam('invoice_id',$invoice_id);
        	parent::printAction();
        }
        else {
        	Mage::getSingleton(‘core/session’)->addError('Can\'t make invoice');
        	Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl("adminhtml/sales_order/view", array('order_id'=>$this->getRequest()->getParam('order_id'))));
        }
    }

    protected function _redirect($path,$arguments = array()) {
        // ignore redirects
        //echo $path; exit();
    }
}
