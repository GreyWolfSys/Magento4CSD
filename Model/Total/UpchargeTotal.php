<?php

namespace Altitude\CSD\Model\Total;

class UpchargeTotal extends \Magento\Quote\Model\Quote\Address\Total\AbstractTotal
{
    /**
      * Collect grand total address amount
      *
      * @param \Magento\Quote\Model\Quote $quote
      * @param \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment
      * @param \Magento\Quote\Model\Quote\Address\Total $total
      * @return $this
      */
    protected $quoteValidator = null;

    public function __construct(
        \Magento\Quote\Model\QuoteValidator $quoteValidator,
        \Altitude\CSD\Helper\Data $helper
    )
    {
        $this->quoteValidator = $quoteValidator;
        $this->helper = $helper;
        $this->setCode('upcharge_total');
    }

    public function collect(
        \Magento\Quote\Model\Quote $quote,
        \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment,
        \Magento\Quote\Model\Quote\Address\Total $total
    ) {
        parent::collect($quote, $shippingAssignment, $total);

        $upchargeAmount = $this->getUpchargeAmount($quote, $total);

        $total->setTotalAmount('upcharge_total', $upchargeAmount);
        $total->setBaseTotalAmount('upcharge_total', $upchargeAmount);

        $total->setUpchargeTotal($upchargeAmount);
        $total->setBaseUpchargeTotal($upchargeAmount);

        $quote->setUpchargeTotal($upchargeAmount);
        $quote->setBaseUpchargeTotal($upchargeAmount);

        $total->setGrandTotal($total->getGrandTotal());
        $total->setBaseGrandTotal($total->getBaseGrandTotal());

        return $this;
    }

    protected function clearValues(Address\Total $total)
    {
        $total->setTotalAmount('subtotal', 0);
        $total->setBaseTotalAmount('subtotal', 0);
        $total->setTotalAmount('tax', 0);
        $total->setBaseTotalAmount('tax', 0);
        $total->setTotalAmount('discount_tax_compensation', 0);
        $total->setBaseTotalAmount('discount_tax_compensation', 0);
        $total->setTotalAmount('shipping_discount_tax_compensation', 0);
        $total->setBaseTotalAmount('shipping_discount_tax_compensation', 0);
        $total->setSubtotalInclTax(0);
        $total->setBaseSubtotalInclTax(0);
    }

    /**
     * Assign subtotal amount and label to address object
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @param Address\Total $total
     * @return array
     */
    public function fetch(\Magento\Quote\Model\Quote $quote, \Magento\Quote\Model\Quote\Address\Total $total)
    {
        $amount = $this->getUpchargeAmount($quote);

        return [
            'code' => 'upcharge_total',
            'title' => $this->helper->getUpchargeLabel(),
            'value' => $amount
        ];
    }

    public function getUpchargeAmount($quote)
    {
        $amount = 0;
        $shippingMethods = $this->helper->getUpchargeShipping();
        $paymentMethod = $this->helper->getUpchargePayment();
        $upchargePercent = $this->helper->getUpchargePercent();
        $waiveAmount = $this->helper->getUpchargeWaiveAmount();
        $currentShippingMethod = $quote->getShippingAddress()->getShippingDescription();
        $currentPayment = "";

        if ($quote->getPayment()->getMethod()) {
            $currentPayment = $quote->getPayment()->getMethodInstance()->getTitle();
        }

        if ($quote->getSubtotal() < $waiveAmount &&
            in_array($currentShippingMethod, $shippingMethods) &&
            $paymentMethod == $currentPayment
        ) {
            $amount = $quote->getSubtotal() * $upchargePercent / 100;
        }

        return $amount;
    }

    /**
     * Get Subtotal label
     *
     * @return \Magento\Framework\Phrase
     */
    public function getLabel()
    {
        return $this->helper->getUpchargeLabel();
    }
}
