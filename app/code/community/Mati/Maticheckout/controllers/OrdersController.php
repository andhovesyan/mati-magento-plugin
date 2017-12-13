<?php

class Mati_Maticheckout_OrdersController extends Mage_Core_Controller_Front_Action
{
    public function webhookAction()
    {
        $body = json_decode(file_get_contents('php://input'), true);
        switch ($body['eventName']) {
            case 'address_added':
                $this->getShippingMethods($body['data']['transfer']['meta']['quoteId'], $body['data']['address']);
                break;
            case 'transfer_confirmed':
                $this->confirm($body['data']['transfer']);
                break;
            default:
                $this->getResponse()->setBody(json_encode(array(
                    'success' => true
                )));
                break;
        }
    }

    public function getcompletedAction()
    {
        $session = $this->getOnepage()->getCheckout();
        $quote = $session->getQuote();
        $collection = Mage::getModel('sales/order')->getCollection();
        $collection->addFieldToFilter('quote_id', $quote->getId());
        $order = $collection->getFirstItem();
        if ($order->getId()) {
            $redirectUrl = $quote->getPayment()->getOrderPlaceRedirectUrl();
            $session->setLastOrderId($order->getId())
                ->setLastQuoteId($quote->getId())
                ->setLastSuccessQuoteId($quote->getId())
                ->setRedirectUrl($redirectUrl)
                ->setLastRealOrderId($order->getIncrementId());
            Mage::dispatchEvent(
                'checkout_submit_all_after',
                array('order' => $order, 'quote' => $quote, 'recurring_profiles' => array())
            );
            $quote->save();
            $this->getResponse()->setBody($order->getId());
            // echo $session->getLastSuccessOrderId();
        } else {
            $this->getResponse()->setBody('0');
        }
    }

    public function getOnepage()
    {
        return Mage::getSingleton('checkout/type_onepage');
    }

    public function confirm($data)
    {
        $websiteId = Mage::app()->getWebsite()->getId();
        $store = Mage::app()->getStore();

        $paymentMethod = 'mati_face';
        $quote = Mage::getModel('sales/quote')->load($data['meta']['quoteId']);

        $items = $quote->getAllItems();
        $quote->getShippingAddress()->setShippingMethod($data['shippingMethod']['code'])->save();
        $quote->getReservedOrderId();

        $quotePayment = $quote->getPayment();
        $quotePayment->setMethod($paymentMethod);
        $quote->setPayment($quotePayment);

        $converter = Mage::getSingleton('sales/convert_quote');
        $order = $converter->toOrder($quote)
            ->setShippingAddress($converter->addressToOrderAddress($quote->getShippingAddress()))
            ->setBillingAddress($converter->addressToOrderAddress($quote->getBillingAddress()));
        $order->setShippingMethod($data['shippingMethod']['code']);
        $t = $order->getShippingMethod();

        $order->setPayment($converter->paymentToOrderPayment($quotePayment));
        foreach ($items as $item) {
            $orderItem = $converter->itemToOrderItem($item);
            $options = array();
            if ($productOptions = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct())) {
                $options = $productOptions;
            }
            if ($addOptions = $item->getOptionByCode('additional_options')) {
                $options['additional_options'] = unserialize($addOptions->getValue());
            }
            if ($options) {
                $orderItem->setProductOptions($options);
            }
            if ($item->getParentItem()) {
                $orderItem->setParentItem($order->getItemByQuoteItemId($item->getParentItem()->getId()));
            }
            $order->addItem($orderItem);
        }
        $quote->collectTotals();
        $service = Mage::getModel('sales/service_quote', $quote);
        $service->submitAll();
        $order = $service->getOrder();
        $order->setCanShipPartiallyItem(false);
        $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        try {
            $order->save();
            $order = Mage::getModel('sales/order')->load($order->getId());
            $invoice = $order->prepareInvoice();

            if ($invoice->getTotalQty()) {
                $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
                $invoice->register();
                $transaction = Mage::getModel('core/resource_transaction');
                $transaction->addObject($invoice);
                $transaction->addObject($order);
                $transaction->save();
            }
            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, 'Paid with Mati');
            $order->save();
            foreach (Mage::getSingleton('checkout/session')->getQuote()->getItemsCollection() as $item) {
                Mage::getSingleton('checkout/cart')->removeItem($item->getId())->save();
            }
            $this->getResponse()->setBody($order->getId());
        } catch (Exception $e) {
            Mage::log($e->getMessage());
            Mage::log($e->getTraceAsString());
            $this->getResponse()->setBody("Exception:" . $e->getMessage());
        }
    }

    protected function countryToCode($countryName)
    {
        $countryCode = $countryName = strtolower($countryName);
        $codes = array(
            'us' => array('usa', 'united states'),
            'mx' => array('mexico'),
        );
        foreach ($codes as $code => $variants) {
            if (in_array($countryName, $variants)) {
                $countryCode = $code;
                break;
            }
        }
        return strtoupper($countryCode);
    }

    public function getShippingMethods($quoteId, $address)
    {
        $countryCode = $this->countryToCode($address['country']);

        $quote = Mage::getModel('sales/quote')->load($quoteId);
        $paymentMethod = 'mati_face';
        $items = $quote->getAllItems();
        $quote->reserveOrderId();

        $items = $quote->getAllItems();

        $weight = 0;
        foreach($items as $item) {
            $weight += ($item->getWeight() * $item->getQty()) ;
        }

        $region = Mage::getModel('directory/region')->loadByCode($address['state'], "US");

        $shippingAddress = Mage::getModel('sales/quote_address');
        $shippingAddress
            ->setFirstname($address['firstName'])
            ->setLastname($address['lastName'])
            ->setStreet($address['street'])
            ->setCity($address['city'])
            ->setCountryId($countryCode)
            ->setRegion($region->getDefaultName())
            ->setRegionId($region->getId())
            ->setPostcode($address['zipcode'])
            ->setTelephone($address['phone'])
            ->setCollectShippingRates(true);

        $quote->setBillingAddress($shippingAddress);

        $shippingAddress
            ->setSameAsBilling(1)
            ->setSaveInAddressBook(0)
            ->setCollectShippingRates(true)
            ->setFreeMethodWeight(0)
            ->setWeight($weight);
        $quote->setShippingAddress($shippingAddress);
        $quote->save();
        $quote->getShippingAddress()->collectShippingRates()->save();
        $shippingRates = $quote->getShippingAddress()->getGroupedAllShippingRates();
        $shippingMethods = array();
        foreach ($shippingRates as $code => $rates) {
            foreach ($rates as $rate) {
                $method = array(
                    'name' => Mage::getStoreConfig('carriers/'.$code.'/title') . ' (' . $rate->getMethodTitle() . ')',
                    'price' => array(
                        'value' => (float) $rate->getPrice(),
                        'currency' => 'USD',
                    ),
                    'code' => $rate->getCode(),
                );
                $shippingMethods[] = $method;
            }
        }
        $this->getResponse()->setHeader('HTTP/1.0', '200', true);
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody(json_encode(array(
            'shippingMethods' => $shippingMethods
        )));
    }
}
