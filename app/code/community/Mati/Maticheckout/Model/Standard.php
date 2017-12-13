<?php

class Mati_Maticheckout_Model_Standard extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'mati_face';

    protected $_isInitializeNeeded      = true;
    protected $_canUseInternal          = false;
    protected $_canUseForMultishipping  = false;

}
