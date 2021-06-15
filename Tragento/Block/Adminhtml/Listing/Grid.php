<?php

class Magemore_Tragento_Block_Adminhtml_Listing_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    protected $storeId = 0;
    private $event_stat = '';

    public function __construct()
    {
        parent::__construct();
        $template = $this->setTemplate('tragento/grid.phtml');

        $this->setId('productInListingGrid');
        $this->setDefaultSort('product_id');
        $this->setDefaultDir('DESC');
        $this->setUseAjax(true);
        $this->setSaveParametersInSession(true);

        $store = $this->_getStore();
        $this->curCode = $store->getBaseCurrency()->getCode();
    }

    protected function _prepareCollection()
    {
        $trademe = Mage::getModel('tragento/trademe');
        if (!defined('DISABLE_TRAGENTO_REINDEX')) $trademe->reindexTragentoProduct();

        $collection = Mage::getModel('tragento/product')->getCollection();

        // copied from m2e ebay. figure out how it maybe usable for trademe
        // Shown only items that active on trademe not been relisted
        // don't use this field for now
        // $collection->addFieldToFilter('main_table.item_is_relisted', 0);

        // eav attribute_id is always constant that equals to 55. so i can optimize code removing attribute id check. and getting attribute id
        // by code = name with prior sql query. find out how i got attribute id by code before
        //->join(array('ea'=>Mage::getSingleton('core/resource')->getTableName('eav_attribute')), '(`cpev`.`attribute_id` = `ea`.`attribute_id` AND `ea`.`attribute_code` = "name")',
        // SELECT * FROM `costcut`.`eav_entity_type` WHERE `entity_type_id` = 4
        // entity_type_code = catalog_product
        // $query = "SELECT attribute_id FROM eav_attribute WHERE attribute_code='name' AND entity_type_id=$type_id LIMIT 1";
        // $name_attribute_id = $db->fetchOne($query);


        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $query = "SELECT entity_type_id FROM eav_entity_type WHERE entity_type_code='catalog_product' LIMIT 1";
        $type_id = $db->fetchOne($query);

        $query = "SELECT attribute_id FROM eav_attribute WHERE attribute_code='name' AND entity_type_id=$type_id LIMIT 1";
        $name_attribute_id = $db->fetchOne($query);

        $query = "SELECT attribute_id FROM eav_attribute WHERE attribute_code='sku' AND entity_type_id=$type_id LIMIT 1";
        $sku_attribute_id = $db->fetchOne($query);

        // todo: move name into reindex
        // it's possible to optimize table removing last join to filter cpev var char entities by name attribute
        // acutally i just commented lower join and added cpev.attribute_id=$name_attribute_id
        // if store id = 0 than max(store_id) join probably not necessary
        // it's needed in case to select name of product for selected store, maybe different name or language
        // this thing needs real optimization. looks messing because i forgot why it requires to support multistore.
        // also it's possible to make in nicer optmized way... without included insert that executes each time to get max
        // anyway on small data it's irrelevant
        $cpev = "( `cpev`.`entity_id` = `main_table`.product_id
                  AND cpev.attribute_id=$name_attribute_id AND cpev.store_id = (
                    SELECT
                      MAX(`store_id`)
                    FROM
                      `".Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar')."`
                    WHERE
                      `entity_id` = `main_table`.`product_id`
                      AND
                      `attribute_id` = $name_attribute_id
                      AND
                      (`store_id` = 0 OR `store_id` = {$this->storeId}))
                    )";
        // for some reason if i try to add sky in such way it will return empty result
        //$csku = "( `csku`.`entity_id` = `main_table`.product_id
        //          AND csku.attribute_id=$sku_attribute_id AND csku.store_id = 0 )";
        $collection->getSelect()
                  ->join(array('csi'=>Mage::getSingleton('core/resource')->getTableName('cataloginventory_stock_item')), '(csi.product_id = `main_table`.product_id)',array('qty'))
                  ->join(array('cpev'=>Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar')), $cpev, array('value'));
                  //->join(array('csku'=>Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar')), $csku, array('sku'=>'value'));
                  //->join(array('ea'=>Mage::getSingleton('core/resource')->getTableName('eav_attribute')), '(`cpev`.`attribute_id` = `ea`.`attribute_id` AND `ea`.`attribute_code` = "name")',array());

        // it calculated wrong value.
        // simplified by reindexing table each time
        //$collection->addExpressionFieldToSelect('title_length','LENGTH(cpev.value)',array());
        //var_dump($this->storeId);
        //echo '<pre>';
        //exit($collection->getSelect()->__ToString());

        // add filters for event stat ids here
        $filter = $this->getRequest()->getParam('filter');
        if ($filter) {
            $this->eventStatFilter($filter,$collection);
        }

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    private function cleanIdsForSQL($s) {
        $a = explode(',',$s);
        $ids = array_map('intval',$a);
        // return array
        // because $collection->addAttributeToFilter('field_name', array(
        // 'in' => array(1, 2, 3),));
        // 'in' => $ids
        return $ids;
        //return implode(',',$ids);
    }

    private function eventStatFilter($filter,$collection) {
        $filter = base64_decode($filter);
        $filter = trim($filter);
        // error_ids=2213,2214
        if (!strpos($filter,'_ids')) {
            return;
        }
        //in order to filter. it doesn't really matters if it errors or something else
        $a = explode('=',$filter);
        if (!isset($a[0]) || !isset($a[1])) return;
        $es = trim($a[0]); $es = rtrim($es,'_ids');
        $this->event_stat=$es;

        $ids = end($a);
        // don't need to explode ids by ,... hovewer can't also directly insert. it will be sql injection
        // you need admin rights to execute it but still
        $ids = $this->cleanIdsForSQL($ids);
        // test if it support such field filter with main_table.
        $collection->addFieldToFilter('main_table.product_id', array('in' => $ids));
        // result looking SQL
        // it works. filters by ids
        // echo (string)$collection->getSelect();
        // SELECT `main_table`.*, `csi`.`qty`, `cpev`.`value` FROM `tragento_product` AS `main_table` INNER JOIN `cataloginventory_stock_item` AS `csi` ON (csi.product_id = `main_table`.product_id) INNER JOIN `catalog_product_entity_varchar` AS `cpev` ON ( `cpev`.`entity_id` = `main_table`.product_id AND cpev.attribute_id=55 AND cpev.store_id = ( SELECT MAX(`store_id`) FROM `catalog_product_entity_varchar` WHERE `entity_id` = `main_table`.`product_id` AND `attribute_id` = 55 AND (`store_id` = 0 OR `store_id` = 0)) )
        //-- here reulst of filter:
        // WHERE (main_table.product_id IN(2213, 2214))
    }

    protected function _getStore()
    {
        return Mage::app()->getStore($this->storeId);
    }

    protected function _prepareColumns()
    {
        $store = $this->_getStore();

        $this->addColumn('product_id', array(
            'header'    => Mage::helper('m2e')->__('Product ID'),
            'align'     => 'right',
            'width'     => '20px',
            'type'      => 'number',
            'index'     => 'product_id',
            'filter_index' => 'main_table.product_id',
            'renderer' => 'tragento/adminhtml_listing_grid_renderer_id',
        ));

        $this->addColumn('sku', array(
            'header'    => Mage::helper('m2e')->__('Product Title / SKU'),
            'align'     => 'left',
            'width'     => '200px',
            'type'      => 'text',
            'index'     => 'sku',
            'filter_index' => 'main_table.sku',
            'renderer' => 'tragento/adminhtml_listing_grid_renderer_sku'
        ));

        //$this->addColumn('title', array(
        //    'header'    => Mage::helper('m2e')->__('Title'),
        //    'align'     => 'right',
        //    'width'     => '150px',
        //    'index'     => 'value',
        //    'filter_index' => 'cpev.value'
        //));

        $this->addColumn('title_length', array(
            'header'    => Mage::helper('m2e')->__('Length'),
            'align'     => 'right',
            'width'     => '20px',
            'index'     => 'title_length',
            'filter_index' => 'title_length',
            'type'  => 'number',
        ));

        $this->addColumn('trademe_ready', array(
            'header'    => Mage::helper('m2e')->__('TM Ready'),
            'align'     => 'right',
            'width'     => '30px',
            'index'     => 'trademe_ready',
            'filter_index' => 'trademe_ready',
            'type'  => 'text',
            'renderer' => 'tragento/adminhtml_listing_grid_renderer_ready'
        ));

        $this->addColumn('status',
            array(
                'header'=> Mage::helper('catalog')->__('TM Status'),
                'width' => '50px',
                'index' => 'status',
                'filter_index' => 'main_table.status',
                'type'  => 'options',
                'options' => array(
                    '0' => Mage::helper('m2e')->__('Not Listed'),
                    '1' => Mage::helper('m2e')->__('Sold'),
                    '2' => Mage::helper('m2e')->__('Listed'),
                    '3' => Mage::helper('m2e')->__('Stopped'),
                    '5' => Mage::helper('m2e')->__('Finished')
                ),
                'frame_callback' => array($this, 'callbackColumnStatus')
        ));

        // @todo: update tm end date after listed action
        $this->addColumn('tm_end_date',
            array(
                'header'=> Mage::helper('catalog')->__('TM End Date'),
                'width' => '50px',
                'index' => 'tm_end_date',
                'filter_index' => 'main_table.tm_end_date',
                'type'  => 'datetime',
                'format' => Mage::app()->getLocale()->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM),
        ));

        $this->addColumn('item_id',
            array(
                'align'     => 'right',
                'header'=> Mage::helper('catalog')->__('TradeMe<br> Item<br> Number'),
                'width' => '50px',
                'index' => 'item_id',
                'type'  => 'number',
                'filter_index' => 'main_table.item_id'
        ));

        $this->addColumn('ebay_item_id',
            array(
                'align'     => 'right',
                'header'=> Mage::helper('catalog')->__('Ebay<br> Item<br> Number'),
                'width' => '50px',
                'index' => 'ebay_item_id',
                'type'  => 'number',
                'filter_index' => 'main_table.ebay_item_id'
        ));

        $this->addColumn('trademe_price', array(
            'header'    => Mage::helper('m2e')->__('TradeMe<br> Price NZ'),
            'align'     =>'right',
            'width'     => '50px',
            'type'      => 'price',
            'currency_code' => $this->curCode,
            'index'     => 'trademe_price',
            'filter_index' => 'main_table.trademe_price'
        ));

        $this->addColumn('ebay_price', array(
            'header'    => Mage::helper('m2e')->__('Ebay<br> Price AU'),
            'align'     =>'right',
            'width'     => '50px',
            'type'      => 'price',
            'currency_code' => $this->curCode,
            'index'     => 'ebay_price',
            'filter_index' => 'main_table.ebay_price'
        ));

        $this->addColumn('magento_price', array(
            'header'    => Mage::helper('m2e')->__('Magento<br>Store Price'),
            'align'     =>'right',
            'width'     => '50px',
            'type'      => 'price',
            'currency_code' => $this->curCode,
            'index'     => 'magento_price',
            'filter_index' => 'main_table.magento_price'
        ));

        $this->addColumn('trademe_shipping', array(
            'header'    => Mage::helper('m2e')->__('TradeMe<br> Shipping NZ'),
            'align'     =>'right',
            'width'     => '50px',
            'type'      => 'price',
            'currency_code' => $this->curCode,
            'index'     => 'trademe_shipping',
            'filter_index' => 'main_table.trademe_shipping'
        ));

        $this->addColumn('ebay_shipping', array(
            'header'    => Mage::helper('m2e')->__('Ebay<br> Shipping AU'),
            'align'     =>'right',
            'width'     => '50px',
            'type'      => 'price',
            'currency_code' => $this->curCode,
            'index'     => 'ebay_shipping',
            'filter_index' => 'main_table.ebay_shipping'
        ));


        $this->addColumn('trademe_last_sale',
            array(
                'header'=> Mage::helper('catalog')->__('Last Trademe Sale'),
                'width' => '50px',
                'index' => 'trademe_last_sale',
                'filter_index' => 'main_table.trademe_last_sale',
                'type'  => 'datetime',
                'format' => Mage::app()->getLocale()->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM),
        ));

        $this->addColumn('ebay_last_sale',
            array(
                'header'=> Mage::helper('catalog')->__('Last Ebay Sale'),
                'width' => '50px',
                'index' => 'ebay_last_sale',
                'filter_index' => 'main_table.ebay_last_sale',
                'type'  => 'datetime',
                'format' => Mage::app()->getLocale()->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM),
        ));

        $this->addColumn('magento_last_sale',
            array(
                'header'=> Mage::helper('catalog')->__('Last Store Sale'),
                'width' => '50px',
                'index' => 'magento_last_sale',
                'filter_index' => 'main_table.magento_last_sale',
                'type'  => 'datetime',
                'format' => Mage::app()->getLocale()->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM),
        ));

        $this->addColumn('max_last_sale',
            array(
                'header'=> Mage::helper('catalog')->__('Last Sale (max)'),
                'width' => '50px',
                'index' => 'max_last_sale',
                'filter_index' => 'main_table.max_last_sale',
                'type'  => 'datetime',
                'format' => Mage::app()->getLocale()->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM),
        ));

        $this->addColumn('magento_status', array(
            'header' => Mage::helper('catalog')->__('M Status'),
            'align' => 'center',
            'width' => '40px',
            'index' => 'magento_status',
            'type' => 'options',
            'options' => array(
                1 => Mage::helper('catalog')->__('Enabled'),
                2 => Mage::helper('catalog')->__('Disabled')
            )
        ));

        $this->addColumn('ebay_status', array(
            'header' => Mage::helper('catalog')->__('eBay Status'),
            'align' => 'center',
            'width' => '40px',
            'index' => 'ebay_status',
            'type'  => 'options',
            'options' => array(
                '0' => Mage::helper('m2e')->__('Not Listed'),
                '1' => Mage::helper('m2e')->__('Sold'),
                '2' => Mage::helper('m2e')->__('Listed'),
                '3' => Mage::helper('m2e')->__('Stopped'),
                '5' => Mage::helper('m2e')->__('Finished')
            ),
        ));

        //$this->addColumn('trademe_qty', array(
        //    'header'    => Mage::helper('m2e')->__('TradeMe Total/Avail. QTY'),
        //    'align'     => 'right',
        //    'width'     => '150px',
        //    'index'     => 'ebay_qty',
        //    'filter_index' => 'main_table.trademe_qty',
        //));

