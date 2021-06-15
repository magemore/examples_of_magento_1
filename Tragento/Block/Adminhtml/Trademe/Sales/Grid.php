<?php

class Magemore_Tragento_Block_Adminhtml_Trademe_Sales_Grid extends Mage_Adminhtml_Block_Widget_Grid {
    public function __construct()
    {
        parent::__construct();
        $this->setId('trademeSalesGrid');
    }

    protected function _getStore()
    {
        $storeId = 0;
        return Mage::app()->getStore((int)$storeId);
    }

    protected function _prepareCollection()
    {
        $collection = Mage::getModel('tragento/sales')->getCollection();
        //exit($collection->getSelect()->__ToString());
        $this->setCollection($collection);
        parent::_prepareCollection();
        return $this;
    }

    protected function _prepareColumns()
    {
        $store = $this->_getStore();
        $currency_code = $store->getBaseCurrency()->getCode();

        $this->addColumn('purchase_id', array(
            'header'    => Mage::helper('tragento')->__('Trademe<br /> Order #'),
            'align'     => 'right',
            'width'     => '50px',
            'type'      => 'number',
            'index'     => 'purchase_id',
            'filter_index' => 'purchase_id',
        ));

        $this->addColumn('title', array(
            'header'    => Mage::helper('tragento')->__('Title'),
            'type'      => 'text',
            'index'     => 'title',
            'filter_index' => 'title',
        ));


        $this->addColumn('qty', array(
            'header'    => Mage::helper('tragento')->__('Qty'),
            'type'      => 'number',
            'index'     => 'qty',
            'filter_index' => 'qty',
        ));

        $this->addColumn('sold_date', array(
            'header'    => Mage::helper('tragento')->__('Trademe<br /> Sold Date'),
            'type'      => 'date',
            'index'     => 'sold_date',
            'filter_index' => 'sold_date',
        ));

        //$this->addColumn('price', array(
        //    'header'    => Mage::helper('tragento')->__('Price'),
        //    'type'      => 'price',
        //    'index'     => 'price',
        //    'filter_index' => 'price',
        //    'currency_code' => $currency_code,
        //));

        //$this->addColumn('subtotal_price', array(
        //    'header'    => Mage::helper('tragento')->__('Subtotal Price'),
        //    'type'      => 'price',
        //    'index'     => 'subtotal_price',
        //    'filter_index' => 'subtotal_price',
        //    'currency_code' => $currency_code,
        //));

        //$this->addColumn('total_shipping_price', array(
        //    'header'    => Mage::helper('tragento')->__('Total Shipping Price'),
        //    'type'      => 'price',
        //    'index'     => 'total_shipping_price',
        //    'filter_index' => 'total_shipping_price',
        //    'currency_code' => $currency_code,
        //));

        $this->addColumn('total_sale_price', array(
            'header'    => Mage::helper('tragento')->__('Sale Price'),
            'type'      => 'price',
            'index'     => 'total_sale_price',
            'filter_index' => 'total_sale_price',
            'currency_code' => $currency_code,
        ));

        $this->addColumn('listing_id', array(
            'header'    => Mage::helper('tragento')->__('Trademe<br /> Listing Id'),
            'type'      => 'number',
            'index'     => 'listing_id',
            'filter_index' => 'listing_id',
        ));

        $this->addColumn('buyer_nickname', array(
            'header'    => Mage::helper('tragento')->__('Buyer Nickname<br /> Buyer Email<br />Buyer Phone'),
            'type'      => 'text',
            'index'     => 'buyer_nickname',
            'filter_index' => 'buyer_nickname',
            'renderer' => 'tragento/adminhtml_trademe_sales_grid_renderer_buyer',
        ));

        //$this->addColumn('buyer_member_id', array(
        //    'header'    => Mage::helper('tragento')->__('Buyer Member Id'),
        //    'type'      => 'number',
        //    'index'     => 'buyer_member_id',
        //    'filter_index' => 'buyer_member_id',
        //));

        //$this->addColumn('buyer_nickname', array(
        //    'header'    => Mage::helper('tragento')->__('Buyer Nickname'),
        //    'type'      => 'text',
        //    'index'     => 'buyer_nickname',
        //    'filter_index' => 'buyer_nickname',
        //));

        //$this->addColumn('buyer_email', array(
        //    'header'    => Mage::helper('tragento')->__('Buyer Email'),
        //    'type'      => 'text',
        //    'index'     => 'buyer_email',
        //    'filter_index' => 'buyer_email',
        //));

        //$this->addColumn('buyer_delivery_address', array(
        //    'header'    => Mage::helper('tragento')->__('Buyer Delivery Address'),
        //    'type'      => 'text',
        //    'index'     => 'buyer_delivery_address',
        //    'filter_index' => 'buyer_delivery_address',
        //));

        //$this->addColumn('delivery_name', array(
        //    'header'    => Mage::helper('tragento')->__('Delivery Name'),
        //    'type'      => 'text',
        //    'index'     => 'delivery_name',
        //    'filter_index' => 'delivery_name',
        //));

        //$this->addColumn('delivery_address1', array(
        //    'header'    => Mage::helper('tragento')->__('Delivery Address1'),
        //    'type'      => 'text',
        //    'index'     => 'delivery_address1',
        //    'filter_index' => 'delivery_address1',
        //));

        //$this->addColumn('delivery_address2', array(
        //    'header'    => Mage::helper('tragento')->__('Delivery Address2'),
        //    'type'      => 'text',
        //    'index'     => 'delivery_address2',
        //    'filter_index' => 'delivery_address2',
        //));

        //$this->addColumn('delivery_suburb', array(
        //    'header'    => Mage::helper('tragento')->__('Delivery Suburb'),
        //    'type'      => 'text',
        //    'index'     => 'delivery_suburb',
        //    'filter_index' => 'delivery_suburb',
        //));

        //$this->addColumn('delivery_city', array(
        //    'header'    => Mage::helper('tragento')->__('Delivery City'),
        //    'type'      => 'text',
        //    'index'     => 'delivery_city',
        //    'filter_index' => 'delivery_city',
        //));

        //$this->addColumn('delivery_postcode', array(
        //    'header'    => Mage::helper('tragento')->__('Delivery Postcode'),
        //    'type'      => 'text',
        //    'index'     => 'delivery_postcode',
        //    'filter_index' => 'delivery_postcode',
        //));

        //$this->addColumn('delivery_country', array(
        //    'header'    => Mage::helper('tragento')->__('Delivery Country'),
        //    'type'      => 'text',
        //    'index'     => 'delivery_country',
        //    'filter_index' => 'delivery_country',
        //));

        //$this->addColumn('delivery_phonenumber', array(
        //    'header'    => Mage::helper('tragento')->__('Delivery Phonenumber'),
        //    'type'      => 'text',
        //    'index'     => 'delivery_phonenumber',
        //    'filter_index' => 'delivery_phonenumber',
        //));

        $this->addColumn('product_id', array(
            'header'    => Mage::helper('tragento')->__('Magento<br /> Product Id'),
            'type'      => 'number',
            'index'     => 'product_id',
            'filter_index' => 'product_id',
            'renderer' => 'tragento/adminhtml_trademe_sales_grid_renderer_product',
        ));

        //$this->addColumn('customer_id', array(
        //    'header'    => Mage::helper('tragento')->__('Customer Id'),
        //    'type'      => 'number',
        //    'index'     => 'customer_id',
        //    'filter_index' => 'customer_id',
        //    'renderer' => 'tragento/adminhtml_trademe_sales_grid_renderer_customer',
        //));

        $this->addColumn('order_id', array(
            'header'    => Mage::helper('tragento')->__('Magento Order #<br />Magento Sold Date'),
            'type'      => 'number',
            'index'     => 'order_id',
            'filter_index' => 'order_id',
            'renderer' => 'tragento/adminhtml_trademe_sales_grid_renderer_ordercreate',
        ));

        // create order button... thought to make it inside actions column
        // decided to merge it with order_id renderer
		//$this->addColumn('actions', array(
        //    'header'    => Mage::helper('tragento')->__('Actions'),
        //    'align'     => 'left',
        //    'width'     => '150px',
        //    'type'      => 'action',
        //    'index'     => 'actions',
        //    'filter'    => false,
        //    'sortable'  => false,
        //    'getter'    => 'getId',
        //    'actions'   => array()
		//));

    }


    public function getRowUrl($row) {
    }
}
