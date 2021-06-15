<?php

class Magemore_Calltab_Block_Previoussales extends Mage_Adminhtml_Block_Widget_Grid_Container
{

    public function __construct()
    {
        $this->_blockGroup = 'calltab';
        $this->_controller = 'previoussales';
        $this->_headerText = Mage::helper('calltab')->__('Previous sales');
        parent::__construct();
        $this->removeButton('add');

    }

}