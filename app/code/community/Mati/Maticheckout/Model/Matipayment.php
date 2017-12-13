<?php

class Mati_Checkout_Model_Standard extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'mycheckout';

    protected $_isInitializeNeeded      = true;
    protected $_canUseInternal          = false;
    protected $_canUseForMultishipping  = false;

}
