<?php


class Magemore_Tragento_Adminhtml_Trademe_ListingController
    extends Mage_Adminhtml_Controller_Action
{

    protected  $sessionKey = 'trademe_listing_index';

    protected function _initAction()
    {
        $this->loadLayout();

        $this->getLayout()->getBlock('head')
            ->setCanLoadExtJs(true)
            ->addJs('M2ePro/General/PhpFunctions.js')
            ->addJs('M2ePro/General/CommonHandler.js')
            ->addJs('M2ePro/General/TranslatorHandler.js')
            ->addJs('M2ePro/General/PhpHandler.js')
            ->addJs('M2ePro/General/UrlHandler.js')
            ->addJs('M2ePro/Listing/ProductGridHandler.js')
            ->addJs('M2ePro/ActionHandler.js')
            ->addJs('M2ePro/Listing/ActionHandler.js')
            ->addJs('M2ePro/Listing/MovingHandler.js')
            ->addJs('M2ePro/GridHandler.js')
            ->addJs('M2ePro/Listing/GridHandler.js')
            ->addJs('M2ePro/Ebay/Listing/ViewGridHandler.js')
            ->addJs('M2ePro/Ebay/Listing/Ebay/GridHandler.js')
            ->addJs('M2ePro/Ebay/Listing/AutoActionHandler.js')
            ->addJs('tragento/ProductInListingGridHelpLog.js')
            ->addJs('jquery.js')
            // doesn't work properly...
            //->addJs('tragento/jquery.floatThead.js')
            ->addJs('tragento/queue.js')
            ->addCss('M2ePro/css/main.css')
            ->addCss('tragento/ProductInListingGrid.css')
            ->addCss('tragento/queue.css');

        return $this;
    }

    protected function _isAllowed()
    {
        // just same permissions for trademe as for ebay
        // maybe rewrite in future
        return Mage::getSingleton('admin/session')->isAllowed('m2epro_ebay/listings');
    }

    public function massStatusAction() {
        $p = Mage::getSingleton('Magemore_Tragento_Model_Product');
        $R = $this->getRequest();
        $ids = $R->getParam('massaction');

        // protect from double click. it happens for some reason. once in 100. hard to predict. once it delisted twice.
        // get param writing this way to make unique key. need to specify param name before yes word for selected param

//        $sess_key = md5(serialize($ids).'list'.$R->getParam('list').'delist'.$R->getParam('delist').'remove'.$R->getParam('remove').'revise'.$R->getParam('revise'));
//        $old_sess_key = Mage::getSingleton('adminhtml/session')->getTragentoMassactionSessKey();
//        if ($old_sess_key==$sess_key) {
//            // maybe also check time... if 5 min difference for example.
//            $msg = Mage::helper('adminhtml')->__('Trying to execute same request twice. It just ignored second request with same parameters. No need to add same task to queue twice. You can do it now if you want to.');
//            Mage::getSingleton('adminhtml/session')->addError($msg);
//            // empty key to allow next request
//            Mage::getSingleton('adminhtml/session')->setTragentoMassactionSessKey('');
//            $this->_redirect('*/*/');
//            return;
//        }
//        Mage::getSingleton('adminhtml/session')->setTragentoMassactionSessKey($sess_key);


        // actuall massaction
        if ($ids) {
            $msg_action = '';
            if ($R->getParam('list')=='yes') {
                $msg_action = 'list';
                $p->addQueueEvent('list',$ids);
            }
            if ($R->getParam('delist')=='yes') {
                $msg_action = 'delist';
                $p->addQueueEvent('delist',$ids);
            }
            if ($R->getParam('remove')=='yes') {
                $msg_action = 'remove';
                $p->addQueueEvent('remove',$ids);
                foreach ($ids as $id) $p->removeDBProduct($id);
            }
            if ($R->getParam('revise')=='yes') {
                $msg_action = 'revise';
                $p->addQueueEvent('revise',$ids);
            }
            $msg = Mage::helper('adminhtml')->__('Total of %d '.$msg_action.' task(s) were added to queue', count($ids));
            Mage::getSingleton('adminhtml/session')->addSuccess($msg);
        }
        $this->_redirect('*/*/');
    }

    public function indexAction()
    {
        if ($this->getRequest()->isXmlHttpRequest()) {
            $this->loadLayout();
            $response = $this->getLayout()->createBlock(new Magemore_Tragento_Block_Adminhtml_Listing_Grid())->toHtml();
            $response .= '<script>$("loading-mask").hide();</script>';
            echo $response;
            return;
        }

        $this->_initAction();
        $this->_title(Mage::helper('tragento')->__('TradeMe Products'))
            ->_addContent($this->getLayout()->createBlock('tragento/adminhtml_listing_edit'))
            ->renderLayout();
    }

    public function queueAction()
    {
        header('Content-type: text/plain');
        $queue = Mage::getSingleton('Magemore_Tragento_Model_Queue');
        echo $queue->getJSON();
    }


}
