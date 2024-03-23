<?php
namespace Altitude\CSD\Observer;

use \Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\Event\Observer;

class ChangeTaxTotal implements ObserverInterface
{
  public $additionalTaxAmt = 0;

  public function execute(Observer $observer)
  {
    /** @var Magento\Quote\Model\Quote\Address\Total */
    //  $total = $observer->getData('total');

    //make sure tax value exist
    //if (count($total->getAppliedTaxes()) > 0) {
    // $total->addTotalAmount('tax', $this->additionalTaxAmt);
    // }

    // return $this;
  }
}