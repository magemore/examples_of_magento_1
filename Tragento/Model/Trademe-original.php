<?php

class Magemore_Tragento_Model_Trademe extends Mage_Core_Model_Abstract {
    private $trademe;
    private $environment = 'sandbox';
    private $consumer_key    = '24A9C9569BBE9D28B99A4D9F05EF52EF';
    private $consumer_secret = '202497C2A773D58830CE71CCFCE4810A';
    private $callback_url    = 'http://costcutters.com.au/callback.php';

    public function __construct() {
        parent::__construct();
        if($this->environment == 'sandbox')
        {
            require_once('gogoTradeMe/gogoTradeMeSandbox.php');
            $this->trademe = new gogoTradeMeSandbox(
                $this->consumer_key,                          // Obtain from "My TradeMe"
                $this->consumer_secret,                       // Obtain from "My TradeMe"
                $this->callback_url                           // Handles the return from TradeMe
            );
        }
        else
        {
            require_once('gogoTradeMe/gogoTradeMe.php');
            $this->trademe = new gogoTradeMe(
                $this->consumer_key,                          // Obtain from "My TradeMe"
                $this->consumer_secret,                       // Obtain from "My TradeMe"
                $this->callback_url                           // Handles the return from TradeMe
            );
        }

        $AccessToken = array (
            'oauth_token' => '6C4DC9B11BD74B27309D4127E9F4701B',
            'oauth_token_secret' => 'B303982F150E0ECDBFFA4DBEDBD1B461',
            'oauth_token_type' => 'access',
            'oauth_token_time' => 1430369803,
        );
        $this->trademe->set_token($AccessToken);
    }

    public function getListingUrl($id) {
        return $this->trademe->get_listing_url($id);
    }


    private function getProductData($product_id) {
        $product = Mage::getModel('catalog/product')->load($product_id);
        $price = trim($product->getTrademeNzprice());
        $freight = $product->getTrademeFreight();
        $data = array(
            // get product attribute category
            'Category' => $product->getTrademeCategory(),
            'Title' => $product->getTrademeTitle(),
            // maybe even comment this field, subtitles are paid
            //'Subtitle' => '',
            'Description' => $product->getTrademeDescription(),
            // replace with getTrademePrice() when attribute created
            'StartPrice' => $price,
            'ReservePrice' => $price,
            'BuyNowPrice' => $price,
            // constant 7 days
            'Duration' => 'Seven',
            // probably not allowed
            'Pickup' => 'Forbid',
            'IsBrandNew' => '1',
            // need to figure out meaning
            'SendPaymentInstructions' => '1',
            // just place 1
            // maybe use stock of product
            'Quantity' => $product->getTrademeQty(),
            'HasAgreedWithLegalNotice' => '1',
        );
        $photoId = $this->getPhotoId($product->getImage());
        if ($photoId) {
            // strange that it has so many arrays
            // because wrapper creates xml requests based on arrays
            // and data format universal for many different requests
            // add just 1 photo per product (free)
            $data['PhotoIds'] = array(
                    'PhotoId' => array(0 => $photoId,),
                );
        }
        // need to set proper shipping option
        // maybe use freight attribute from product
        $data['ShippingOptions'] = array(
                'ShippingOption' => array(
                    0 => array(
                        'Type' => 'Custom',
                        'Price' => $freight,
                        'Method' => 'Standard Post',
                    ),
                ),
            );
        $data['PaymentMethods'] = array(
                'PaymentMethod' => array(
                    0 => 'BankDeposit',
                    1 => 'Other',
                ),
            );
        return $data;
    }

    public function listProduct($product_id) {
        $data = $this->getProductData($product_id);
        $err = '';
        try {
            $response = $this->trademe->post('Selling::ListingRequest', $data);
            if ($response->is_ok()) {
                return $response->ListingId;
            }
            else {
                $err = $response->error_message();
            }
        }
        catch (Exception $e) {
            $err = $e->getMessage();
            $err .= (string)$e;
        }
        if ($err) {
            $d = array('product_id'=>$product_id);
            $this->tragentoLog($d,$err,'list_product_error');
        }
        
        $backlink = '<a href="/index.php/tragento/adminhtml_trademe_listing/">back</a><br /><br />';
        $link = '/index.php/drszjj/catalog_product/edit/id/'.$product_id.'/';
        die($backlink.$err.'<br>product id: <a href="'.$link.'" target="_blank">'.$product_id.'</a><br>'.'<pre>'.var_export($data,true));
    }

    public function relistProduct($item_id) {
        $data = array(
            'ListingId' => $item_id,
        );
        $response = $this->trademe->post('Selling/Relist::RelistListingRequest', $data);
        if ($response->is_ok()) {
            return 'success';
            // todo: make table with logs
            // todo: make grid for logs
            // columns: id, date, product_id, listing_id, action, success, error_message 
            //die($item_id.' delisted');
        }
        else {
            $err = $response->error_message();
            $d = array('listing_id'=>$item_id);
            $this->tragentoLog($d,$err,'relist_error');
            //return $response->error_message();
            //die($response->error_message());
        }
    }

