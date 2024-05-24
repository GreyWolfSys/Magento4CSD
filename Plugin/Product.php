<?php

namespace Altitude\CSD\Plugin;

class Product
{
    protected $objectManager;

    public $customerSession;

    protected $customerRepository;

    public $cid;

    public function __construct(
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Framework\ObjectManagerInterface $objectManager
    ) {
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->kunde = $this->customerSession->isLoggedIn();
        $this->cid = $this->customerSession->getCustomerId();
        $this->objectManager = $objectManager;
    }

    public function afterGetPrice(\Magento\Catalog\Model\Product $subject, $result)
    {
        return $result;
        $debuggingflag = "false";
        if (!empty ($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty ($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        // error_log ("!ip: " . $ip);
        //  error_log ("!ip: " . $ip);
        if ($ip != '10.0.71.1') {
            //   return $result;
        }
        //   error_log ("!ip: " . $ip);
        try {
            if (!empty ($_SERVER['HTTP_USER_AGENT'])) {
                $user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);

                $bot_identifiers = [
                    'bot',
                    'slurp',
                    'crawler',
                    'spider',
                    'curl',
                    'facebook',
                    'fetch',
                    'linkchecker',
                    'semrush',
                    'xenu',
                    'google',
                ];
                // See if one of the identifiers is in the UA string.
                foreach ($bot_identifiers as $identifier) {
                    if (strpos($user_agent, $identifier) !== false) {
                        #return $result;
                    }
                }
            }
            if (empty ($_SERVER['HTTP_USER_AGENT'])) {
                #return $result;
            }
        } catch (exception $e) {
        }
        if ($debuggingflag == "true") {
            error_log("Starting price check product page");
        }

        if ($debuggingflag == "true") {
            error_log("prod price check agent: " . $_SERVER['HTTP_USER_AGENT']);
        }
        if ($debuggingflag == "true") {
            error_log("prod price check referrer: " . $_SERVER['HTTP_REFERER']);
        }

        $url = $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];

        $id = $subject->getId();
        $sku = $subject->getSku();

        if (isset ($_SESSION[$url . $sku])) {
            if ($debuggingflag == "true") {
                error_log("sku/url already  " . $sku);
            }

            return $result;
        } else {
            $_SESSION[$url . $sku] = 1;
        }

        if ($debuggingflag == "true") {
            error_log("prod=" . $sku);
        }
        $result = $this->calculate($result, $sku, $result, $debuggingflag);
        unset($_SESSION[$url . $sku]);

        return $result;
    }

    public function calculate($price, $sku, $result, $debuggingflag)
    {
        //global $apikey, $apiurl, $csdcustomerid, $cono, $whse, $slsrepin, $defaultterms, $operinit, $transtype, $shipviaty, $slsrepout, $updateqty;
		$configs = $this->csd->getConfigValue(['p21customerid', 'cono', 'whse','slsrepin','defaultterms','operinit','transtype','shipviaty','slsrepout','updateqty','localpriceonly','localpricediscount' ]);
        extract($configs);
        if (empty($localpricediscount) || !isset($localpricediscount) || $localpricediscount==0) {
            $localpricediscount=1;
        } else {
            $localpricediscount=(100-$localpricediscount)/100;
        }
        $newprice = $result;
        //$isLoggedIn = $customerSession->getValue(\Magento\Customer\Model\Context::CONTEXT_AUTH);

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        if ($debuggingflag == "true") {
            error_log("!!");
        }

        $productRepository = $objectManager->get('\Magento\Catalog\Model\ProductRepository');

        // get product by product sku
        $product = $productRepository->get($sku);
        if ($debuggingflag == "true") {
            error_log("name=" . $product->getName());
        }
        //$debuggingflag="true";
        $productRepository = $objectManager->get('\Magento\Catalog\Model\ProductRepository');
        $product = $productRepository->get($sku);
        $productparent = $objectManager->create('Magento\ConfigurableProduct\Model\Product\Type\Configurable')->getParentIdsByChild($product->getId());
        if (isset ($productparent[0])) {
            if ($debuggingflag == "true") {
                error_log("is child, skipping");
            }

            return "Select option to see price";// $result;
        }

        if ($product->getTypeId() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
            if ($debuggingflag == "true") {
                error_log("product is configurable");
            }

            return "Select option to see price";// $result;
        }

        if ($debuggingflag == "true") {
            error_log("@@@");
        }
        $singleitem = "false";
        $visibility = "";
        $request = $objectManager->get('\Magento\Framework\App\Request\Http');
        $controller = $request->getControllerName();
        $currparent = "";
        $productparent = "";
        $singleitem = "true";

        //******************
        if ($debuggingflag == "true") {
            error_log("controller=" . $controller);
        }
        if ($controller == "category" || $controller == "cart" || $controller == "section") {
            if ($debuggingflag == "true") {
                error_log("..skipping for controller " . $controller);
            }

            return $result;
        }elseif ($localpriceonly=="Magento"){
            if ($debuggingflag == "true") {
                $this->csd->gwLog("..skipping for local price only  " . $controller);
            }

            return $result;
        } elseif ($controller != 'product') {
            try {
                if ($debuggingflag == "true") {
                    $this->csd->gwLog("Skipping for controller " . $controller);
                }

                return $result;
            } catch (Exception $e) {
                if ($debuggingflag == "true") {
                    $this->csd->gwLog('Error ' . $e->getMessage());
                }
                $visibility = "4";
            }
        } else {
            $visibility = "1";

            $currparent = "";
            unset($productparent);
            if (isset ($parentdone)) {
            } else {
                $parentdone = "|";
            }
        }
        try {
            if ($singleitem == "false") {
                if ($controller == 'product') {
                    $visibility = "4";
                } else {
                }
            }
        } catch (Exception $e) {
            if ($debuggingflag == "true") {
                $this->csd->gwLog('Error ' . $e->getMessage());
            }
        }

        //****************

        // error_log ("continuing...vis=" . $visibility . "; singleitem=" . $singleitem);

        if ($visibility != "" && $visibility != "4" && $singleitem == "false") {
            if ($debuggingflag == "true") {
                error_log("Skipping  price check for invis");
            }
            //$price=rand() ;

            return $result;
        } else {
            if ($this->customerSession->isLoggedIn()) {
                // Logged In
                $customerSession2 = $objectManager->get('Magento\Customer\Model\Session');
                $customerData = $customerSession2->getCustomer();

                $custno = $customerData['csd_custno'];
            } else {
                // Not Logged In
                $custno = $csdcustomerid;
            }

            if (empty ($custno)) {
                $custno = $csdcustomerid;
            }

            try {
                if (isset ($_SESSION['x' . $custno . $sku])) {
                    return $_SESSION['x' . $custno . $sku];
                } else {
                    //  error_log ("doing price check");

                    // $gcnl=SalesCustomerPricingSelect($cono, $custno, $sku, "1", $whse, $whse,"ea","","","","" );
                    $gcnl = SalesCustomerPricingSelect($cono, $sku, $whse, $custno, '', 1);
                }
            } catch (Exception $e) {
                error_log('Error ' . $e->getMessage());
                $newprice = $result;
            }

            if (isset ($gcnl["price"])) {
                if (!empty ($gcnl["listprice"])) {
                    $listprice = $gcnl["listprice"];
                } else {
                    $listprice = 0;
                }
                error_log("listprice=" . $listprice);
                $newprice = $gcnl["price"];
                if (!empty ($gcnl["pround"])) {
                    switch ($gcnl["pround"]) {
                        case 'u';
                            $newprice = \ceil($newprice);
                            break;
                        case 'd';
                            $newprice = \floor($newprice);
                            break;
                        case 'n';
                            $newprice = \round($newprice);
                            break;
                        default;
                            break;
                    }
                } //end pround check
                if ($listprice > 0) {
                    $productRepository = $objectManager->get('\Magento\Catalog\Model\ProductRepository');
                    $_product = $productRepository->get($sku);
                    $_product->setSpecialPrice($listprice);
                    $_product->getResource()->saveAttribute($_product, 'special_price');
                    $_product->save();
                }
            } else {
                $newprice = $result;
            }
			if ($newprice==0 && $localpriceonly=="Hybrid") {
                $newprice = $product->getPrice();
                $newprice = $newprice*$localpricediscount;
            } 
            return $newprice;
        }
    }
}
