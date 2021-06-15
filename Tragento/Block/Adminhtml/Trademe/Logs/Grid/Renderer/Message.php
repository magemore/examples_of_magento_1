<?php

/**
 * Log Message Renderer for Tragento Log Grid
 *
 * @category    Magemore
 * @package     Magemore_Tragento
 * @author      Alexandr Martynov <alex@magemore.com>
 */
class Magemore_Tragento_Block_Adminhtml_Trademe_Logs_Grid_Renderer_Message extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract {


    private function shortErrorMessage($s) {
        $a = explode("\n",$s);
        if (isset($a[0])) return $a[0];
        return $s;
    }

    /**
    * Renderer of link to product
    *
    * @param Varien_Object $row
    * @return string
    */
    public function render(Varien_Object $row) {
        $err = strpos($row->getType(),'_error')!==FALSE;
        $m = $this->shortErrorMessage($row->getMessage());
        if ($err) {
            $m = '<span style="color:red">'.$m.'</span>';
        }
        return $m;
    }

}
