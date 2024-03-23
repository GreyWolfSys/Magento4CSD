<?php

namespace Altitude\CSD\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\ObjectManager;

class Data extends AbstractHelper
{
    private $csd;
	protected $customerSession;
    protected $_customerFactory;
    protected $_addressFactory;
    public function __construct(
        Context $context,
        \Altitude\CSD\Model\CSD $csd,
		\Magento\Customer\Model\Session $customerSession,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Model\AddressFactory $addressFactory
    ) {
        parent::__construct($context);
        $this->scopeConfig = $context->getScopeConfig();
        $this->csd = $csd;
		$this->customerSession = $customerSession;
        $this->_customerFactory = $customerFactory;
        $this->_addressFactory = $addressFactory;
    }

    public function getConfigData($field)
    {
        return $this->csd->getConfigValue("settings/erporders/$field");
    }

    public function isActive()
    {
        return $this->csd->getConfigValue('settings/erporders/multicsdorders');
    }

    public function useShippingCostPerWH()
    {
        return $this->csd->getConfigValue('settings/erporders/shipping_per_wh');
    }

    public function defaultWh()
    {
        return $this->csd->getConfigValue("settings/defaults/whse");
    }

    public function cheapestWh($warehouses)
    {
        $wh = -1;

        foreach ($warehouses as $whID => $rates) {
            if ($wh == -1) {
                $wh = $whID;
            }

            foreach ($rates as $_rate) {
                foreach ($warehouses as $subWhID => $subRates) {
                    foreach ($subRates as $_subRate) {
                        if ($_rate->getMethod() == $_subRate->getMethod() && $_rate->getPrice() > $_subRate->getPrice()) {
                            $wh = $subWhID;
                        }
                    }
                }
            }
        }

        return $wh;
    }

    public function getMostWh($warehouses)
    {
    }

    public function getWarehouses($items)
    {
        $moduleName = $this->csd->getModuleName(get_class($this)) . "SxOHD1";
        $configs = $this->csd->getConfigValue(['cono']);
        extract($configs);

        $warehouses = [];

        foreach ($items as $item) {
            $_sku = $item->getSku();
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Calling ItemsWarehouseProductList on line 74 of CSD/Helper/Data.php for prod " . $_sku);
            $itemAllQty = $this->csd->ItemsWarehouseProductList($cono, $_sku,"", $moduleName);

            if (isset($itemAllQty)) {
                if (!isset($itemAllQty["errordesc"])) {
                    foreach ($itemAllQty["ItemsWarehouseProductListResponseContainerItems"] as $_itemQty) {
                        $AvailQty = $_itemQty["qtyonhand"] - $_itemQty["qtyreservd"] - $_itemQty["qtycommit"];

                        if ($AvailQty >= $item->getQty()) {
                            $warehouses[$_itemQty["whse"]][$_sku] = $AvailQty;
                        }
                    }
                }
            }
        }

        return $warehouses;
    }

    public function getOrderWarehouses($items)
    {
        $moduleName = $this->csd->getModuleName(get_class($this)) . "SxOHD2";
        $configs = $this->csd->getConfigValue(['cono']);
        extract($configs);

        $warehouses = $orderWhs = [];

        foreach ($items as $item) {
            $_sku = $item->getSku();
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Calling ItemsWarehouseProductList on line 103 of CSD/Helper/Data.php for prod " . $_sku);
            $itemAllQty = $this->csd->ItemsWarehouseProductList($cono, $_sku, "",$moduleName);

            if (isset($itemAllQty)) {
                if (!isset($itemAllQty["errordesc"])) {
                    foreach ($itemAllQty["ItemsWarehouseProductListResponseContainerItems"] as $_itemQty) {
                        $AvailQty = $_itemQty["qtyonhand"] - $_itemQty["qtyreservd"] - $_itemQty["qtycommit"];

                        if ($AvailQty >= $item->getQty()) {
                            $warehouses[$_sku][$_itemQty["whse"]] = [
                                'item' => $item,
                                'qty' => $AvailQty,
                                'whse' => $_itemQty["whse"]
                            ];
                        }
                    }
                }
            }
        }

        foreach ($warehouses as $_sku => $skuWhs) {
            $_tmpWhs = $skuWhs;
            usort($_tmpWhs, function ($a, $b) {
                return $b['qty'] <=> $a['qty'];
            });

            $warehouses[$_sku] = $_tmpWhs;
        }

        foreach ($warehouses as $_sku => $skuWhs) {
            $firstWh = current(array_keys($skuWhs));
            $_wh = $skuWhs[$firstWh];

            $orderWhs[$_wh['whse']][$_sku] = $_wh['item'];
        }

        return $orderWhs;
    }

