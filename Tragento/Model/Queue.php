<?php

class Magemore_Tragento_Model_Queue extends Mage_Core_Model_Abstract {
    private $events = array();
    private $total = 0;
    private $errors = 0;
    private $done = 0;

    public function __construct() {
        $this->db = Mage::getSingleton('core/resource')->getConnection('core_write');
    }

    private function addTaskToEvent($task) {
        $event_id = $task['event_id'];
        $key='';
        if ($task['status']==Magemore_Tragento_Model_Product::TRAGENTO_QUEUE_STATUS_START) {
            $key = 'waiting';
        }
        else if ($task['status']==Magemore_Tragento_Model_Product::TRAGENTO_QUEUE_STATUS_PROCESSING) {
            $key = 'waiting';
        }
        else if ($task['status']==Magemore_Tragento_Model_Product::TRAGENTO_QUEUE_STATUS_SUCCESS) {
            $key = 'success';
        }
        else if ($task['status']==Magemore_Tragento_Model_Product::TRAGENTO_QUEUE_STATUS_ERROR) {
            $key = 'error';
        }
        else if ($task['status']==Magemore_Tragento_Model_Product::TRAGENTO_QUEUE_STATUS_DUPLICATE) {
            $key = 'duplicate';
        }
        if (!isset($this->events[$event_id][$key.'_ids'])) $this->events[$event_id][$key.'_ids'] = array();
        if (!isset($this->events[$event_id]['total_ids'])) $this->events[$event_id]['total_ids'] = array();

        $this->events[$event_id][$key.'_ids'][] = $task['product_id'];
        $this->events[$event_id]['total_ids'][] = $task['product_id'];
    }

    public function summarize() {
        $total = 0;
        $errors = 0;
        $wait = 0;
        $stats = array('waiting','error','duplicate','total','success');
        foreach ($this->events as $id => $event) {
            foreach ($stats as $stat) {
                $this->events[$id][$stat]=0;
                // duplicates checked when product added to queue
                if (isset($this->events[$id][$stat.'_ids'])) $this->events[$id][$stat]=count($this->events[$id][$stat.'_ids']);
            }
            if ($event['action']=='delist' && $this->events[$id]['error']) $this->events[$id]['status']='It\'s already not listed on TradeMe. Marked TM Status as "Not Listed". Click on error.';
            $total+=$this->events[$id]['total'];
            $errors+=$this->events[$id]['error'];
            $wait+=$this->events[$id]['waiting'];
        }
        $this->total = $total;
        $this->errors = $errors;
        $this->done = $total-$wait;
    }

    public function updateEvents() {
        $query = "SELECT * FROM tragento_queue_event ORDER BY id DESC LIMIT 5";
        $events = $this->db->fetchAll($query);
        $events = array_reverse($events);
        $ids = array();
        foreach ($events as $event) {
            $event['date']=$event['created_at'];
            // status is string, it may display api limit reached message... currently not used. but ui will display it.
            $event['status']='';
            unset($event['created_at']);
            unset($event['updated_at']);
            $event['event_id'] = $event['id'];
            unset($event['id']);
            // rename id to event_id. db has field id. but json uses event_id
            $this->events[$event['event_id']] = $event;
            $ids[]=$event['event_id'];
        }
        $ids = implode(',',$ids);

        $query = "SELECT * FROM tragento_queue WHERE event_id IN ($ids)";
        $tasks = $this->db->fetchAll($query);
        foreach ($tasks as $task) {
            $this->addTaskToEvent($task);
        }
        $this->summarize();
    }

    public function getJSON() {
        $this->updateEvents();
        // remove index from events to make it json array. not json object. so js array map function will work.
        $events = array();
        foreach ($this->events as $event) {
            // only if event has some tasks
            if ($event['total']) $events[]=$event;
        }
        $queue = array(
            'total' => $this->total,
            'errors' => $this->errors,
            'done' => $this->done,
            'events' => $events
        );
        return json_encode($queue);
    }
}
