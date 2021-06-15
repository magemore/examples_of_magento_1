<?php


class Magemore_Tragento_Block_Adminhtml_Trademe_Sales extends Mage_Adminhtml_Block_Widget_Grid_Container {

    public function __construct()
    {
        parent::__construct();

        // Initialization block
        //------------------------------
        $this->setId('trademeSales');
        $this->_blockGroup = 'tragento';
        $this->_controller = 'adminhtml_trademe_sales';
        //------------------------------

        // Set header text
        //------------------------------

        $this->_headerText = Mage::helper('tragento')->__('TradeMe Sales');
        //------------------------------

        // Set buttons actions
        //------------------------------
        $this->removeButton('back');
        $this->removeButton('reset');
        $this->removeButton('delete');
        $this->removeButton('add');
        $this->removeButton('save');
        $this->removeButton('edit');
        //------------------------------

        $url = $this->getUrl('*/adminhtml_trademe_sales/sync/');
        $this->_addButton('sync', array(
            'label'     => Mage::helper('tragento')->__('Sync'),
            'class'     => 'sync',
            'onclick'   => 'setLocation(\''.$url.'\')'
        ));
    }
}
