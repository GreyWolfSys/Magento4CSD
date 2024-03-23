<?php

namespace Altitude\CSD\Observer;

use Magento\Framework\Event\ObserverInterface;

class SuccessCSD implements ObserverInterface
{
    protected $helper;

    protected $csd;

    protected $customerRepositoryInterface;

    public function __construct(
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepositoryInterface,
        \Altitude\CSD\Helper\Data $helper,
        \Altitude\CSD\Model\CSD $csd
    ) {
        $this->helper = $helper;
        $this->csd = $csd;
        $this->customerRepositoryInterface = $customerRepositoryInterface;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if ($this->helper->isActive()) {
            $order = $observer->getEvent()->getOrder();
            $itemsCollection = $order->getAllItems();
            $warehouses = $this->helper->getOrderWarehouses($itemsCollection);

            foreach ($warehouses as $whID => $items) {
                $this->sendERPOrder($order, $whID, $items);
            }
        }
    }

    public function sendERPOrder($order, $whID, $items)
    {
        $moduleName = $this->csd->getModuleName(get_class($this));
        $configs = $this->csd->getConfigValue([
            'apikey',
            'cono',
            'csdcustomerid',
            'whse',
            'shipto2erp',
            'slsrepin',
            'defaultterms',
            'operinit',
            'transtype',
            'shipviaty',
            'slsrepout',
            'holdifover',
            'shipto2erp',
            'potermscode',
            'sendtoerpinv',
            'orderaspo'
        ]);
        extract($configs);

        $orderincid = $order->getIncrementId();
        $orderid = $order->getId();
        $payment = $order->getPayment();
        $poNumber = $payment->getPoNumber();
        $method = $payment->getMethodInstance();
        $methodTitle = $method->getTitle();
        $methodcode = $payment->getMethod();

        $shipping_address = $order->getShippingAddress()->getData();
        $erpAddress = "";
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        try {
            $addressId = $shipping_address["customer_address_id"];
            $addressOBJ = $objectManager->get('\Magento\Customer\Api\AddressRepositoryInterface');
            $addressObject = $addressOBJ->getById($addressId);
            try {
                if (isset ($addressObject)) {
                    $erpAddress = $addressObject->getCustomAttribute("ERPAddressID");
                }
            } catch (\Exception $e2) {
                $erpAddress = "";
            }
        } catch (\Exception $e) {
            $erpAddress = "";
        }

        $billing_address = $order->getBillingAddress()->getData();
        #$items = $order->getAllVisibleItems();
        $total = $order->getGrandTotal();

        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = 'directory_country_region';

        $region = $objectManager->create('Magento\Directory\Model\Region')->load($shipping_address["region_id"]); // Region Id
        $statecd = $region->getData()['code'];

        $batchnm = substr(date("YmdHi") . rand(), -8);
        $custno = $csdcustomerid;

        $customerSession2 = $this->csd->getSession();
        $customerData = $customerSession2->getCustomer();
        $whse = $whID;

        if ($order->getCustomerIsGuest()) {
            $custno = $csdcustomerid;
        } else {
            if ($order->getCustomerId()) {
                $customer = $this->_customerRepositoryInterface->getById($order->getCustomerId());
                $custno = $customer->getData('csd_custno');
            } else {
                $custno = $csdcustomerid;
            }

            if (!$custno) {
                $custno = $csdcustomerid;
            }
        }

        if ($erpAddress == "") {
            $shipto = $batchnm;
        } else {
            $shipto = $erpAddress;
        }

        $TermsToUse = $defaultterms;
        if ($methodcode == "purchaseorder") {
            if (isset ($potermscode)) {
                $TermsToUse = $potermscode;
            }
        } elseif ($methodcode == "cashondelivery") {
            $TermsToUse = "cod";
        } elseif (stripos($methodcode, "credit") !== false) {
            $TermsToUse = "cc";
        }

        //change shipviaty ****************************************************************
        $shippingMethod = $order->getShippingMethod();
        //error_log($shippingMethod);
        if (strpos($shippingMethod, "_") !== false) {
            $shippingMethod = substr($shippingMethod, 0, stripos($shippingMethod, '_'));
            $shipviaty = $shippingMethod;
        }

        $shipviaty = "fedx";

        if ($erpAddress == "" && $shipto2erp == "1") {
            $gcnlship = $this->csd->SalesShipToInsertUpdate(
                $custno,
                $shipto,
                $shipping_address,
                $shipping_address["email"],
                $moduleName
            );
        }

        $placedby = $slsrepin;
        $takenby = $slsrepin;
        $custpo = ($orderaspo == 1) ? $orderincid : $poNumber;

        $shiptonm = $name;
        $shiptoaddr1 = $addr1;
        $shiptoaddr2 = $addr2;
        $shiptocity = $city;
        $shiptost = $statecd;
        $shiptozip = $zipcd;
        $enterdt = date("m/d/Y");
        $transdt = date("m/d/Y");
        $terms = $TermsToUse;
        $printprice = 'y';
        $wodiscamt = 0;
        $wodiscpct = 0;
        $createsalesorderlinesyesno = 'yes';
        $user6 = 0;
        $user7 = 0;
        $user24 = '';

        if ($holdifover != "" && $total > $holdifover) {
            $user24 = 'h';
        }

        $lineno = '1';
        $price = 0;
        $qtyord = 1;
        $shipprod = '';
        $enterdt = '';
        $unit = 'ea';

        $paramsHead = new \ArrayObject();//(object)array();
        $paramsHead[] = new \SoapVar($cono, XSD_DECIMAL, null, null, 'cono');
        $paramsHead[] = new \SoapVar($operinit, XSD_STRING, null, null, 'operinit');
        $paramsHead[] = new \SoapVar($transtype, XSD_STRING, null, null, 'transtype');
        $paramsHead[] = new \SoapVar($batchnm, XSD_STRING, null, null, 'batchnm');
        $paramsHead[] = new \SoapVar($whse, XSD_STRING, null, null, 'whse');
        $paramsHead[] = new \SoapVar($custno, XSD_DECIMAL, null, null, 'custno');
        $paramsHead[] = new \SoapVar($slsrepin, XSD_STRING, null, null, 'slsrepin');
        $paramsHead[] = new \SoapVar($wodiscamt, XSD_DECIMAL, null, null, 'wodiscamt');
        $paramsHead[] = new \SoapVar($wodiscpct, XSD_DECIMAL, null, null, 'wodiscpct');
        $paramsHead[] = new \SoapVar($createsalesorderlinesyesno, XSD_STRING, null, null, 'createsalesorderlinesyesno');
        $paramsHead[] = new \SoapVar($user6, XSD_DECIMAL, null, null, 'user6');
        $paramsHead[] = new \SoapVar($user7, XSD_DECIMAL, null, null, 'user7');
        $paramsHead[] = new \SoapVar($apikey, XSD_STRING, null, null, 'APIKey');
        $paramsHead[] = new \SoapVar($placedby, XSD_STRING, null, null, 'placedby');
        $paramsHead[] = new \SoapVar($takenby, XSD_STRING, null, null, 'takenby');
        $paramsHead[] = new \SoapVar($custpo, XSD_STRING, null, null, 'custpo');

        if ($erpAddress == "" && $shipto2erp == "1") {
            $paramsHead[] = new \SoapVar($shipto, XSD_STRING, null, null, 'shipto');
        }

        $paramsHead[] = new \SoapVar($shipviaty, XSD_STRING, null, null, 'shipviaty');
        $paramsHead[] = new \SoapVar($shiptonm, XSD_STRING, null, null, 'shiptonm');
        $paramsHead[] = new \SoapVar($shiptoaddr1, XSD_STRING, null, null, 'shiptoaddr1');
        $paramsHead[] = new \SoapVar($shiptocity, XSD_STRING, null, null, 'shiptocity');
        $paramsHead[] = new \SoapVar($shiptost, XSD_STRING, null, null, 'shiptost');
        $paramsHead[] = new \SoapVar($shiptozip, XSD_STRING, null, null, 'shiptozip');
        $paramsHead[] = new \SoapVar($enterdt, XSD_STRING, null, null, 'enterdt');
        $paramsHead[] = new \SoapVar($transdt, XSD_STRING, null, null, 'transdt');
        $paramsHead[] = new \SoapVar($terms, XSD_STRING, null, null, 'terms');
        $paramsHead[] = new \SoapVar($printprice, XSD_STRING, null, null, 'printprice');
        $paramsHead[] = new \SoapVar($slsrepout, XSD_STRING, null, null, 'slsrepout');

        if ($user24 != '') {
            $paramsHead[] = new \SoapVar($user24, XSD_STRING, null, null, 'user24');
        }

        $lineno = 0;
        foreach ($items as $item) {
            if ($sendtoerpinv == "1") {
                if ($item->getParentItemId()) {
                    continue;
                }
            }

            $lineno++;
            $name = $item->getName();
            $type = $item->getSku();
            $id = $item->getProductId();
            $qty = $item->getQtyOrdered();
            $price = $item->getPrice();

            try {
                $getunit = $this->csd->ItemsProductSelect($cono, $type, '', '', '', $moduleName);

                if (isset ($getunit["unitstock"])) {
                    $unit = $getunit["unitstock"];
                } else {
                    $unit = 'ea';
                }
            } catch (\Exception $eUnit) {
                $unit = 'ea';
            }

            if (!isset ($unit)) {
                $unit = 'ea';
            }

            $description = $item["description"];
            $paramsDetail = new \ArrayObject();
            $paramsDetail[] = new \SoapVar($lineno, XSD_DECIMAL, null, null, 'lineno');
            $paramsDetail[] = new \SoapVar($qty, XSD_DECIMAL, null, null, 'qtyord');
            $paramsDetail[] = new \SoapVar($type, XSD_STRING, null, null, 'shipprod');
            $paramsDetail[] = new \SoapVar($unit, XSD_STRING, null, null, 'unit');
            $paramsDetail[] = new \SoapVar($price, XSD_STRING, null, null, 'price');
            $paramsHead->append(new \SoapVar($paramsDetail, SOAP_ENC_OBJECT, null, null, 'SalesOrderLinesInsertRequestContainer'));

            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "paramsHead request: " . json_encode($paramsHead));
        }

        $gcnl = $this->csd->SalesOrderInsert($paramsHead, $moduleName);

        if (isset ($_SESSION["cpReferenceNumber"])) {
            $this->csd->UpdateOrderWithCCAuthNo($order, $_SESSION["cpAutorizationNumber"], $moduleName);
        }

        if (isset ($_SESSION["CCSaveFields"])) {
            $this->csd->UpdateOrderWithRapidConnectInfo($order, $moduleName);
        }

        if (isset ($gcnl["ordernumber"])) {
            $orderno = $gcnl["ordernumber"];

            if ($orderno != "0") {
                $extOrderId = $order->getExtOrderId();

                if ($extOrderId != "") {
                    $extOrderId .= ",$orderno";
                } else {
                    $extOrderId = $orderno;
                }

                $order = $objectManager->create('Magento\Sales\Model\Order')->load($order->getId());
                $order->setExtOrderId($extOrderId)->save();
            }
        }
    }
}
