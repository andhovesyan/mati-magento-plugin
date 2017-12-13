<?php

class Mati_Maticheckout_Block_Button extends Mage_Core_Block_Template
{

    private $quote;
    private $products;
    private $token;

    public function getProducts()
    {
        $nonShippableTypes = array('virtual', 'downloadable');
        if (!$this->products) {
            $this->products = array();
            $items = $this->getQuote()->getAllItems();
            foreach ($items as $item) {
                $this->products[] = array(
                    'label' => $item->getName(),
                    'description' => $item->getDescription() ? $item->getDescription() : '',
                    'price' => $item->getPrice(),
                    'sku' => $item->getSku(),
                    'quantity' => $item->getQty(),
                    'url' => $item->product->getProductUrl(),
                    'thumbnail' => $item->product->getImageUrl(),
                    'shipping' => !in_array($item->product->getTypeId(), $nonShippableTypes),
                );
            }
        }
        return $this->products;
    }

    public function getQuote()
    {
        if (!$this->quote) {
            $this->quote = Mage::getSingleton('checkout/session')->getQuote();
        }
        return $this->quote;
    }

    public function getGrandTotal()
    {
        $total = $this->getQuote()->getGrandTotal();
        if (!$total) {
            $total = 0;
        }
        return $total;
    }

    public function getSingleProductUrl()
    {
        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB)
        . 'mati_checkout/cart/addSingleProduct';
    }
}