    public function getWarehouseInfo($whID)
    {
        $moduleName = $this->csd->getModuleName(get_class($this));
        $configs = $this->csd->getConfigValue(['cono']);
        extract($configs);
	//	status=bad, save for next version
        return $this->csd->ItemsWarehouseList($cono, $whID, $moduleName);
    }

    public function getProductImageData()
    {
        $imageData = dirname(__FILE__) . '/paid_invoice.jpg';
        return file_get_contents($imageData);
    }

	public function getModuleName()
    {
        return self::MODULE_NAME;
    }

    public function getQtyInfoArray($products, $region = '')
    {
         $this->jx->gwLog("Starting  getQtyInfoArray" );
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        $url= $storeManager->getStore()->getCurrentUrl(false);
        $stamp=date('H:i');

  /*          ob_start();
             var_dump($products);
            $result = ob_get_clean;
             $this->csd->gwLog($result);
*/
        $didthis="|" . $this->customerSession->getCustomer()->getId() . ";" . $product->getSku() . ";" . $stamp . ";" . $url . ";";
        if (!isset($_SESSION["didthis"])){
            $_SESSION["didthis"]=$didthis;
        } elseif (strpos($_SESSION["didthis"], $didthis) !== false)  {
            //error_log ("already processed" . $didthis);
            return "";
        }else{
           $_SESSION["didthis"] .= $didthis;
        }

        $moduleName = $this->csd->getModuleName(get_class($this)) . "SxP3";
        $configs = $this->csd->getConfigValue(['cono', 'csdcustomerid', 'whse', 'whselist', 'whsename']);
        $hideqtyavai = $this->csd->getConfigValue('defaults/products/hideqtyavai');
        extract($configs);
        $whselist = $whse.','.$whselist;

        if (strpos($url,"/cart")===false){
            return "";
        }

        if($region){
            $warehouseData = $this->csd->getWhseAndWhseList($region);
            if($warehouseData['whse'] && isset($warehouseData['whse'])){
                $whse = $warehouseData['whse'];
        }
            if($warehouseData['whselist'] && isset($warehouseData['whselist'])){
                $whselist = $warehouseData['whselist'];
            }
        }else if ($this->customerSession && $this->customerSession->getCustomer() && $this->customerSession->getCustomer()->getId() > 0){
                $customerId = $this->customerSession->getCustomer()->getId();
                $customer = $this->_customerFactory->create()->load($customerId);
                $shippingAddressId = $customer->getDefaultShipping();
                $shippingAddress = $this->_addressFactory->create()->load($shippingAddressId);
                $regionCode = $shippingAddress->getRegionCode();
                $warehouseData = $this->csd->getWhseAndWhseList($regionCode);
                if($warehouseData['whse'] && isset($warehouseData['whse'])){
                    $whse = $warehouseData['whse'];
                }
                if($warehouseData['whselist'] && isset($warehouseData['whselist'])){
                    $whselist = $warehouseData['whselist'];
                }
        }

        //error_log("whselist = " . $whselist);
        if ($this->csd->botDetector()) {
            return false;
        }


        $CustWhseName = "";
        $result = "";
        $qtyAvailable = [];

        $customerSession = $this->csd->getSession();
        if ($customerSession->isLoggedIn()) {
            $customerData = $customerSession->getCustomer();

            $customer = $customerSession->getCustomer();
            $cust = $customerSession->getCustomerData();

            if ($customerData['csd_custno'] > 0) {
                $csdcustno = $customerData['csd_custno'];
            } else {
                $csdcustno = $csdcustomerid;
            }

          if(!empty($customerData['warehouse'] )){
               $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "using warehouse " .$customerData['whse']  );
               $whse = $customerData['whse'] ;
          }

        } else {
            if ($hideqtyavai) {
                return false;
            }

            $csdcustno = $csdcustomerid;
        }

