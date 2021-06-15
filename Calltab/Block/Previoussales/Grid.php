<?php

class Magemore_Calltab_Block_Previoussales_Grid extends Mage_Adminhtml_Block_Widget_Grid
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
        $customer_id = (int)$this->getRequest()->getParam('id');

        // magento db request
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');

        $time_start = microtime(true);

        // get order qty
        $query = "SELECT COUNT(entity_id) FROM sales_flat_order WHERE customer_id=$customer_id";
        $ordersqty = $db->fetchOne($query);

        // get sales_prev data using index fields customer_id,product_id
        $query = "SELECT oi.product_id, oi.sku, oi.name, COUNT(oi.item_id) as n_times_purchased, ROUND(COUNT(oi.item_id)/$ordersqty*100) as p_times_purchased,
    SUM(oi.qty_ordered) as qty, oilast.price as last_price, oilast.qty as last_qty, oilast.updated_at as last_purchased_at
FROM sales_flat_order_item oi
LEFT JOIN sales_flat_order o
ON oi.order_id=o.entity_id
LEFT JOIN (SELECT oi1mx.product_id, oi1mx.price, oi1mx.qty_ordered as qty, oi1mx.updated_at
    FROM sales_flat_order_item oi1mx
    INNER JOIN (SELECT MAX(oi2mx.item_id) item_id FROM sales_flat_order_item oi2mx, sales_flat_order o2mx WHERE oi2mx.order_id=o2mx.entity_id AND o2mx.customer_id=$customer_id GROUP BY oi2mx.product_id) oi3mx
    ON oi1mx.item_id=oi3mx.item_id) oilast
