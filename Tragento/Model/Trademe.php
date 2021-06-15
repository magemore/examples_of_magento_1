<?php

// if uncomment this line it will redirect to dashboard
// todo: replace die with tragentoLog
//die(123);

class Magemore_Tragento_Model_Trademe extends Mage_Core_Model_Abstract {
    private $trademe;
    private $environment;
    private $consumer_key;
    private $consumer_secret;
    private $callback_url    = 'http://www.costcutters.com.au/callback.php';
    public $queue_id=0;
    public $queue_event_id=0;
    public $queue_product_id=0;

    public function __construct() {
        parent::__construct();

        $this->environment = Mage::getStoreConfig('tragento/apiconnect/environment');
        $this->consumer_key = Mage::getStoreConfig('tragento/apiconnect/tragento-consumer-key');
        $this->consumer_secret = Mage::getStoreConfig('tragento/apiconnect/tragento-consumer-secret');

        if (!isset($_SERVER['HTTP_HOST']) || !$_SERVER['HTTP_HOST']) {
            // needed for gogo lib
            $_SERVER['HTTP_HOST']='costcutters.com.au';
            $_SERVER['SERVER_NAME']='costcutters.com.au';
        }

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

        $AccessToken = $this->getAccessToken();
        $this->trademe->set_token($AccessToken);
    }

    public function reindexTragentoProduct() {
        // all reindexing code inside constructor
        $tp = new Magemore_Tragento_Reindex_Product_Process();
    }

    private function getAccessToken() {
        return unserialize(Mage::getStoreConfig('tragento/apiconnect/accesstoken'));
    }

    private function getListingUrl($id) {
        return $this->trademe->get_listing_url($id);
    }


    private function getProductData($product_id) {
        $product = Mage::getModel('catalog/product')->load($product_id);
        $price = trim($product->getTrademeNzprice());
        $freight = $product->getTrademeFreight();
        $description = trim($product->getTrademeDescription());
        $description_footer = trim(Mage::getStoreConfig("tragento/api/tragento-footer"));
        if ($description && $description_footer) {
            $description .= PHP_EOL . $description_footer;
        }
        $description = strip_tags($description);
        $qty = (int)$product->getTrademeQty();
        $category = rtrim($product->getTrademeCategory(),'.00');
        $data = array(
            // get product attribute category
            'Category' => $category,
            'Title' => $product->getTrademeTitle(),
            // maybe even comment this field, subtitles are paid
            //'Subtitle' => '',
            'Description' => $description,
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
            'Quantity' => $qty,
            'HasAgreedWithLegalNotice' => '1',
        );
        $photoId = $this->getPhotoId($product->getImage(),$product_id);
        if ($photoId) {
            // strange that it has so many arrays
            // because wrapper creates xml requests based on arrays
            // and data format universal for many different requests
            // add just 1 photo per product (free)
            $data['PhotoIds'] = array(
                    'PhotoId' => array(0 => $photoId,),
                );
        }
        else {
            // can't upload listing without photo
            return false;
        }

        $shipping_method1 = trim($product->getTrademeShippingMethod1());
        $shipping_method2 = trim($product->getTrademeShippingMethod2());
        $shipping_price1 = trim($product->getTrademeShippingPrice1());
        $shipping_price2 = trim($product->getTrademeShippingPrice2());
        // need to set proper shipping option
        // maybe use freight attribute from product
        $opt = array(
            0 => array(
                'Type' => 'Custom',
                'Price' => $freight,
                'Method' => 'Standard Post',
            ),
        );
        if ($shipping_method1 && $shipping_price1) {
            $opt[1]=array('Type'=>'Custom','Price'=>$shipping_price1,'Method'=>$shipping_method1);
        }
        if ($shipping_method2 && $shipping_price2) {
            $opt[2]=array('Type'=>'Custom','Price'=>$shipping_price2,'Method'=>$shipping_method2);
        }
        $data['ShippingOptions'] = array('ShippingOption' => $opt);


        //echo '<pre>';
        //print_r($data['ShippingOptions']);
        //exit();
        $data['PaymentMethods'] = array(
                'PaymentMethod' => array(
                    0 => 'BankDeposit',
                    1 => 'Other',
                ),
            );
        return $data;
    }

