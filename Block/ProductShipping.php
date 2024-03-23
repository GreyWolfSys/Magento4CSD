<?php

namespace Altitude\CSD\Block;

class ProductShipping extends \Magento\Framework\View\Element\Template
{
    public function getShippingDisplay()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');
        if ($customerSession->isLoggedIn()) {
            //
            $customer = $customerSession->getCustomer();
            $custno = $customer->getData('csd_custno');
            //$checkout_session_data =$customerSession->getQuote()->getData();
            // $orderid=$customerSession->getLastOrderId();
            // if (isset($customerSession->getQuote())){
            //    $quote = $customerSession->getQuote();
            //    $shipping_address =  $quote->getShippingAddress()->getData();
            //  } else {
            $shipping_address = $customer->getDefaultShippingAddress(); // = $quote->getShippingAddress()->getData();

            //  }
            if (!isset ($shipping_address["region_id"])) {
                return "";
            }
            $region = $objectManager->create('Magento\Directory\Model\Region')->load($shipping_address["region_id"]); // Region Id

            //$region = $objectManager->create('Magento\Directory\Model\Region')->loadByCode($shipping_address["statecd"], $countrycode);
            $statecd = $region->getData()['code'];

            $addressID = $shipping_address->getData("ERPAddressID");

            $address = '<div class="gw_address_block" style="padding-top: 10px;    padding-bottom: 10px;">';
            $address .= "<b>" . __("Customer ID") . ":</b> " . $custno . "<br />";
            $address .= "<h3 style='text-align:center; padding-top:5px; padding-bottom:0px;margin-bottom: 0px;'><b>" . __("Ship To Address") . "</b></h3>";
            $address .= $addressID . "</b><br />";
            $address .= $shipping_address->getCompany() . "<br />";
            $address .= $shipping_address["street"] . "<br />";
            $address .= $shipping_address->getCity() . ", ";
            $address .= $statecd . ", ";
            $address .= $shipping_address["postcode"] . "<br /><br/>";
            //$address .= $shipping_address["country_id"] . "<br />";//
            $address .= "<div style='text-align: center;'><a href='" . $this->escapeUrl($this->getUrl("customer/address/")) . "' style='background-color: #FFB400;    color: #333333;    border: 1px solid #FFB400; text-align:center;' class=' gw_button  primary tocart'><span>" . __("Change Ship To") . "</span></a></div>";
            $address .= '</div>';
            return $address;
            //return 'Hello World';
        }
    }
}