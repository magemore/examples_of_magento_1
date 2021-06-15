<?php


class Magemore_Calltab_Block_Customer_Edit_Tab_Manager extends Mage_Adminhtml_Block_Widget_Form {

    /**
     * Initialize form
     *
     * @return Magemore_Calltab_Block_Customer_Edit_Tab_Manager
     */
    public function initForm() {
        $form = new Varien_Data_Form();
        $form->setHtmlIdPrefix('_manager');
        $form->setFieldNameSuffix('manager');

        $fieldset = $form->addFieldset('base_fieldset', array(
            'legend' => 'Assign Calltab Manager'
        ));

        $c = Mage::registry('current_customer');
        $calltab_manager_id = $c->getCalltabManagerId();

        $fieldset->addField('manager', 'select', array(
            'label' => 'manager',
            'name' => 'manager',
            'options' => $this->getManagerOptions(),
            'value' => $calltab_manager_id
        ));

        $this->setForm($form);
        return $this;
    }

    private function getManagerOptions() {
        $m = Mage::getSingleton('calltab/manager');
        return $m->getOptions();
    }

}
