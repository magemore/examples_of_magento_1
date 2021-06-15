<?php

class Magemore_Tragento_Adminhtml_Trademe_ConnectController
    extends Mage_Adminhtml_Controller_Action
{
    protected  $sessionKey = 'trademe_connect_index';

    private $trademe;
    private $environment;
    private $consumer_key;
    private $consumer_secret;
    private $callback_url;

    protected function _initAction()
    {
        $this->loadLayout();
        return $this;
    }

    protected function _isAllowed()
    {
        // just same permissions for trademe as for ebay
        // maybe rewrite in future
        return Mage::getSingleton('admin/session')->isAllowed('m2epro_ebay/listings');
    }

    public function indexAction()
    {
    }

    private function loadConfig() {
        // load data from magento configs for

        $this->environment = Mage::getStoreConfig('tragento/apiconnect/environment');
        $this->consumer_key = Mage::getStoreConfig('tragento/apiconnect/tragento-consumer-key');
        $this->consumer_secret = Mage::getStoreConfig('tragento/apiconnect/tragento-consumer-secret');
        $this->callback_url =  Mage::helper('adminhtml')->getUrl('tragento/adminhtml_trademe_connect/callback');

        if($this->environment == 'sandbox')
        {
            require_once('gogoTradeMe/gogoTradeMeSandbox.php');
            $this->trademe = new gogoTradeMeSandbox(
                $this->consumer_key,                          // Obtain from "My TradeMe"
                $this->consumer_secret,                       // Obtain from "My TradeMe"
                $this->callback_url                           // Handles the return from TradeMe
            );
        }
        else
        {
            require_once('gogoTradeMe/gogoTradeMe.php');
            $this->trademe = new gogoTradeMe(
                $this->consumer_key,                          // Obtain from "My TradeMe"
                $this->consumer_secret,                       // Obtain from "My TradeMe"
                $this->callback_url                           // Handles the return from TradeMe
            );
        }
    }

    private function store_token($Token)
    {
        Mage::getSingleton('core/session')->setTrademeToken($Token);
    }

    private function retrieve_token()
    {
        $token = Mage::getSingleton('core/session')->getTrademeToken();
        if(!isset($token['oauth_token_type']))
        {
            $msg = 'You have not stored a token, you probably need to hit connect';
            Mage::getSingleton('adminhtml/session')->addError($msg);
            return false;
        }
        return $token;
    }

    private function storeAccessToken($token) {
        $conf = new Mage_Core_Model_Config();
        $conf->saveConfig('tragento/apiconnect/accesstoken', serialize($token), 'default', 0);
    }

    public function connectAction() {
        $this->loadConfig();
        $RequestToken = $this->trademe->get_request_token();
        $this->store_token($RequestToken);
        $url = $this->trademe->get_authorize_url();
        Mage::app()->getResponse()->setRedirect($url)->sendResponse();
    }

    public function callbackAction()
    {
        $this->loadConfig();
        $RequestToken = $this->retrieve_token();
        if ($RequestToken) {
            $this->trademe->set_token($RequestToken);
            $oauth_verifier = $this->getRequest()->getParam('oauth_verifier');
            $AccessToken = $this->trademe->get_access_token($oauth_verifier);
            $this->storeAccessToken($AccessToken);
            Mage::getSingleton('core/session')->addSuccess('TradeMe OAuth successfully authorized');
        }
        $url = Mage::helper('adminhtml')->getUrl('adminhtml/system_config/edit/section/tragento');
        Mage::app()->getResponse()->setRedirect($url)->sendResponse();
    }
}
