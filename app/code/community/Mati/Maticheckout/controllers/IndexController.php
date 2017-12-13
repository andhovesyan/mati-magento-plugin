<?php

class Mati_Maticheckout_IndexController extends Mage_Core_Controller_Front_Action
{

    public function indexAction()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Authorization');
        header('Access-Control-Allow-Methods: *');

        $version = Mage::getConfig()->getNode()->modules->Mati_Maticheckout->version;
        $token = Mage::getStoreConfig('payment/maticheckout/merchant_token');
        $this->getResponse()->setBody(json_encode([
            'version' => (string) $version,
            'configured' => !!$token,
        ]));
    }

    public function _isFormKeyEnabled()
    {
        return false;
    }
}