    // it's id of listing on trademe website
    // not id of product at magento
    public function delistProduct($item_id) {
        $data = array(
            'ListingId' => $item_id,
            //'ReturnListingDetails' => false,
            'Type' => 'ListingWasNotSold',
            // todo: make admin interface 
            // general setting for reason, or ask at input when clicking delist
            'Reason' => 'To be advised',
        );
        $response = $this->trademe->post('Selling/Withdraw::WithdrawRequest', $data);
        if ($response->is_ok()) {
            return true;
            // todo: make table with logs
            // todo: make grid for logs
            // columns: id, date, product_id, listing_id, action, success, error_message 
            //die($item_id.' delisted');
        }
        else {
            $err = $response->error_message();
            $d = array('listing_id'=>$item_id);
            $this->tragentoLog($d,$err,'delist_error');
            //die($response->error_message());
        }
    }

    public function getPhotoId($filename) {
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $query = "SELECT photo_id FROM tragento_photo WHERE filename=:filename LIMIT 1";
        $photoId = (int)$db->fetchOne($query,array('filename'=>$filename));
        if ($photoId) return $photoId;
        $dir = Mage::getBaseDir('media').'/catalog/product';
        $photoId = $this->uploadPhoto($dir.$filename);
        if ($photoId) {
            $query = "INSERT INTO tragento_photo (`filename`, `photo_id`) VALUES (:filename,:photo_id)";
            $db->query($query,array('filename'=>$filename,'photo_id'=>$photoId));
        }
        return $photoId;
    }

    private function uploadPhoto($filename) {
        $response = $this->trademe->post('Photos::PhotoUploadRequest', array('FileName' => $filename));
        if ($response->is_ok()) {
            $photoId = $response->PhotoId;
            return (int)$photoId;
            //die($photoId.' photoId');
        }
        else {
            die($response->error_message());
        }
    }


    private function checkSaleExists($id) {
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $query = "SELECT purchase_id FROM tragento_sales WHERE purchase_id=:purchase_id LIMIT 1";
        $purchaseId = (int)$db->fetchOne($query,array('purchase_id'=>$id));
        return $purchaseId>0;
    }

    private function convertDate($date) {
        $d = (string)$date;
        return $d;
    }

    private function newSale($item) {
        $purchaseId = $item->PurchaseId;
        if ($this->checkSaleExists($purchaseId)) return false;
        $data = array(
            'purchase_id' => (int)$item->PurchaseId,
            'title' => (string)$item->Title,
            'qty' => (int)$item->QuantitySold,
            'sold_date' => $this->convertDate($item->SoldDate),
            'price' => (string)$item->Price,
            'subtotal_price' => (string)$item->SubtotalPirce,
            'total_shipping_price' => (string)$item->TotalShippingPrice,
            'total_sale_price' => (string)$item->TotalSalePrice,
            'listing_id' => (int)$item->ListingId,
            'buyer_member_id' => (int)$item->Buyer->MemberId,
            'buyer_nickname' => (string)$item->Buyer->Nickname,
            'buyer_email' => (string)$item->Buyer->Email,
            'buyer_delivery_address' => (string)$item->BuyerDeliveryAddress,
            'delivery_name' => (string)$item->DeliveryAddress->Name,
            'delivery_address1' => (string)$item->DeliveryAddress->Address1,
            'delivery_address2' => (string)$item->DeliveryAddress->Address2,
            'delivery_suburb' => (string)$item->DeliveryAddress->Suburb,
            'delivery_city' => (string)$item->DeliveryAddress->City,
            'delivery_postcode' => (string)$item->DeliveryAddress->Postcode,
            'delivery_country' => (string)$item->DeliveryAddress->Country,
            'delivery_phonenumber' => (string)$item->DeliveryAddress->PhoneNumber,
            //'product_id' => '',
            //'customer_id' => '',
            //'order_id' => '',
        );
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $query = "INSERT INTO `tragento_sales` (`purchase_id`,`title`,`qty`,`sold_date`,`price`,`subtotal_price`,`total_shipping_price`,`total_sale_price`,`listing_id`,`buyer_member_id`,`buyer_nickname`,`buyer_email`,`buyer_delivery_address`,`delivery_name`,`delivery_address1`,`delivery_address2`,`delivery_suburb`,`delivery_city`,`delivery_postcode`,`delivery_country`,`delivery_phonenumber`) VALUES (:purchase_id,:title,:qty,:sold_date,:price,:subtotal_price,:total_shipping_price,:total_sale_price,:listing_id,:buyer_member_id,:buyer_nickname,:buyer_email,:buyer_delivery_address,:delivery_name,:delivery_address1,:delivery_address2,:delivery_suburb,:delivery_city,:delivery_postcode,:delivery_country,:delivery_phonenumber)";
        $db->query($query,$data);
    }

