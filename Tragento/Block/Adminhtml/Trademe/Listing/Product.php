<?php


class Magemore_Tragento_Block_Adminhtml_Trademe_Listing_Product extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        parent::__construct();

        // Initialization block
        //------------------------------
        $this->setId('trademeListingProduct');
        $this->_blockGroup = 'tragento';
        $this->_controller = 'adminhtml_trademe_listing_product_source';
        //------------------------------

        // Set header text
        //------------------------------

        $this->_headerText = Mage::helper('tragento')->__('Select Products');
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

        $url = $this->getUrl('*/adminhtml_trademe_listing/');
        $this->_addButton('back', array(
            'label'     => Mage::helper('tragento')->__('Back'),
            'class'     => 'back',
            'onclick'   => 'setLocation(\''.$url.'\')'
        ));

        //------------------------------
        $this->_addButton('continue', array(
            'label'     => Mage::helper('M2ePro')->__('Add Products to TradeMe Listing'),
            'class'     => 'scalable next',
            'onclick'   => 'ListingProductAddHandlerObj.continue();'
        ));
        //------------------------------
    }

    public function getGridHtml()
    {
        // removed listing header. because it has just only 1 listing
        // can't remove... it's needed
        // removed :-) it was nice hack html of generated block . getGridHtml
        // if needed again check m2epro source for same block
        return parent::getGridHtml();
    }

    protected function _toHtml()
    {
        return '<div id="add_products_progress_bar"></div>' .
               '<div id="add_products_container">' .
               parent::_toHtml() .
               '</div>';
    }



    //#############################################
}
