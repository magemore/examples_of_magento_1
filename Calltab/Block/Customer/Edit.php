<?php

class Magemore_Calltab_Block_Customer_Edit extends Mage_Adminhtml_Block_Widget_Form_Container {

    public function __construct() {
        parent::__construct();
        $this->setId('calltab_customer_edit');
        $this->_removeButton('delete');
        $this->_removeButton('reset');
        $this->_updateButton('save', 'label', 'Capture Call');

        $url = $this->getUrl('*/sales_order_create/index/', array('customer_id' => $this->getRequest()->getParam('id')));

        $this->_addButton('order', array(
            'label'     => Mage::helper('calltab')->__('Sale (Create Order)'),
            'onclick'   => "setLocation('".$url."');",
            'class'     => 'scalable'
        ));
    }

     public function getHeaderText() {
         return $this->__('Capture Call');
     }
}
