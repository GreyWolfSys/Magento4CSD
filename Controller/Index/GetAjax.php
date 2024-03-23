<?php

namespace Altitude\CSD\Controller\Index;

use Magento\Framework\Controller\ResultFactory;

class GetAjax extends \Magento\Framework\App\Action\Action
{
    protected $_pageFactory;
    protected $coreSession;
    protected $customerSession;
    protected $_resultJsonFactory;
    protected $_storeManager;
    private $csd;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Framework\Session\SessionManagerInterface $coreSession,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Altitude\CSD\Model\CSD $csd
    ) {
        $this->_pageFactory = $pageFactory;
        $this->coreSession = $coreSession;
        $this->customerSession = $customerSession;
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->_storeManager = $storeManager;
        $this->csd = $csd;

        return parent::__construct($context);
    }

    public function execute()
    {
        if ($this->csd->botDetector() || !$this->getRequest()->isXmlHttpRequest()) {
            $this->_redirect('/');
            return;
        }

        $moduleName = $this->csd->getModuleName(get_class($this));
        $controller = $this->getRequest()->getControllerName();
        $url = $this->csd->urlInterface()->getCurrentUrl();
        $configs = $this->csd->getConfigValue(['cono', 'csdcustomerid', 'whse']);
        extract($configs);

        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "$moduleName: " . $controller . " / u: " . $url);

        if ($this->csd->getSession()->getApidown()) {
            $apidown = $this->csd->getSession()->getApidown();
        } else {
            $apidown = false;
        }

        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "config price started");
        $newprice = 0;

        if ($this->customerSession->isLoggedIn()) {
            // Logged In
            $customerSession = $this->customerSession;
            $customerData = $customerSession->getCustomer();
            $custno = $customerData['csd_custno'];
        } else {
            // Not Logged In
            $custno = $csdcustomerid;
        }

        if (empty($custno)) {
            $custno = $csdcustomerid;
        }

        $prod = $this->getRequest()->getParam('sku');

        if ($apidown == false) {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "calling config price api. cono= " . $cono . " prod= " . $prod . " whse= " . $whse . " cust= " . $custno);

            try {
                $gcnl = $this->csd->SalesCustomerPricingSelect($cono, $prod, $whse, $custno, '', '1', $moduleName);
                if (!isset($gcnl) || isset($gcnl["fault"])) {
					$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "api failed in getAjax, retrying");
                    $gcnl = $this->csd->SalesCustomerPricingSelect($cono, $prod, $whse, $custno, '', '1', $moduleName);
                }
                if (!isset($gcnl) || isset($gcnl["fault"])) {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "error from pricing");
                    $this->csd->getSession()->setApidown(true);
                } elseif (isset($gcnl["price"])) {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "gcnl: " . json_encode($gcnl));
                    if ($gcnl["price"]>0) {
                        $newprice = $gcnl["price"];
                       // $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "price==::" . $newprice);
                        //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "pround==::" . $newprice);
                            if (!empty($gcnl["pround"])){
                                switch($gcnl["pround"])
                                {
                                    case 'u';
                                        $newprice=\ceil($newprice);
                                        break;
                                    case 'd';
                                        $newprice=\floor($newprice);
                                        break;
                                    case 'n';
                                        $newprice=\round($newprice);
                                        break;
                                    default;
                                        break;
                                }
                            } //end pround check
                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "price==" . $newprice);
                    } else {
                        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                        $productRepository = $objectManager->get('\Magento\Catalog\Model\ProductRepository');
                        $product = $productRepository->get($prod);
                        $newprice = $product->getPrice();
                        //$newprice = $gcnl["price"];
                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "price===" . $newprice);
                    }
                }
            } catch (\Exception $e1) {
                $this->csd->gwLog($e1->getMessage());
            }
        } else {
            $this->csd->gwLog ("skipping config price api down");
        }

        $result = $this->_resultJsonFactory->create();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); 
        $store = $objectManager->get('\Magento\Framework\Locale\Resolver'); 

        $this->csd->gwLog("result: $newprice");
        $this->csd->gwLog('currentCurrencyCode: ' . $store->getLocale());
        return $result->setData(json_encode([
            'result'                => $newprice,
            'currentCurrencyCode'   => $this->_storeManager->getStore()->getCurrentCurrencyCode(),
            'localeCode'            => $store->getLocale()
        ]));
    }
}
