<?php

class Magemore_Calltab_Model_Mysql4_Customer extends Mage_Core_Model_Mysql4_Abstract {
    protected function _construct() {
        $this->_init('calltab/customer','customer_id');
    }
}