    public function reviseProduct($product_id,$listing_id) {
        $data = $this->getProductData($product_id);
        if (!$data) return false;
        $data['ListingId'] = $listing_id;
        $err = '';
        try {
            $response = $this->trademe->post('Selling/Edit::EditListingRequest', $data);
            if ($response->is_ok()) {
                $listing_id =  $response->ListingId;
            }
            else {
                $err = $response->error_message();
            }
        }
        catch (Exception $e) {
            $err = $e->getMessage();
            $err .= ' '.PHP_EOL.(string)$e;
        }
        $d = array('product_id'=>$product_id);
        if ($err) {
            // @todo: add magento message about error
            $this->tragentoLog($d,$err,'revise_product_error');
        }
        else if ($listing_id) {
            $d['listing_id']=$listing_id;
            $this->tragentoLog($d,'Product revised successfully','revise_product_success');
            $this->checkListingExpire($listing_id,$product_id);
            return $listing_id;
        }
        return false;
    }

    private function tragentoHistory($product_id,$item_id) {
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $data = array(
            'product_id' => $product_id,
            'item_id' => $item_id
        );
        $query = "INSERT INTO `tragento_product_history` (`product_id`,`item_id`) VALUES (:product_id,:item_id)";
        $db->query($query,$data);
    }

    // silent: means it will not create log error on relist
    private function tryRelistProductFromHistory($product_id,$silent=true) {
        // it should check if product was delisted manually. if so than TradeMe will reject relist
        // don't waste TradeMe API calls limit
        // try relist in case of time out and wrong trademe id
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');

        $data = array('product_id'=>$product_id);
        $query = "SELECT item_id FROM `tragento_product_history` WHERE product_id=:product_id ORDER BY updated_at DESC LIMIT 1";
        $item_id = $db->fetchOne($query,$data);

        if ($item_id) {
            // get last delisted date
            // item_id is listing_id. it's confusing.
            // m2e pro names it item_id. trademe names it listing_id
            // m2e pro listing id is id of list with products. it has several lists with names.
            // tragento has just one list. so item_id and listing_id used interchangeably
            $data = array('item_id'=>$item_id);
            // i am not sure about: delist_error check.
            $query = "SELECT * FROM `tragento_log` WHERE `type`='delist_error' OR `type`='delist_success' AND listing_id=:item_id ORDER BY log_id DESC LIMIT 1";
            $results = $db->fetchAll($query,$data);
            // it was delisted
            if (count($results)) {
                // don't waste API call it's manually delisted and not possible to relist
                return false;
            }
            $r = $this->relistProduct($item_id,$silent);
            if ($r=='success') {
                $this->reviseProduct($product_id,$item_id);
                return $item_id;
            }
        }
        return false;
    }

    public function listProduct($product_id) {
        $listing_id = $this->tryRelistProductFromHistory($product_id);
        if ($listing_id) return $listing_id;

        $data = $this->getProductData($product_id);
        if (!$data) return false;
        $err = '';
        try {
            $response = $this->trademe->post('Selling::ListingRequest', $data);
            if ($response->is_ok()) {
                // PHP_INT_MAX = 2147483647
                // changed to string. mysql supports much larger ints. bigint(100)
                $listing_id =  (string)$response->ListingId;
            }
            else {
                $err = $response->error_message();
            }
        }
        catch (Exception $e) {
            $err = $e->getMessage();
            $err .= ' '.PHP_EOL.(string)$e;
        }
        $d = array('product_id'=>$product_id);
        if ($err) {
            $this->tragentoLog($d,$err,'list_product_error');
        }
        else if ($listing_id) {
            $d['listing_id']=$listing_id;
            $this->tragentoLog($d,'Product listed successfully','list_product_success');
            $this->tragentoHistory($product_id,$listing_id);
            $this->checkListingExpire($listing_id,$product_id);
            return $listing_id;
        }
        return false;
    }

    public function relistProduct($item_id,$silent=false) {
        $data = array(
            'ListingId' => $item_id,
        );
        $response = $this->trademe->post('Selling/Relist::RelistListingRequest', $data);
        $d = array('listing_id'=>$item_id);
        if ($response->is_ok()) {
            $this->tragentoLog($d,'Product relisted successfully','relist_success');
            $this->checkListingExpire($item_id);
            return 'success';
        }
        else {
            $err = $response->error_message();
            if (!$silent) $this->tragentoLog($d,$err,'relist_error');
            return 'error';
            //return $response->error_message();
            //die($response->error_message());
        }
    }

