<?php

namespace Altitude\CSD\Model;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use SoapVar;

class GetCSDPrice implements ObserverInterface
{
    protected $csd;

    protected $request;

    protected $_addressFactory;

    protected $_proxy;

    public function __construct(
        \Altitude\CSD\Model\CSD $csd,
        \Magento\Customer\Model\AddressFactory $addressFactory,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress
    ) {
        $this->csd = $csd;
        $this->addressFactory = $addressFactory;
        $this->remoteAddress = $remoteAddress;
        $this->request = $request;

    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {


        if ($this->csd->botDetector()) {
            return "";
        }

        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "checking price... ");
        $moduleName = $this->csd->getModuleName(get_class($this));
        $configs = $this->csd->getConfigValue(['apikey', 'cono', 'csdcustomerid', 'whse', 'onlycheckproduct']);
        extract($configs);

        $url = $this->csd->urlInterface()->getCurrentUrl();
        $ip = $this->remoteAddress->getRemoteAddress();
        $displayText = $observer->getEvent()->getName();
        $controller = $this->request->getControllerName();
        $singleitem = "true";
        $shipto = "";
        $custno = 0;
        $products = $productsCollection = [];

        $debuggingflag = "true";
        //   $debuggingflag = "false";

        if ($this->csd->getSession()->getProdDone()) {
            $prodDone = $this->csd->getSession()->getProdDone();
        } else {
            $prodDone = $url . $controller . "|";
            $this->csd->getSession()->setProdDone($prodDone);
        }

        if ($this->csd->getSession()->getApidown()) {
            $apidown = $this->csd->getSession()->getApidown();
        } else {
            $apidown = false;
        }
        $apidown = false;
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        if ($debuggingflag == "true") {
            error_log(__CLASS__ . "/" . __FUNCTION__ . ": url: " . $url);
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "url: " . $url);
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "ip:: " . $ip);
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "controller:: " . $controller);
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", ":::::::::::::::::::::::::::::::::::: ");
        }
        if ($url == "http:///") {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "skipping for console...");
            return "";
        }
        try {
            $singleProduct = $observer->getEvent()->getProduct();
            if (is_null($singleProduct)) {
                if ($debuggingflag == "true") {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Item Collection");
                }

                $productsCollection = $observer->getCollection();
                $singleitem = "false";
            } else {
                if ($debuggingflag == "true") {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Single Item");
                }
                $products = [];
                $productsCollection[] = $singleProduct;
                $singleitem = "true";
            }
        } catch (exception $e) {
        }

        $customerSession = $objectManager->get('Magento\Customer\Model\Session');

        if ($customerSession->isLoggedIn()) {// || (strpos($url, '/rest/') !== false)) {
            // Logged In
            if ($debuggingflag == "true") {
                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", " logged in!");
            }

            $customerData = $customerSession->getCustomer();
            //$customerData = $this->csd->getSession()->getCustomer();
            //ob_start();
            //var_dump($customerData);
            //$resultprice = ob_get_clean();
            // $this->csd->gwLog($resultprice);
            $custno = $customerData['csd_custno'];
            $customer_id = $customerData->getId();
            if ($debuggingflag == "true") {
                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "cust= " . $custno);
            }

            $shippingAddressId = $customerData['default_shipping'];
            $shippingAddress = $this->addressFactory->create()->load($shippingAddressId);

            if ($shippingAddress->getData('ERPAddressID') != "") {
                $shipto = "";
            }
        } else {
            // Not Logged In
            $custno = $csdcustomerid;
            $customer_id = 0;
        }

        if (empty ($custno)) {
            $custno = $csdcustomerid;
        }

        if ($debuggingflag == "true") {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Product retrieved");
        }

        if ($this->csd->df_is_admin()) {
            $admin = true;
        } else {
            $admin = false;
        }

        if ($debuggingflag == "true") {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "admin = " . $admin);
        }

        $bSkip = '';
        $params = new \ArrayObject();

        $thisparam = array(
            'cono' => $cono,
            'custno' => $custno,
            'whse' => $whse,
            'shipto' => $shipto,
            'qty' => '1',
            'APIKey' => $apikey
        );
        $params[] = new \SoapVar($thisparam, SOAP_ENC_OBJECT);

        /* $params[] = new SoapVar($cono, XSD_STRING, null, null, 'cono');
         $params[] = new SoapVar($custno, XSD_STRING, null, null, 'custno');
         $params[] = new SoapVar($whse, XSD_STRING, null, null, 'whse');
         $params[] = new SoapVar($shipto, XSD_STRING, null, null, 'shipto');
         $params[] = new SoapVar("1", XSD_STRING, null, null, 'qty');
         $params[] = new SoapVar($apikey, XSD_STRING, null, null, 'APIKey');*/

        foreach ($productsCollection as $product) {
            $price = 0;
            $visibility = "";
            $prod = $product->getSku();
            $products[$prod] = $product;

            $price = $product->getPrice();

            if ($debuggingflag == "true") {
                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "product sku: $prod");
                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "product price: $price");
            }
            if ($controller != "product" && $controller != "block" && strpos($url, 'cart') == false && $controller != "order" && $controller != "order_create") {
                if ($debuggingflag == "true") {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "controllervar=" . $onlycheckproduct);
                }
                if ($onlycheckproduct == "1" && strpos($url, 'wishlist') === false && strpos($url, 'amasty_quickorder') === false && strpos($url, 'checkout') === false && strpos($url, 'loginVerification') === false) {
                    if ($debuggingflag == "true") {
                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "skip price for non-product page! . " . $url);
                    }
                    $bSkip = 'true';
                }
            }
            try {
                if (strpos($url, '/wishlist/index/index') !== false) {
                    $didthis = "|" . $custno . ";" . $product->getSku() . ";" . $url . ";";
                    if (!isset ($_SESSION["wldidthis"])) {
                        $_SESSION["wldidthis"] = $didthis;
                    } elseif (strpos($_SESSION["wldidthis"], $didthis) !== false) {
                        $this->csd->jwLog("already processed: " . $didthis . " // " . $_SESSION["wldidthis"]);
                        return "";
                    } else {
                        $_SESSION["wldidthis"] .= $didthis;
                    }
                }
            } catch (\Exception $e) {
                $this->csd->gwLog('Error trace test ::: ' . $e->getMessage());
                $this->csd->gwLog('Error trace' . $e->getTraceAsString());

            }
            try {

                $productcount = count($productsCollection);
            } catch (\Exception $e) {
                $productcount = 1;
            }
            try {

                // $this->csd->jwLog("...checking $url for performance");
                // $this->csd->jwLog("...checking count " . $productcount . " for performance");
                if (
                    (strpos($url, '/totals-information') !== false && $productcount > 0)
                    or (strpos($url, '/carts/mine/shipping-information') !== false && $productcount > 0)
                    or (strpos($url, '/carts/mine/estimate-shipping-methods') !== false && $productcount > 0)
                    or (strpos($url, 'carts/mine/payment-information') !== false)
                )////
                {

                    /*if ( $customer_id>0){
                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "...setting $prod price from cart (before): $price");
                        $collection = $objectManager->get('\Magento\Framework\App\ResourceConnection');
                        $conn = $collection->getConnection();
                        $quote_table = $collection->getTableName('quote');
                        $quote_item_table = $collection->getTableName('quote_item');
                        $query = "SELECT q.entity_id, customer_id, q.store_id, i.sku, i.price as price FROM $quote_table q INNER JOIN $quote_item_table i ON i.quote_id=q.entity_id WHERE customer_id=$customer_id AND is_active=1 AND sku='$prod'";
                        $result = $conn->fetchAll($query);
                        //ob_start();
                        //var_dump($result);
                        //$result2 = ob_get_clean();
                        //$this->csd->jwLog($result2);
                        if(!empty($result)) {
                            $price=$result[0]["price"];
                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "...new price: $price");
                            $product->setSpecialPrice($price);
                            $product->setPrice($price);
                            $product->setFinalPrice($price);
                        } else {
                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": Product $prod not found in db, new price not set");
                        }
                        //return $price;
                    } else {
                        //return $price;
                    }*/

                } elseif (strpos($url, '/checkout/cart/') !== false && $controller == 'cart') {
                    //\Magento\Checkout\Model\Cart\updateItems();
                    //return "";
                }
            } catch (\Exception $e) {
                $this->csd->gwLog('Error ::: ' . $e->getMessage());
                //$this->csd->jwLog('Error trace' . $e->getTraceAsString());

            }

            if ($debuggingflag == "true") {
                $this->csd->gwLog(__CLASS__ . '/' . __FUNCTION__ . ': ', 'Product: ' . $prod . ' - Magento Price: ' . $price);
            }

            $currparent = "";
            unset($productparent);

            if (!isset ($parentdone)) {
                $parentdone = "|";
            }

            if ($debuggingflag == "true") {
                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Child check" . $parentdone);
            }
            try {
                $productparent = $objectManager->create('Magento\ConfigurableProduct\Model\Product\Type\Configurable')->getParentIdsByChild($product->getId());
                if (isset ($productparent[0])) {
                    $currparent = $productparent[0];
                }
            } catch (Exception $e) {
                if ($debuggingflag == "true") {
                    $this->csd->gwLog('Error ' . $e->getMessage());
                }
            }

            if ($debuggingflag == "true") {
                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "price controller: " . $controller . " -- singleitem: " . $singleitem . " -- currparent: " . $currparent . " -- isset:" . isset ($currparent));
            }
            if ($controller != 'product') {
                try {
                    if ($currparent == "" && $singleitem == "false" && isset ($productparent[0])) {
                        if ($debuggingflag == "true") {
                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Skipping CSD price check for parent of collection");
                        }
                        $visibility = "0";
                    } elseif ($singleitem == "true" && 1 == 2) {
                        if ($debuggingflag == "true") {
                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Skipping CSD price check single item");
                        }
                        $visibility = "0";

                    } else {
                        if ($debuggingflag == "true") {
                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Setting vis by prev run");
                        }
                        if (isset ($productparent[0])) {
                            if (strpos($parentdone, "|" . $productparent[0] . "|") !== false) {
                                $visibility = "0";
                                if ($debuggingflag == "true") {
                                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "hiding");
                                }
                            } else {
                                if ($debuggingflag == "true") {
                                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "not hiding");
                                }
                                $visibility = "4";
                            }
                        } else {
                            $visibility = "4";
                        }
                    }
                } catch (Exception $e) {
                    if ($debuggingflag == "true") {
                        $this->csd->gwLog('Error ' . $e->getMessage());
                    }
                    $visibility = "4";
                }
            }

            try {
                if ($singleitem == "false") {
                    if ($controller == 'product') {
                        if (isset ($productparent[0])) {
                            if ($debuggingflag == "true") {
                                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "skipping " . $prod);
                            }
                            $visibility = "0";
                        } else {
                            if ($debuggingflag == "true") {
                                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "checking  " . $prod);
                            }
                            $visibility = "4";
                        }
                    } else {
                    }
                }
            } catch (Exception $e) {
                if ($debuggingflag == "true") {
                    $this->csd->gwLog('Error ' . $e->getMessage());
                }
            }

            if (strpos($url, 'cart') !== false) {
                if ($debuggingflag == "true") {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "checking for cart  " . $prod);
                }
                $visibility = "4";
            }

            //	 $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Checking url for cart: " . $url);

            if (strpos($url, 'checkout') === false && strpos($url, 'cart') === false && (strpos($url, 'wishlist') === false) && (strpos($url, 'stores/store/switch') === false)) { //cart
            } else {
                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Checkout, checking price");
                $controller = "cart";
                $bSkip = 'false';
            }

            $this->csd->getSession()->setApidown(false);
            $apidown = $this->csd->getSession()->getApidown();
            $pagestate = $objectManager->get('Magento\Framework\App\State');

            if (strpos($url, 'admin') !== false || strpos($url, '/catalog/product/index/key/') !== false || $admin == true || strpos($url, '/product/index/key') !== false || $pagestate->getAreaCode() == 'adminhtml') { //https://nee2go.com/cstore/rest/cstore/V1/carts/mine/totals-information
                if ($debuggingflag == "true") {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Skipping CSD price check for admin");
                }

                return "";
                ///checkout/cart/
            } elseif ((strpos($url, 'checkout/cartxxx/') !== false) && $controller == 'cart') {
                if ($debuggingflag == "true") {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Skipping CSD price check for possibly unneeded page " . $url);
                }
                return "";
            } elseif (
                strpos($url, 'cartquickpro/cart/updateItemOptions') !== false ||
                //strpos($url, 'customer/section/load') !== false ||
                strpos($url, 'cartquickpro/cart/configure') ||
                //strpos($url, 'cartquickpro/cart/add') !== false ||
                strpos($url, 'cartquickpro/cart/updateItemOption') !== false ||
                strpos($url, 'cartquickpro/sidebar/removeItemx') !== false ||
                strpos($url, 'checkout/cart/updateItemQty') !== false ||
                strpos($url, 'checkout/cart/updatePost') !== false ||
                //strpos($url, 'checkout/cart') !== false ||
                strpos($url, '/wishlist/index/add') !== false ||
                //strpos($url, 'cartquickpro/cart/delete') !== false ||
                strpos($url, '/multiwishlist') !== false ||
                strpos($url, 'mine/totals-informationxx') !== false ||
                strpos($url, '/carts/mine/totals-informationx') !== false
            ) {

                // } elseif (strpos($url, 'customer/section/load') !== false || strpos($url, 'cartquickpro/cart/add') !== false || strpos($url, 'cartquickpro/sidebar/removeItem') !== false || strpos($url, '/wishlist/index/add') !== false || strpos($url, 'cartquickpro/cart/delete') !== false  || strpos($url, '/multiwishlist') !== false   || strpos($url, 'mine/totals-informationxx') !== false || strpos($url, '/carts/mine/totals-informationx') !== false  ) { 
                if (strpos($url, 'checkout/cart/updateItemQty') !== false) {
                    $currentTime = microtime(true);
                    $oldTime = isset ($_SESSION['update_item_qty_check']) ? $_SESSION['update_item_qty_check'] : 0;
                    if (($currentTime - $oldTime) < 5) {
                        if ($debuggingflag == "true") {
                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Skipping CSD price check for unneeded page & very recent update " . $url);
                            return "";
                        }
                    } else {
                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Running CSD price on cart due to quantity update " . $url);
                    }
                    $_SESSION['update_item_qty_check'] = $currentTime;
                } else {
                    if ($debuggingflag == "true") {
                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Skipping CSD price check for unneeded page " . $url);
                    }
                    return "";
                }
            } elseif ($apidown == true || $bSkip == 'true') {
                if ($debuggingflag == "true") {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Skipping CSD price check for apidown or non-product page" . ($apidown));
                }

                return "";
            } elseif ($visibility != "" && $visibility != "4" && $singleitem == "false" && $controller != "product") {
                if ($debuggingflag == "true") {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Skipping CSD price check for invis1");
                }

                return "";
            } elseif ($visibility != "" && $visibility != "4" && $singleitem == "true") {
                if ($debuggingflag == "true") {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Skipping CSD price check for invis2");
                }

                return "";
            } elseif ($controller == "product" && $singleitem == "false" && false) {
                if ($debuggingflag == "true") {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Skipping CSD price check for prod...");
                }

                return "";
            } elseif ($currparent !== "" && $controller !== "cart" && $singleitem == "false" && strpos($url, 'amasty_quickorder') === false) {
                if ($debuggingflag == "true") {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Skipping CSD price check for child item");
                }

                return "";
            } elseif ((strpos($prodDone, "|" . $prod . "xxx|") !== false) && (strpos($url, 'checkout') === false && strpos($url, 'cart') === false)) {
                if ($debuggingflag == "true") {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Skipping CSD price check for already done item");
                }

                return "";
            } elseif (strpos($url, '/wishlist/index/index') === false && strpos($url, '/wishlist') !== false) {
                if ($debuggingflag == "true") {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Skipping CSD price check for wishlist parent page " . $url);
                }

                return "";

            } else {
                if ($debuggingflag == "true") {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Price check continues...");
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "launching api");
                }
            }
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "adding prod: " . $prod . " to SCPL  ----  " . $url);
            $this->csd->getSession()->setProdDone($prodDone . $prod . "|");

            //$this->csd->getSession()->setApidown(true);
            //$debugstr = var_export(debug_backtrace(1,1));
            // $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , $debugstr);

            $productParams = new \ArrayObject();
            $productParams[] = new \SoapVar(array('product' => $prod), SOAP_ENC_OBJECT);
            // $productParams[] = new SoapVar($prod, XSD_STRING, null, null, 'product');
            //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "GetCSDPrice: Setting up SalesCustomerPricingList " . $url);
            $thisparamLines = array('SalesCustomerPricingListProductRequestContainer' => $productParams->getArrayCopy());
            $params->append(
                new SoapVar(
                    $thisparamLines,
                    SOAP_ENC_OBJECT,
                    null,
                    null,
                    'SalesCustomerPricingListProductRequestContainer'
                )
            );
        }
        $qty=1;
        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "GetCSDPrice: Calling SalesCustomerPricingSelect " . $url);
        $dTime = $this->csd->LogAPITime("SalesCustomerPricingSelect", "request", $moduleName, ""); //request/result // //request/result
        $gcnl = $this->csd->SalesCustomerPricingSelect($cono, $custno, $this->csd->getConfigValue('operinit'), '', $whse, $qty, $prod);
        $this->csd->LogAPITime("SalesCustomerPricingSelect", "result", $moduleName, $dTime);
    


        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Processing data...");

        $newprice = 0;

        try {
            if (!isset ($gcnl)) {
                if ($debuggingflag == "true") {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "error from pricing: apidown");
                }

                $this->csd->getSession()->setApidown(true);
                $apidown = $this->csd->getSession()->getApidown();
            }

            if (isset ($gcnl["fault"])) {
                if ($debuggingflag == "true") {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "error from pricing: " . $gcnl["fault"]);
                }

                $this->csd->getSession()->setApidown(true);
                $apidown = $this->csd->getSession()->getApidown();

                if ($debuggingflag == "true") {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "API error: " . $gcnl["fault"]);
                }
            }
        } catch (\Exception $e) {
            $this->csd->gwLog($e->getMessage());
        }

        try {
            $listprice = null;

            if (isset ($gcnl["SalesCustomerPricingListResponseContainerItems"])) {
                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "mult item");

                foreach ($gcnl["SalesCustomerPricingListResponseContainerItems"] as $_gcnl) {
                    if ($_gcnl["product"] != "") {
                        $product = $products[$_gcnl["product"]];
                        $price = $product->getPrice();
                        $prod = $product->getSku();

                        if (isset ($_gcnl["price"])) {
                            $price = $_gcnl["price"];
                            if (!empty ($_gcnl["pround"])) {
                                switch ($_gcnl["pround"]) {
                                    case 'u';
                                        $price = \ceil($price);
                                        break;
                                    case 'd';
                                        $price = \floor($price);
                                        break;
                                    case 'n';
                                        $price = \round($price);
                                        break;
                                    default;
                                        break;
                                }
                            } //end pround check
                        }
                        if (isset ($_gcnl["listprice"])) {
                            $listprice = $_gcnl["listprice"];
                        }

                        if ($price > 0) {
                            $product->setSpecialPrice($price);
                            $product->setPrice($price);
                            $product->setFinalPrice($price);
                        } elseif ($listprice > 0) {
                            $product->setPrice($listprice);
                            $product->setFinalPrice($price);
                            $product->setSpecialPrice($price);
                        } else {
                            $price = $product->getPrice();
                        }

                        $message = $prod . " Before: " . $price . " After: " . $product->getData('final_price');
                        $this->csd->gwLog(__CLASS__ . '/' . __FUNCTION__ . ': ', $message);
                    }
                }
            } elseif (isset ($gcnl["product"])) {
                if ($debuggingflag == "true") {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "single item " . $gcnl["product"]);
                }
                try {
                    $product = $products[$gcnl["product"]];
                    $price = $product->getPrice();
                    $prod = $product->getSku();

                    if (isset ($gcnl["price"])) {
                        $price = $gcnl["price"];
                        if (!empty ($gcnl["pround"])) {
                            switch ($gcnl["pround"]) {
                                case 'u';
                                    $price = \ceil($price);
                                    break;
                                case 'd';
                                    $price = \floor($price);
                                    break;
                                case 'n';
                                    $price = \round($price);
                                    break;
                                default;
                                    break;
                            }
                        } //end pround check

                    }
                    if (isset ($gcnl["listprice"])) {
                        $listprice = $gcnl["listprice"];
                    }

                    $product->setPrice($price);
                    $product->setFinalPrice($price);

                    if ($listprice > 0) {
                        $product->setPrice($price);
                        $product->setFinalPrice($price);
                        $product->setSpecialPrice($price);
                    } elseif ($price > 0) {
                        $product->setSpecialPrice($price);
                        $product->setPrice($price);
                        $product->setFinalPrice($price);
                    } else {
                        $price = $product->getPrice();
                    }
                    /*****/
                    if (strpos($url, '/checkout/cart/') !== false && $controller == 'cart') {
                        //\Magento\Checkout\Model\Cart\updateItems();
                        //return "";
                        $collection = $objectManager->get('\Magento\Framework\App\ResourceConnection');
                        $conn = $collection->getConnection();
                        $quote_table = $collection->getTableName('quote');
                        $quote_item_table = $collection->getTableName('quote_item');
                        $query = "SELECT q.entity_id, customer_id, q.store_id, i.item_id, i.sku, i.price, i.product_id as price FROM $quote_table q INNER JOIN $quote_item_table i ON i.quote_id=q.entity_id WHERE customer_id=$customer_id AND is_active=1 AND sku='$prod'";
                        $result = $conn->fetchAll($query);
                        $QuoteId = $result[0]["entity_id"];
                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "updating " . $gcnl["product"] . " price in cart1");
                        $quote = $objectManager->get('\Magento\Quote\Api\CartRepositoryInterface')->getActive($QuoteId);
                        $quoteItem = $quote->getItemById($result[0]["item_id"]);
                        $quoteItem->setPrice($price);
                        $quoteItem->setBasePrice($price);
                        $quoteItem->setCustomPrice($price);
                        $quoteItem->setOriginalCustomPrice($price);
                        /* maybe this is a solution??*/
                        $rowtotal = $price * $quoteItem->getQty();
                        $quoteItem->setCustomRowTotalPrice($rowtotal);
                        $quoteItem->setRowTotal($rowtotal);
                        $quoteItem->setBaseRowTotal($rowtotal);
                        $quoteItem->save();
                        //$query="UPDATE quote_item set row_total=$rowtotal, base_row_total=$rowtotal where quote_id=$QuoteId and item_id=" . $result[0]['item_id'] . "";
                        // $this->csd->gwLog($query);
                        //$quote->updateItem($result[0]["item_id"],null,null);
                        // $quote->collectTotals();
                        // $quote->save($quote);
                        // $quote->setTotalsCollectedFlag(false)->collectTotals()->save();
                        $conn->closeConnection();
                    }
                    /******/
                    $message = $prod . " !!Before: " . $price . " After: " . $product->getData('final_price');
                    if ($debuggingflag == "true") {
                        $this->csd->gwLog($message);
                    }
                } catch (\Exception $e1) {
                }
            } else {
                if ($debuggingflag == "true") {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "not set");
                }
            }
        } catch (Exception $e) {
            $this->csd->gwLog($e->getMessage());
            $this->csd->gwLog('Error ' . $e->getMessage());
        }

        return $price;
    }
}
