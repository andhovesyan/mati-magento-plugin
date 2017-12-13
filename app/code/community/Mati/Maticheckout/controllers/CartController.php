<?php

use Magento\Framework\Exception\InputException;

class Mati_Maticheckout_CartController extends Mage_Core_Controller_Front_Action
{
    public function addSingleProductAction()
    {
        $productId = (int) filter_input(INPUT_POST, 'product');
        $quantity = (int) filter_input(INPUT_POST, 'qty');
        $formKey = (string) filter_input(INPUT_POST, 'form_key');
        $options = filter_input(INPUT_POST, 'options', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);

        $cart = Mage::getSingleton('checkout/cart');

        try {

            $product = Mage::getModel('catalog/product')->load($productId);

            $request = new Varien_Object();
            $request->setData(array(
                'product' => $product->getId(),
                'qty' => $quantity,
                'form_key' => $formKey,
                'options' => $options,
            ));

            $cart->truncate();
            $cart->addProduct($product, $request);
            $cart->save();

            $cart = Mage::getModel('checkout/cart')->getQuote();

            $nonShippableTypes = array('virtual', 'downloadable');
            $item = $cart->getAllItems()[0];
            $product = array();
            $product = array(
                'label' => $item->getName(),
                'description' => $item->getDescription() ? $item->getDescription() : '',
                'price' => $item->getPrice(),
                'sku' => $item->getSku(),
                'quantity' => $item->getBuyRequest()->getQty(),
                // 'options' => $item->getBuyRequest()->getOptions(),
                'url' => $item->product->getProductUrl(),
                'thumbnail' => $item->product->getImageUrl(),
                'shipping' => !in_array($item->product->getTypeId(), $nonShippableTypes),
            );

            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array(
                'quoteId' => $cart->getId(),
                'total_price' => $cart->getGrandTotal(),
                'product' => $product,
            )));

        } catch (Exception $e) {
            $this->errorRespose($e);
        }
    }

    public function recoverAction()
    {
        $items = filter_input(INPUT_POST, 'items', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);

        try {
            $cart = Mage::getSingleton('checkout/cart');
            $cart->truncate();
            foreach ($items as $item) {
                $product = Mage::getModel('catalog/product')->load($item['product']);

                $request = new Varien_Object();
                $request->setData(array(
                    'product' => $product->getId(),
                    'qty' => $item['quantity'],
                    'form_key' => $item['form_key'],
                    'options' => $item['options'],
                ));

                $cart->addProduct($product, $request);
            }
            $cart->save();

            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array(
                'success' => true,
            )));
        } catch (Exception $e) {
            $this->errorRespose($e);
        }
    }

    protected function errorRespose($e)
    {
        $this->getResponse()->setHeader('HTTP/1.0', '422', true);
        $this->getResponse()->setBody(json_encode(array(
            'error' => $e->getMessage(),
        )));
    }
}
