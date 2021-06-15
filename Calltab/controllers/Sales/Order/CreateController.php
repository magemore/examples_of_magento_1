<?php

function extract_options($a) {
    $o = array();
    foreach ($a as $i => $d) {
        if (strpos($i,'options[')!==FALSE) {
            $s = explode('options[',$i);
            $k = end($s);
            $k = intval($k);
            $o[$k]=(int)$d;
        }
    }
    return $o;
}

require_once MAGENTO_ROOT.'/app/code/core/Mage/Adminhtml/controllers/Sales/Order/CreateController.php';
class Magemore_Calltab_Sales_Order_CreateController extends Mage_Adminhtml_Sales_Order_CreateController {


    private function _addLayoutJs() {
// lol. commenting this out solved create order problem for new version calltab.
// @todo: check one by one which file make error appear.
// @todo: is it jQuery no conflict?

        // this line of code create javascript error
        $this->getLayout()->getBlock('head')->addJs('jquery.js');


        $this->getLayout()->getBlock('head')
//            ->setCanLoadExtJs(true)
            ->addJs('jquery.js')
            ->addJs('calltab/grid.js')
            ->addJs('calltab/jquery.fancybox-1.3.4.js')
            ->addCss('calltab/jquery.fancybox-1.3.4.css');
    }


    protected function _initSession() {
        $customer_id = $this->getRequest()->getParam('customer_id');
        // check if need to clear old session
        $old_customer_id = $this->_getSession()->getCustomerId();
        if ($old_customer_id && $old_customer_id!=$customer_id) {
            $this->_getSession()->clear();
        }
        return parent::_initSession();
    }

    public function indexAction()
    {
        // get action name for layout route
        // echo strtolower($this->getFullActionName()); exit();
        // admincalltab_sales_order_create_index
        // http://www.costcutters.com.au/index.php/admincalltab/sales_order_create/index/customer_id/1/

        $this->_title($this->__('Sales'))->_title($this->__('Orders'))->_title($this->__('New Order'));
        $this->_initSession();
        $this->loadLayout();

        $this->_addLayoutJs();

        // $this->_setActiveMenu('sales/order')
        $this->renderLayout();
    }

    public function cartaddAction() {
        $customer_id = $this->getRequest()->getParam('customer_id');
        $_POST['customer_id'] = $customer_id;

        // check if request has customer id in it
        // @todo: store_id replace dummy with actual
        $_POST['store_id'] = 1;

        // convert array to fromat acceptable for _processActionData
        if (isset($_POST['product_data'])) {
            $a = json_decode($_POST['product_data'], true);
            $_POST['product'] = $a['product'];
            $_POST['options'] = extract_options($a);
            $q = (int)$a['qty'];
            if ($q<1) $q=1;
            $_POST['qty'] = $q;
        }
        // options
        $o=array();
        $o['qty']=$_POST['qty'];
        $o['options']=$_POST['options'];
        $_POST['item'][$_POST['product']]=$o;

        // _initSession required to make add to cart work. call before processData
        $this->_initSession();
        $this->_processData();
    }

}
