<?php

namespace Altitude\CSD\Model;

use Magento\Framework\Event\ObserverInterface;

class GWCartOrder implements ObserverInterface
{
    protected $csd;

    protected $resourceConnection;

    public function __construct(
        \Altitude\CSD\Model\CSD $csd,
        \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {
        $this->csd = $csd;
        $this->resourceConnection = $resourceConnection;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $moduleName = $this->csd->getModuleName(get_class($this));
        $sendtoerpinv = $this->csd->getConfigValue('sendtoerpinv');

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $orderids = $observer->getEvent()->getOrderIds();
        $dbConnection = $this->resourceConnection->getConnection();

        try {
            foreach ($orderids as $orderid) {
                $order = $objectManager->create('Magento\Sales\Api\Data\OrderInterface')->load($orderid);
                $payment = $order->getPayment();
                $paymentMethod = (string) $payment->getMethod();

                if (strpos($paymentMethod, "authorizenet") === false && strpos($paymentMethod, "anet_") === false) {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "not Authorize: $paymentMethod");
                } else {
                    $additionalInfo = $payment->getData('additional_information');
                    $authNo = "";

                    if (isset ($additionalInfo['authCode']) && $additionalInfo['authCode'] != "") {
                        $authNo = $additionalInfo['authCode'];
                        $sql = "UPDATE `sales_order` SET `CC_AuthNo`='$authNo' WHERE `entity_id`=$orderid";
                        $dbConnection->query($sql);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->csd->gwLog($e->getMessage());
        }

        if ($sendtoerpinv == "0") {
            try {
                foreach ($orderids as $orderid) {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "oid == " . $orderid);
                    $order = $objectManager->create('Magento\Sales\Api\Data\OrderInterface')->load($orderid);
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "inc id = " . $order->getIncrementId());
                    $this->csd->SendToGreyWolf($order);
                }
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                // Error logic
                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Error 1 - " . $e->getMessage());
            } catch (\Exception $e) {
                // Generic error logic
                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Error 2 - " . $e->getMessage());
                $order = $observer->getEvent()->getOrder();
                $this->csd->SendToGreyWolf($order);
            }
        }

        return true;
    }
}
