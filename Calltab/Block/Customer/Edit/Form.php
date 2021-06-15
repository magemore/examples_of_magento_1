<?php

class Magemore_Calltab_Block_Customer_Edit_Form extends Mage_Adminhtml_Block_Widget_Form {

    public function __construct() {
        parent::__construct();
        $this->setId('calltabCustomerEditForm');
    }

    private function getCallTypeValues() {
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $query = "SELECT * FROM call_type";
        $call_type_tmp = $db->fetchAll($query);
        $call_type = array();
        $call_type[]='';
        foreach ($call_type_tmp as $d) {
            $call_type[$d['call_type_id']] = $d['name'];
        }
        return $call_type;
    }

    private function getPendingActionValues() {
        $a = array();
        $a[]='';
        return $a;
    }

    protected function _prepareForm()
    {
        $dateFormatIso = Mage::app()->getLocale()->getDateFormat( Mage_Core_Model_Locale::FORMAT_TYPE_SHORT );
        $form = new Varien_Data_Form(array(
            'id'      => 'edit_form',
            'action'  => $this->getUrl('*/*/save'),
            'method'  => 'post',
            'enctype' => 'multipart/form-data'
        ));

        $fieldset = $form->addFieldset('base_fieldset',
            array(
                'legend' => Mage::helper('calltab')->__( 'Current Call' )
            ));
        $fieldset->addField('customer_id', 'hidden',
            array(
                'name' => 'customer_id',
                'value' => $this->getRequest()->getParam('id'),
            ));
        $fieldset->addField('incoming_call', 'select',
            array(
                'name' => 'incoming_call',
                'label' => Mage::helper('calltab')->__('Incoming call'),
                'title' => Mage::helper('calltab')->__('Incoming call'),
                'values' => array(''=>'None'),
                'required' => false
            ));
        $fieldset->addField('person_spoke_to', 'text',
            array(
                'name' => 'person_spoke_to',
                'label' => Mage::helper('calltab')->__('Person spoke to'),
                'title' => Mage::helper('calltab')->__('Person spoke to'),
                'required' => false
            ));

        $fieldset->addField('dont_call', 'radios',
            array(
                'name' => 'dont_call',
                'label' => Mage::helper('calltab')->__('Don\'t call'),
                'title' => Mage::helper('calltab')->__('Don\'t call'),
                'values' => array(
                        array('value'=>0,'label'=>'&nbsp;Can call&nbsp;&nbsp;'),
                        array('value'=>1,'label'=>'&nbsp;Don\'t call (1)&nbsp;&nbsp;'),
                        array('value'=>100,'label'=>'&nbsp;Don\'t call (100)&nbsp;&nbsp;'),
                    ),
                'required' => false
            ));

        $fieldset->addField('call_type', 'select',
            array(
                'name' => 'call_type',
                'label' => Mage::helper('calltab')->__('Current call type'),
                'title' => Mage::helper('calltab')->__('Current call type'),
                'values' => $this->getCallTypeValues(),
                'required' => true
            ));

        $fieldset->addField('notes', 'textarea',
            array(
                'name' => 'notes',
                'label' => Mage::helper('calltab')->__('Current call notes'),
                'title' => Mage::helper('calltab')->__('Current call notes'),
                'required' => false
            ));

        $fieldset = $form->addFieldset('pending_fieldset',
            array(
                'legend' => Mage::helper('calltab')->__( 'Pending action' )
            ));

        $fieldset->addField('pending_action', 'select',
            array(
                'name' => 'pending_action',
                'label' => Mage::helper('calltab')->__('Pending action'),
                'title' => Mage::helper('calltab')->__('Pending action'),
                'values' => $this->getPendingActionValues(),
                'required' => false
            ));

        $fieldset->addField('pending_action_date', 'date',
            array(
                'name' => 'pending_action_date',
                'label' => Mage::helper('calltab')->__('Date'),
                'title' => Mage::helper('calltab')->__('Date'),
                'image' => $this->getSkinUrl ( 'images/grid-cal.gif' ),
                'input_format' => Varien_Date::DATE_INTERNAL_FORMAT,
                'format' => $dateFormatIso,
                'required' => false
            ));
        $fieldset->addField('pending_action_assign_to', 'select',
            array(
                'name' => 'pending_action_assign_to',
                'label' => Mage::helper('calltab')->__('Assign to'),
                'title' => Mage::helper('calltab')->__('Assign to'),
                'values' => array(''=>'Gary Hendler'),
                'required' => false
            ));
        $fieldset->addField('pending_action_notes', 'textarea',
            array(
                'name' => 'pending_action_notes',
                'label' => Mage::helper('calltab')->__('Pending action notes'),
                'title' => Mage::helper('calltab')->__('Pending action notes'),
                'required' => false
            ));
        $fieldset->addField('pending_action_attachment', 'file',
            array(
                'name' => 'pending_action_attachment',
                'label' => Mage::helper('calltab')->__('Attachment'),
                'title' => Mage::helper('calltab')->__('Attachment'),
                'required' => false
            ));
        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }

}
