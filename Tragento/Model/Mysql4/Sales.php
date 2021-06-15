<?php

class Magemore_Tragento_Model_Mysql4_Sales extends Mage_Core_Model_Mysql4_Abstract {
    protected function _construct() {
        $this->_init('tragento/sales','purchase_id');
    }
}
