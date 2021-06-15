<?php


class Magemore_Tragento_Block_Adminhtml_Listing_Product_Rule extends Mage_Adminhtml_Block_Widget_Form
{
    // #################################################

    protected $_isShowHideProductsOption = false;

    // #################################################

    public function __construct()
    {
        parent::__construct();

        // Initialization block
        //------------------------------
        $this->setId('listingProductRule');
        //------------------------------

        $this->setTemplate('tragento/listing/product/rule.phtml');
    }

    // #################################################

    public function setShowHideProductsOption($isShow = true)
    {
        $this->_isShowHideProductsOption = $isShow;
        return $this;
    }

    public function isShowHideProductsOption()
    {
        return $this->_isShowHideProductsOption;
    }

    // #################################################

    protected function _prepareForm()
    {
        $form = new Varien_Data_Form(array(
            'id'      => 'rule_form',
            'action'  => '',
            'method'  => 'post',
            'enctype' => 'multipart/form-data'
        ));

        $this->setForm($form);

        return parent::_prepareForm();
    }

    protected function _beforeToHtml()
    {
        $ruleModel = Mage::helper('M2ePro/Data_Global')->getValue('rule_model');
        $ruleBlock = $this->getLayout()
                          ->createBlock('M2ePro/adminhtml_magento_product_rule')
                          ->setData(array('rule_model' => $ruleModel));
        $this->setChild('rule_block', $ruleBlock);

        return parent::_beforeToHtml();
    }

    // #################################################
}
