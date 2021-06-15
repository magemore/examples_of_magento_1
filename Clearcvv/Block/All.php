<?php

class Magemore_Clearcvv_Block_All extends Mage_Adminhtml_Block_Widget_Grid_Container {

	public function __construct()
    {
        $this->_controller = 'clearcvv';
        $this->_headerText = Mage::helper('core')->__('Clear All CVV');
        parent::__construct();
        $this->_removeButton('add');

        $message = Mage::helper('core')->__('Are you sure that you want to clear all cards cvv?');
        $this->_addButton('flush_system', array(
            'label'     => Mage::helper('core')->__('Clear All CVV'),
            'onclick'   => 'confirmSetLocation(\''.$message.'\', \'' . $this->getClearAllCvvUrl() .'\')',
            'class'     => 'delete',
        ));
    }

    private function getClearAllCvvUrl() {
		return Mage::helper("adminhtml")->getUrl("adminclearcvv/order/doClearAllCVV/");
	}

    protected function _prepareLayout() {
    	// do nothing. it doesn't have grid just title and buttons. don't want to use some special template
    }


	/* protected function _toHtml() {
		$html = 'Button Clear All CVV';
		return $html;
	} */
}