//        $this->addColumn('magento_qty_product', array(
//            'header'    => Mage::helper('m2e')->__('Stock Qty'),
//            'align'     => 'right',
//            'type'      => 'number',
//            'width'     => '150px',
//            'index'     => 'qty',
//            'filter_index' => 'csi.qty'
//        ));



        //      $this->addColumn('status_relisted', array(
        //          'header'    => Mage::helper('m2e')->__('Relisted Status'),
        //          'align'     =>'left',
        //          'index'     => 'status_relisted',
        //          'width'     =>'180',
        //          'type'      => 'options',
        //          'options'   => array(
        //                0 => Mage::helper('cms')->__('None'),
        //                1 => Mage::helper('cms')->__('Relisted')
        //            )
        //      ));


        //$this->addColumn('view_product_action',
        //    array(
        //        'header'    =>  Mage::helper('m2e')->__('View'),
        //        'width'     => '100',
        //        'type'      => 'action',
        //        'getter'    => 'getProductId',
        //        'actions'   => array(
        //            array(
        //                'caption'   => Mage::helper('m2e')->__('View'),
        //                'url'       => array('base'=> 'adminhtml/catalog_product/edit'),
        //                'field'     => 'id',
        //                'target'    => '_blank',
        //            )
        //        ),
        //        'filter'    => false,
        //        'sortable'  => false,
        //        'index'     => 'stores',
        //        'filter_index' => 'main_table.stores',
        //        'is_system' => true
        //));


        return parent::_prepareColumns();
    }

    protected function _prepareMassaction()
    {
        $this->setNoFilterMassactionColumn(true);
        $this->setMassactionIdField('product_id');
        $this->getMassactionBlock()->addItem('2', array(
            'label'    => Mage::helper('tragento')->__('List Item(s)'),
            'url'      => $this->getUrl('tragento/adminhtml_trademe_listing/massStatus', array('list' => 'yes')),
            'confirm'  => Mage::helper('tragento')->__('List. Are you sure?')
        ));
        $this->getMassactionBlock()->addItem('3', array(
            'label'    => Mage::helper('tragento')->__('Delist Item(s)'),
            'url'      => $this->getUrl('tragento/adminhtml_trademe_listing/massStatus', array('delist' => 'yes')),
            'confirm'  => Mage::helper('tragento')->__('Delist. Are you sure?')
        ));
        $this->getMassactionBlock()->addItem('4', array(
            'label'    => Mage::helper('tragento')->__('Remove Item(s)'),
            'url'      => $this->getUrl('tragento/adminhtml_trademe_listing/massStatus', array('remove' => 'yes')),
            'confirm'  => Mage::helper('tragento')->__('Remove. Are you sure?')
        ));
        $this->getMassactionBlock()->addItem('5', array(
            'label'    => Mage::helper('tragento')->__('Revise Item(s)'),
            'url'      => $this->getUrl('tragento/adminhtml_trademe_listing/massStatus', array('revise' => 'yes')),
            'confirm'  => Mage::helper('tragento')->__('Revise. Are you sure?')
        ));
        //$this->getMassactionBlock()->setTemplate();
        return $this;
    }

    public function getRowUrl($row)
    {
    }

    public function getGridUrl()
    {
        $R = $this->getRequest();
        $params = array(
            'id'     => $R->getParam('id'),
            'limit'  => $R->getParam('limit'),
            'sort'   => $R->getParam('sort'),
            'dir'    => $R->getParam('dir'),
            'page'   => $R->getParam('page'),
            'method' => $R->getParam('method')
        );
        return $this->getUrl('tragento/adminhtml_trademe_listing/', $params);
    }

    public function callbackColumnStatus($value, $row, $column, $isExport)
    {
        return $value.$this->getViewLogIconHtml($row->getData('product_id'),$row->getData('item_id'));
    }

    private function shortErrorMessage($s) {
        $a = explode("\n",$s);
        if (isset($a[0])) return $a[0];
        return $s;
    }

    public function getViewLogIconHtml($product_id, $listing_id) {
        // get last log by product id or listing id.
        $listing_cond = '';
        if ($listing_id) $listing_cond = "OR listing_id=$listing_id";
        $query = "SELECT * FROM tragento_log WHERE product_id=$product_id $listing_cond ORDER BY log_id DESC LIMIT 1";
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $row = $db->fetchRow($query);
        if (!$row) return;
        if (strpos($row['type'],'error')===FALSE) return;

        // @todo: refactor m2e pro logs to tragento logs
        $tips = array(
            Ess_M2ePro_Model_Log_Abstract::TYPE_SUCCESS => 'Last action was completed successfully.',
            Ess_M2ePro_Model_Log_Abstract::TYPE_ERROR => 'Last action was completed with error(s).',
            Ess_M2ePro_Model_Log_Abstract::TYPE_WARNING => 'Last action was completed with warning(s).'
        );

        $icons = array(
            Ess_M2ePro_Model_Log_Abstract::TYPE_SUCCESS => 'normal',
            Ess_M2ePro_Model_Log_Abstract::TYPE_ERROR => 'error',
            Ess_M2ePro_Model_Log_Abstract::TYPE_WARNING => 'warning'
        );

        // @todo: 'initiator' => 'Manual' set Cron if from Cron.
        $actionsRows = array (
          0 =>
          array (
            'type' => 4, // 4 = error
            'date' => $row['created_at'],
            'action' => $row['type'],
            'initiator' => 'Manual',
            'items' =>
            array (
              0 =>
              array (
                'log_id' => $row['log_id'],
                'type' => '4',
                'description' => $this->shortErrorMessage($row['message']),
                'create_date' => $row['created_at'],
                'initiator' => '1',
              ),
            ),
          ),
        );

        $summary = $this->getLayout()->createBlock('tragento/adminhtml_log_grid_summary', '', array(
            'entity_id' => $product_id,
            'rows' => $actionsRows,
            'tips' => $tips,
            'icons' => $icons,
            'view_help_handler' => 'TragentoGridHandlerObj.viewItemHelp',
            'hide_help_handler' => 'TragentoGridHandlerObj.hideItemHelp',
        ));

        return $summary->toHtml();
    }

    public function fetchView($fileName) {
        $html = parent::fetchView($fileName);
        // here place html js script for selected event id
        // and process this elected event in queue.js
        $event_id = intval($filter = $this->getRequest()->getParam('event_id'));
        if ($event_id) {
            $html.='<script>var queue_event_id = '.$event_id.';  var queue_event_stat = \''.$this->event_stat.'\';</script>';
        }
        return $html;
    }


}
