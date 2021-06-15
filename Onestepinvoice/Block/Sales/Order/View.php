<?php

class Magemore_Onestepinvoice_Block_Sales_Order_View extends Magemore_Clearcvv_Block_Sales_Order_View {
	public function __construct() {
		parent::__construct();
		$this->_addButton('order_invoiceprint', array(
			'label'    => Mage::helper('sales')->__('Invoice & Print'),
			'onclick'  => 'setLocation(\'' . $this->getInvoicePrintUrl() . '\')',
		));
	}
	
	private function getInvoicePrintUrl() {
		$order = $this->getOrder();
		$order_id = $order->getId();
		return Mage::helper("adminhtml")->getUrl("adminonestepinvoice/index/printinvoice/",array("order_id"=>$order_id));
	}
}
