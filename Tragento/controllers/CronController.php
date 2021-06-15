<?php


class Magemore_Tragento_CronController extends Mage_Core_Controller_Front_Action
{

    public function indexAction()
    {
        header('Connection: Close');
        header('Content-Length: 2');
        echo 'ok';
        flush();

        exit();
    }

}
