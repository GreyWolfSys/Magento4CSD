<?php

namespace Altitude\CSD\Observer;

use Magento\Framework\Event\ObserverInterface;

class Success implements ObserverInterface
{
    protected $csd;

    protected $upchargeTotalHelper;

    protected $quoteFactory;

    public function __construct(
        \Altitude\CSD\Model\CSD $csd,
        \Altitude\CSD\Helper\Data $upchargeTotalHelper,
        \Magento\Quote\Model\QuoteFactory $quoteFactory
    ) {
        $this->csd = $csd;
        $this->upchargeTotalHelper = $upchargeTotalHelper;
        $this->quoteFactory = $quoteFactory;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $quote = $this->quoteFactory->create()->load($order->getQuoteId());
        $upchargeAmount = $this->upchargeTotalHelper->getUpchargeAmount($quote);
        $incrementId = $order->getIncrementId();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();

        try {
            $result = $connection->fetchAll("SELECT * FROM `gws_GreyWolfOrderFieldUpdate` WHERE `orderid` = '$incrementId'");

            if (count($result) > 0) {
                $connection->query("UPDATE `gws_GreyWolfOrderFieldUpdate` SET `shipping_upcharge`='$upchargeAmount' WHERE `orderid`='$incrementId'");
            } else {
                $connection->query("INSERT INTO `gws_GreyWolfOrderFieldUpdate` (`orderid`, `dateentered`, `shipping_upcharge`) VALUES ('$incrementId', now(), '$upchargeAmount')");
            }
        } catch (\Exception $e) {
            $this->csd->gwLog($e->getMessage());
        }
    }
}
