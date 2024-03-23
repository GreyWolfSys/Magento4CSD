<?php

namespace Altitude\CSD\Observer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\ObserverInterface;

class CustomerRegister implements ObserverInterface
{

    /** @var CustomerRepositoryInterface */
    protected $customerRepository;
    private $csd;

    /**
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        \Altitude\CSD\Model\CSD $csd,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->customerRepository = $customerRepository;
        $this->csd = $csd;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Customer registered");
        $customer = $observer->getEvent()->getCustomer();
        $configs = $this->csd->getConfigValue(['cono', 'slsrepin', 'slsrepout', 'whse', 'defaultterms', 'shipviaty', 'createcustomer', 'csdcustomerid']);

        //'createcustomer',
        extract($configs);
        //$createcustomer=0;
        $statecd = "";
        $name = $customer->getFirstname() . " " . $customer->getLastname();
        $termstype = $defaultterms;
        $taxablety = "";

        //not so required fields
        $custno = "0";
        $addr1 = "";//"$address['street']";
        $addr2 = "";
        $addr3 = "";
        $city = "";//$address['city'];
        $state = "";
        $zipcd = "";
        $phoneno = "";
        $faxphoneno = "";
        $countrycd = "";
        $countycd = "";
        $email = "";

        $custtype = "";
        $salester = "";
        $pricetype = "";

        $pricecd = "1";
        $minord = "0";
        $maxord = "0";
        $siccd = "0";
        $bofl = "Y";
        $subfl = "Y";
        $shipreqfl = "N";
        $transproc = "arscr";
        //safe to ignore these fields
        $nontaxtype = "";
        $taxcert = "";
        $creditmgr = "";
        $taxauth = "";
        $dunsno = "";
        $user1 = "";
        $user2 = "";
        $user3 = "";
        $user4 = "";
        $user5 = "";
        $user6 = "0";
        $user7 = "0";
        $user8 = "";
        $user9 = "";
        $addon1 = "0";
        $addon2 = "0";
        $addon3 = "0";
        $addon4 = "0";
        $addon5 = "0";
        $addon6 = "0";
        $addon7 = "0";
        $addon8 = "0";
        $custpo = "";
        $inbndfrtfl = "";
        $outbndfrtfl = "";

        try {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "csdcustomerid= " . $csdcustomerid);
            if ($createcustomer == 1) {

                $GCCustomer = SalesCustomerInsert(
                    $cono,
                    $operinit,
                    $custno,
                    $statecd,
                    $name,
                    $addr1,
                    $addr2,
                    $city,
                    $state,
                    $zipcd,
                    $phoneno,
                    $faxphoneno,
                    $siccd,
                    $termstype,
                    $custtype,
                    $salester,
                    $bofl,
                    $subfl,
                    $minord,
                    $maxord,
                    $taxcert,
                    $shipviaty,
                    $whse,
                    $slsrepin,
                    $slsrepout,
                    $shipreqfl,
                    $taxauth,
                    $taxablety,
                    $nontaxtype,
                    $creditmgr,
                    $dunsno,
                    $user1,
                    $user2,
                    $user3,
                    $user4,
                    $user5,
                    $user6,
                    $user7,
                    $user8,
                    $user9,
                    $countrycd,
                    $countycd,
                    $email,
                    $pricetype,
                    $pricecd,
                    $transproc,
                    $addon1,
                    $addon2,
                    $addon3,
                    $addon4,
                    $addon5,
                    $addon6,
                    $addon7,
                    $addon8,
                    $inbndfrtfl,
                    $outbndfrtfl,
                    $custpo,
                    $addr3
                );



                if (isset ($GCCustomer)) {

                    $custno = trim(explode('Customer #:', $GCCustomer["returnData"])[1]) . "";


                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "New custno: " . $custno);
                    #$customer->setCustomAttribute("CSD_CustNo", $custno);

                    #$this->customerRepository->save($customer);

                    $customer2 = $this->customerRepository->getById($customer->getId());
                    $customer2->setCustomAttribute('csd_custno', $custno);
                    $this->customerRepository->save($customer2);
                }
            } else {
                $custno = $csdcustomerid;
                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Setting custno: " . $custno);
                $customer2 = $this->customerRepository->getById($customer->getId());
                $customer2->setCustomAttribute('csd_custno', $custno);
                $this->customerRepository->save($customer2);
            }
        } catch (\Exception $e) {
            $this->csd->gwLog($e->getMessage());
        }
        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Customer registered - complete");
    }
}
