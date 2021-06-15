<?php

class Magemore_Bulkadd_IndexController extends Mage_Core_Controller_Front_Action {
    public function indexAction() {
        $qty = $this->getRequest()->getParam('qty');
        // handle action add to cart
        if ($qty) {
            $wrong = array();
            $wrong_qty = array();
            $sku = $this->getRequest()->getParam('sku');
            $added = array();
            foreach ($sku as $i => $s) {
                // ignore without qty or sku
                if ($qty[$i]<1 || trim($sku[$i])=='') {
                    unset($qty[$i]);
                    continue;
                }
                $p = $this->addToCart($sku[$i],$qty[$i]);
                if ($p == 'wrong') {
                    $wrong[$i]=$s;
                    $wrong_qty[$i]=$qty[$i];
                }
                else {
                    $a = array('sku'=>$sku[$i],'qty'=>$qty[$i],'name'=>$p);
                    $added[] = $a;
                }
            }
            Mage::register('bulkadd_added',$added);
            Mage::register('bulkadd_wrong',$wrong);
            Mage::register('bulkadd_wrong_qty',$wrong_qty);
        }
        $this->loadLayout();
        $this->renderLayout();
    }
    
    private function addToCart($sku,$qty) {
        try {
            $sku = trim($sku);
            $qty = intval($qty);
            if ($qty<1) return false;
            if (!$sku) return false;
            $product_model = Mage::getModel('catalog/product');
            $id = $product_model->getIdBySku($sku);
            $product = $product_model->load($id);
            $cart = Mage::getSingleton('checkout/cart');
            $cart->init();
            $cart->addProduct($product,array('qty'=>$qty));
            $cart->save();
            Mage::getSingleton('checkout/session')->setCartWasUpdated(true);
            return $product->getName();
       }
       catch (Exception $e) {
            return 'wrong';
       }
       return true;
    }
}
