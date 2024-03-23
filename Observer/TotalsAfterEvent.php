<?php

namespace Altitude\CSD\Observer;

use Magento\Quote\Model\Quote;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Altitude\CSD\Helper\Data;

/**
 * Class TotalsAfterEvent
 */
class TotalsAfterEvent implements ObserverInterface
{

    protected $productrepository;

    public function __construct(
        Data $dataHelper,
        \Magento\Catalog\Api\ProductRepositoryInterface $productrepository
    ) {
        $this->dataHelper = $dataHelper;
        $this->productrepository = $productrepository;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        return "";
        /** Fetch Address related data */
        $shippingAssignment = $observer->getEvent()->getShippingAssignment();
        $address = $shippingAssignment->getShipping()->getAddress();
        $region = $address->getRegionCode();

        // fetch quote data
        /** @var Quote $quote */
        $quote = $observer->getEvent()->getQuote();

        $items = $quote->getAllItems();

        foreach ($items as $item) {
            $productId = $item->getProductId();
            $product = $this->productrepository->getById($productId);
            error_log("Calling getQtyInfo on line 47 of CSD/Observer/TotalsAfterEvent.php");
            $this->dataHelper->getQtyInfo($product, $region);
        }
    }
}