<?php

class Magemore_Calltab_Model_Mysql4_Customer_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract {
    protected function _construct() {
        $this->_init('calltab/customer');
    }

    /**
     * Add Filter by store
     *
     * @param int|Mage_Core_Model_Store $store
     * @return Mage_Cms_Model_Mysql4_Page_Collection
     */
    public function addStoreFilter($store) {
        if (! Mage::app()->isSingleStoreMode()) {
            if ($store instanceof Mage_Core_Model_Store) {
                $store = array($store->getId());
            }
            $this->addFieldToFilter('website_id', array('eq' => $store));
            return $this;
        }
        return $this;
    }

}
