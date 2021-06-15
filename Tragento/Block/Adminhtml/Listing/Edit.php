<?php

class Magemore_Tragento_Block_Adminhtml_Listing_Edit extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        parent::__construct();
                 
        $this->_objectId = 'id';
        $this->_blockGroup = 'tragento';
        $this->_controller = 'adminhtml_listing';
        
        $this->_removeButton('save');
        $this->_removeButton('back');
        $this->_removeButton('reset');
        $this->_removeButton('delete');
        //$this->_removeButton('add');
        $this->_removeButton('new');

        $url = $this->getUrl('*/adminhtml_trademe_listing_productAdd/');
        $this->_updateButton('add', 'label', 'Add Products');
        $this->_updateButton('add', 'onclick', 'document.location.assign(\''.$url.'\');');

//        $this->_addButton('save_listing', array(
//            'label'     => Mage::helper('adminhtml')->__('Save'),
//            'onclick'   => 'saveListing()',
//            'class'     => 'save',
//        ), -100);
    }

    public function getHeaderText()
    {
        return Mage::helper('tragento')->__('TradeMe Products');
    }
}
