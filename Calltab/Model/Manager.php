<?php

class Magemore_Calltab_Model_Manager
{

    public function getOptions() {
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $sq = "SELECT role_id FROM `admin_role` WHERE role_name='calltab_manager' LIMIT 1";
        $role_id = (int)$db->fetchOne($sq);
        $sq = "SELECT user_id FROM `admin_role` WHERE parent_id = $role_id";
        $ids = $db->fetchCol($sq);
        $mids = implode(',',$ids);

        $sq = "SELECT firstname, lastname, user_id FROM admin_user WHERE user_id IN ($mids) ORDER BY firstname, lastname";
        $managers = $db->fetchAll($sq);

        $options = array(0=>'-- Select Manager --');
        foreach ($managers as $d) {
            $options[$d['user_id']] = $d['firstname'].' '.$d['lastname'];
        }

        return $options;
    }

    public function getGridOptions() {
        $o = $this->getOptions();
        //$o[0]='';
        // unset because it adds empty automatically for grid columns filter
        unset($o[0]);
        return $o;
    }
    
    public function getAdminUserManagerId() {
        $id = Mage::getSingleton('admin/session')->getUser()->getUserId();
        $role = Mage::getModel('admin/user')->load($id)->getRole()->getData();
        if (!isset($role['role_name'])) return false;
        if ($role['role_name']=='calltab_manager') {
            return $id;
        }
        return false;
    }
    
    public function getCallTypeName($id) {
        $id = (int)$id;
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $sq = "SELECT name FROM call_type WHERE call_type_id=$id LIMIT 1";
        $name = $db->fetchOne($sq);
        return $name;
    }

}

