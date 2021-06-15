<?php

class Magemore_Tragento_Model_Product extends Mage_Core_Model_Abstract {

    const TRAGENTO_QUEUE_STATUS_START = 0;
    const TRAGENTO_QUEUE_STATUS_PROCESSING = 1;
    const TRAGENTO_QUEUE_STATUS_SUCCESS = 2;
    const TRAGENTO_QUEUE_STATUS_ERROR = 3;
    const TRAGENTO_QUEUE_STATUS_ERROR_API_CALL_LIMIT = 4;
    const TRAGENTO_QUEUE_STATUS_DUPLICATE = 5;

    const MAX_TASKS_LIMIT = 50;

    protected function _construct() {
        $this->_init('tragento/product');
        parent::_construct();
    }

    public function isListed($id) {
        $id = (int)$id;
        $statusListed = 2;
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $query = "SELECT item_id FROM tragento_product WHERE status=$statusListed AND product_id = $id LIMIT 1";
        $result = $db->fetchAll($query);
        return count($result)>0;
    }

    public function getListedItemId($id,$checkStatus=true) {
        $id = (int)$id;
        $statusListed = 2;
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $status = '';
        if ($checkStatus) {
            $status = "status=$statusListed AND";
        }
        $query = "SELECT item_id FROM tragento_product WHERE $status product_id = $id LIMIT 1";
        $result = $db->fetchOne($query);
        return $result;
    }

    // marks status processing as error when timeout > 3 minutes
    // maybe mark as timeout, not error for less confusion. when checking errors. and automatically restore on timeout.
    // make this call from same cron process
    public function checkProcessingQueueExpired($product_id,$action) {
        // if processing updated_at minutes > 3
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $data = array(
            'product_id'=>$product_id,
            'action'=>$action
        );
        $query = 'SELECT TIMESTAMPDIFF(MINUTE,updated_at,NOW()) as minutes_processing, id, event_id FROM `tragento_queue` WHERE product_id=:product_id AND action=:action AND status='.self::TRAGENTO_QUEUE_STATUS_PROCESSING.' HAVING minutes_processing>3;';
        $toolong = $db->fetchAll($query,$data);
        if ($toolong) {
            $trademe = Mage::getSingleton('Magemore_Tragento_Model_Trademe');
            foreach ($toolong as $task) {
                //$data['status'] = self::TRAGENTO_QUEUE_STATUS_ERROR;
                //$query = 'UPDATE `tragento_queue` SET status=:status WHERE product_id=:product_id AND action=:action AND status='.self::TRAGENTO_QUEUE_STATUS_PROCESSING;
                //$db->query($query,$data);
                $data['queue_event_id']=$task['event_id'];
                $data['queue_id'] = $task['id'];
                // it's better to restart them from processing
                $this->setQueueStatus($task['id'],self::TRAGENTO_QUEUE_STATUS_START);
                //$this->setQueueStatus($task['id'],self::TRAGENTO_QUEUE_STATUS_ERROR);
                $trademe->tragentoLog($data,'Queue Processing Expired. Something interrupted cron processing queue task to '.$action,$action.'_product_error. It\'s restarted');
            }
        }
    }

    public function isAlreadyInQueue($action,$product_id,$trademe_item_id=0) {
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $data = array(
            'product_id'=>$product_id,
            'action'=>$action
        );
        $query = 'SELECT COUNT(*) FROM tragento_queue WHERE (status='.self::TRAGENTO_QUEUE_STATUS_START.' OR status='.self::TRAGENTO_QUEUE_STATUS_PROCESSING.') AND product_id=:product_id AND action=:action';
        if ($trademe_item_id) {
            $data['trademe_item_id'] = $trademe_item_id;
            $query.=' AND trademe_item_id=:trademe_item_id';
        }
        $count = $db->fetchOne($query,$data);
        return $count>0;
    }

    public function insertDBQueue($data) {
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $query = "INSERT INTO `tragento_queue` (event_id,product_id,action,trademe_item_id,status,created_at,updated_at) VALUES(:event_id,:product_id,:action,:trademe_item_id,:status,NOW(),NOW())";
        $db->query($query,$data);
        return $db->lastInsertId();
    }

    public function insertDBQueueEvent($action) {
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $data = array('action'=>$action);
        $query = "INSERT INTO `tragento_queue_event` (action,created_at,updated_at) VALUES(:action,NOW(),NOW())";
        $db->query($query,$data);
        return $db->lastInsertId();
    }

