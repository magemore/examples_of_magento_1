<?php

class Magemore_Clearcvv_Block_Sales_Order_View extends Mage_Adminhtml_Block_Sales_Order_View {
	public function __construct() {
		parent::__construct();
		$onclickJs = 'deleteConfirm(\''
			. Mage::helper('sales')->__('Are you sure you want to clear CVV for this order?')
			. '\', \'' . $this->getClearCvvUrl() . '\');';
		$this->_addButton('order_clearcvv', array(
			'label'    => Mage::helper('sales')->__('Clear CVV'),
			'onclick'  => $onclickJs,
			'class' => 'delete',
		),
            0,
            1000
        );
	}
	
	private function getClearCvvUrl() {
		$order = $this->getOrder();
		$order_id = $order->getId();
		return Mage::helper("adminhtml")->getUrl("adminclearcvv/order/clearCard/",array("order_id"=>$order_id));
	}
}
