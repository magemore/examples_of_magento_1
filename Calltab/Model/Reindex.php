<?php

class Magemore_Calltab_Model_Reindex {

    private function makeIndexKey($a,$key) {
        $r = array();
        foreach ($a as $d) {
            $r[$d[$key]]=$d;
        }
        return $r;
    }

    private function extractIds($a,$key) {
        $ids = array();
        foreach ($a as $d) {
            if ($d[$key]) {
                $ids[$d[$key]]=$d[$key];
            }
        }
        return $ids;
    }

    private function getEntityTypeIdByCode($code) {
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $sql = "SELECT entity_type_id FROM eav_entity_type WHERE entity_type_code='$code'";
        return $db->fetchOne($sql);
    }

    private function getAttributeIdByCode($code,$entity_type_code) {
        $entity_type_id = $this->getEntityTypeIdByCode($entity_type_code);
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $sql = "SELECT attribute_id FROM eav_attribute WHERE attribute_code='$code' AND entity_type_id=$entity_type_id";
        return $db->fetchOne($sql);
    }

    private function getCustomerEntitiesVarchar($id, $code)
    {
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $where = '';
        if (is_array($id)) {
            $where = 'entity_id IN ('.implode(',',$id).')';
        }
        else {
            $where = 'entity_id = '.$id;
        }
        $attrId = $this->getAttributeIdByCode($code,'customer');
        $query = "SELECT entity_id, value FROM customer_entity_varchar WHERE $where AND attribute_id = $attrId";
        $a = $db->fetchAll($query);
        $r = array();
        foreach ($a as $d) {
            $r[$d['entity_id']] = $d['value'];
        }
        return $r;
    }