ON oilast.product_id=oi.product_id
WHERE o.customer_id=$customer_id
GROUP BY oi.product_id;";
        $summary = $db->fetchAll($query);

        foreach ($summary as $i => $d) {
            $d['d1'] = 0;
            $d['d2'] = 0;
            $d['d3'] = 0;
            $d['d1q'] = 0;
            $d['d2q'] = 0;
            $d['d3q'] = 0;
            $d['avg'] = 0;
            $query = "SELECT oi.qty_ordered as qty, oi.updated_at as pd, TIMESTAMPDIFF(DAY,NOW(),oi.updated_at) as d
FROM sales_flat_order_item oi, sales_flat_order o
WHERE oi.order_id=o.entity_id AND o.customer_id = $customer_id AND oi.product_id=$d[product_id]
ORDER BY oi.updated_at DESC";
            $ds = $db->fetchAll($query);
            if (isset($ds[0])) {
                if (isset($ds[1])) {
                    $d['d1'] = $ds[0]['d']-$ds[1]['d'];
                    $d['d1q'] = $ds[1]['qty'];
                    $d['avg'] = $d['d1'];
                    if (isset($ds[2])) {
                        $d['d2'] = $ds[1]['d']-$ds[2]['d'];
                        $d['d2q'] = $ds[2]['qty'];
                        $d['avg'] = ($d['d1'] + $d['d2']) / 2;
                        if (isset($ds[3])) {
                            $d['d3'] = $ds[2]['d']-$ds[3]['d'];
                            $d['d3q'] = $ds[3]['qty'];
                            $d['avg'] = ($d['d1'] + $d['d2'] + $d['d3']) / 3;
                        }
                    }
                }
            }

            // insert/update data into index table
            $d['customer_id']=$customer_id;
            $query = "INSERT INTO prev_sales (customer_id,product_id,sku,name,n_times_purchased,p_times_purchased,qty,last_price,last_qty,last_purchased_at,d1,d2,d3,d1q,d2q,d3q,avg)
VALUES (:customer_id,:product_id,:sku,:name,:n_times_purchased,:p_times_purchased,:qty,:last_price,:last_qty,:last_purchased_at,:d1,:d2,:d3,:d1q,:d2q,:d3q,:avg)
ON DUPLICATE KEY UPDATE sku=:sku,name=:name,n_times_purchased=:n_times_purchased,p_times_purchased=:p_times_purchased,last_price=:last_price,last_qty=:last_qty,last_purchased_at=:last_purchased_at,d1=:d1,d2=:d2,d3=:d3,d1q=:d1q,d2q=:d2q,d3q=:d3q,avg=:avg";
            $db->query($query,$d);
            $summary[$i]=$d;
        }

        // Price - current price based on Magento working out the price
        // Magento price on current product...
        // product also can be configured, so price can be based on this
        // for first time just take product base price

        // show qty_ordered as "No times purchased"
        // how to calculate % of times purchased (check evernote)
        // i remember. count of orders with this item / total count of orders (both with customer_id)
        // D1
        // D2
        // D3
        // AVG
        // Last quantity
        // Last price = price. In price field it cointains input field if it needs to change...
        //        It will update product price for user only or product for all? Ask Gary.
        //            probably only for user if they want to give him discount, etc
        //            calltab users can't change price of product, but can change price for call order
        // Last purchased
        // Quantity
        // Price (probably price from last order)
        // Active (what does it mean?)

        // try to do this all calculations just in 1 SQL query. It should be much easier

        foreach ($summary as $i => $d) {
            $d['d1t'] = $d['d1'].' ('.round($d['d1q']).')';
            $d['d2t'] = $d['d2'].' ('.round($d['d2q']).')';
            $d['d3t'] = $d['d3'].' ('.round($d['d3q']).')';
            $summary[$i]=$d;
        }

        //echo '<pre>';
        //print_r($summary);
        //exit();

        $collection = new Varien_Data_Collection();
        foreach ($summary as $i => $d) {
            $v = new Varien_Object($d);
            $collection->addItem($v);
        }

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns() {
        $this->addColumn('sku', array(
            'header'    => Mage::helper('calltab')->__('SKU'),
            'index'     => 'sku',
            'type'      => 'text',
            'escape'    => true,
            'align'     => 'left',
        ));
        $this->addColumn('name', array(
            'header'    => Mage::helper('calltab')->__('Name'),
            'index'     => 'name',
            'type'      => 'text',
            'escape'    => true,
            'align'     => 'left',
        ));
        $this->addColumn('n_times_purchased', array(
            'header'    => Mage::helper('calltab')->__('# times purchased'),
            'index'     => 'n_times_purchased',
            'type'      => 'number',
            'escape'    => true,
            'align'     => 'right',
        ));
        $this->addColumn('p_times_purchased', array(
            'header'    => Mage::helper('calltab')->__('% times purchased'),
            'index'     => 'p_times_purchased',
            'type'      => 'number',
            'escape'    => true,
            'align'     => 'right',
        ));
        $this->addColumn('last_price', array(
            'header'    => Mage::helper('calltab')->__('Last Price'),
            'index'     => 'last_price',
            'type'      => 'price',
            'escape'    => true,
            'align'     => 'right',
        ));
        $this->addColumn('qty', array(
            'header'    => Mage::helper('calltab')->__('Qty'),
            'index'     => 'qty',
            'type'      => 'number',
            'escape'    => true,
            'align'     => 'right',
        ));
        $this->addColumn('last_qty', array(
            'header'    => Mage::helper('calltab')->__('Last Qty'),
            'index'     => 'last_qty',
            'type'      => 'number',
            'escape'    => true,
            'align'     => 'right',
        ));
        $this->addColumn('last_purchased_at', array(
            'header'    => Mage::helper('calltab')->__('Last Purchased At'),
            'index'     => 'last_purchased_at',
            'type'      => 'date',
            'escape'    => true,
            'align'     => 'right',
        ));
        $this->addColumn('d1t', array(
            'header'    => Mage::helper('calltab')->__('D1'),
            'index'     => 'd1t',
            'type'      => 'text',
            'escape'    => true,
            'align'     => 'right',
        ));
        $this->addColumn('d2t', array(
            'header'    => Mage::helper('calltab')->__('D2'),
            'index'     => 'd2t',
            'type'      => 'text',
            'escape'    => true,
            'align'     => 'right',
        ));
        $this->addColumn('d3t', array(
            'header'    => Mage::helper('calltab')->__('D3'),
            'index'     => 'd3t',
            'type'      => 'text',
            'escape'    => true,
            'align'     => 'right',
        ));
        $this->addColumn('avg', array(
            'header'    => Mage::helper('calltab')->__('AVG'),
            'index'     => 'avg',
            'type'      => 'number',
            'escape'    => true,
            'align'     => 'right',
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
        // just ignore it. no need for url. because this click action handled from jquery and /js/calltab/grid.js
    }

}