    private function _getStoreId() {
        // costcutters store_id = 1. Make admin config for store
        $store_id = 1;
        return $store_id;
    }

    private function _getStore() {
        return Mage::app()->getStore($this->_getStoreId());
    }

    private function _getWebsiteId() {
        return Mage::getModel('core/store')->load($this->_getStoreId())->getWebsiteId();
    }

    private function getCustomerId($email) {
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $query = "SELECT entity_id FROM `customer_entity` WHERE email=:email LIMIT 1";
        $id = $db->fetchOne($query,array('email'=>$email));
        return $id;
    }

    private function getFirstName($order) {
        $name = explode(' ',$order['delivery_name']);
        return current($name);
    }

    private function getLastName($order) {
        $name = explode(' ',trim($order['delivery_name']));
        return end($name);
    }

    private function getCountryId($order) {
        $b = strtolower($order['delivery_country']);
        $c = Mage::getModel('directory/country')->getCollection();
        foreach ($c as $d) {
            $s = strtolower($d->getName());
            if (strpos($s,$b)!==FALSE) {
                return $d->getCountryId();
            }
        }
        // country not found
        // maybe return australia
        return '';
    }

    public function getCustomer($order) {
        // return if exists
        $id =$this->getCustomerId($order['buyer_email']);
        $customer = Mage::getModel('customer/customer');
        if ($id) {
            $customer->load($id);
            // still need to update address
            // return $customer;
        }
        else {
        // create customer
            $customer->setWebsiteId($this->_getWebsiteId())
                ->setCreatedIn('Tragento')
                ->setStore($this->_getStore())
                ->setFirstname($this->getFirstname($order))
                ->setLastname($this->getLastname($order))
                ->setEmail($order['buyer_email'])
                ->setPassword($customer->generatePassword(8));
            $customer->save();
        }

        // send customer email about account created
        //$customer->sendNewAccountEmail();


        // get region id from city name if country is new zealand or australia
        //     [region_id] => 189
        // setRegionId works. But can't get state from database based on city and postcode
        // it maybe possible to get using google api
        // https://developers.google.com/maps/documentation/geocoding/
        // request http to google map
        // problem same city names in different states. so also need to use zip/postcode
        // reverse geocoding
        // http://maps.googleapis.com/maps/api/geocode/json?latlng=40.714224,-73.961452&sensor=false
        // hack created region saying: "Nope / TradeMe". To pass the validation and not spend to much time rewriting it
        // ->setRegionId(7777)
        $phone = trim($order['delivery_phonenumber']);
        // make phone field 000 when it's empty
        if (!$phone) $phone = '#00000';


        $address = $customer->getDefaultShippingAddress();
        if (!$address) {
            $address = Mage::getModel('customer/address');
        }
        $address->setCustomerId($customer->getId())
            ->setFirstname($customer->getFirstname())
            ->setLastname($customer->getLastname())
            ->setCountryId($this->getCountryId($order))
            ->setPostcode($order['delivery_postcode'])
            //->setRegionId(189)
            ->setRegionId(7777)
            ->setCity($order['delivery_city'])
            ->setTelephone($phone)
            ->setStreet($order['delivery_address1'].' '.$order['delivery_address2'])
            ->setIsDefaultBilling('1')
            ->setIsDefaultShipping('1')
            ->setSaveInAddressBook('1');
        $address->save();
        
        return $customer;
    }

    public function getProductIdByListingId($listingId) {
        // listingId == item_id
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $query = "SELECT product_id FROM `tragento_product` WHERE item_id=:item_id LIMIT 1";
        $id = $db->fetchOne($query,array('item_id'=>$listingId));
        return $id;
    }

    public function getProduct($order) {
        $id = $this->getProductIdByListingId($order['listing_id']);
        if (!$id && $order['product_id']) {
           $id = $order['product_id'];
        } 
        if (!$id) return false;
        $product = Mage::getModel('catalog/product');
        $product->load($id);
        if (!$product) return false;
        $price = $order['price'];
        $product->setPrice($price);
        $product->setSpecialPrice($price);
        // not saving the product. price is hack for quote
        return $product;
    }

