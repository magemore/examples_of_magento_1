<?php


class Magemore_Tragento_Adminhtml_Trademe_Listing_ProductAddController
    extends Magemore_Tragento_Controller_Adminhtml_BaseController
{
    //#############################################

    protected  $sessionKey = 'trademe_listing_product_add';

    //#############################################

    protected function _initAction()
    {
       
        $this->loadLayout();

        $this->getLayout()->getBlock('head')
            ->addCss('M2ePro/css/Plugin/ProgressBar.css')
            ->addCss('M2ePro/css/Plugin/AreaWrapper.css')
            ->addJs('M2ePro/Plugin/ProgressBar.js')
            ->addJs('M2ePro/Plugin/AreaWrapper.js')

            ->addJs('mage/adminhtml/rules.js')
            ->addJs('M2ePro/Ebay/Listing/ProductAddHandler.js')
            ->addJs('M2ePro/Listing/ProductGridHandler.js')

            ->addJs('M2ePro/ActionHandler.js')
            ->addJs('M2ePro/Listing/ActionHandler.js')
            ->addJs('M2ePro/GridHandler.js')
            ->addJs('M2ePro/Listing/GridHandler.js')
            ->addJs('M2ePro/Ebay/Listing/ViewGridHandler.js')
            ->addJs('M2ePro/Ebay/Listing/Settings/GridHandler.js')
            ->addJs('M2ePro/Ebay/Listing/ProductAdd/Settings/GridHandler.js')

            ->addJs('M2ePro/AttributeSetHandler.js')
            ->addJs('M2ePro/TemplateHandler.js')
            ->addJs('M2ePro/Ebay/Listing/Template/SwitcherHandler.js')
            ->addJs('M2ePro/Ebay/Template/PaymentHandler.js')
            ->addJs('M2ePro/Ebay/Template/ReturnHandler.js')
            ->addJs('M2ePro/Ebay/Template/ShippingHandler.js')
            ->addJs('M2ePro/Ebay/Template/SellingFormatHandler.js')
            ->addJs('M2ePro/Ebay/Template/DescriptionHandler.js')
            ->addJs('M2ePro/Ebay/Template/SynchronizationHandler.js')

            ->addJs('M2ePro/Listing/Category/TreeHandler.js')
            ->addJs('M2ePro/Ebay/Listing/AutoActionHandler.js')
            ->addJs('M2ePro/Ebay/Listing/Category/ChooserHandler.js')
            ->addJs('M2ePro/Ebay/Listing/Category/SpecificHandler.js')
            ->addJs('M2ePro/Ebay/Listing/Category/Chooser/BrowseHandler.js')
        ;

        $this->getLayout()->getBlock('head')->setCanLoadExtJs(true);

        return $this;
    }

    protected function _isAllowed()
    {
        // just same permissions for trademe as for ebay
        // maybe rewrite in future
        return Mage::getSingleton('admin/session')->isAllowed('m2epro_ebay/listings');
    }

    //#############################################
    
    private function addProductsToListing($ids) {
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $product = Mage::getModel('catalog/product');
        foreach ($ids as $id) {
            $product->load($id);
            $sku = $product->getSku();
            $query = "INSERT INTO tragento_product (product_id,sku,action_time) 
                VALUES (:product_id,:sku,NOW())
                ON DUPLICATE KEY UPDATE action_time=NOW()";
            $write->query($query,array('product_id'=>$id,'sku'=>$sku));
        }
    }
    
    public function addAction() {
        $products = $this->getRequest()->getParam('products');
        if ($products) {
            $ids = explode(',',$products);
            $this->addProductsToListing($ids);
        }
        echo 'success';
    }

    public function indexAction()
    {
        // Set rule model
        // ---------------------------
        $this->setRuleData('trademe_product_add_step_one');
        // ---------------------------

        if ($this->getRequest()->isXmlHttpRequest()) {
            return $this->getResponse()->setBody(
                $this->loadLayout()
                     ->getLayout()
                     ->createBlock('tragento/adminhtml_trademe_listing_product_source_grid')
                     ->toHtml()
            );
        }

        $this->_initAction();

        $this->_title(Mage::helper('M2ePro')->__('Select Products'))
             ->_addContent($this->getLayout()->createBlock('tragento/adminhtml_trademe_listing_product'))
             ->renderLayout();
    }

    protected function setRuleData($prefix)
    {

        $storeId = 0;
        $prefix .= '';
        Mage::helper('M2ePro/Data_Global')->setValue('rule_prefix', $prefix);

        $ruleModel = Mage::getModel('M2ePro/Magento_Product_Rule')->setData(
            array(
                'prefix' => $prefix,
                'store_id' => $storeId,
            )
        );

        $ruleParam = $this->getRequest()->getPost('rule');
        if (!empty($ruleParam)) {
            Mage::helper('M2ePro/Data_Session')->setValue(
                $prefix, $ruleModel->getSerializedFromPost($this->getRequest()->getPost())
            );
        } elseif (!is_null($ruleParam)) {
            Mage::helper('M2ePro/Data_Session')->setValue($prefix, array());
        }

        $sessionRuleData = Mage::helper('M2ePro/Data_Session')->getValue($prefix);
        if (!empty($sessionRuleData)) {
            $ruleModel->loadFromSerialized($sessionRuleData);
        }

        Mage::helper('M2ePro/Data_Global')->setValue('rule_model', $ruleModel);
    }


    //#############################################

    protected function setSessionValue($key, $value)
    {
        $sessionData = $this->getSessionValue();
        $sessionData[$key] = $value;

        Mage::helper('M2ePro/Data_Session')->setValue($this->sessionKey, $sessionData);

        return $this;
    }

    protected function getSessionValue($key = NULL)
    {
        $sessionData = Mage::helper('M2ePro/Data_Session')->getValue($this->sessionKey);

        if (is_null($sessionData)) {
            $sessionData = array();
        }

        if (is_null($key)) {
            return $sessionData;
        }

        return isset($sessionData[$key]) ? $sessionData[$key] : NULL;
    }

}