    private function getCustomerAddressEntitiesVarchar($id, $code)
    {
        if (!$id) return array();
        // it may contain multiple records for parents, but it will return last because $r[$d['parent_id']]
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $where = '';
        if (is_array($id)) {
            $where = 'a.parent_id IN ('.implode(',',$id).')';
        }
        else {
            $where = 'a.parent_id = '.$id;
        }
        $attrId = $this->getAttributeIdByCode($code,'customer_address');
        $sq = "SELECT a.parent_id, av.value FROM customer_address_entity_varchar av, customer_address_entity a WHERE $where AND av.attribute_id = $attrId AND a.entity_id=av.entity_id";

        $a = $db->fetchAll($sq);
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

    public function makeCall($customer_id,$call_type_id) {
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $call_outcome = Mage::getModel('calltab/manager')->getCallTypeName($call_type_id);
        $sq = 'UPDATE calltab_customer SET last_call_date=NOW(), last_call_outcome=:call_outcome WHERE customer_id=:customer_id';
        $db->query($sq,array('call_outcome'=>$call_outcome,'customer_id'=>$customer_id));
    }
    
    private function getCallTypes() {
        // get all call_type names by id
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $sq = "SELECT * FROM call_type";
        $call_type_tmp = $db->fetchAll($sq);
        $call_type = array();
        foreach ($call_type_tmp as $d) {
            $call_type[$d['call_type_id']] = $d['name'];
        }
        return $call_type;
    }

    private function getLastOrders($customer_ids) {
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        if ($customer_ids) {
            $wcids = implode(',',$customer_ids);
            $sq = "SELECT MAX(entity_id) as entity_id, customer_id FROM sales_flat_order WHERE customer_id IN ($wcids) GROUP BY customer_id";
            $max_order = $db->fetchAll($sq);
            $mxids = $this->extractIds($max_order,'entity_id');
            if ($mxids) {
                $wmx = implode(',',$mxids);
                $sq = "SELECT entity_id, customer_id, updated_at FROM sales_flat_order WHERE entity_id IN ($wmx)";
                $order = $db->fetchAll($sq);
                $r = $this->makeIndexKey($order,'customer_id');
                return $r;
            }
        }
        return array();
    }

    private function getLastSaleCalls($customer_ids) {
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        if ($customer_ids) {
            $wcids = implode(',',$customer_ids);
            $sq = "SELECT MAX(call_timestamp) as last_sale_date, customer_id as customer_id
                FROM calls WHERE customer_id IN ($wcids)
                AND call_type_id=1
                GROUP BY customer_id";
            // call_type_id=1 Sale
            $msales = $db->fetchAll($sq);
            $r = $this->makeIndexKey($msales,'customer_id');
            return $r;
        }
        return array();
    }
    
    // reindex customer infor into calltab_customer
    public function reindexCustomer($id=false) {
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        if ($id) {
            // limit selected customers
            $id = (int)$id;
            $sq = "SELECT entity_id, website_id, calltab_manager_id FROM customer_entity WHERE entity_id = $id";
        }
        else {
            // get list of customers array(entity_id,website_id)
            $sq = "SELECT entity_id, website_id, calltab_manager_id FROM customer_entity";
        }
        $customers = $db->fetchAll($sq);
        
        // list of customer ids
        $customer_ids = array();
        foreach ($customers as $d) {
            $customer_ids[]=$d['entity_id'];
        }
        $fullname = array();
        foreach ($customer_ids as $id) {
            $fullname[$id]='';
        }

        // first_name
        $firstname = $this->getCustomerEntitiesVarchar($customer_ids,'firstname');
        $lastname = $this->getCustomerEntitiesVarchar($customer_ids,'lastname');
        foreach ($firstname as $i => $d) {
            $fullname[$i] = $firstname[$i].' '.$lastname[$i];
        }
        unset($lastname);
        unset($firstname);
        
        $empty_ids = array();
        // get ids of empty fullnames
        foreach ($fullname as $id => $name) {
            if (!$name) $empty_ids[]=$id;
        }
        if ($empty_ids) {
            // if some empty than fill them from address
            $firstname = $this->getCustomerAddressEntitiesVarchar($empty_ids,'firstname');
            $lastname = $this->getCustomerAddressEntitiesVarchar($empty_ids,'lastname');
            foreach ($firstname as $i => $d) {
                $fullname[$i] = $firstname[$i].' '.$lastname[$i];
            }
        }
        // write full names as customer_name into table
        // everything in one query is faster but confusing
        // update only customer_name
        foreach ($fullname as $id => $name) {
            $sq = "INSERT INTO calltab_customer (customer_id,customer_name) VALUES (:id,:name) ON DUPLICATE KEY UPDATE customer_name=:name";
            $db->query($sq,array('id'=>$id,'name'=>$name));
        }
        // next i will be sure that all customer ids exits and it's possible just to update
        // for now just same firstname. than maybe add special field contact
        // can extract this from call spoken to
        //$contact = $this->getCustomerEntitiesVarchar($customer_ids,'contact');
        foreach ($fullname as $id => $name) {
            $sq = "UPDATE calltab_customer SET contact=:contact WHERE customer_id=:id LIMIT 1";
            $db->query($sq,array('id'=>$id,'contact'=>$name));
        }
        // update
        // manager id
        // website_id
        foreach ($customers as $cm) {
            $sq = "UPDATE calltab_customer SET manager_id=:manager_id, website_id=:website_id WHERE customer_id=:id LIMIT 1";
            $db->query($sq,array('id'=>$cm['entity_id'],'manager_id'=>$cm['calltab_manager_id'],'website_id'=>$cm['website_id']));
        }
        $phones = $this->getCustomerAddressEntitiesVarchar($customer_ids,'telephone');
        foreach ($phones as $i => $p) {
            $sq = "UPDATE calltab_customer SET phone=:phone WHERE customer_id=:id LIMIT 1";
            $db->query($sq,array('id'=>$i,'phone'=>$p));
        }
        return $customer_ids;
    }
    
    // reindex calls and orders into calltab_customer
    // customer ids can be passed in order to save time
    public function reindexCallsOrders($id=false,$customer_ids=false) {
        if ($id) {
            $id = (int)$id;
            $customer_ids=array($id);
        }
        if (!$customer_ids) {
            $sq = "SELECT entity_id FROM customer_entity";
            $customers = $db->fetchAll($sq);
            // list of customer ids
            $customer_ids = array();
            foreach ($customers as $d) {
                $customer_ids[]=$d['entity_id'];
            }
        }
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $types = $this->getCallTypes();
        // last call. t2 by join limits call_id to last
        $sq = "SELECT t1.* FROM calls t1
            JOIN (SELECT MAX(call_id) call_id
                FROM calls
                WHERE customer_id IN (".implode(',',$customer_ids).")
                GROUP BY customer_id  ) t2
            ON t1.call_id = t2.call_id
            ORDER BY t1.call_id DESC";
        $calls = $db->fetchAll($sq);
        foreach ($calls as $c) {
            $d = array(
                'id' => $c['customer_id'],
                'last_call_date' => $c['call_timestamp'],
                'last_call_outcome' => isset($types[$c['call_type_id']])?$types[$c['call_type_id']]:'',
            );
            $sq = "UPDATE calltab_customer SET last_call_date=:last_call_date, last_call_outcome=:last_call_outcome WHERE customer_id=:id LIMIT 1";
            $db->query($sq,$d);
        }

        // last purchase date. it gets all orders including orders made from magento, not just calltab orders
        $orders = $this->getLastOrders($customer_ids);
        foreach ($orders as $order) {
            $sq = "UPDATE calltab_customer SET last_purchase_date=:last_purchase_date WHERE customer_id=:id LIMIT 1";
            $db->query($sq,array('id'=>$order['customer_id'],'last_purchase_date'=>$order['updated_at']));
        }

        // last_sales_call_date
        $sales = $this->getLastSaleCalls($customer_ids);
        foreach ($sales as $sale) {
            $sq = "UPDATE calltab_customer SET last_sales_call_date=:last_sales_call_date WHERE customer_id=:id LIMIT 1";
            $db->query($sq,array('id'=>$sale['customer_id'],'last_sales_call_date'=>$sale['last_sale_date']));
        }
    }
    
    // id false means reindex everything
    public function reindex($id=false) {
        $customer_ids = $this->reindexCustomer($id);
        $this->reindexCallsOrders($id,$customer_ids);
    }

}

