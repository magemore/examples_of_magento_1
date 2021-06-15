<?php

class Magemore_Clearcvv_OrderController extends Mage_Adminhtml_Controller_Action
{
	public function clearCardAction() {
        $order_id = (int) $this->getRequest()->getParam('order_id');
		$r = Mage::getSingleton('core/resource');
		$db = $r->getConnection('core_write');
		// get quote_id from order.entity_id = order_payment.parent_id
		$q = $db->query("SELECT quote_id FROM ".$r->getTableName('sales_flat_order')
				." WHERE entity_id = $order_id");
		$quote_id = $q->fetchColumn();

		// clear CVV and card number. Instead it will display last 4 cc_last4
		$db->query("UPDATE ".$r->getTableName('sales_flat_order_payment')
				." SET cc_cid_enc = '', cc_number_enc='' WHERE parent_id = $order_id");
		if ($quote_id) {
			$db->query("UPDATE ".$r->getTableName('sales_flat_quote_payment')
				." SET cc_cid_enc = '', cc_number_enc='' WHERE quote_id = $quote_id");
		}
		// hide part of card number
		$this->_getSession()->addSuccess(Mage::helper('sales')->__('Card information was removed from order.'));
		Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl("adminhtml/sales_order/view", array('order_id'=>$order_id)));
	}

	public function clearAllCVVAction() {
		$this->loadLayout();
		$block = $this->getLayout()->createBlock('clearcvv/all');
		$this->getLayout()->getBlock('content')->append($block);
		$this->renderLayout();
	}

	public function doCLearAllCVVAction() {
		$r = Mage::getSingleton('core/resource');
		$db = $r->getConnection('core_write');
		// clear CVV and card number. Instead it will display last 4 cc_last4
		$db->query("UPDATE ".$r->getTableName('sales_flat_quote_payment')
				." SET cc_cid_enc = '', cc_number_enc=''");
		$db->query("UPDATE ".$r->getTableName('sales_flat_order_payment')
				." SET cc_cid_enc = '', cc_number_enc=''");
		$this->_getSession()->addSuccess(Mage::helper('sales')->__('Card information was removed from all orders.'));
		Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl("adminclearcvv/order/clearAllCVV"));		
	}
}
