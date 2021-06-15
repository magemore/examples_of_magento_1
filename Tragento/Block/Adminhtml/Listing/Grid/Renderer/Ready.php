<?php

/**
 * TradeMe Ready Renderer for Tragento Listing Grid
 *
 * @category    Magemore
 * @package     Magemore_Tragento
 * @author      Alexandr Martynov <alex@magemore.com>
 */
class Magemore_Tragento_Block_Adminhtml_Listing_Grid_Renderer_Ready extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract {

    /**
    * Renderer of trademe ready
    *
    * @param Varien_Object $row
    * @return string
    */
    public function render(Varien_Object $row) {
        $s = $row->getTrademeReady();
        if ($s=='ready') {
            return "<span style='color:green'>$s</span>";
        }
        return "<span style='color:red'>$s</span>";
    }

}
