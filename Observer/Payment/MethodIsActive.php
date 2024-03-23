<?php

namespace Altitude\CSD\Observer\Payment;

class MethodIsActive implements \Magento\Framework\Event\ObserverInterface
{
    private $csd;

    private $customerFactory;

    private $addressFactory;

    private $regionFactory;

    private $customerSession;

    public function __construct(
        \Altitude\CSD\Model\CSD $csd,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Model\AddressFactory $addressFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Directory\Model\RegionFactory $regionFactory
    ) {
        $this->csd = $csd;
        $this->customerFactory = $customerFactory;
        $this->addressFactory = $addressFactory;
        $this->regionFactory = $regionFactory;
        $this->customerSession = $customerSession;
    }

    /**
     * Execute observer
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(
        \Magento\Framework\Event\Observer $observer
    ) {
        $moduleName = $this->csd->getModuleName(get_class($this));
        $url = $this->csd->urlInterface()->getCurrentUrl();
        $configs = $this->csd->getConfigValue(['cono', 'csdcustomerid', 'hidepmt', 'blockpofordefault']);
        extract($configs);

        $customerSession = $this->customerSession;
        if ($customerSession->isLoggedIn()) {
            // Logged In
            $customerData = $customerSession->getCustomer();
            $custno = $customerData['csd_custno'];
        } else {
            // Not Logged In
            $custno = $csdcustomerid;
        }

        $method = $observer->getEvent()->getMethodInstance();
        $methodTitle = $method->getTitle();

        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Payment method title: " . $methodTitle);
        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Payment method: " . $method->getCode());
        if ($blockpofordefault == "1") {
            if ($method->getCode() == "purchaseorder") {
                if ($custno == $csdcustomerid) {
                    $result = $observer->getEvent()->getResult();
                    $result->isAvailable = false;
                    $result->setData('is_available', false);
                }
            }
        }
        if (!empty ($hidepmt)) {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "payment method check");

            $terms = "notset";
            $gcnl = $this->csd->SalesCustomerSelect($cono, $custno, $moduleName);

            if (isset ($gcnl["errordesc"])) {
                if ($gcnl["errordesc"] != "") {
                    $nocust = true;
                } else {
                    $nocust = false;
                }
            } else {
                $nocust = false;
            }
            if ($nocust) {
                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Error retrieving results.");
            } else {
                $terms = $gcnl["termstype"];
            }

            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "cust=" . $custno . " terms=" . $terms);



            $result = $observer->getEvent()->getResult();
            $methods = explode(",", $hidepmt);
            foreach ($methods as $splitmethod) {
                $details = explode(":", $splitmethod);
                //COD:Credit Card
                if (strtolower(trim($details[0])) == strtolower(trim($terms)) && strtolower(trim($details[1])) == strtolower(trim($methodTitle))) {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Pmt method not allowed!");
                    $result->isAvailable = false;
                    $result->setData('is_available', false);
                }
            }
        }
    }
}