    public function addQueueProduct($event_id,$action,$product_id) {


        // if it already has same product id with processing status but it was more than 3 minutes ago than mark it as expired ERROR and tragento log
        $this->checkProcessingQueueExpired($product_id,$action);

        $data = array(
            'event_id' => $event_id,
            'product_id' => $product_id,
            'action' => $action,
            'status' => self::TRAGENTO_QUEUE_STATUS_START,
            'trademe_item_id' => $this->getListedItemId($product_id,false)
        );

        // there could be another queue with same action and product id
        // also delisting listed by idea should cancel still waiting to list product
        // but it's time consuming to write such rules and such situations rarely occur
        if ($this->isAlreadyInQueue($action,$product_id,$data['trademe_item_id'])) {
            $data['status'] = self::TRAGENTO_QUEUE_STATUS_DUPLICATE;
        }
        // add with start status to queue
        $this->insertDBQueue($data);
    }

    public function addQueueEvent($action,$ids) {
        // make new event db record
        // on frontend tasks named events... rename them to task
        // it's usually named task, not event.
        $event_id = $this->insertDBQueueEvent($action);
        foreach ($ids as $product_id) {
            $this->addQueueProduct($event_id,$action,$product_id);
        }
    }

     // id = queue id
    public function setQueueStatus($id,$status,$time_update=true) {
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $data = array(
            'id' => $id,
            'status' => $status
        );
        $updated = '';
        if ($time_update) {
            $updated = ', updated_at=NOW()';
        }
        $query = "UPDATE `tragento_queue` SET status=:status $updated WHERE id=:id LIMIT 1";
        $db->query($query,$data);
    }

    // task is tragento_queue row an array containing: id (queue_id), product_id
    public function processQueueTask($task) {
        $trademe = Mage::getSingleton('Magemore_Tragento_Model_Trademe');
        // for tragento log
        $trademe->queue_id = $task['id'];
        $trademe->queue_event_id = $task['event_id'];
        $trademe->queue_product_id = $task['product_id'];
        $trademe->queue_trademe_item_id = $task['trademe_item_id'];

        // update queue status to processing
        $this->setQueueStatus($task['id'],self::TRAGENTO_QUEUE_STATUS_PROCESSING);

        $success = false;
        if ($task['action']=='list') {
            $success = $this->listProduct($task['product_id']);
        }
        else if ($task['action']=='delist') {
            $success = $this->delistProduct($task['product_id'],$task['trademe_item_id']);
        }
        else if ($task['action']=='revise') {
            $success = $this->reviseProduct($task['product_id'],$task['trademe_item_id']);
        }
        else if ($task['action']=='remove') {
            $success = $this->removeProduct($task['product_id'],$task['trademe_item_id']);
        }

        if ($success) {
            $this->setQueueStatus($task['id'],self::TRAGENTO_QUEUE_STATUS_SUCCESS);
        }
        else {
            // check if error about api limit. than set it to TRAGENTO_QUEUE_STATUS_ERROR_API_CALL_LIMIT
            // and set status of singleton to API_CALL_LIMIT so it will break execution and wait
            // maybe don't update queue status. on api limit. just set it back to start. because processing means it's busy.
            // in rare cases there could be several cron processes executing same time
            // or does magento check this? about cron running in several parallel processes
            // check api limit and set variable
            if ($this->isAPICallLimitErr()) {
                $this->setQueueStatus($task['id'],self::TRAGENTO_QUEUE_STATUS_START);
            }
            $this->setQueueStatus($task['id'],self::TRAGENTO_QUEUE_STATUS_ERROR);
        }
    }


    public function getNextQueueTask() {
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $query = "SELECT * FROM `tragento_queue` WHERE status=".self::TRAGENTO_QUEUE_STATUS_START." ORDER BY id LIMIT 1";
        $tasks = $db->fetchAll($query);
        if (!$tasks) {
            // empty queue tasks waiting
            return false;
        }
        return $tasks[0];
    }

    public function isAPICallLimitErr() {
        $trademe = Mage::getSingleton('Magemore_Tragento_Model_Trademe');
        return $trademe->isAPICallLimitErr();
    }

    public function processQueueCron() {
        set_time_limit(0);
        // @todo: check how logs look when trademe sends message about api limit
        // process tasks one by one querying from database. because it may have several cron processes same time. if cron runs each minute
        // but it has really low chance of running in parallel
        for ($i=0;$i<self::MAX_TASKS_LIMIT;$i++) {
            // if trademe api call limit reached
            if ($this->isAPICallLimitErr()) return false;

            $task = $this->getNextQueueTask();
            if (!$task) {
                // no task in queue
                return false;
            }
            $this->processQueueTask($task);
            //sleep(1);
        }
        return true;
    }