    // it's id of listing on trademe website
    // not id of product at magento
    public function delistProduct($item_id,$product_id=0) {
        $data = array(
            'ListingId' => $item_id,
            //'ReturnListingDetails' => false,
            'Type' => 'ListingWasNotSold',
            // todo: make admin interface
            // general setting for reason, or ask at input when clicking delist
            'Reason' => 'To be advised',
        );
        $response = $this->trademe->post('Selling/Withdraw::WithdrawRequest', $data);
        $d = array('listing_id'=>$item_id,'product_id'=>$product_id);
        if ($response->is_ok()) {
            $this->tragentoLog($d,'Product delisted successfully','delist_success');
            return true;
        }
        // else if not ok:
        $err = $response->error_message();
        $this->tragentoLog($d,$err,'delist_error');
        return false;
    }


    private function createImage($path,$type) {
        $im = false;
        if ($type==IMAGETYPE_JPEG) {
            $im = imagecreatefromjpeg($path);
        }
        else if ($type==IMAGETYPE_PNG) {
            $im = imagecreatefrompng($path);
        }
        else if ($type==IMAGETYPE_GIF) {
            $im = imagecreatefromgif($path);
        }
        else if ($type==IMAGETYPE_XBM || $type==IMAGETYPE_WBMP) {
            $im = imagecreatefromxbm($path);
        }
        return $im;
    }

    public function applyWatermark($filename,$watermark) {
        $dir = Mage::getBaseDir('media').'/catalog/product';
        $srcpath = $dir . $filename;
        $watermarkPath = $dir . '/tragento-watermark/' . $watermark;

        if (!is_file($srcpath)) return false;
        if (!is_file($watermarkPath)) return false;

        list($woW,$woH,$woFileType) = getimagesize($watermarkPath);
        list($imW,$imH,$imFileType) = getimagesize($srcpath);

        // generate path to save file. use cache/0 as store_id=0 by default. same trademe images for all stores
        $hash = md5($filename+$watermark+$woW+$woH+$imW+$imH);
        $newFilenameDir = $dir . '/cache/0/tragentowatermarks'.dirname($filename).'/'.$hash;
        if(!is_dir($newFilenameDir)) mkdir($newFilenameDir,0777,true);
        $newName = basename($filename);
        $newName = str_replace('.jpg','.png',$newName);
        $newName = str_replace('.bmp','.png',$newName);
        $newName = str_replace('.gif','.png',$newName);
        $newFilename = $newFilenameDir.'/'.$newName;
        if (is_file($newFilename)) return $newFilename;

        $im = $this->createImage($srcpath,$imFileType);
        $wa = $this->createImage($watermarkPath,$woFileType);

        // assuming watermark width bigger than height. watermark width should be 4 times smaller than average image side size. and height. proportional to width from watermark original size.
        $waW = round((($imW + $imH) / 2) / 3);
        $waH = round($waW * ($woH/$woW));
        $waX = round(($imW - $waW)/2);
        $waY = round(($imH - $waH)/2);

        imagecopyresampled($im,$wa,$waX,$waY,0,0, $waW, $waH, $woW, $woH);
        imagedestroy($wa);

        imagepng($im,$newFilename);
        imagedestroy($im);

        return $newFilename;
    }

    /*
    public function applyWatermark_old($filename,$watermark) {
        $dir = Mage::getBaseDir('media').'/catalog/product';
        $srcpath = $dir . $filename;

        $watermarkPath = $dir . '/tragento-watermark/' . $watermark;

        if (!is_file($srcpath)) return false;
        if (!is_file($watermarkPath)) return false;

        list($woWidth,$woHeight) = getimagesize($watermarkPath);
        list($imgSrcWidth, $imgSrcHeight, $imgFileType, ) = getimagesize($srcpath);

        // assuming watermark width bigger than height. watermark width should be 4 times smaller than average image side size. and height. proportional to width from watermark original size.
        $watermarkWidth = (($imgSrcWidth + $imgSrcHeight) / 2) / 3;
        $watermarkHeight = $watermarkWidth * ($woHeight/$woWidth);

        $image = Mage::getModel('catalog/product_image');
        $image->setDestinationSubdir('tragentowatermarks');
        $image->setBaseFile($filename);
        //$image->setWidth(1000)->setKeepAspectRatio(true)->resize();
        $processor = $image->getImageProcessor();
        $processor->keepTransparency(true);
        $processor->setWatermarkPosition( 'center' )
            ->setWatermarkImageOpacity( 50 )
            ->setWatermarkWidth( $watermarkWidth )
            ->setWatermarkHeigth( $watermarkHeight )
            ->watermark($watermarkPath);

        $image->saveFile();

        return $image->getNewFile();
    }
    */

