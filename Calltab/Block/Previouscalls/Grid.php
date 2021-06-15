<?php

class Magemore_Calltab_Block_Previouscalls_Grid extends Mage_Adminhtml_Block_Widget_Grid
{

    public function __construct()
    {
        parent::__construct();
    }

    private function getAttributeIdByCode($code) {
        // attribute id 5 = company name - first name
        // attribute id 7 = customer name - last name
        // get attribute id for first_name
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $query = "SELECT attribute_id FROM eav_attribute WHERE attribute_code='$code'";
        return $db->fetchOne($query);
    }

    private function getCustomerEntitiesVarchar($id, $code) {
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $where = '';
        if (is_array($id)) {
            $where = 'entity_id IN ('.implode(',',$id).')';
        }
        else {
            $where = 'entity_id = '.$id;
        }
        $attrId = $this->getAttributeIdByCode($code);
        $query = "SELECT entity_id, value FROM customer_entity_varchar WHERE $where AND attribute_id = $attrId";
        $a = $db->fetchAll($query);
        $r = array();
        foreach ($a as $d) {
            $r[$d['entity_id']] = $d['value'];
        }
        return $r;
    }

    private function getCustomerAddressEntitiesVarchar($id, $code) {
        // it may contain multiple records for parents, but it will return last because $r[$d['parent_id']]
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $where = '';
        if (is_array($id)) {
            $where = 'a.parent_id IN ('.implode(',',$id).')';
        }
        else {
            $where = 'a.parent_id = '.$id;
        }
        $attrId = $this->getAttributeIdByCode($code);
        $query = "SELECT a.parent_id, av.value FROM customer_address_entity_varchar av, customer_address_entity a WHERE $where AND av.attribute_id = $attrId AND a.entity_id=av.entity_id";
        $a = $db->fetchAll($query);
        $r = array();
        // prefill with empty data
        if (is_array($id)) {
            foreach ($id as $i) {
                $r[$i]='';
            }
        }
        foreach ($a as $d) {
            $r[$d['parent_id']] = $d['value'];
        }
        return $r;
    }

    protected function _prepareCollection()
    {
        // get customer id
        $id = (int)$this->getRequest()->getParam('id');

        // magento db request
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');

        $query = "SELECT * FROM calls WHERE customer_id=$id ORDER BY call_id DESC ";
        $calls = $db->fetchAll($query);

        $query = "SELECT * FROM call_type";
        $call_type_tmp = $db->fetchAll($query);
        $call_type = array();
        foreach ($call_type_tmp as $d) {
            $call_type[$d['call_type_id']] = $d['name'];
        }

        $data = array();
        foreach ($calls as $call) {
            $d = array();
            $id = $call['call_id'];
            $d['id'] = $id;
            $d['call_date'] = $call['call_timestamp'];
            $d['person_spoke_to'] = $call['person_spoke_to'];
            $d['outcome'] = ''; // outcome
            $d['notes'] = $call['notes'];
            $d['call_type'] = isset($call_type[$call['call_type_id']])?$call_type[$call['call_type_id']]:'';
            $d['sales_person'] = 'Gary Hedler';
            $data[]=$d;
        }

        $collection = new Varien_Data_Collection();
        foreach ($data as $i => $d) {
            $v = new Varien_Object($d);
            $collection->addItem($v);
        }

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns() {
        $this->addColumn('call_date', array(
            'header'    => Mage::helper('calltab')->__('Time'),
            'type'      => 'datetime',
            'align'     => 'left',
            'width'     => '150px',
            'format'    => Mage::app()->getLocale()->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM),
            'index'     => 'call_date',
        ));
        $this->addColumn('call_type', array(
            'header'    => Mage::helper('calltab')->__('Call type'),
            'index'     => 'call_type',
            'type'      => 'text',
            'escape'    => true,
            'align'     => 'left',
        ));
        $this->addColumn('sales_person', array(
            'header'    => Mage::helper('calltab')->__('Sales Person'),
            'index'     => 'sales_person',
            'type'      => 'text',
            'escape'    => true,
            'align'     => 'left',
        ));
        $this->addColumn('person_spoke_to', array(
            'header'    => Mage::helper('calltab')->__('Person spoke to'),
            'index'     => 'person_spoke_to',
            'type'      => 'text',
            'escape'    => true,
            'align'     => 'left',
        ));
        $this->addColumn('outcome', array(
            'header'    => Mage::helper('calltab')->__('Outcome'),
            'index'     => 'outcome',
            'type'      => 'text',
            'escape'    => true,
            'align'     => 'left',
        ));
        $this->addColumn('notes', array(
            'header'    => Mage::helper('calltab')->__('Notes'),
            'index'     => 'notes',
            'type'      => 'text',
            'escape'    => true,
            'align'     => 'left',
        ));
    }

    // example of callback function for count column
    public function callbackColumnDummyPlug($value, $row, $column, $isExport)
    {
        if (is_null($value) || $value === '') {
            $value = Mage::helper('calltab')->__('N/A');
        } else if ($value <= 0) {
            $value = '<span style="color: red;">0</span>';
        }

        return $value;
    }

    public function getGridUrl()
    {
        // this is needed in order to keep url clean. if return false it will use current url + grid filter.
        // it maybe nice for filter several grids on one page
        // if needed return false. it will allow multiple grid urls... till url max length reached
        return $this->getUrl('*/*/*', array('_current'=>true));
    }

    public function getRowUrl($row)
    {
    }

}