    public function listProduct($id) {
        // magic var for listed status
        $statusNotListed = 0;
        $statusListed = 2;
        $id = (int)$id;
        $listed = $this->isListed($id);
        // check staus = false. because it's checked manually with $listed var
        $listed_trademe_id = $this->getListedItemId($id,false);
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');

        // check if it's already listed
        // if no trademe_id then it's not actually listed. just status listed in magento
        if ($listed && $listed_trademe_id) return;

        $trademe = Mage::getSingleton('Magemore_Tragento_Model_Trademe');
        // try to relist if expired
        if ($listed_trademe_id) {
            $msg = $trademe->relistProduct($listed_trademe_id);
            $withdrawnErr = false;
            // You cannot relist a listing that was withdrawn by the seller.
            if ((strpos($msg,'cannot')!==FALSE) && (strpos($msg,'withdrawn')!==FALSE)) {
                $withdrawnErr = true;
            }
            if ($msg=='success') {
                // update status on success
                $query = "UPDATE tragento_product SET status=$statusListed, action_time=NOW() WHERE product_id = $id LIMIT 1";
                $db->query($query);

                // revise if relist is success
                $trademe->reviseProduct($id,$listed_trademe_id);

                // todo: also revise ptoduct
                // or use relist edit
                // Selling/RelistWithEdits
                return true;
            }
            if ($withdrawnErr) {
                // remove listing id
                $itemId = 0;
                $query = "UPDATE tragento_product SET status=$statusNotListed, item_id=$itemId, action_time=NOW() WHERE product_id = $id LIMIT 1";
                $db->query($query);
                $listed_trademe_id = 0;
            }
        }
        if (!$listed_trademe_id) {
            // send api request to trademe to list it
            $itemId = $trademe->listProduct($id);
            // handle error here. or inside listProduct. but it needs to know about API LIMIT errors so
            // $trademe->isAPICallLimitErr();


            // update item_id
            if ($itemId) {
                $query = "UPDATE tragento_product SET status=$statusListed, item_id=$itemId, action_time=NOW() WHERE product_id = $id LIMIT 1";
                $db->query($query);
                return true;
            }
        }
        // if result is true than it was listed
        return false;
    }

    public function delistProduct($product_id,$queue_trademe_item_id=0) {
        $product_id = (int)$product_id;
        if ($queue_trademe_item_id) {
            $listed = true;
            $listed_trademe_id = $queue_trademe_item_id;
        }
        else {
            $listed = $this->isListed($product_id);
            // set false because sometimes it has listing item id set even when status is not listed
            $listed_trademe_id = $this->getListedItemId($product_id,false);
        }
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        if (!$listed) {
            // if listing item id not cleared when not listed
            if ($listed_trademe_id) {
                $this->clearProductListingId($product_id);
            }
            return true;
        }

        $result = true;
        // first try to make api call and than update status. if some error occurs it will probably be listed but status say delisted if update status first
        if ($listed_trademe_id) {
            // send api request to trademe to delist it
            $trademe = Mage::getSingleton('Magemore_Tragento_Model_Trademe');
            // here it should check result if delist was success. if error than it will return false
            $result = $trademe->delistProduct($listed_trademe_id,$product_id);

            if ($this->isAPICallLimitErr()) return false;
            //if (!$r) return false;
            // hard to decide what to return as error... it will delist anyway.
            // but if error occurs. than notify from queue about api call error.
            // but change trangeto listing status as delisted anyway
        }

        // magic var for not listed status
        $statusNotListed = 0;
        $query = "UPDATE tragento_product SET status=$statusNotListed, action_time=NOW() WHERE product_id = $product_id LIMIT 1";
        $db->query($query);

        $this->clearProductListingId($product_id);
        return $result;
    }

    public function clearProductListingId($product_id) {
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $query = "UPDATE tragento_product SET item_id=0, action_time=NOW() WHERE product_id = $product_id LIMIT 1";
        $db->query($query);
    }

    // don't use queue and trademe api to remove product. but add queue for delist
    // or use same as remove queue but delete product anyway first
    function removeDBProduct($id) {
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $query = "DELETE FROM tragento_product WHERE product_id = $id LIMIT 1";
        $db->query($query);
    }

    public function removeProduct($id,$queue_trademe_item_id=0) {
        $id = (int)$id;
        $r = $this->delistProduct($id,$queue_trademe_item_id);
        if ($this->isAPICallLimitErr()) return false;
        $this->removeDBProduct($id);
        return $r;
    }

    public function reviseProduct($product_id,$queue_trademe_item_id=0) {
        $product_id = (int)$product_id;
        $trademe = Mage::getSingleton('Magemore_Tragento_Model_Trademe');
        if ($queue_trademe_item_id) {
            $listed_trademe_id = $queue_trademe_item_id;
        }
        else {
            $listed_trademe_id = $this->getListedItemId($product_id,false);
        }
        if (!$listed_trademe_id) return false;
        return $trademe->reviseProduct($product_id, $listed_trademe_id);
    }
}
