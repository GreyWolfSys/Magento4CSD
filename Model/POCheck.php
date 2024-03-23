<?php

namespace Altitude\CSD\Model;

use Magento\Framework\Event\ObserverInterface;

class POCheck implements ObserverInterface
{
    protected $csd;

    protected $resourceConnection;

    public function __construct(
        \Altitude\CSD\Model\CSD $csd,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\UrlInterface $url,
        \Magento\Framework\App\ResponseFactory $responseFactory,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->csd = $csd;
        $this->resourceConnection = $resourceConnection;
        $this->_url = $url;
        $this->_responseFactory = $responseFactory;
        $this->_messageManager = $messageManager;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        // $configs = $this->csd->getConfigValue(['cono', 'csdcustomerid','autoinvoice','forceuniquepo']);
        // extract($configs);
        $cono = $this->csd->getConfigValue('cono');
        $csdcustomerid = $this->csd->getConfigValue('csdcustomerid');
        $autoinvoice = $this->csd->getConfigValue('autoinvoice');
        $forceuniquepo = $this->csd->getConfigValue('forceuniquepo');

        if ($forceuniquepo == 0) {
            return true;
        }
        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "starting event for dupe po check");
        $moduleName = $this->csd->getModuleName(get_class($this));
        $sendtoerpinv = $this->csd->getConfigValue('sendtoerpinv');

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "dupe1.1");
        $customerSession2 = $objectManager->get('Magento\Customer\Model\Session');
        //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "dupe1.2");
        $customerData = $customerSession2->getCustomer();
        //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "dupe1.3");
        $bDuplicate = true;
        $order = $observer->getEvent()->getOrder();
        if ($order->getCustomerIsGuest()) {
            //  $this->csd->gwLog ("customer is guest");
            $custno = $csdcustomerid;
        } else {

            //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "dupe1.31");
            $customer = $objectManager->create('Magento\Customer\Model\Customer')->load($order->getCustomerId());
            // $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "dupe1.32");
            $custno = $customer->getData('csd_custno');
            //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "dupe1.33");
            if (!$custno) {

                //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "dupe1.34");
                // Not Logged In
                $custno = $csdcustomerid;
                //	$this->csd->gwLog ("csd custno is default");

            }
        }

        //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "dupe1.4");
        if ($custno == $csdcustomerid) {
            // don't need to block these
            return true;
        }
        //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "dupe1.5");
        //$orderids = $observer->getEvent()->getOrderIds();

        //$dbConnection = $this->resourceConnection->getConnection();
        //$customerBeforeAuthUrl = $this->_url->getUrl('checkout/cart/index');
        $customerBeforeAuthUrl = $this->_url->getUrl('checkout', ['_fragment' => 'payment']);

        //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "dupe1");
        try {

            $payment = $order->getPayment();
            //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "dupe2");
            try {
                if (isset ($payment)) {
                    $poNumber = $payment->getPoNumber();
                } else {
                    $poNumber = "";
                }
            } catch (\Exception $ePO) {
                $poNumber = "";
            }
            //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "dupe3");
            if (empty ($poNumber)) {
                // don't need to block these
                return true;
            }
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "PO = " . $poNumber . "; custno = " . $custno);

            $gcnl = $this->csd->SalesOrderList($cono, $custno, "", "", "", "", $poNumber, "", "", "", "", "");
            if (isset ($gcnl["errorcd"])) {
                if ($gcnl["errorcd"] == "045-001") {
                    $bDuplicate = false;
                }
            }
        } catch (\Exception $e) {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Dupe check error: " . $e->getMessage());
        }
        if ($bDuplicate) {
            try {
                $message = __("Purchase order number has already been used and must be unique.");
                $this->_messageManager->addError($message);
                throw new \Magento\Framework\Exception\LocalizedException(__($message));

            } catch (\Exception $e) {

                //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "dupe7" . $customerBeforeAuthUrl);
                $this->_responseFactory->create()->setRedirect($customerBeforeAuthUrl)->sendResponse();
                throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
                return;
                exit;
                // \Magento\Framework\App\ObjectManager::getInstance()->get('Psr\Log\LoggerInterface')->critical($exception);
            }
        }
        return true;
    }
}
