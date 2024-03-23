<?php

namespace Altitude\CSD\Model;

use Magento\Customer\Api\Data\AddressInterfaceFactory as CustomerAddressFactory;
use Magento\Customer\Api\Data\RegionInterfaceFactory as CustomerAddressRegionFactory;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote\Address;
use Magento\Tax\Api\Data\TaxClassKeyInterface;
use Magento\Tax\Model\Calculation;
use Magento\Framework\App\Response\RedirectInterface;

class SalesTotalTax extends \Magento\Tax\Model\Sales\Total\Quote\Tax
{
    protected $csd;
    /**
     * @var RedirectInterface
     */
    protected $redirector;
    /**
     * Class constructor
     *
     * @param \Magento\Tax\Model\Config $taxConfig
     * @param \Magento\Tax\Api\TaxCalculationInterface $taxCalculationService
     * @param \Magento\Tax\Api\Data\QuoteDetailsInterfaceFactory $quoteDetailsDataObjectFactory
     * @param \Magento\Tax\Api\Data\QuoteDetailsItemInterfaceFactory $quoteDetailsItemDataObjectFactory
     * @param \Magento\Tax\Api\Data\TaxClassKeyInterfaceFactory $taxClassKeyDataObjectFactory
     * @param CustomerAddressFactory $customerAddressFactory
     * @param CustomerAddressRegionFactory $customerAddressRegionFactory
     * @param \Magento\Tax\Helper\Data $taxData
     * @param RedirectInterface $redirector
     */
    public function __construct(
        \Magento\Tax\Model\Config $taxConfig,
        \Magento\Tax\Api\TaxCalculationInterface $taxCalculationService,
        \Magento\Tax\Api\Data\QuoteDetailsInterfaceFactory $quoteDetailsDataObjectFactory,
        \Magento\Tax\Api\Data\QuoteDetailsItemInterfaceFactory $quoteDetailsItemDataObjectFactory,
        \Magento\Tax\Api\Data\TaxClassKeyInterfaceFactory $taxClassKeyDataObjectFactory,
        CustomerAddressFactory $customerAddressFactory,
        CustomerAddressRegionFactory $customerAddressRegionFactory,
        \Magento\Tax\Helper\Data $taxData,
        \Altitude\CSD\Model\CSD $csd,
        RedirectInterface $redirector
    ) {
        $this->setCode('tax');
        parent::__construct(
            $taxConfig,
            $taxCalculationService,
            $quoteDetailsDataObjectFactory,
            $quoteDetailsItemDataObjectFactory,
            $taxClassKeyDataObjectFactory,
            $customerAddressFactory,
            $customerAddressRegionFactory,
            $taxData
        );
        $this->csd = $csd;
        $this->redirector = $redirector;
    }

    /**
     * Custom Collect tax totals for quote address
     *
     * @param Quote $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Address\Total $total
     * @return $this
     * @throws RemoteServiceUnavailableException
     */
    public function collect(
        \Magento\Quote\Model\Quote $quote,
        \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment,
        \Magento\Quote\Model\Quote\Address\Total $total
    ) {
        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Tax text1");
        #$this->clearValues($total);
        if (!$shippingAssignment->getItems()) {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "no items");
            return $this;
        }
        $currentTime = microtime(true);
        $oldTime = isset ($_SESSION['tax_last_checked']) ? $_SESSION['tax_last_checked'] : 0;
        if (($currentTime - $oldTime) < 5) {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "skipping, checked tax within last 30 seconds");

            $taxAmount = $_SESSION['tax_amount'];
            if ($taxAmount > 0) {
                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Tax amount = " . $taxAmount);
                $total->setTaxAmount($taxAmount);
                $total->setBaseTaxAmount($taxAmount);

                $total->setTotalAmount('tax', $taxAmount);
                $total->setBaseTotalAmount('tax', $taxAmount);
            } else {
                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "No tax amount set");
                unset($_SESSION['tax_last_checked']);
            }
            return $this;
        }
        $_SESSION['tax_last_checked'] = $currentTime;

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $customerSession2 = $objectManager->get('Magento\Customer\Model\Session');
        $request = $objectManager->get('Magento\Framework\App\Request\Http');
        $url = $this->csd->urlInterface()->getCurrentUrl();
        error_log("Tax url = " . $url);

        if (!$customerSession2->isLoggedIn()) {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "not logged in. not fetching tax");
            return $this;
        }

        $taxfromquote = $this->csd->getConfigValue('taxfromquote');

        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Tax text2 taxfromquote=" . $taxfromquote);

        if ($taxfromquote == "0") {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Calling SalesOrderPreInsert...");
            $taxAmount = $this->csd->SalesOrderPreInsert($quote);
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Done calling SalesOrderPreInsert");
        } else {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Calling SubmitOrder for tax");
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "url: " . $url);
            $controller = $request->getControllerName();
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "controller: " . $controller);
            //error_log("Altitude-SubmitOrder-tax" );
            if ($controller == "store") {
                return true;
            }
            \Magento\Framework\Profiler::start("Altitude-SubmitOrder-tax");
            $taxAmount = $this->csd->SubmitOrder($quote, "pre-tax", true);
            \Magento\Framework\Profiler::stop("Altitude-SubmitOrder-tax");

        }
        if ($taxAmount > 0) {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Tax amount = " . $taxAmount);
            $total->setTaxAmount($taxAmount);
            $total->setBaseTaxAmount($taxAmount);

            $total->setTotalAmount('tax', $taxAmount);
            $total->setBaseTotalAmount('tax', $taxAmount);
        } else {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "No tax amount set");
            unset($_SESSION['tax_last_checked']);
        }
        $_SESSION['tax_amount'] = $taxAmount;
        error_log("Tax url end = " . $url);
        return $this;
    }

}
