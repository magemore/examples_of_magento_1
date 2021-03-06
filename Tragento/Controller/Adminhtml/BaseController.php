<?php

abstract class Magemore_Tragento_Controller_Adminhtml_BaseController
    extends Mage_Adminhtml_Controller_Action
{
    protected $generalBlockWasAppended = false;

    //#############################################

    public function indexAction()
    {
        echo 'page not exist. contact alex@magemore.com for support';
        //$this->_redirect(Mage::helper('M2ePro/Module_Support')->getPageRoute());
    }

    //#############################################

    public function preDispatch()
    {
        parent::preDispatch();

        // client was logged out
        if ($this->getRequest()->isXmlHttpRequest() &&
            !Mage::getSingleton('admin/session')->isLoggedIn()) {

            exit(json_encode( array(
                'ajaxExpired' => 1,
                'ajaxRedirect' => $this->_getRefererUrl()
            )));
        }

        // flag controller loaded
        if (is_null(Mage::helper('M2ePro/Data_Global')->getValue('is_tragento_base_controller_loaded'))) {
            Mage::helper('M2ePro/Data_Global')->setValue('is_tragento_base_controller_loaded',true);
        }

        return $this;
    }

    public function dispatch($action)
    {
        try {

            Mage::helper('M2ePro/Module_Exception')->setFatalErrorHandler();
            parent::dispatch($action);

        } catch (Exception $exception) {

            if ($this->getRequest()->getControllerName() ==
                Mage::helper('M2ePro/Module_Support')->getPageControllerName()) {
                exit($exception->getMessage());
            } else {

                if (Mage::helper('M2ePro/Magento')->isDeveloper()) {
                    throw $exception;
                } else {

                    Mage::helper('M2ePro/Module_Exception')->process($exception);

                    if (($this->getRequest()->isGet() || $this->getRequest()->isPost()) &&
                        !$this->getRequest()->isXmlHttpRequest()) {

                        $this->_getSession()->addError(
                            Mage::helper('M2ePro/Module_Exception')->getUserMessage($exception)
                        );

                        $params = array(
                            'error' => 'true'
                        );

                        if (!is_null(Mage::helper('M2ePro/View')->getCurrentView())) {
                            $params['referrer'] = Mage::helper('M2ePro/View')->getCurrentView();
                        }

                        $this->_redirect(Mage::helper('M2ePro/Module_Support')->getPageRoute(), $params);
                    } else {
                        exit($exception->getMessage());
                    }
                }
            }
        }
    }

    //#############################################

    public function loadLayout($ids=null, $generateBlocks=true, $generateXml=true)
    {
        $customLayout = 'tragento';
        is_array($ids) ? $ids[] = $customLayout : $ids = array('default',$customLayout);
        return parent::loadLayout($ids, $generateBlocks, $generateXml);
    }

    //---------------------------------------------

    protected function _addLeft(Mage_Core_Block_Abstract $block)
    {
        $this->appendGeneralBlock($this->getLayout()->getBlock('left'));
        $this->beforeAddLeftEvent();
        return $this->addLeft($block);
    }

    protected function _addContent(Mage_Core_Block_Abstract $block)
    {
        $this->appendGeneralBlock($this->getLayout()->getBlock('content'));
        $this->beforeAddContentEvent();
        return $this->addContent($block);
    }

    //---------------------------------------------

    protected function beforeAddLeftEvent() {}

    protected function beforeAddContentEvent() {}

    //#############################################

    public function getSession()
    {
        return $this->_getSession();
    }

    protected function getRequestIds()
    {
        $id = $this->getRequest()->getParam('id');
        $ids = $this->getRequest()->getParam('ids');

        if (is_null($id) && is_null($ids)) {
            return array();
        }

        $requestIds = array();

        if (!is_null($ids)) {
            if (is_string($ids)) {
                $ids = explode(',', $ids);
            }
            $requestIds = (array)$ids;
        }

        if (!is_null($id)) {
            $requestIds[] = $id;
        }

        return array_filter($requestIds);
    }

    //#############################################

    protected function appendGeneralBlock(Mage_Core_Block_Abstract $block)
    {
        if ($this->generalBlockWasAppended) {
            return;
        }

        $generalBlockPath = Ess_M2ePro_Helper_View::GENERAL_BLOCK_PATH;
        $blockGeneral = $this->getLayout()->createBlock($generalBlockPath);

        $block->append($blockGeneral);
        $this->generalBlockWasAppended = true;
    }

    protected function addLeft(Mage_Core_Block_Abstract $block)
    {
        return parent::_addLeft($block);
    }

    protected function addContent(Mage_Core_Block_Abstract $block)
    {
        return parent::_addContent($block);
    }

    //#############################################
}
