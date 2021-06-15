<?php

class Magemore_Calltab_Model_Customer extends Mage_Core_Model_Abstract {

    protected function _construct() {
        $this->_init('calltab/customer');
        parent::_construct();
    }

    public function loadPost($data) {
        // template to set model data from post
    }

}
