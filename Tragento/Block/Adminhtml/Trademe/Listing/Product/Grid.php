<?php

abstract class Magemore_Tragento_Block_Adminhtml_Trademe_Listing_Product_Grid
    extends Magemore_Tragento_Block_Adminhtml_Magento_Product_Grid_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('trademeListingProductGrid');
        //------------------------------

        $this->hideMassactionDropDown = true;
    }

    // ####################################

    protected function isShowRuleBlock()
    {
        if (Mage::helper('M2ePro/View_Ebay')->isSimpleMode()) {
            return false;
        }

        return parent::isShowRuleBlock();
    }

    // ####################################

    protected function _prepareCollection()
    {
        // Get collection
        //----------------------------
        /* @var $collection Mage_Core_Model_Mysql4_Collection_Abstract */
        $collection = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToSelect('sku')
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('type_id')
            ->joinTable(
                array('cisi' => 'cataloginventory/stock_item'),
                'product_id=entity_id',
                array('qty' => 'qty',
                      'is_in_stock' => 'is_in_stock'),
                '{{table}}.stock_id=1',
                'left'
            );
        //----------------------------

        //----------------------------
        $collection->getSelect()->distinct();
        //----------------------------

        // Set filter store
        //----------------------------
        $store = $this->_getStore();

        if ($store->getId()) {
            $collection->joinAttribute(
                'price', 'catalog_product/price', 'entity_id', NULL, 'left', $store->getId()
            );
            $collection->joinAttribute(
                'status', 'catalog_product/status', 'entity_id', NULL, 'inner',$store->getId()
            );
            $collection->joinAttribute(
                'visibility', 'catalog_product/visibility', 'entity_id', NULL, 'inner',$store->getId()
            );
            $collection->joinAttribute(
                'thumbnail', 'catalog_product/thumbnail', 'entity_id', NULL, 'left',$store->getId()
            );
        } else {
            $collection->addAttributeToSelect('price');
            $collection->addAttributeToSelect('status');
            $collection->addAttributeToSelect('visibility');
            $collection->addAttributeToSelect('thumbnail');
        }
        //----------------------------


        $collection->addFieldToFilter(
            array(
                array('attribute'=>'type_id','neq'=>'virtual'),
            )
        );

        //exit($collection->getSelect()->__toString());
        // Set collection to grid
        $this->setCollection($collection);

        parent::_prepareCollection();
        $this->getCollection()->addWebsiteNamesToResult();

        return $this;
    }

    protected function _prepareColumns()
    {
        $this->addColumn('product_id', array(
            'header'    => Mage::helper('M2ePro')->__('Product ID'),
            'align'     => 'right',
            'width'     => '100px',
            'type'      => 'number',
            'index'     => 'entity_id',
            'filter_index' => 'entity_id',
            'frame_callback' => array($this, 'callbackColumnProductId')
        ));

        $this->addColumn('name', array(
            'header'    => Mage::helper('M2ePro')->__('Product Title'),
            'align'     => 'left',
            //'width'     => '100px',
            'type'      => 'text',
            'index'     => 'name',
            'filter_index' => 'name',
            'frame_callback' => array($this, 'callbackColumnProductTitle')
        ));

        $types = Mage::getSingleton('catalog/product_type')->getOptionArray();
        unset($types['virtual']);

        $this->addColumn('type', array(
            'header'    => Mage::helper('M2ePro')->__('Type'),
            'align'     => 'left',
            'width'     => '90px',
            'type'      => 'options',
            'sortable'  => false,
            'index'     => 'type_id',
            'filter_index' => 'type_id',
            'options'   => $types
        ));

        $this->addColumn('is_in_stock', array(
            'header'    => Mage::helper('M2ePro')->__('Stock Availability'),
            'align'     => 'left',
            'width'     => '90px',
            'type'      => 'options',
            'sortable'  => false,
            'index'     => 'is_in_stock',
            'filter_index' => 'is_in_stock',
            'options' => array(
                '1' => Mage::helper('M2ePro')->__('In Stock'),
                '0' => Mage::helper('M2ePro')->__('Out of Stock')
            ),
            'frame_callback' => array($this, 'callbackColumnIsInStock')
        ));

        $this->addColumn('sku', array(
            'header'    => Mage::helper('M2ePro')->__('SKU'),
            'align'     => 'left',
            'width'     => '90px',
            'type'      => 'text',
            'index'     => 'sku',
            'filter_index' => 'sku'
        ));

        $store = $this->_getStore();

        $this->addColumn('price', array(
            'header'    => Mage::helper('M2ePro')->__('Price'),
            'align'     => 'right',
            'width'     => '100px',
            'type'      => 'price',
            'currency_code' => $store->getBaseCurrency()->getCode(),
            'index'     => 'price',
            'filter_index' => 'price',
            'frame_callback' => array($this, 'callbackColumnPrice')
        ));

        $this->addColumn('qty', array(
            'header'    => Mage::helper('M2ePro')->__('Qty'),
            'align'     => 'right',
            'width'     => '100px',
            'type'      => 'number',
            'index'     => 'qty',
            'filter_index' => 'qty',
            'frame_callback' => array($this, 'callbackColumnQty')
        ));

        return parent::_prepareColumns();
    }

    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('entity_id');

        // Set fake action
        //--------------------------------
        $this->getMassactionBlock()->addItem('attributes', array(
            'label' => '&nbsp;&nbsp;&nbsp;&nbsp;',
            'url'   => $this->getUrl('*/adminhtml_listing/massStatus', array('_current'=>true)),
        ));
        //--------------------------------

        return parent::_prepareMassaction();
    }

    // ####################################

    protected function _addColumnFilterToCollection($column)
    {
        if ($this->getCollection()) {
            if ($column->getId() == 'websites') {
                $this->getCollection()->joinField('websites',
                    'catalog/product_website',
                    'website_id',
                    'product_id=entity_id',
                    null,
                    'left');
            }
        }
        return parent::_addColumnFilterToCollection($column);
    }

    protected function _getStore()
    {
        $storeId = 0;
        return Mage::app()->getStore((int)$storeId);
    }

    // ####################################

    protected function _toHtml()
    {
        $helper = Mage::helper('M2ePro');

        if ($this->getRequest()->isXmlHttpRequest()) {
            return parent::_toHtml();
        }


        $urls = array();
        $path = 'adminhtml_trademe_listing';
        $urls[$path] = $this->getUrl('*/' . $path);
        $path = 'adminhtml_trademe_listing_productAdd';
        $urls[$path] = $this->getUrl('*/' . $path);
        $path = 'adminhtml_trademe_listing_productAdd/add';
        $urls[$path] = $this->getUrl('*/' . $path);
        $urls = json_encode($urls);
        //------------------------------

        //------------------------------
        $translations = json_encode(array(
            'eBay Categories' => $this->__('eBay Categories'),
            'Specifics' => $this->__('Specifics'),
            'Automatic Actions' => $this->__('Automatic Actions'),
            'Based on Magento Categories' => $this->__('Based on Magento Categories'),
            'You must select at least 1 category.' => $this->__('You must select at least 1 category.'),
            'Rule with the same title already exists.' => $this->__('Rule with the same title already exists.'),
            'Listing Settings Customization' => $this->__('Listing Settings Customization'),
        ));
        //------------------------------

        //------------------------------
        $showAutoActionPopup = false;
        $showAutoActionPopup = json_encode($showAutoActionPopup);

        $showSettingsStep = false;
        $showSettingsPopup = false;
        $showSettingsStep  = json_encode($showSettingsStep);
        $showSettingsPopup = json_encode($showSettingsPopup);

        //------------------------------

        $js = <<<HTML
<script type="text/javascript">

    M2ePro.url.add({$urls});
    M2ePro.translator.add({$translations});

    Event.observe(window, 'load', function() {

        WrapperObj = new AreaWrapper('add_products_container');

        ListingProductAddHandlerObj = new TrademeListingProductAddHandler({
            show_settings_step: {$showSettingsStep},
            show_settings_popup: {$showSettingsPopup},
            show_autoaction_popup: {$showAutoActionPopup},

            get_selected_products: {$this->getSelectedProductsCallback()}
        });

    });

</script>
HTML;

        return parent::_toHtml().$js;
    }

    // ####################################

    abstract protected function getSelectedProductsCallback();

    // ####################################
}