        $AvailQty=0;
        try {
            $prod=[];
            foreach ($products as $product){
                if ($product->getTypeId() != 'simple') {
                    continue;
                }
                $prod[] = $product->getSku();
            }
            $testwhse=array("3000");
            $qtyAvailable['qty'] = $AvailQty;
            $qtyAvailable['more'] = [];
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Calling ItemsWarehouseProductList on line 124 of CSD/Helper/Data.php for prod list " );
            $gcAllQty = $this->csd->ItemsWarehouseProductList($cono, $prod,$testwhse,$moduleName);
           // ob_start();
           //  var_dump($gcAllQty);
           //  $result = ob_get_clean;
           //   $this->csd->gwLog($result);
           if (!empty($gcAllQty)){

                if ((!isset($gcAllQty["errordesc"]) || $gcAllQty["errordesc"] == "") ) {
                    foreach ($gcAllQty["ItemsWarehouseProductListResponseContainerItems"] as $item) {
                        if ((trim($whselist) == "") || (strpos(strtoupper($whselist), strtoupper($item["whse"])) !== false)) {
                            if ($whsename == "1") {
                                $showwhse = $item["whsename"];
                            } else {
                                $showwhse = $item["whse"];
                            }

                            $qtyAvailable['more'][] = [
                                'whName' => $this->csd->TrimWHSEName($showwhse, "-"),
                                'qty' => ($item["qtyonhand"] - $item["qtyreservd"] - $item["qtycommit"])
                            ];
                            $qtyAvailable['qty'] +=($item["qtyonhand"] - $item["qtyreservd"] - $item["qtycommit"]);
                        }
                    }
                } else {
                    $item=$gcAllQty;
					if (!empty($item["whse"])) {
                    if ((trim($whselist) == "") || (strpos(strtoupper($whselist), strtoupper($item["whse"])) !== false)) {
                            if ($whsename == "1") {
                                $showwhse = $item["whsename"];
                            } else {
                                $showwhse = $item["whse"];
                            }

                            $qtyAvailable['more'][] = [
                                'whName' => $this->csd->TrimWHSEName($showwhse, "-"),
                                'qty' => ($item["qtyonhand"] - $item["qtyreservd"] - $item["qtycommit"])
                            ];
                            $qtyAvailable['qty']=($item["qtyonhand"] - $item["qtyreservd"] - $item["qtycommit"]);
                        }
					}
                }

           }
        } catch (\Exception $e) {
            $this->csd->gwLog('Error ' . $e->getMessage());
        }

/*ob_start();
var_dump($qtyAvailable);
$result = ob_get_clean();
error_log($result);*/
        return $qtyAvailable;
    }
    public function getQtyInfo($product, $region = '')
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        $url= $storeManager->getStore()->getCurrentUrl(false);
        $stamp=date('H:i');
        $didthis="|" . $this->customerSession->getCustomer()->getId() . ";" . $product->getSku() . ";" . $stamp . ";" . $url . ";";
        if (!isset($_SESSION["didthis"])){
            $_SESSION["didthis"]=$didthis;
        } elseif (strpos($_SESSION["didthis"], $didthis) !== false)  {
            //error_log ("already processed" . $didthis);
            return "";
        }else{
           $_SESSION["didthis"] .= $didthis;
        }
        error_log ("Starting getQtyInfo for prod " . $product->getSku() . " and customer " .  $this->customerSession->getCustomer()->getId());