    private function getProductImage($product_id) {
        $product = Mage::getModel('catalog/product')->load($product_id);
        $img = $product->getImage();
        unset($product);
        return $img;
    }

    /* Generates new watermark images if watermark or image changed. but it doesn't upload it to trademe */
    public function updateWatermarks() {
        set_time_limit(0);
        $watermark = trim(Mage::getStoreConfig("tragento/api/tragento-watermark"));
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $query = "SELECT * FROM tragento_product";
        $products = $db->fetchAll($query);
        $i=0;
        $total = count($products);
        echo 'Total watermarks to generate '.$total.PHP_EOL;
        foreach ($products as $p) {
            $i++;
            echo 'Generating '.$i.PHP_EOL;
            echo $this->applyWatermark($this->getProductImage($p['product_id']),$watermark);
            echo PHP_EOL;
        }
    }

    private function getPhotoId($filename,$product_id) {
        $watermark = trim(Mage::getStoreConfig("tragento/api/tragento-watermark"));
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $query = "SELECT photo_id FROM tragento_photo WHERE filename=:filename AND watermark=:watermark LIMIT 1";
        $photoId = (int)$db->fetchOne($query,array('filename'=>$filename,'watermark'=>$watermark));
        if ($photoId) return $photoId;
        $dir = Mage::getBaseDir('media').'/catalog/product';
        $filenameWithWatermark = $this->applyWatermark($filename,$watermark);
        if (!$filenameWithWatermark) {
            $this->tragentoLog(array('product_id'=>$product_id),'Can\'t find file to apply watermark','make_watermark_error');
            return false;
        }
        $photoId = $this->uploadPhoto($filenameWithWatermark,$product_id);
        if ($photoId) {
            $query = "INSERT INTO tragento_photo (`filename`, `watermark`, `photo_id`) VALUES (:filename,:watermark,:photo_id)";
            $db->query($query,array('filename'=>$filename,'watermark'=>$watermark,'photo_id'=>$photoId));
        }
        return $photoId;
    }

