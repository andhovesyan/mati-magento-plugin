<?php

class Mati_Maticheckout_Model_Method_Standard extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'mati_face';

    protected $_isInitializeNeeded      = true;
    // protected $_canUseCheckout          = true;
    protected $_canUseInternal          = false;
    protected $_canUseForMultishipping  = false;

    public function canUseCheckout()
    {
        return false;
    }
}
