<?php

class Magemore_Calltab_Block_Previouscalls extends Mage_Adminhtml_Block_Widget_Grid_Container
{

    public function __construct()
    {
        $this->_blockGroup = 'calltab';
        $this->_controller = 'previouscalls';
        $this->_headerText = Mage::helper('calltab')->__('Previous calls');
        parent::__construct();
        $this->removeButton('add');

    }

}
