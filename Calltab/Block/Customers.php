<?php

class Magemore_Calltab_Block_Customers extends Mage_Adminhtml_Block_Widget_Grid_Container
{

    public function __construct()
    {
        $this->_blockGroup = 'calltab';
        $this->_controller = 'customers';
        $this->_headerText = Mage::helper('calltab')->__('Customers');
        // remove add button
        //$this->_addButtonLabel = Mage::helper('calltab')->__('Add New Customer');
        
        parent::__construct();
        $this->setTemplate('calltab/customers.phtml');
        $this->_removeButton('add');

    }

    /**
     * Check whether it is single store mode
     *
     * @return bool
     */
    public function isSingleStoreMode()
    {
        if (!Mage::app()->isSingleStoreMode()) {
            return false;
        }
        return true;
    }

}
