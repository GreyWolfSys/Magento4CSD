<?php

namespace Altitude\CSD\Cron;

class Payments
{
    protected $csd;

    protected $resourceConnection;

    /**
     * Constructor
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Altitude\CSD\Model\CSD $csd,
        \Magento\Framework\App\ResourceConnection $resourceConnection
    )
    {
        $this->csd = $csd;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Execute the cron
     *
     * @return void
     */
    public function execute()
    {
        $moduleName = $this->csd->getModuleName(get_class($this));
        $configs = $this->csd->getConfigValue(['apikey', 'cono']);
        extract($configs);

        //*********************************************************************************
        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Starting payment cron---");
        $dbConnection = $this->resourceConnection->getConnection();

        try {
            $sql = "select * from `mg_sales_order` where `CC_AuthNo` is not null and `CC_AuthNo` != '' and `status` != 'complete' and `status` != 'closed';";
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "checking orders to invoice");
            $result = $dbConnection->fetchAll($sql);
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $collection = $objectManager->create('Magento\Sales\Model\Order');
            if (count($result) > 0) {
                // output data of each row
                $this->csd->gwLog(count($result) . ' CC records found');
                foreach ($result as $row) {
                    $incrementid = $row["increment_id"];
                    $authno = $row["CC_AuthNo"];
                    $erpOrderNo = $row["CSD_OrderNo"];
                    $CSD_OrderSuf = $row["CSD_OrderSuf"];
                  //  $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "inc=" . $incrementid);
                  //  $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "order=" . $erpOrderNo);
                    $order = $collection->loadByIncrementId($incrementid);// order->loadByIncrementId

                    //$gcnlOrder=SalesOrderSelect($cono,$ERPOrderNo,"");

                    if ($order->canInvoice()) {
                        // $invIncrementId = array();

                        $gcOrder = $this->csd->SalesOrderSelect($cono, $erpOrderNo, $CSD_OrderSuf, $moduleName);

                        if (isset($gcOrder)) { //
                            if ($gcOrder["cono"] != "0") {
                                $item = $gcOrder;
                                if (isset($item["orderno"])) {
                                    if ($item["stagecd"] >= 3) {
                                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Order is good to process...stage " . $item["stagecd"]);

                                        if (!$order->hasInvoices()) {
                                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Already invoiced");
                                            $invoice = $this->_invoiceService->prepareInvoice($order);
                                            $invoice->register();
                                            $invoice->save();
                                            $transactionSave = $this->_transaction
                                                            ->addObject($invoice)
                                                            ->addObject($invoice->getOrder());

                                            $transactionSave->save();
                                            $this->invoiceSender->send($invoice);

                                            //send notification code
                                            $order->addStatusHistoryComment(
                                                        __('Notified customer about invoice #%1.', $invoice->getId())
                                                    )
                                                    ->setIsCustomerNotified(true)
                                                    ->save();
                                        } else {
                                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Getting order status");
                                        }
                                    } else {
                                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Order will not process...stage " . $item["stagecd"]);
                                    }
                                }

                                //} //end for each gcpackage
                            } else {
                                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "no order found");
                            } //is set item
                        } else {
                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "GC call fail");
                        } //end is set gc call
                    } //end can invoice
                }//end has no invoice
            } else {
                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "0 results");
            }
        } catch (\Exception $e) {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Failed to open update order table: " . $e->getMessage());
        }
        try {
          //  $dbConnection->close();
        } catch (\Exception $e) {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Failed to close db connection: " . $e->getMessage());
        }

        return true;
    }

    //end process shipping
}
