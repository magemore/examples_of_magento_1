<?php

class Magemore_Clearcvv_Block_Info_Ccsave extends Mage_Payment_Block_Info_Ccsave
{
    /**
     * Show also cvv if secure mode... need pdfs but not emails
     *
     * Expiration date and full number will show up only in secure mode (only for admin, not in emails or pdfs)
     *
     * @param Varien_Object|array $transport
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        if (null !== $this->_paymentSpecificInformation) {
            return $this->_paymentSpecificInformation;
        }
        $info = $this->getInfo();
        $transport = new Varien_Object(array(Mage::helper('payment')->__('Name on the Card') => $info->getCcOwner(),));
        $transport = parent::_prepareSpecificInformation($transport);
        if (!$this->getIsSecureMode()) {
            $cc_number = $info->getCcNumber();
            // dont show full number at pdf. Also show 4 last digits if cleared
            if (!$cc_number || $this->isPdfUrl()) {
                $last4 = $info->getCcLast4();
                if ($last4) {
                    $cc_number = 'xxxx-'.$last4;
                }
            }
            $transport->addData(array(
                Mage::helper('payment')->__('Expiration Date') => $this->_formatCardDate(
                    $info->getCcExpYear(), $this->getCcExpMonth()
                ),
                Mage::helper('payment')->__('Credit Card Number') => $cc_number,
            ));
            if ($info->getCcCid()) {
                $transport->addData(array(
                    Mage::helper('payment')->__('CVV') => $info->getCcCid(),
                )); 
            }
        }
        return $transport;
    }

    private function isPdfUrl() {
        $module = Mage::app()->getRequest()->getModuleName();
        $controller = Mage::app()->getRequest()->getControllerName();
        $action = Mage::app()->getRequest()->getActionName();
        if ($module=='admin' 
            and (
                ($controller='sales_order_invoice' and $action=='print')
                or ($controller=='sales_invoice' and $action=='pdfinvoices')
                or ($controller=='sales_order_invoice' and $action=='pdfinvoices')
                or ($controller=='sales_order_invoice' and $action=='pdfdocs')
                )
            ) {
            return true;
        }
        return false;
    }

    // allow to print CVV and full card number for invoice PDF
    public function getIsSecureMode() {
        if ($this->isPdfUrl()) return false;
        return parent::getIsSecureMode();
    }

}