//$this->csd->gwLog('getQtyInfo starting ' . $url);
        $moduleName = $this->csd->getModuleName(get_class($this)) . "SxP1";
        $configs = $this->csd->getConfigValue(['cono', 'csdcustomerid', 'whse', 'whselist', 'whsename']);
        $hideqtyavai = $this->csd->getConfigValue('defaults/products/hideqtyavai');
        extract($configs);
        $whselist = $whse.','.$whselist;

        /*if (strpos($url,"/cart")===false){
            return "";
        }*/

        if($region){
            $warehouseData = $this->csd->getWhseAndWhseList($region);
            if($warehouseData['whse'] && isset($warehouseData['whse'])){
                $whse = $warehouseData['whse'];
        }
            if($warehouseData['whselist'] && isset($warehouseData['whselist'])){
                $whselist = $warehouseData['whselist'];
            }
        }else if ($this->customerSession && $this->customerSession->getCustomer() && $this->customerSession->getCustomer()->getId() > 0){
                $customerId = $this->customerSession->getCustomer()->getId();
                $customer = $this->_customerFactory->create()->load($customerId);
                $shippingAddressId = $customer->getDefaultShipping();
                $shippingAddress = $this->_addressFactory->create()->load($shippingAddressId);
                $regionCode = $shippingAddress->getRegionCode();
                $warehouseData = $this->csd->getWhseAndWhseList($regionCode);
                if($warehouseData['whse'] && isset($warehouseData['whse'])){
                    $whse = $warehouseData['whse'];
                }
                if($warehouseData['whselist'] && isset($warehouseData['whselist'])){
                    $whselist = $warehouseData['whselist'];
                }
        }

        //error_log("whselist = " . $whselist);
        if ($this->csd->botDetector()) {
            return false;
        }

        if ($product->getTypeId() != 'simple') {
            return false;
        }
        $CustWhseName = "";
        $result = "";
        $qtyAvailable = [];

        $customerSession = $this->csd->getSession();
        if ($customerSession->isLoggedIn()) {
            $customerData = $customerSession->getCustomer();

            $customer = $customerSession->getCustomer();
            $cust = $customerSession->getCustomerData();

            if ($customerData['csd_custno'] > 0) {
                $csdcustno = $customerData['csd_custno'];
            } else {
                $csdcustno = $csdcustomerid;
            }

          if(!empty($customerData['warehouse'] )){
               $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "using warehouse " .$customerData['whse']  );
               $whse = $customerData['whse'] ;
          }
          /*  $gcCust = $this->csd->SalesCustomerSelect($cono, $csdcustno, $moduleName);

            if (isset($gcCust["whse"])) {
                $whse = $gcCust["whse"];
                $CustWhseName = $this->csd->TrimWHSEName($gcCust["whse"], "-");
            }*/
        } else {
            if ($hideqtyavai) {
                return false;
            }

            $csdcustno = $csdcustomerid;
        }
//error_log ("qty check");

        //$this->csd->gwLog('whse1 = ' . $whse);
 $AvailQty=0;
        try {
            $prod = $product->getSku();
            $prodID = $product->getId();
             //$this->csd->gwLog('qty check for  ' . $prod);
            #$gcQty = $this->csd->ItemsWarehouseProductSelect($cono, $prod, $whse, '');
           // $gcQty = $this->csd->ItemsWarehouseProductList($cono, $prod, $whse, "helperdata");

            if (isset($gcQty)){
                if (isset($gcQty["cono"])){
                    if ($gcQty["cono"] == 0) {
                        $AvailQty = 0;
                    } else {
                        $AvailQty = $gcQty["qtyonhand"] - $gcQty["qtyreservd"] - $gcQty["qtycommit"];
                    }
                } else {
                    $AvailQty = 0;
                    //ob_start();
                   // var_dump($gcQty);
                   // $result = ob_get_clean();
                   // error_log($result);
                    foreach ($gcQty["ItemsWarehouseProductListResponseContainerItems"] as $item) {
                       if ($gcQty["cono"] == 0) {
                            $AvailQty += 0;
                        } else {
                            $AvailQty += ($gcQty["qtyonhand"] - $gcQty["qtyreservd"] - $gcQty["qtycommit"]);
                        }
                    }
                }
            }
            $qtyAvailable['qty'] = $AvailQty;
            $qtyAvailable['more'] = [];
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Calling ItemsWarehouseProductList on line 306 of CSD/Helper/Data.php for prod " . $prod);
            $gcAllQty = $this->csd->ItemsWarehouseProductList($cono, $prod, "", $moduleName);
           // $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "qtyavailable: " . $AvailQty . " gcAllQty: " . json_encode($gcAllQty));
            
           // ob_start();
           //  var_dump($gcAllQty);
           //  $result = ob_get_clean;
           //   $this->csd->gwLog($result);
           if (!empty($gcAllQty)){

                if ((!isset($gcAllQty["errordesc"]) || $gcAllQty["errordesc"] == "") ) {
                    foreach ($gcAllQty["ItemsWarehouseProductListResponseContainerItems"] as $item) {
                        if ((trim($whselist) == "") || (strpos(strtoupper($whselist), strtoupper($item["whse"])) !== false)) {
                            if ($whsename == "1") {
                                $showwhse = $item["whsename"];
                            } else {
                                $showwhse = $item["whse"];
                            }

                            $qtyAvailable['more'][] = [
                                'whName' => $this->csd->TrimWHSEName($showwhse, "-"),
                                'qty' => ($item["qtyonhand"] - $item["qtyreservd"] - $item["qtycommit"])
                            ];
                            $qtyAvailable['qty'] +=($item["qtyonhand"] - $item["qtyreservd"] - $item["qtycommit"]);
                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "qtyavailable: " . json_encode($qtyAvailable));
            
                        }
                    }
                } else {
                    $item=$gcAllQty;
                    if ((trim($whselist) == "") || (strpos(strtoupper($whselist), strtoupper($item["whse"])) !== false)) {
                            if ($whsename == "1") {
                                $showwhse = $item["whsename"];
                            } else {
                                $showwhse = $item["whse"];
                            }

                            $qtyAvailable['more'][] = [
                                'whName' => $this->csd->TrimWHSEName($showwhse, "-"),
                                'qty' => ($item["qtyonhand"] - $item["qtyreservd"] - $item["qtycommit"])
                            ];
                            $qtyAvailable['qty']=($item["qtyonhand"] - $item["qtyreservd"] - $item["qtycommit"]);
                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "qtyavailable2: " . json_encode($qtyAvailable));
                        }
                }

           }
        } catch (\Exception $e) {
            $this->csd->gwLog('Error ' . $e->getMessage());
        }

