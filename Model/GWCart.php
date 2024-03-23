<?php

namespace Altitude\CSD\Model;

use Magento\Framework\Event\ObserverInterface;

class GWCart implements ObserverInterface
{
    protected $csd;

    public function __construct(
        \Altitude\CSD\Model\CSD $csd
    ) {
        $this->csd = $csd;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $moduleName = $this->csd->getModuleName(get_class($this));
        $sendtoerpinv = $this->csd->getConfigValue('sendtoerpinv');

        if ($sendtoerpinv == "1") {
            $invoice = $observer->getEvent()->getInvoice();
            $this->csd->SendToGreyWolf($invoice, $moduleName);
        }

        return true;
    }
}
