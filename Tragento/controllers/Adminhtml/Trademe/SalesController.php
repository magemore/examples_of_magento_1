<?php

class Magemore_Tragento_Adminhtml_Trademe_SalesController
    extends Mage_Adminhtml_Controller_Action
{

    protected function _initAction() {
        $this->loadLayout();
        return $this;
    }

    public function indexAction() {
        $this->_initAction();
        $this->_title(Mage::helper('tragento')->__('TradeMe Sales'))
            ->_addContent($this->getLayout()->createBlock('tragento/adminhtml_trademe_sales'))
            ->renderLayout();
    }

    public function syncAction() {
        //echo 'sync';

        $m = Mage::getModel('tragento/trademe');
        $m->makeOrders();

        Mage::getSingleton('core/session')->addSuccess('Magento orders synced with Trademe');

        $this->_redirect('*/adminhtml_trademe_sales/');
    }

    public function createorderAction() {
        $id = (int)$this->getRequest()->getParam('purchase_id');
        if (!$id) {
            echo 'Error: Purchase # not provided'.PHP_EOL;
            return;
        }
        $m = Mage::getModel('tragento/trademe');
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $query = "SELECT * FROM `tragento_sales` WHERE order_id=0 AND purchase_id=$id LIMIT 1";
        $order = $db->fetchRow($query);
        //echo '<pre>'; print_r($order); exit();
        if ($order) {
            $m->makeOrder($order);
        }
        else {
            Mage::getSingleton('core/session')->addSuccess('Order already created');
            $this->redirectSales();
            return;
        }

        // check if purchase id have order id set
        $query = "SELECT * FROM `tragento_sales` WHERE purchase_id=$id LIMIT 1";
        $order = $db->fetchRow($query);
        if ($order['order_id']) {
            Mage::getSingleton('core/session')->addSuccess('Order Created');
        }
        else {
            $query = "SELECT message FROM `tragento_log` WHERE purchase_id=$id ORDER BY log_id DESC LIMIT 1";
            $error = $db->fetchOne($query);
            Mage::getSingleton('core/session')->addError('Create Order Error: '.$error);
        }

        $this->redirectSales();
    }

    private function redirectSales() {
        $url = Mage::helper('adminhtml')->getUrl('tragento/adminhtml_trademe_sales');
        Mage::app()->getResponse()->setRedirect($url)->sendResponse();
    }

    public function logsAction() {
        $this->_initAction();
        $this->_title(Mage::helper('tragento')->__('Tragento Logs'))
            ->_addContent($this->getLayout()->createBlock('tragento/adminhtml_trademe_logs'))
            ->renderLayout();
    }

}