/*ob_start();
var_dump($qtyAvailable);
$result = ob_get_clean();
error_log($result);*/
        return $qtyAvailable;
    }

    public function getPriceInfo($product)
    {
        if ($this->csd->botDetector()) {
            return [];
        }


        /******************************************/
        /*cheat to add fields without rebuilding */
        if (false){
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $eavSetupFactory= $objectManager->get('\Magento\Eav\Setup\EavSetupFactory');
            $eavSetup = $eavSetupFactory->create();
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                'qtybrkfl',
                [
                    'group' => 'general',
                    'type' => 'varchar',
                    'label' => 'CSD Qty Brk Flag',
                    'input' => 'text',
                    'required' => false,
                    'sort_order' => 56,
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'is_used_in_grid' => false,
                    'is_visible_in_grid' => false,
                    'is_filterable_in_grid' => false,
                    'visible' => false,
                    'is_user_defined'=>true,
                    'is_html_allowed_on_front' => false,
                    'visible_on_front' => true
                ]
            );
        $eavSetup->save;
        }/**/

        /******************************************/
        #global $apikey,$apiurl,$csdcustomerid,$cono,$whse,$slsrepin, $defaultterms,$operinit,$transtype,$shipviaty,$slsrepout,$updateqty,$whselist,$whsename;

        $moduleName = $this->csd->getModuleName(get_class($this) ). "SxP2";
        $url = $this->csd->urlInterface()->getCurrentUrl();
        $configs = $this->csd->getConfigValue(['cono', 'csdcustomerid', 'whse', 'whselist', 'whsename']);
        extract($configs);
        $qtyPricing = [];
        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "getPriceInfo (qty): " . $url);
       // $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "getPriceInfo sku:: " . $product->getSku());
       // $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "getPriceInfo sku::: " . $product->getId());
        if ($this->csd->botDetector()) {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "bot");
            return [];
        }

        if ($product->getTypeId() != 'simple') {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "not simple");
            return [];
        }
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $productmodel = $objectManager->create('Magento\Catalog\Model\Product')->load($product->getId());

        return [];
       // $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "qty brk value: " . $product->getData('qtybrkfl'));
        if ($product->getData("qtybrkfl")=="N"){
       //     $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "No qty brk: " . $product->getSku());
            return [];
        }
        $customerSession = $this->csd->getSession();
        if ($customerSession->isLoggedIn()) {
            $customerData = $customerSession->getCustomer();

            $customer = $customerSession->getCustomer();
            $cust = $customerSession->getCustomerData();

            if ($customerData['csd_custno'] > 0) {
                $csdcustno = $customerData['csd_custno'];
               $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "whse=====". $customerData['whse'] );
                $custwhse = $customerData['whse'];
            } else {
                $csdcustno = $csdcustomerid;
                $custwhse=$whse;
            }

            if (!isset($custwhse)){
                //get CSD customer data, particularly the default warehouse
                //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "API: SCS");
                $gcCust = $this->csd->SalesCustomerSelect($cono, $csdcustno);

                if (isset($gcCust["whse"]) && $gcCust["whse"] != "") {
                    $whse = $gcCust["whse"];
                }
            } else {
                $whse=$custwhse;
            }
        } else {
            $csdcustno = $csdcustomerid;
        }

        try {
		//	status=bad, save for next version
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "SalesCustomerQuantityPricingList: " . $url . " - " . $product->getSku());
            $response = $this->csd->SalesCustomerQuantityPricingList($cono, $whse, $csdcustno, $product, $moduleName);
            $formater = new \NumberFormatter('en_US', \NumberFormatter::CURRENCY);

            if (isset($response['price2']) && $response['price2'] > 0) {
                for ($i = 1; $i <= 8; $i++) {
                    if ($response['price' . $i] > 0) {
                        if ($i == 1) {
                            $qtyFrom = 0;
                        } else {
                            $qtyFrom = $response['qty' . ($i - 1)];
                        }

                        $qtyTo = $response['qty' . $i] - 1;
                        $qtyFromTo = "$qtyFrom - $qtyTo";

                        if (
                            (isset($response['qty' . ($i + 1)]) && $response['qty' . ($i + 1)] == 0) ||
                            !isset($response['qty' . ($i + 1)])
                        ) {
                            $qtyFromTo = $qtyFrom . "+";
                        }

                        $qtyPricing[] = [
                            'fromTo' => $qtyFromTo,
                            'price' => $formater->formatCurrency($response['price' . $i], "USD")
                        ];
                    }
                }
            } else {

                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Setting No qty brk: " . $product->getSku());
                $product->setData("qtybrkfl", "N");
                $product->save;
                return [];
            }
        } catch (\Exception $e) {
            return [];
        }

        return $qtyPricing;
    }

