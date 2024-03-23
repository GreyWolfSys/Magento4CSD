<?php

namespace Altitude\CSD\Observer;

use Magento\Framework\Event\ObserverInterface;

class CustomerLogin implements ObserverInterface
{
    private $csd;

    private $customerFactory;

    private $addressFactory;

    private $regionFactory;
    private $_customerRepositoryInterface;

    public function __construct(
        \Altitude\CSD\Model\CSD $csd,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Model\AddressFactory $addressFactory,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepositoryInterface
    ) {
        $this->csd = $csd;
        $this->customerFactory = $customerFactory;
        $this->addressFactory = $addressFactory;
        $this->regionFactory = $regionFactory;
        $this->_customerRepositoryInterface = $customerRepositoryInterface;
    }

    public function ProcessAddress($item, $customer, $addrSet)
    {
        if (isset ($item["errordesc"]) && $item["errordesc"] != "") {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Error " . $item["errordesc"]);
            return "";
        }
        if (isset ($item["phoneno"])) {
            $phone = $item["phoneno"];
            if (strlen($phone) < 1) {
                $phone = "1112223333";
            }
        } else {
            $phone = "1112223333";
        }

        //   try {
        unset($address);

        try {
            //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , " Looping addresses");
            foreach ($customer->getAddresses() as $address1) {
                $erp = $address1->getData("ERPAddressID");
                if ($erp == $item["shipto"]) {
                    $address = $address1;
                    break;
                }
            }
        } catch (\Exception $e) {
            $this->csd->gwLog(json_encode($e->getMessage()));
        }

        if (!empty ($item["countrycd"])) {
            $countrycode = $item["countrycd"];
        } else {
            $countrycode = "US";
        }

        //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , " Address - getting region from erp addr $countrycode");

        /*    try {
               $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
               $region = $objectManager->create('Magento\Directory\Model\Region')->loadByCode($item["state"], $countrycode);
           } catch (Exception $exregion) {
               $this->csd->gwLog(json_encode($exregion->getMessage()));

           }*/

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        //$_regionFactory = $objectManager->get('Magento\Directory\Model\RegionFactory');
        //$regionModel = $_regionFactory->create();
        //$region = $_regionFactory->loadByCode($addressData["region_id"], $countrycode);

        // if (empty($region->getId())) {
        $region = $objectManager->create('Magento\Directory\Model\Region')->loadByCode($item["statecd"], $countrycode); //->load($item["statecd"]); // Region Id

        // $region = $this->regionFactory->loadByCode($addressData["region_id"], $countrycode);
        $regionId = $region->getId();
        //  } else {
        //      $regionId = $region->getId();
        $statecd = $regionId;


        //  $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "s state= " . $item["statecd"]);

        // $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "s regionid= " . $regionId );

        // $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "s statecd " . $statecd);
        //  }
//ob_start();
//var_dump($region);
//$result = ob_get_clean();
        //$this->csd->gwLog($result);
        //$this->csd->gwLog($regionId);
        //$addressData['state'] = $regionId;

        if (!isset ($address)) {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", " No address found for " . $item["shipto"]);
            $address = $this->addressFactory->create();
        }


        $address->setCustomerId($customer->getId());
        $address->setFirstname($customer->getFirstname());
        $address->setLastname($customer->getLastname());
        $address->setStreet([$item["addr1"], $item["addr2"]]);
        $address->setCompany($item["name"]);
        $address->setCity($item["city"]);
        $address->setRegionId($statecd);
        $address->setPostcode($item["zipcd"]);
        $address->setCountryId($countrycode);
        $address->setTelephone($phone);
        $address->setFax($item["faxphoneno"]);
        //$address->setIsDefaultBilling('0');
        //$address->setIsDefaultShipping('0');
        if ($addrSet) {
            //$address->setIsDefaultBilling('0');
        } else {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "2 setting default billing: " . $item["addr1"]);
            $address->setIsDefaultBilling('1');
            $addrSet = true;
        }
        if ($addrSet) {
            //$address->setIsDefaultShipping('0');
        } else {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "2 setting default shipping: " . $item["addr1"]);
            $address->setIsDefaultShipping('1');
            $addrSet = true;
        }
        $address->setSaveInAddressBook('1');
        $address->SetData("ERPAddressID", $item["shipto"]);
        $address->save();

        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", " shipto=" . $address->getData("ERPAddressID") . " and " . $item["shipto"]);
        //   } catch (\Exception $e) {
        //       $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "ERROR");
        //       $this->csd->gwLog(json_encode($e->getMessage()));
        //   }

    }
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        //error_log ("Address import check");
        //$disableAddressImport = $this->csd->getConfigValue('defaults/gwcustomer/disable_address_import');
        //if ($disableAddressImport) {
        //    return;
        //}
        //error_log("address import");
        // $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Running address import into Altitude");
        // $debug_export = var_export($observer, true);
        // $this->csd->gwLog($debug_export);

        $moduleName = $this->csd->getModuleName(get_class($this));
        $configs = $this->csd->getConfigValue(['cono', 'csdcustomerid', 'importshipto', 'defaultcurrency']);
        extract($configs);

        if (isset ($importshipto)) {
            if ($importshipto != "1") {
                //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "address import diallowedallowed, exiting");
                return;
            }
        }
        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "address import allowed, continuing");
        $customerSession = $this->csd->getSession();
        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "$moduleName: Check if logged in");

        if ($customerSession->isLoggedIn()) {
            $customerData = $customerSession->getCustomer();

            $customer = $customerSession->getCustomer();
            $cust = $customerSession->getCustomerData();

            $csdcustno = $customerData['csd_custno'];
            $cattrValue = $customer->getCustomAttribute('CSD_CustNo');

            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "$moduleName: Customer logged in!");

            if ($csdcustno == "" || $csdcustno == "0") {
                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "$moduleName: CSD_Custno is empty or zero. Exit.");

                return;
            }

            try {
                $GCCust = $this->csd->SalesCustomerSelect($cono, $csdcustno, $moduleName);

                if (isset ($GCCust)) {
                    $_customer = $this->customerFactory->create();
                    $_customer->load($customer->getId());

                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "setting warehouse to " . $GCCust["whse"]);
                    // $customerData = $this->customerFactory->create()->load($customer->getId())->getDataModel();
                    // $customerData->setCustomAttribute('warehouse', $GCCust["whse"]);
                    // $this->_customerRepositoryInterface->save($customerData);

                    $_customer->setData('erpshipvia', $GCCust["shipviaty"]);
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "setting warehouse now");
                    //$_customer->setData('warehouse', 'xx');

                    $_customer->setData('whse', $GCCust["whse"] . "");
                    $_customer->setData('taxabletype', $GCCust["taxabletype"] . "");
                    $_customer->setData('erpshipviadesc', $GCCust["shipviatydesc"]);

                    // $objectManager = \Magento\Framework\App\ObjectManager::getInstance();        
                    // $customer2 = $objectManager->create('Magento\Customer\Model\Customer')->load($customer->getId());
                    // $warehouse = $customer2->getData('whse');
                    //    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "cust  warehouse is " . $warehouse);

                    if (isset ($defaultcurrency)) {
                        $_customer->setData('currencyty', $GCCust["currencyty"]);

                        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                        $storeManager = $objectManager->get(\Magento\Store\Model\StoreManagerInterface::class);
                        if ($GCCust["currencyty"] == "US" || $GCCust["currencyty"] == "US") {
                            $currency = "USD";
                        } elseif ($GCCust["currencyty"] == "CA" || $GCCust["currencyty"] == "CAD") {
                            $currency = "CAD";
                        } elseif ($GCCust["currencyty"] == "EU" || $GCCust["currencyty"] == "EUR") {
                            $currency = "EUR";
                        } else {
                            $currency = $defaultcurrency; // set currency code which you want to set //set this to default currency setting...new setting
                        }
                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Setting currency to " . $currency);
                        if ($currency) {
                            $storeManager->getStore()->setCurrentCurrencyCode($currency);
                        }
                    }

                    /* try {
                         if (!empty($GCCust["user6"])){
                             $_customer->setData('moq_switch', 1);
                             $_customer->setData('moq_value', $GCCust["user6"]);
                         }
                     } catch (\Exception $e1) {
                         $this->csd->gwLog(json_encode($e1->getMessage()));
                     }*/
                    $_customer->save();
                }
            } catch (\Exception $e) {
                $this->csd->gwLog(json_encode($e->getMessage()));
            }
        } else {
            $csdcustno = "";
            $customer = $observer->getEvent()->getCustomer();
        }

        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "$moduleName: Default cust no: $csdcustomerid");
        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "$moduleName: Cust no: $csdcustno");
        //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , " checking if should check addresses for arsc");
        if ($customerSession->isLoggedIn() && $csdcustno != $csdcustomerid) {
            //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , " checking addresses for arsc");
            try {
                if (isset ($GCCust["addr1"])) {
                    //put arsc address in addressbook
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", " we have an address for arsc");
                    try {
                        //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , " Looping addresses for arsc check");
                        foreach ($customer->getAddresses() as $address1) {
                            $erp = $address1->getData("ERPAddressID");
                            if (empty ($erp)) {
                                //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , " Found existing arsc address");
                                $address = $address1;
                                break;
                            }
                        }
                    } catch (\Exception $e) {
                        $this->csd->gwLog(json_encode($e->getMessage()));
                    }

                    if (isset ($GCCust["phoneno"])) {
                        $phone = $GCCust["phoneno"];
                        if (strlen($phone) < 1) {
                            $phone = "1112223333";
                        }
                    } else {
                        $phone = "1112223333";
                    }



                    if (!empty ($GCCust["countrycd"])) {
                        $countrycode = $GCCust["countrycd"];
                    } else {
                        $countrycode = "US";
                    }
                    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                    //  $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "state= " . $GCCust["state"]);
                    $region = $objectManager->create('Magento\Directory\Model\Region')->loadByCode($GCCust["state"], $countrycode); //->load($item["statecd"]); // Region Id
                    $regionId = $region->getId();
                    //   $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "regionid= " . $regionId );
                    $statecd = $regionId;
                    //   $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "statecd " . $statecd);
                    //////////////
                    if (!isset ($address)) {
                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", " No address found for " . $GCCust["custno"]);
                        $address = $this->addressFactory->create();
                    }
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", " setting arsc address: " . $GCCust["addr1"]);
                    $bShip = $customer->getDefaultShipping();
                    $bBill = $customer->getDefaultBilling();
                    $address->setCustomerId($customer->getId());
                    $address->setFirstname($customer->getFirstname());
                    $address->setLastname($customer->getLastname());
                    $address->setStreet([$GCCust["addr1"], $GCCust["addr2"], $GCCust["addr3"]]);
                    $address->setCompany($GCCust["name"]);
                    $address->setCity($GCCust["city"]);
                    $address->setRegionId($statecd);
                    $address->setPostcode($GCCust["zipcd"]);
                    $address->setCountryId($countrycode);
                    $address->setTelephone($phone);
                    $address->setFax($GCCust["faxphoneno"]);
                    if (isset ($bBill)) {
                        $addrSet = true;
                        //$address->setIsDefaultBilling('0');
                    } else {
                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", " setting default billing: " . $GCCust["addr1"]);
                        $address->setIsDefaultBilling('1');
                        $addrSet = true;
                    }
                    if (isset ($bShip)) {
                        $addrSet = true;
                        //$address->setIsDefaultShipping('0');
                    } else {
                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", " setting default shipping: " . $GCCust["addr1"]);
                        $address->setIsDefaultShipping('1');
                        $addrSet = true;
                    }
                    $address->setSaveInAddressBook('1');
                    //$address->SetData("ERPAddressID", $GCCust["shipto"]);
                    $address->save();
                    //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "arsc address saved: " . $GCCust["addr1"] );
                    $customer = $customerSession->getCustomer();
                    //////////////
                }
            } catch (\Exception $e) {
                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "arsc address error: " . json_encode($e->getMessage()));
            }
            try {
                $GCShip = $this->csd->SalesShipToList($cono, $csdcustno, $moduleName);
                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "post ship-to API");
                if (isset ($GCShip)) {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "gcship is set");
                    if (isset ($GCShip["SalesShipToListResponseContainerItems"])) {
                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "multiple records");
                        foreach ($GCShip["SalesShipToListResponseContainerItems"] as $item) {
                            $this->ProcessAddress($item, $customer, $addrSet);
                        }
                    } else {
                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "single record");
                        $this->ProcessAddress($GCShip, $customer, $addrSet);
                    }
                }
            } catch (\Exception $e) {
                $this->csd->gwLog(json_encode($e->getMessage()));
            }
        }
    }
}
