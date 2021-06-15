<?php

class Magemore_Calltab_Model_Observer_Adminhtml_Controller extends Varien_Object
{
    /**
     * Adds tab into admin interface to edit customer if logged in as administrator
     *
     * @return Magemore_Calltab_Model_Observer_Adminhtml_Controller
    */
    public function addCustomerManagerTab($observer) {
        $block = $observer->getEvent()->getBlock();
        $class = get_class($block);
        if ($class=='Mage_Adminhtml_Block_Customer_Edit_Tabs') {
            $content = $block->getLayout()
                ->createBlock('Magemore_Calltab_Block_Customer_Edit_Tab_Manager')->initForm()->toHtml();
            $block->addTab('managers', array(
                'label'     => 'Assign Calltab Manager',
                'content'   => $content
            ));
        }
        return $this;
    }


    public function placeOrder($observer) {
        // to make sure it works
        //file_put_contents('/tmp/place_order',time());

        $order = $observer->getEvent()->getOrder();

        // update last_purchase_date
        $date = $order->getUpdatedAt();
        $customer_id = $order->getCustomerId();
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $sq = 'UPDATE calltab_customer SET last_purchase_date=:date WHERE customer_id=:customer_id LIMIT 1';
        $db->query($sq,array('customer_id'=>$customer_id,'date'=>$date));

        $call_id = Mage::getSingleton('admin/session')->getCalltabCallId();
        if ($call_id) {
            $sq = 'UPDATE calltab_customer SET last_sales_call_date=:date WHERE customer_id=:customer_id LIMIT 1';
            $db->query($sq,array('customer_id'=>$customer_id,'date'=>$date));

            $manager_id = (int)$this->getManagerId();

            $sq = 'UPDATE calls SET order_id=:order_id, manager_id=:manager_id WHERE call_id=:call_id LIMIT 1';
            $db->query($sq,array('call_id'=>$call_id,'order_id'=>$order->getId(),'manager_id'=>$manager_id));
        }

        return $this;
    }

    private function _redirect($path, $arguments = array()) {
        $url = Mage::getUrl($path, $arguments);
        //file_put_contents('/tmp/redir',$url);
        $this->getResponse()->setRedirect($url)
            ->sendResponse();
        //header('Location: '.$url);
        exit();
        //return $this;
    }

    private function getManagerId() {
        return Mage::getModel('calltab/manager')->getAdminUserManagerId();
    }

    public function managerStartup($o) {
        $id = $this->getManagerId();
        if ($id) {
            $this->setResponse($o->getControllerAction()->getResponse());
            $this->_redirect('admincalltab/index/',array('filter'=>base64_encode('manager_id='.$id)));
        }
    }

    public function saveCustomer($observer) {
        $customer_id = $observer->getEvent()->getCustomer()->getEntityId();

        // it should first save manager. manager_id reindexed from customer calltab_manager_id
        $this->saveManager($customer_id);
        $this->reindexCalltabCustomer($customer_id);
        return $this;
    }


    public function deleteCustomer($observer) {
        $customer_id = (int)$observer->getEvent()->getCustomer()->getEntityId();

        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $sq = "DELETE FROM calltab_customer WHERE customer_id=$customer_id";
        $db->query($sq);

        return $this;
    }

    private function reindexCalltabCustomer($customer_id) {
        if (!$customer_id) return;
        $r = Mage::getSingleton('calltab/reindex');
        $r->reindexCustomer($customer_id);
    }

    private function saveManager($customer_id) {
        $p = Mage::app()->getRequest()->getParam('manager');
        if (isset($p['manager'])) {
            // manager id from select. assign manager tab
            $manager_id = (int)$p['manager'];
            $customer_id = (int)$customer_id;
            // maybe just id instead of customer_id param. because id inside url /id/1/
            $db = Mage::getSingleton('core/resource')->getConnection('core_write');
            $sq = "UPDATE `customer_entity` SET calltab_manager_id=$manager_id WHERE entity_id=$customer_id";
            $db->query($sq);
        }
    }

}