    private function uploadPhoto($filename,$product_id) {
        $response = $this->trademe->post('Photos::PhotoUploadRequest', array('FileName' => $filename));
        if ($response->is_ok()) {
            $photoId = $response->PhotoId;
            return (int)$photoId;
            //die($photoId.' photoId');
        }
        else {
            $d=array('product_id'=>$product_id);
            $this->tragentoLog($d,$response->error_message(),'upload_photo_error');
            //die($response->error_message());
        }
        return false;
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

    private function getCustomer($order) {
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

    private function getProductIdByListingId($listingId) {
        // listingId == item_id
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $query = "SELECT product_id FROM `tragento_product` WHERE item_id=:item_id LIMIT 1";
        $id = $db->fetchOne($query,array('item_id'=>$listingId));

        if (!$id) {
            // try to get from history
            $query = "SELECT product_id FROM `tragento_product_history` WHERE item_id=:item_id LIMIT 1";
            $id = $db->fetchOne($query,array('item_id'=>$listingId));
        }

        return $id;
    }

    private function getProduct($order) {
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

    public function tragentoLog($params,$message='',$type='error') {
        $data = array(
            'purchase_id' => 0,
            'listing_id' => 0,
            'order_id' => 0,
            'customer_id' => 0,
            'product_id' => 0,
            'message' => $message,
            'type' => $type,
            'queue_id' => 0,
            'queue_event_id' => 0
        );

        if (isset($params['purchase_id']) && $params['purchase_id']) $data['purchase_id']=$params['purchase_id'];
        if (isset($params['listing_id']) && $params['listing_id']) $data['listing_id']=$params['listing_id'];
        if (isset($params['order_id']) && $params['order_id']) $data['order_id']=$params['order_id'];
        if (isset($params['customer_id']) && $params['customer_id']) $data['customer_id']=$params['customer_id'];
        if (isset($params['product_id']) && $params['product_id']) $data['product_id']=$params['product_id'];

        if (isset($params['queue_id']) && $params['queue_id']) $data['queue_id']=$params['queue_id'];
        if (isset($params['queue_event_id']) && $params['queue_event_id']) $data['queue_event_id']=$params['queue_event_id'];

        if (!$data['queue_id'] && $this->queue_id) $data['queue_id'] = $this->queue_id;
        if (!$data['queue_event_id'] && $this->queue_event_id) $data['queue_event_id'] = $this->queue_event_id;
        if (!$data['product_id'] && $this->queue_product_id) $data['product_id'] = $this->queue_product_id;
        if (!$data['listing_id'] && $this->queue_trademe_item_id) $data['listing_id'] = $this->queue_trademe_item_id;

        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $query = "INSERT INTO `tragento_log` (`log_id`,`purchase_id`,`listing_id`,`order_id`,`customer_id`,`product_id`,`queue_id`,`queue_event_id`,`message`,`type`,`created_at`) VALUES (NULL,:purchase_id,:listing_id,:order_id,:customer_id,:product_id,:queue_id,:queue_event_id,:message,:type,NOW())";
        $db->query($query,$data);
    }

    private function checkListingExpire($tm_item_id,$product_id=false) {
        $d = $this->trademe->get_listing_for_edit($tm_item_id);
        if (!isset($d['EndDateTime'])) return;
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $MYSQL_DATE_FORMAT = "Y-m-d";
        $t = strtotime($d['EndDateTime']);
        $dt = date($MYSQL_DATE_FORMAT,$t);
        if (!$product_id) {
            $query = "UPDATE `tragento_product` SET tm_end_date=:tm_end_date WHERE item_id=:item_id LIMIT 1";
            $db->query($query,array(
                'item_id' => $tm_item_id,
                'tm_end_date' => $dt
            ));
        }
        else {
            // when product id set update record using product id in condition
            // checkListingExpire sometimes called before new item_id stored to tragento_product by product_id
            $query = "UPDATE `tragento_product` SET tm_end_date=:tm_end_date, item_id=:item_id WHERE product_id=:product_id LIMIT 1";
            $db->query($query,array(
                'product_id' => $product_id,
                'item_id' => $tm_item_id,
                'tm_end_date' => $dt
            ));
        }
        // compare EndDateTime with current date
        // if current date bigger than it's expired
        // make request to trademe anyway.
        // don't cache in mysql
        // maybe it was updated, relisted
        //$d['EndDateTime'];
        //print_r($d);
        //exit();
        // before relist check if it wasn't removed.

    }

    public function relistExpired($product_id, $trademe_id) {
        $d=array('product_id'=>$product_id);
        $this->tragentoLog($d,'call relistExpired function','relist_expired_event');
        $err = $this->relistProduct($trademe_id);
        if ($err=='error') {
            $item_id = (int)$this->listProduct($product_id);
            if (!$item_id) return;
            $db = Mage::getSingleton('core/resource')->getConnection('core_write');
            $query = "UPDATE `tragento_product` SET item_id=:item_id WHERE product_id=:product_id LIMIT 1";
            $db->query($query,array(
                'item_id' => $item_id,
                'product_id' => $product_id
            ));
            // called inside listProduct
            //$this->checkListingExpire($item_id);
        }

    }

    /*
     * It's too expensive on TradeMe API to call this function
     *
    public function updateListedExpireDates() {
        // make query to trademe and using api check if current product is listed
        // do it by listing id
        // but first get local list of listed products
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $status_listed = 2;
        // status = 2 means listed
        // refactor magic var
        $query = "SELECT * FROM tragento_product WHERE status=$status_listed";
        $a = $db->fetchAll($query);
        foreach ($a as $d) {
            // item_id = listing_id
            $this->checkListingExpire($d['item_id']);
        }
    }
    */

    public function checkListed() {
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $status_listed = 2;
        // get listed expired and relist them

        // maybe double check over api call if listing really expired. only for listings that expired from local data.
        // there could be situation when it can't relist
        // because it's not expired
        // track logs with errors from trademe on relist
        $query = "SELECT * FROM tragento_product WHERE status=$status_listed AND tm_end_date<NOW()";
        $a = $db->fetchAll($query);
        foreach ($a as $d) {
            $this->relistExpired($d['product_id'],$d['item_id']);
        }
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
            $message .= ' '.PHP_EOL.(string)$e;
            $this->tragentoLog($order,$message,'create_order_error');
        }
    }

    private function updateSaleIds($data) {
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $query = "UPDATE `tragento_sales` SET order_id=:order_id, customer_id=:customer_id, product_id=:product_id WHERE purchase_id=:purchase_id LIMIT 1";
        $db->query($query,$data);
    }

    private function cleanOrderLogs() {
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $query = "delete from tragento_log where type='create_order_error'";
        $db->query($query);
    }

    public function makeOrders() {
        // clean annoying old logs about orders because of missed link to product id
        $this->cleanOrderLogs();
        $r = $this->updateSales();
        if (!$r) return false;
        // select tragento_sales with order_id = 0
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $query = "SELECT * FROM `tragento_sales` WHERE order_id=0 ORDER BY purchase_id ASC";
        $orders = $db->fetchAll($query);
        foreach ($orders as $order) {
            $this->makeOrder($order);
        }
        return true;
    }

    public function updateSales() {
        $items = $this->getSoldItems();
        if (!$items) {
            return false;
        }
        foreach ($items as $item) {
            $this->newSale($item);
        }
        return true;
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
        // error
        $this->tragentoLog(array(),$response->error_message(),'get_sold_items_error');
        return false;
    }

    public function isAPICallLimitErr() {
        // in future after each call check error message
        // create implementation of this function inside gogoLib for trademe
        // it's easier to hook it there for api call limit checks
        // also if call limit occurs. create log at /var/tragento/apicalllimit.log
        // with dates. than when running new cron process check if date in call is less than 1 hour.
        // actually check if it is in current hour. if current hour than don't execute tragento cron
        // it's nice but requires coding time. don't overcomplicate error handling

        // $response->is_ok()
        // $response->error_message()
        // You have exceeded your API call quota for the current hour
        // it's a state for the life of trademe model. when api limit reached it can't be changed to ok later.
        // hovewer in next 5 minutes there will be another call from cron with clear state
        return $this->trademe->isAPICallLimitErr();
    }
}

// list of fields to reindex
// Ebay Item Number
//      get from m2e pro
// TradeMe Price NZ
//      get from product attribute
// Ebay Price AU
//      get from m2e pro
// Magento Store Price
//      get from product attribute
// TradeMe Shipping NZ
//      get from product attribute
// Ebay Shipping AU
//      get from m2e pro
// Last Trademe Sale
//      trademe sales tables... or from trademe
//      find this table
// Last Ebay Sale
//      from m2e pro sales
// Last Store Sale
//      from magento sales orders by product id
class Magemore_Tragento_Reindex_Product_Process {
    private $db;
    public function __construct() {
        //$def_store_id = Mage::app()
        //    ->getWebsite()
        //    ->getDefaultGroup()
        //    ->getDefaultStoreId();
        // it returns 1 but set 0 anyway
        $def_store_id = 0;
        Mage::app()->setCurrentStore($def_store_id);
        $store_id = Mage::app()->getStore()->getStoreId();
        $this->db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $query = "SELECT * FROM tragento_product";
        $a = $this->db->fetchAll($query);
        $ids = array();
        foreach ($a as $d) {
            $id = $d['product_id'];
            $ids[$id]=$id;
        }
        list($ebay_items,$ebay_marketplace,$ebay_account) = $this->getEbayItems($ids);
        $ebay_price = array();
        $ebay_shipping = array();
        $ebay_last_sale = $this->getEbayLastSale($ids);
        $ebay_last_status = $this->getEbayLastStatus($ids);
        $trademe_price = array();
        $trademe_shipping = array();
        $trademe_last_sale = $this->getTrademeLastSale($ids);
        $magento_price = array();
        $magento_last_sale = $this->getMagentoLastSale($ids);
        $error_log = array();
        $title_length = array(); // trademe title length
        $magento_status = array();

        // product ids
        foreach ($ids as $id) {
            $product = Mage::getModel('catalog/product');
            $product->load($id);
            $trademe_price[$id] = trim($product->getTrademeNzprice());
            $trademe_shipping[$id] = trim($product->getTrademeFreight());
            $magento_price[$id] = $product->getPrice();
            $ebay_shipping[$id] = trim($product->getEbayfreightsingleauspost());
            $trademe_ready[$id] = $this->checkTrademeReady($product);
            $title_length[$id] = $this->getTrademeTitleLength($product);
            $error_log[$id] = $this->getLastErrorLog($id);
            $magento_status[$id] = $product->getStatus();
            $ebay_price[$id] = trim($product->getEbayprice());
        }

        foreach ($ids as $id) {
            $data = array(
                'ebay_item_id' => '',
                'ebay_marketplace_id' => '',
                'ebay_account_id' => '',
                'ebay_price' => '',
                'ebay_shipping' => '',
                'ebay_last_sale' => '',
                'trademe_price' => '',
                'trademe_shipping' => '',
                'trademe_last_sale' => '',
                'magento_price' => '',
                'magento_last_sale' => '',
                'trademe_ready' => '',
                'title_length' => '',
                'error_log' => '',
                'product_id' => $id,
                'magento_status' => '',
                'ebay_status' => '',
                'max_last_sale' => '',
            );
            if (isset($ebay_items[$id])) $data['ebay_item_id'] = $ebay_items[$id];
            if (isset($ebay_marketplace[$id])) $data['ebay_marketplace_id'] = $ebay_marketplace[$id];
            if (isset($ebay_account[$id])) $data['ebay_account_id'] = $ebay_account[$id];
            if (isset($ebay_price[$id])) $data['ebay_price'] = $ebay_price[$id];
            if (isset($ebay_shipping[$id])) $data['ebay_shipping'] = $ebay_shipping[$id];
            if (isset($ebay_last_sale[$id])) $data['ebay_last_sale'] = $ebay_last_sale[$id];
            if (isset($trademe_price[$id])) $data['trademe_price'] = $trademe_price[$id];
            if (isset($trademe_shipping[$id])) $data['trademe_shipping'] = $trademe_shipping[$id];
            if (isset($trademe_last_sale[$id])) $data['trademe_last_sale'] = $trademe_last_sale[$id];
            if (isset($magento_price[$id])) $data['magento_price'] = $magento_price[$id];
            if (isset($magento_last_sale[$id])) $data['magento_last_sale'] = $magento_last_sale[$id];
            if (isset($trademe_ready[$id])) $data['trademe_ready'] = $trademe_ready[$id];
            if (isset($title_length[$id])) $data['title_length'] = $title_length[$id];
            if (isset($error_log[$id])) $data['error_log'] = $error_log[$id];
            if (isset($magento_status[$id])) $data['magento_status'] = $magento_status[$id];
            if (isset($ebay_last_status[$id])) $data['ebay_status'] = $ebay_last_status[$id];

            $max = $data['ebay_last_sale'];
            $imax = strtotime($data['ebay_last_sale']);
            if ($imax<strtotime($data['trademe_last_sale'])) {
                $imax = strtotime($data['trademe_last_sale']);
                $max = $data['trademe_last_sale'];
            }
            if ($imax<strtotime($data['magento_last_sale'])) {
                $imax = strtotime($data['magento_last_sale']);
                $max = $data['magento_last_sale'];
            }
            $data['max_last_sale']=$max;

            $set = array();
            foreach ($data as $i => $d) {
                if ($i=='product_id') continue;
                if ($d) {
                    $set[]='`'.$i.'`=:'.$i;
                }
                else {
                    unset($data[$i]);
                }
            }
            $set = implode(', ',$set);
            $query = "UPDATE `tragento_product` SET $set WHERE product_id=:product_id LIMIT 1";
            //echo $query.PHP_EOL;
            $this->db->query($query,$data);
        }
    }

    private function getLastErrorLog($id) {
        $query = "SELECT message FROM `tragento_log` WHERE product_id=$id ORDER BY log_id DESC LIMIT 1";
        return $this->db->fetchOne($query);
    }

    private function getTrademeTitleLength($product) {
        return strlen($product->getTrademeTitle());
    }

    private function checkTrademeReady($product) {
        $errors='';
        $empty = array();
        if (!trim($product->getTrademeCategory())) {
            $empty[]='Category';
        }
        if (!trim($product->getTrademeDescription())) {
            $empty[]='Description';
        }
        if (!trim($product->getTrademeFreight())) {
            $empty[]='Freight';
        }
        if (!trim($product->getTrademeNzprice())) {
            $empty[]='NZPrice';
        }
        if (!trim($product->getTrademeQty())) {
            $empty[]='Qty';
        }
        if (!trim($product->getTrademeTitle())) {
            $empty[]='Title';
        }
        if ($empty) {
            $errors.='empty: '.PHP_EOL.implode(', '.PHP_EOL,$empty);
        }
        $title_len = strlen($product->getTrademeTitle());
        if ($title_len>50) {
            $errors.=PHP_EOL.'error: Title>50'.PHP_EOL;
        }
        $errors=trim($errors);
        if (!$errors) return 'ready';
        return $errors;
    }

    private function getEbayItems($ids) {
        $ids = implode(',',$ids);
        $query = "SELECT product_id,item_id,marketplace_id,account_id FROM `m2epro_ebay_item` WHERE product_id IN ($ids) ORDER BY update_date DESC, item_id DESC";
        $items = $this->db->fetchAll($query);

        $ai = array();
        $am = array();
        $aa = array();
        foreach ($items as $d) {
            if (isset($ai[$d['product_id']])) continue;
            $ai[$d['product_id']]=$d['item_id'];
            $am[$d['product_id']]=$d['marketplace_id'];
            $aa[$d['product_id']]=$d['account_id'];
        }
        return array($ai,$am,$aa);
    }

    private function getEbayLastSale($ids) {
        $ids = implode(',',$ids);
        $query = "SELECT i.order_id, i.product_id, p.additional_data FROM sales_flat_order_payment p, sales_flat_order_item i WHERE p.parent_id = i.order_id AND p.method='m2epropayment' AND i.product_id IN ($ids) ORDER BY i.order_id DESC";
        $items = $this->db->fetchAll($query);

        $a = array();
        foreach ($items as $d) {
            if (isset($a[$d['product_id']])) continue;
            if (!$d['additional_data']) continue;
            $ad = unserialize($d['additional_data']);
            if (!isset($ad['component_mode'])) continue;
            if ($ad['component_mode']!='ebay') continue;
            if (!isset($ad['transactions'][0]['transaction_date'])) continue;
            $a[$d['product_id']]=$ad['transactions'][0]['transaction_date'];
        }
        return $a;
    }

    // it should get ebay status
    // but it has several listings with same product ids and different statuses
    // so return last updated status for given product id in any listing
    private function getEbayLastStatus($ids) {
        $ids = implode(',',$ids);
        // SELECT * FROM `m2epro_listing_product` WHERE product_id=1348 ORDER BY update_date DESC
        $query = "SELECT p.status, p.product_id FROM m2epro_listing_product p WHERE p.product_id IN ($ids) ORDER BY p.update_date DESC";
        $items = $this->db->fetchAll($query);

        $a = array();
        foreach ($items as $d) {
            if (isset($a[$d['product_id']])) continue;
            $a[$d['product_id']]=$d['status'];
        }
        return $a;
    }

    private function getTrademeLastSale($ids) {
        $ids = implode(',',$ids);
        $query = "SELECT product_id, sold_date FROM tragento_sales WHERE product_id IN ($ids) ORDER BY sold_date DESC";
        $items = $this->db->fetchAll($query);

        $a = array();
        foreach ($items as $d) {
            if (isset($a[$d['product_id']])) continue;
            $a[$d['product_id']]=$d['sold_date'];
        }
        return $a;
    }

    private function getMagentoLastSale($ids) {
        $ids = implode(',',$ids);
        // select all orders except m2epro and tragento
        $query = "SELECT i.product_id, i.order_id, o.updated_at, o.shipping_description, ts.order_id as ts_order_id FROM sales_flat_order_item i, sales_flat_order_payment p, sales_flat_order o LEFT JOIN tragento_sales ts ON ts.order_id=o.entity_id WHERE (o.status='complete' OR o.status='processing') AND o.entity_id=i.order_id AND p.parent_id = i.order_id AND p.method<>'m2epropayment' AND i.product_id IN ($ids) HAVING ts_order_id IS NULL ORDER BY o.updated_at DESC";
        $items = $this->db->fetchAll($query);

        $a = array();
        foreach ($items as $d) {
            if (isset($a[$d['product_id']])) continue;
            // it may have tragento orders not recorded inside tragento_sale table... due to development and truncated tragento_sale table.
            // double check by shipping method
            // just ignore test orders i have created before implementing tragento shipping method
            //echo $d['shipping_description'].PHP_EOL;
            //echo $d['updated_at'].PHP_EOL;
            //echo $d['product_id'].PHP_EOL;
            if (strpos($d['shipping_description'],'Tragento')!==FALSE) continue;
            $a[$d['product_id']]=$d['updated_at'];
        }
        return $a;
    }
}