/*    public function getConfigData($field)
    {
        return $this->csd->getConfigValue($field);
    }*/

	public function getUpchargeShipping()
    {
        $methods = [];

        $configShippingMethods = $this->getConfigValue('shipping_methods');

        if ($configShippingMethods && is_object(json_decode($configShippingMethods))) {
            foreach (json_decode($configShippingMethods) as $_method) {
                $methods[] = $_method->shippingtitle;
            }
        }

        return $methods;
    }

    public function getUpchargeLabel()
    {
        return $this->getConfigValue('upcharge_label');
    }

    public function getUpchargePayment()
    {
        return $this->getConfigValue('payment_method');
    }

    public function getUpchargePercent()
    {
        $upchargePercent = $this->getConfigValue('upcharge_percent');
        return str_replace("%", "", $upchargePercent);
    }

    public function getUpchargeWaiveAmount()
    {
        return $this->getConfigValue('waive_amount');
    }

    public function getConfigValue($configName)
    {
        return $this->scopeConfig->getValue(
            "shipping_upcharge/general/$configName",
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getUpchargeAmount($quote)
    {
        if ($quote->getPayment() && $quote->getSubtotal()) {
            $objectManager = ObjectManager::getInstance();
            $upchargeTotal = $objectManager->create('Altitude\CSD\Model\Total\UpchargeTotal');

            return $upchargeTotal->getUpchargeAmount($quote);
        } else {
            return 0;
        }
    }

    public function sendAddressToERP()
    {
        return $this->scopeConfig->getValue(
            'defaults/gwcustomer/address_to_erp',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function isAbleToEditAddress()
    {
        return ($this->scopeConfig->getValue(
            'defaults/gwcustomer/allow_edit_address',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE) || $this->isCustomerDefault()
        );
    }
    public function isCustomerDefault()
    {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');

        $custID= $customerSession->getCustomer()->getData('csd_custno');

        $defID= $this->scopeConfig->getValue(
            'defaults/gwcustomer/erpcustomerid',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        if ($defID==$custID) {
            return true;
        } else {
            return false;
        }
        //return $defID;
    }
    public function isLoggedIn()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');

        if ($customerSession->isLoggedIn()) {
            return true;
        }

        return false;
    }

    public function getCustomer()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');

        return $customerSession->getCustomer();
    }

    public function getDefaultShipVia()
    {
        return "";//$this->getConfigValue('default_erpshipvia');
    }

    public function getDefaultShipViaDesc()
    {
        return "";//$this->getConfigValue('default_erpshipviadesc');
    }

    public function getShippingNotice()
    {
        return $this->scopeConfig->getValue(
            "settings/general/shipping_notice",
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
}
