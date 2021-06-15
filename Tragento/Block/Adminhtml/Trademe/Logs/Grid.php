<?php

class Magemore_Tragento_Block_Adminhtml_Trademe_Logs_Grid extends Mage_Adminhtml_Block_Widget_Grid {
    public function __construct()
    {
        parent::__construct();
        $this->setId('trademeLogsGrid');
        $this->setDefaultSort('log_id');
        $this->setDefaultDir('DESC');
    }

    protected function _getStore()
    {
        $storeId = 0;
        return Mage::app()->getStore((int)$storeId);
    }

    protected function _prepareCollection()
    {
        $collection = Mage::getModel('tragento/logs')->getCollection();
        // ignore errors where order from trademe can't be created in magento because magento product id was deleted after listed on trademe
        $collection->addFieldToFilter('message', array('nlike' => '%find product by listing id%'));
        //$collection->addFieldToFilter('type', array('neq' => 'create_order_error'));
        $this->setCollection($collection);
        parent::_prepareCollection();
        return $this;
    }

    protected function _prepareColumns()
    {
        $store = $this->_getStore();
        $currency_code = $store->getBaseCurrency()->getCode();

        $this->addColumn('log_id', array(
            'header'    => Mage::helper('tragento')->__('Log ID'),
            'align'     => 'right',
            'width'     => '50px',
            'type'      => 'number',
            'index'     => 'log_id',
            'filter_index' => 'log_id',
        ));

        $this->addColumn('type', array(
            'header'    => Mage::helper('tragento')->__('Type'),
            'type'      => 'text',
            'index'     => 'type',
            'filter_index' => 'type',
            'width'     => '50px',
        ));

        $this->addColumn('message', array(
            'header'    => Mage::helper('tragento')->__('Message'),
            'type'      => 'text',
            'index'     => 'message',
            'filter_index' => 'message',
            'renderer' => 'tragento/adminhtml_trademe_logs_grid_renderer_message',
        ));

        $this->addColumn('created_at', array(
            'header'    => Mage::helper('tragento')->__('Created At'),
            'type'      => 'datetime',
            'index'     => 'created_at',
            'filter_index' => 'created_at',
            'width'     => '150px',
        ));

        $this->addColumn('purchase_id', array(
            'header'    => Mage::helper('tragento')->__('Purchase Id'),
            'type'      => 'number',
            'index'     => 'purchase_id',
            'filter_index' => 'purchase_id',
            'width'     => '50px',
        ));

        $this->addColumn('listing_id', array(
            'header'    => Mage::helper('tragento')->__('Listing Id'),
            'type'      => 'number',
            'index'     => 'listing_id',
            'filter_index' => 'listing_id',
            'width'     => '50px',
        ));

        $this->addColumn('product_id', array(
            'header'    => Mage::helper('tragento')->__('Product Id'),
            'type'      => 'number',
            'index'     => 'product_id',
            'filter_index' => 'product_id',
            'renderer' => 'tragento/adminhtml_trademe_sales_grid_renderer_product',
            'width'     => '50px',
        ));

        $this->addColumn('customer_id', array(
            'header'    => Mage::helper('tragento')->__('Customer Id'),
            'type'      => 'number',
            'index'     => 'customer_id',
            'filter_index' => 'customer_id',
            'renderer' => 'tragento/adminhtml_trademe_sales_grid_renderer_customer',
            'width'     => '50px',
        ));

        $this->addColumn('order_id', array(
            'header'    => Mage::helper('tragento')->__('Order Id'),
            'type'      => 'number',
            'index'     => 'order_id',
            'filter_index' => 'order_id',
            'renderer' => 'tragento/adminhtml_trademe_sales_grid_renderer_order',
            'width'     => '50px',
        ));


    }


    public function getRowUrl($row) {
    }
}
