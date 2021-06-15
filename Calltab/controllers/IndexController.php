<?php


class Magemore_Calltab_IndexController extends Mage_Adminhtml_Controller_Action {

    protected function _isAllowed()
    {
        //return true;
        return Mage::getSingleton('admin/session')->isAllowed('customer/calltab');
    }
    
    public function managerAction() {
        $id = Mage::getModel('calltab/manager')->getAdminUserManagerId();
        $url = Mage::getUrl('admincalltab/index/', array('filter'=>base64_encode('manager_id='.$id)));
        $this->getResponse()->setRedirect($url);
    }
    
    public function indexAction()
    {
        $this->loadLayout();
        $this->_initHeadBlock('Calltab Customers');
        $this->renderLayout();
    }

    private function _initHeadBlock($title,$addJs=false) {
        if ($headBlock = $this->getLayout()->getBlock('head')) {
            $headBlock->setTitle($title);
            if ($addJs) {
                $headBlock->setCanLoadExtJs(true)
                    ->addJs('jquery.js')
                    ->addJs('calltab/jquery.fancybox-1.3.4.js')
                    ->addCss('calltab/jquery.fancybox-1.3.4.css')
                    ->addJs('calltab/grid.js');
            }
        }
    }
	
    public function editAction()
    {
        $customerId = (int) $this->getRequest()->getParam('id');
        $customer = Mage::getModel('customer/customer');
        if ($customerId) {
            $customer->load($customerId);
        }
        Mage::register('current_customer', $customer);
        $this->loadLayout();
	$this->_initHeadBlock('Customer',true);
        $this->renderLayout();
    }

    public function saveAction()
    {
        // this is just temporary. after prototyping move this action into a model
        // todo: validate call_type_id shouldn't be null
        $call_type_id = (int)$this->getRequest()->getParam('call_type');
        $customer_id = (int)$this->getRequest()->getParam('customer_id');
        $notes = $this->getRequest()->getParam('notes');
        $person_spoke_to = $this->getRequest()->getParam('person_spoke_to');
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $manager_id = (int)Mage::getModel('calltab/manager')->getAdminUserManagerId();
        // @todo: rewrite sql query for production to remove sql injection
        $query = "INSERT INTO calls (call_id,manager_id,call_type_id,customer_id,notes,call_timestamp,person_spoke_to) VALUES (NULL,$manager_id,'$call_type_id','$customer_id','$notes',NOW(),'$person_spoke_to')";
        $db->query($query);

        $call_id = $db->lastInsertId();
        Mage::getSingleton('admin/session')->setCalltabCallId($call_id);
        
        // reindex
        Mage::getModel('calltab/reindex')->makeCall($customer_id,$call_type_id);
        
        // if sale redirect to create order form
        if ($call_type_id==1) {
            $this->_redirect('*/sales_order_create/index/customer_id/'.$customer_id.'/');
        }
        else {
            $this->_redirect('*/*/');
        }
    }


    private function getAddButton($id) {
        return "<button id='cartadd_$id' class='scalable add' type='button'><span>Add</span></button>";
    }

    private function showSaleRow($a,$cart) {
        $cc = '';
        if ($cart) {
            $cc = 'cart';
        }
        $btn = $this->getAddButton($a['product_id']);
        echo "<tr class='sale $cc'>
            <td class='sku a-left'>$a[sku]</td>
            <td class='name'>$a[name]</td>
            <td>$a[n_times_purchased]</td>
            <td>$a[p_times_purchased]</td>
            <td>$a[qty]</td>
            <td>$a[last_price]</td>
            <td>$a[last_qty]</td>
            <td>$a[last_purchased_at]</td>
            <td>$a[d1] ($a[d1q])</td>
            <td>$a[d2] ($a[d2q])</td>
            <td>$a[d3] ($a[d3q])</td>
            <td>$a[avg]</td>
            <td class='add_price'><input type='text' name='price_$a[product_id]' id='price_$a[product_id]' value='$a[price]' /></td>
            <td class='add_qty'><input type='text' name='qty_$a[product_id]' id='qty_$a[product_id]' value='' /> $btn</td>
            </tr>";
    }