    public function tragentoLog($order,$message='',$type='error') {
        $data = array(
            'purchase_id' => 0,
            'listing_id' => 0,
            'order_id' => 0,
            'customer_id' => 0,
            'product_id' => 0,
            'message' => $message,
            'type' => $type,
        );
        if ($order['purchase_id']) $data['purchase_id']=$order['purchase_id'];
        if ($order['listing_id']) $data['listing_id']=$order['listing_id'];
        if ($order['order_id']) $data['order_id']=$order['order_id'];
        if ($order['customer_id']) $data['customer_id']=$order['customer_id'];
        if ($order['product_id']) $data['product_id']=$order['product_id'];
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $query = "INSERT INTO `tragento_log` (`log_id`,`purchase_id`,`listing_id`,`order_id`,`customer_id`,`product_id`,`message`,`type`,`created_at`) VALUES (NULL,:purchase_id,:listing_id,:order_id,:customer_id,:product_id,:message,:type,NOW())";
        $db->query($query,$data);
    }

    public function makeOrder($order) {
        try {
            $product = $this->getProduct($order);
            // maybe add log message "can't find product"
            $customer = $this->getCustomer($order);
            $order['customer_id']=$customer->getId();
            if (!$product) {
                $message = 'Can\'t find product by listing id';
                $this->tragentoLog($order,$message,'create_order_error'); 
                return;
            }
            $order['product_id']=$product->getId();
            $qty = $order['qty'];

            // disable email notifications
            Mage::app()->getStore()->setConfig(Mage_Sales_Model_Order::XML_PATH_EMAIL_ENABLED, "0");

            $quote = Mage::getModel('sales/quote'); 
            $quote->setStore($this->_getStore());
            // Mage_Checkout_Model_Type_Onepage::METHOD_GUEST
            // Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER
            // Mage_Checkout_Model_Type_Onepage::METHOD_CUSTOMER
            $quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER);
            $quote->getStore()->setData('current_currency', $quote->getStore()->getBaseCurrency());
            $quote->assignCustomer($customer);
            $quote->save();
            $quote->addProduct($product,$qty);
            $quote->save();
            $items = $quote->getAllItems();
            foreach ($items as $item) {
                $item->save();
            }
            $quote->save();

            Mage::unregister('tragentoShippingPrice');
            Mage::register('tragentoShippingPrice', $order['total_shipping_price']);
            $shippingAddress = $quote->getShippingAddress();
            $shippingAddress->setCollectShippingRates(true)
                ->collectShippingRates()
                ->setShippingMethod('tragentoshipping_tragentoshipping')
                ->setBaseShippingAmount($order['total_shipping_price'])
                ->setShippingAmount($order['total_shipping_price']);


            $quote->getPayment()->importData(array('method'=>'checkmo'));
            //$quote->setPayment($payment);
            $quote->setShippingAmount($order['total_shipping_price']);
            $quote->setBaseShippingAmount($order['total_shipping_price']);
            //echo get_class_lineage($quote);
            //exit();
            $quote->collectTotals();
            $quote->reserveOrderId();
            $quote->setIsActive(0);
            $quote->save();

            $service = Mage::getModel('sales/service_quote', $quote);
            $service->submitAll();
            $mOrder = $service->getOrder();
            // duplicate set shipping
            // previouse setShippingAmount not worked.
            //$mOrder->setShippingAmount($order['total_shipping_price']);
            //$mOrder->setBaseShippingAmount($order['total_shipping_price']);
            //$mOrder->collectTotals();
            $mOrder->save();

            // get order id and update table
            $idsData = array(
                'purchase_id' => $order['purchase_id'],
                'order_id' => $mOrder->getId(),
                'customer_id' => $order['customer_id'],
                'product_id' => $order['product_id'],
            );
            $this->updateSaleIds($idsData);
        }
        catch(Exception $e) {
            $message = $e->getMessage();
            $message .= (string)$e;
            $this->tragentoLog($order,$message,'create_order_error'); 
        }
    }

    private function updateSaleIds($data) {
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $query = "UPDATE `tragento_sales` SET order_id=:order_id, customer_id=:customer_id, product_id=:product_id WHERE purchase_id=:purchase_id LIMIT 1";
        $db->query($query,$data);
    }

    public function makeOrders() {
        //plog('makeOrders');
        //exit();
        $this->updateSales();
        // select tragento_sales with order_id = 0
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $query = "SELECT * FROM `tragento_sales` WHERE order_id=0 ORDER BY purchase_id ASC";
        $orders = $db->fetchAll($query);
        foreach ($orders as $order) {
            $this->makeOrder($order);
        }
    }

    public function updateSales() {
        $items = $this->getSoldItems();
        foreach ($items as $item) {
            $this->newSale($item);
        }
    }

    public function getSoldItems() {
        // for cron job better hour or less
        //$filter = 'LastHour';
        $filter = 'Last45Days';
        //$filter = 'SaleCompleted';
        $response = $this->trademe->get('MyTradeMe/SoldItems/'.$filter);

        if ($response->is_ok()) {
            return $response->List->SoldItem;
        }
        else {
            die($response->error_message());
        }
    }
}
