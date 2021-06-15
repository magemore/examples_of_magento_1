<?php

class Magemore_Calltab_Block_Customers_Grid extends Mage_Adminhtml_Block_Widget_Grid
{

    public function __construct()
    {
        parent::__construct();
    }

    protected function _getStore()
    {
        $storeId = (int) $this->getRequest()->getParam('store', 0);
        return Mage::app()->getStore($storeId);
    }

    protected function _prepareCollection()
    {
        $store = $this->_getStore();

        $collection = Mage::getResourceModel('calltab/customer_collection');
        if ($store->getId()) {
            $collection->addStoreFilter($store);
        }
        $this->setCollection($collection);
        parent::_prepareCollection();
        return $this;

    }

    protected function _prepareColumns()
    {
        $this->addColumn('customer_name', array(
            'header'    => Mage::helper('calltab')->__('Customer Name'),
            'index'     => 'customer_name',
            'type'      => 'text',
            'escape'    => true,
            'align'     => 'left',
        ));
        $this->addColumn('contact', array(
            'header'    => Mage::helper('calltab')->__('Contact'),
            'index'     => 'contact',
            'type'      => 'text',
            'escape'    => true,
            'align'     => 'left',
        ));
        $this->addColumn('phone', array(
            'header'    => Mage::helper('calltab')->__('Phone'),
            'index'     => 'phone',
            'type'      => 'text',
            'escape'    => true,
            'align'     => 'left',
        ));
        $this->addColumn('last_call_date', array(
            'header'    => Mage::helper('calltab')->__('Date of last call'),
            'type'      => 'datetime',
            'align'     => 'left',
            'width'     => '150px',
            'format'    => Mage::app()->getLocale()->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM),
            'index'     => 'last_call_date',
        ));
        $this->addColumn('last_call_outcome', array(
            'header'    => Mage::helper('calltab')->__('Last call outcome'),
            'index'     => 'last_call_outcome',
            'type'      => 'text',
            'escape'    => true,
            'align'     => 'left',
        ));
        $this->addColumn('last_purchase_date', array(
            'header'    => Mage::helper('calltab')->__('Date of last purchase'),
            'type'      => 'datetime',
            'align'     => 'left',
            'width'     => '150px',
            'format'    => Mage::app()->getLocale()->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM),
            'index'     => 'last_purchase_date',
        ));
        $this->addColumn('last_sales_call_date', array(
            'header'    => Mage::helper('calltab')->__('Date of last sales call'),
            'type'      => 'datetime',
            'align'     => 'left',
            'width'     => '150px',
            'format'    => Mage::app()->getLocale()->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM),
            'index'     => 'last_sales_call_date',
        ));
        // @todo: calculate if from last_sales_call_date or last_purchase_date
        $this->addColumn('days_since_last_purchase_sales_call_count', array(
            'header'    => Mage::helper('calltab')->__('Days since last purchase/sales call'),
            'index'     => 'days_since_last_purchase_sales_call_count',
            'type'      => 'number',
            'width'     => '100px',
            'align'     => 'right',
            'frame_callback' => array($this, 'callbackColumnDummyPlug')
        ));
        $this->addColumn('days_to_action_count', array(
            'header'    => Mage::helper('calltab')->__('Days to action'),
            'index'     => 'days_to_action_count',
            'type'      => 'number',
            'width'     => '100px',
            'align'     => 'right',
            'frame_callback' => array($this, 'callbackColumnDummyPlug')
        ));
        $this->addColumn('days_until_next_action_count', array(
            'header'    => Mage::helper('calltab')->__('Days until next action'),
            'index'     => 'days_until_next_action_count',
            'type'      => 'number',
            'width'     => '100px',
            'align'     => 'right',
            'frame_callback' => array($this, 'callbackColumnDummyPlug')
        ));
        $m = Mage::getSingleton('calltab/manager');
        $this->addColumn('manager_id', array(
            'header'    => Mage::helper('calltab')->__('Internal Sales Rep'),
            'index'     => 'manager_id',
            'type'      => 'options',
            'options'   => $m->getGridOptions(),
            'align'     => 'left',
        ));

        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn('website_id', array(
                'header'    => Mage::helper('customer')->__('Website'),
                'align'     => 'center',
                'width'     => '80px',
                'type'      => 'options',
                'options'   => Mage::getSingleton('adminhtml/system_store')->getWebsiteOptionHash(true),
                'index'     => 'website_id',
            ));
        }
        
        $this->addColumn('actions', array(
            'header'    => Mage::helper('calltab')->__('Actions'),
            'align'     => 'left',
            'width'     => '150px',
            'type'      => 'action',
            'index'     => 'actions',
            'filter'    => false,
            'sortable'  => false,
            'getter'    => 'getId',
            'actions'   => array()
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
        return $this->getUrl('*/*/*', array('_current'=>true));
    }

    public function getRowUrl($row)
    {
        return $this->getUrl ( '*/*/edit', array ('id' => $row->getId () ) );
    }

}