    private function showSaleRows($a,$cart=false) {
        foreach ($a as $d) {
            $d['qty']=round($d['qty']);
            $d['d1q']=round($d['d1q']);
            $d['d2q']=round($d['d2q']);
            $d['d3q']=round($d['d3q']);
            $d['last_qty']=round($d['last_qty']);
            $d['p_times_purchased'].='%';
            $d['last_price']=Mage::helper('checkout')->formatPrice($d['last_price']);
            if ($d['price']) {
                $d['price']=number_format($d['price'],2);
            }
            $this->showSaleRow($d,$cart);
        }
    }

    private function showProductRow($p,$cart) {
        $cc = '';
        if ($cart) {
            $cc = 'cart';
        }
        $btn = $this->getAddButton($p['entity_id']);
        echo "<tr class='product $cc'>
            <td class='sku a-left'>$p[sku]</td>
            <td class='name'>$p[name]</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td class='add_price'><input type='text' name='price_$p[entity_id]' id='price_$p[entity_id]' value='$p[price]' /></td>
            <td class='add_qty'><input type='text' name='qty_$p[entity_id]' id='qty_$p[entity_id]' value='' /> $btn</td>
            </tr>";
    }

    private function showProductRows($a,$pids,$cart=false) {
        foreach ($a as $p) {
            if (isset($pids[$p['entity_id']])) {
                continue;
            }
            $p['price']=number_format($p['price'],2);
            $this->showProductRow($p,$cart);
        }
    }

    private function getProductsQuery($wcond,$name_attribute_id,$price_attribute_id,$limit=false) {
        $qlim = '';
        if ($limit) {
            $alim='LIMIT '.$limit;
        }
        $query = "SELECT p.entity_id, p.sku, n.value as name, pr.value as price
            FROM catalog_product_entity p, catalog_product_entity_varchar n, catalog_product_entity_decimal pr
            WHERE p.entity_id=n.entity_id AND p.entity_id=pr.entity_id
            $wcond
            AND n.attribute_id=$name_attribute_id
            AND pr.attribute_id=$price_attribute_id
            GROUP BY p.entity_id
            $qlim";
        return $query;
    }

    public function getProductDetailsAction() {
        $sku = $this->getRequest()->getParam('sku');
        $product = Mage::getModel('catalog/product');
        $id = $product->getIdBySku($sku);
        $product->load($id);

        // get html of product and display with form here.
        // test url http://www.costcutters.com.au/index.php/admincalltab/index/getProductDetails/sku/ART-GLITGOLD1LG/
        //echo 'product details '.$sku.PHP_EOL;
        //var_dump($product->getData());

        // magento quick view displays it as iframe
        // D:\shots\jan_2016\2016-01-24 22_56_56-Greenshot.png
        // quickview-index-view
        // http://mage.lightbulbsinternational.com/general-purpose/reflector/br30.html
        // example page with quick view
        // frontend\smartwave\porto\template\catalog/product/view.phtml
        // smartwave theme
        // Mage_Catalog_Block_Product_View
        // D:\srv\mage.lightbulbsinternational.com\www\lbi\app\code\local\Smartwave

    }

