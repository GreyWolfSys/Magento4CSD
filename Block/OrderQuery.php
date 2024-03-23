<?php

namespace Altitude\CSD\Block;

use Magento\Framework\View\Element\Template;

class OrderQuery extends Template
{
    public function formatMoney($money)
    {
        $formater = new \NumberFormatter('en_US', \NumberFormatter::CURRENCY);
        return $formater->formatCurrency($money, "USD");
    }
}