    public function searchAction() {
        $customer_id = (int)$this->getRequest()->getParam('id');
        if (!$customer_id) {
            $customer_id = (int)$this->getRequest()->getParam('customer_id');
        }
        $cart_ids = $this->getRequest()->getParam('ids');
        $keyword = trim($this->getRequest()->getParam('keyword'));

        //--- debug
        // header('Content-type: text/plain');
        // $keyword = 'wipe';
        // $customer_id = 1;


        $db = Mage::getSingleton('core/resource')->getConnection('core_write');

        $sales_cart = array();
        if ($cart_ids) {
            $cart_ids = explode(',',$cart_ids);
            foreach ($cart_ids as $i => $id) {
                $cart_ids[$i]=(int)$id;
            }
        }

        $where_cart_ids_not = '';
        if ($cart_ids) {
            $wci = implode(',',$cart_ids);
            $query = "SELECT * FROM prev_sales WHERE customer_id=$customer_id AND product_id IN ($wci)";
            $sales_cart = $db->fetchAll($query);
            $where_cart_ids_not = "AND product_id NOT IN ($wci)";
        }

        // pids used to filter displayed products
        $pids = array();

        // show sales find ignoring with qty set in input
        $query = "SELECT * FROM prev_sales WHERE customer_id=$customer_id AND (sku LIKE '%$keyword%' OR name LIKE '%$keyword%') $where_cart_ids_not";
        $sales_find = $db->fetchAll($query);

        echo '<div class="grid">';
        echo '<table cellspacing="0" class="data">
            <thead>
            <tr class="headings">
                <th class="sku">SKU</th>
                <th class="name">Name</th>
                <th># times purchased</th>
                <th>% times purchased</th>
                <th>Total Qty</th>
                <th>Last price</th>
                <th>Last qty</th>
                <th>Last purchased at</th>
                <th>D1</th>
                <th>D2</th>
                <th>D3</th>
                <th>AVG</th>
                <th>Price</th>
                <th class="qty">Qty</th>
                </tr>
                </thead><tbody>';

        foreach ($sales_cart as $s) {
            $pids[$s['product_id']]=$s['product_id'];
        }
        foreach ($sales_find as $s) {
            $pids[$s['product_id']]=$s['product_id'];
        }

        $query = "SELECT entity_type_id FROM eav_entity_type WHERE entity_type_code='catalog_product' LIMIT 1";
        $type_id = $db->fetchOne($query);

        $query = "SELECT attribute_id FROM eav_attribute WHERE attribute_code='name' AND entity_type_id=$type_id LIMIT 1";
        $name_attribute_id = $db->fetchOne($query);

        $query = "SELECT attribute_id FROM eav_attribute WHERE attribute_code='price' AND entity_type_id=$type_id LIMIT 1";
        $price_attribute_id = $db->fetchOne($query);

        $products=array();
        // product ids for prev_sale current prices
        if ($pids) {
            $spid = "AND p.entity_id IN (".implode(',',$pids).")";
            $query = $this->getProductsQuery($spid,$name_attribute_id,$price_attribute_id);
            $products = $db->fetchAll($query);
        }
        foreach ($sales_cart as $i => $s) {
            $s['price']='';
            foreach ($products as $j => $p) {
                if ($s['product_id']==$p['entity_id']) {
                    $s['price'] = $p['price'];
                }
            }
            $sales_cart[$i]=$s;
        }
        foreach ($sales_find as $i => $s) {
            $s['price']='';
            foreach ($products as $j => $p) {
                if ($s['product_id']==$p['entity_id']) {
                    $s['price'] = $p['price'];
                }
            }
            $sales_find[$i]=$s;
        }

        $this->showSaleRows($sales_cart,true);

        $products_cart=array();
        if ($cart_ids) {
            $where_in = 'AND p.entity_id IN ('.implode(',',$cart_ids).')';
            $query = $this->getProductsQuery($where_in,$name_attribute_id,$price_attribute_id,20);
            $products_cart = $db->fetchAll($query);
        }

        $this->showProductRows($products_cart,$pids,true);
        foreach ($products_cart as $s) {
            $pids[$s['entity_id']]=$s['entity_id'];
        }

        $this->showSaleRows($sales_find,false);

        $products_find=array();
        if ($keyword) {
            $skey = "AND (p.sku LIKE '%$keyword%' OR n.value LIKE '%$keyword%')";
            $query = $this->getProductsQuery($skey,$name_attribute_id,$price_attribute_id,20);
            $products_find = $db->fetchAll($query);
        }

        $this->showProductRows($products_find,$pids,false);

        echo '</tbody></table>';
        echo '</div>';
    }
	
    public function callorderAction()
    {
        $this->loadLayout();
		$this->_addLayoutJs();
        $this->renderLayout();
    }

}
