<?php

namespace Altitude\CSD\Cron;

// auth.net include
//require 'vendor/autoload.php';

/*need to make these work with M2 for auth.net*/
use Magento\Framework\App\Request\Http;
use Magento\Sales\Api\Data\OrderInterface;
use \Altitude\CSD\Cron\CenposConnector;

//use net\authorize\api\contract\v1 as AnetAPI;
//use net\authorize\api\controller as AnetController;
use Psr\Log\LoggerInterface;



class UpdatePackageShipping
{
    protected $logger;

    protected $order;

    protected $csd;

    protected $resourceConnection;
    protected $_invoiceService;
    protected $transaction;

    public function __construct(
        LoggerInterface $logger,
        \Altitude\CSD\Model\CSD $csd,
        OrderInterface $order,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory $invoiceCollectionFactory,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction
    ) {
        $this->logger = $logger;
        $this->order = $order;
        $this->csd = $csd;
        $this->resourceConnection = $resourceConnection;
        $this->_invoiceCollectionFactory = $invoiceCollectionFactory;
        $this->_invoiceService = $invoiceService;
        $this->transaction = $transaction;
    }

    /**
     * Write to system.log
     *
     * @return void
     */
    public function execute()
    {
        $moduleName = $this->csd->getModuleName(get_class($this));
        $dbConnection = $this->resourceConnection->getConnection();
        $configs = $this->csd->getConfigValue(['cono', 'csdcustomerid', 'autoinvoice', 'shipbystage']);
        extract($configs);

        $this->csd->gwLog('Updating shipping and packages from erp');
        //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Checking Packages Queue");

        try {
            // $sql = "select * from `sales_order` where `CC_AuthNo` is not null and `CC_AuthNo` != '' and `status` != 'complete' and `status` !='canceled';";
            $sql = "select distinct mg_sales_order.*, gws_GreyWolfOrderFieldUpdate.suffix_list from `mg_sales_order` LEFT JOIN gws_GreyWolfOrderFieldUpdate ON mg_sales_order.increment_id=gws_GreyWolfOrderFieldUpdate.orderid where `ext_order_id` is not null and `ext_order_id` != '' and `status` != 'complete' and `status` !='canceled' and `status` !='closed'  AND updated_at > DATE_SUB(CURDATE(),INTERVAL 60 day) ORDER BY entity_id desc;";
            //  $this->csd->gwLog($sql);
            $result = $dbConnection->fetchAll($sql);
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            if (count($result)) {
                // output data of each row
                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Count of records is...");
                $this->csd->gwLog(count($result) . ' incomplete order records found');
                foreach ($result as $row) {
                    $incrementid = $row["increment_id"];
                    $completesuffixlist = $row["suffix_list"];
                    $authno = $row["CC_AuthNo"];
                    $orderfields = explode("-", $row["ext_order_id"]);
                    $erpOrderNo = $orderfields[0]; //$row["CSD_OrderNo"];
                    $CSD_OrderSuf = $orderfields[1]; //$row["CSD_OrderSuf"];
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "checking " . $erpOrderNo);
                    $order = $this->order->loadByIncrementId($incrementid);
                    if (isset ($gcLines)) {
                        unset($gcLines);
                    }
                    // if ($erpOrderNo=="30564572") {
                    //      continue;
                    //   }
                    if ($order->canShip() || $order->canInvoice()) { //  || $erpOrderNo=="30564572"
                        $HasPackage = false;
                        $checkstage = false;
                        $shippedStage = false;
                        $shippingAmount = 0;
                        $invoiceAmount = 0;
                        $taxAmount = 0;
                        $subtotalAmount = 0;
                        if ($order->canShip()) {
                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "can ship = true for " . $order->getIncrementId() . " / " . $erpOrderNo);
                        }
                        if ($order->canInvoice()) {
                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "can invoice = true for " . $order->getIncrementId() . " / " . $erpOrderNo);
                        }
                        //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "shipbystage:: " . $shipbystage);
                        ////don't need invoices in place for this
                        // $invIncrementId = array();
                        //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "checking stage");
                        try {
                            if (1 == 1) {
                                //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "checking order");
                                $gcnlOrder = $this->csd->SalesOrderSelect($cono, $erpOrderNo, $CSD_OrderSuf, $moduleName);
                                if (isset ($gcnlOrder["stagecd"])) {
                                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "stage=" . $gcnlOrder["stagecd"] . "; custno=" . $gcnlOrder["custno"]);

                                    $bCancel = false;

                                    if (strpos($gcnlOrder["errordesc"], 'not found') !== false || $gcnlOrder["custno"] == 0) {
                                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Order does not exist. Canceling");
                                        $bCancel = true;
                                    }

                                    if ($gcnlOrder["stagecd"] >= 9 && !$bCancel) {
                                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Order in stage 9. Canceling");
                                        $bCancel = true;
                                    }

                                    $customer = $objectManager->create('Magento\Customer\Model\Customer')->load($order->getCustomerId());
                                    $custno = $customer->getData('csd_custno');

                                    if ($gcnlOrder["custno"] != $custno && !$bCancel) {
                                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Wrong customer(" . $gcnlOrder["custno"] . ") for order(" . $custno . "). Canceling");
                                        $bCancel = true;
                                    }
                                    //continue; 
                                    if ($shipbystage == 1) {
                                        if ($gcnlOrder["stagecd"] >= 3 && $gcnlOrder["stagecd"] <= 5) {
                                            //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "shipped=true"); 
                                            $shippedStage = true;
                                        } else {
                                            //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "shipped=false"); 
                                            $shippedStage = false;
                                        }
                                    }
                                    if ($bCancel) {
                                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "closing order");
                                        $orderidnow = $order->getIncrementId();
                                        $order1 = $objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($orderidnow);
                                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "got order..");
                                        //set order to complete
                                        $statusCode = "canceled";
                                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "setting status..");
                                        $order1->setState($statusCode)->setStatus($statusCode);
                                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "saving order..");
                                        $order1->save();
                                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "order canceled..");
                                        unset($order1);
                                        continue;
                                    }
                                } else {  //deleted orders?
                                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "order missing..");
                                }

                            }
                        } catch (\Exception $e) {
                            $this->csd->gwLog('Caught exception checking order: ' . json_encode($e->getMessage()));
                        }
                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "checking packages");
                        $gcpackage = $this->csd->SalesPackagesSelect($cono, $erpOrderNo, $CSD_OrderSuf, $moduleName);

                        if (isset ($gcpackage)) {
                            if (isset ($gcpackage["cono"]) && $gcpackage["cono"] != "0") {
                                //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "package=true");
                                $HasPackage = true;
                            } else {
                                //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "package=false");
                                $HasPackage = false;
                            }
                        }
                        if (1 == 1) {
                            if ($HasPackage || $shippedStage) {
                                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "main shipping loop");
                                if ($autoinvoice == "0") { //invoice if shipped...for paradox labs, will auto-capture
                                    $payment = $order->getPayment();
                                    try {
                                        if (isset ($payment)) {
                                            $poNumber = $payment->getPoNumber();
                                            $method = $payment->getMethodInstance();
                                            $methodTitle = $method->getTitle();
                                            $methodcode = $payment->getMethod();
                                            if (strpos($methodcode, "authnetcim") !== false) {
                                                if ($order->canInvoice()) {
                                                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "invoice branch for " . $order->getIncrementId() . " / " . $erpOrderNo);
                                                    $customer = $objectManager->create('Magento\Customer\Model\Customer')->load($order->getCustomerId());
                                                    $custno = $customer->getData('csd_custno');
                                                    $invoiceItems = [];
                                                    if ($shippedStage) {
                                                        $poNumber = $gcnlOrder["custpo"];
                                                    }
                                                    if (!isset ($poNumber)) {
                                                        $poNumber = ""; //$order->getIncrementId() ; //getting this from the order now, don't need to make assumptions.
                                                    }
                                                    $this->csd->gwLog('Order ' . $order->getIncrementId() . '/' . $poNumber . ' invoicing now');
                                                    //*********
                                                    //get list of orders from ERP
                                                    try {
                                                        if (true) {
                                                            $gcnl = $this->csd->SalesOrderSelect($cono, $erpOrderNo, $CSD_OrderSuf, $moduleName);
                                                            if (isset ($gcnl)) {
                                                                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "getting invoice values from salesorderselect");
                                                                if (isset ($gcnl["SalesOrderSelectResponseContainerItems"])) {
                                                                    $arrOrder = $gcnl["SalesOrderSelectResponseContainerItems"];
                                                                } else {
                                                                    $arrOrder[] = $gcnl;
                                                                }
                                                                $ordersuflist = [];
                                                                if (isset ($arrOrder)) {
                                                                    foreach ($arrOrder as $item) {
                                                                        if ($item["stagecd"] > 5 || $item["stagecd"] < 3) {
                                                                            continue;
                                                                        }
                                                                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "setting totals 1");
                                                                        $olsuf = str_pad($item["ordersuf"], 2, "0", STR_PAD_LEFT);
                                                                        $shippingAmount += $item["addonnet"];
                                                                        $invoiceAmount += $item["totinvamt"];
                                                                        $taxAmount += $item["taxamt"];
                                                                        $subtotalAmount += $item["totlineamt"];
                                                                        $ordersuflist[] = $CSD_OrderSuf;
                                                                    }
                                                                } else {
                                                                    /* if ($item["stagecd"]<=5 || $item["stagecd"]>=3){
                                                                         $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "setting totals 2");
                                                                         $olsuf=str_pad($gcnl["ordersuf"], 2, "0", STR_PAD_LEFT);
                                                                         $shippingAmount += $gcnl["addonnet"];
                                                                         $invoiceAmount += $gcnl["totinvamt"];
                                                                         $taxAmount += $gcnl["taxamt"];
                                                                         $subtotalAmount += $gcnl["totlineamt"];
                                                                         $ordersuflist[]=$CSD_OrderSuf;
                                                                     }   */
                                                                }

                                                            }
                                                        }
                                                        //check if suffix already processed
                                                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "checking shipped qty " . count($ordersuflist));
                                                        //end suffix check
                                                        if (count($ordersuflist) > 0) {
                                                            //foreach ($ordersuflist as $suf){
                                                            // $shipQty=[];
                                                            //$orderDetail = $this->csd->SalesOrderLinesSelect($cono, $suf, $suf);

                                                            /*************************************/
                                                            $gcLines = $this->csd->SalesOrderLinesSelect($cono, $erpOrderNo, $CSD_OrderSuf, $moduleName);
                                                            if (isset ($gcLines)) {
                                                                $this->csd->gwLog('got lines...');
                                                                if (isset ($ordersList["SalesOrderLinesSelectResponseContainerItems"])) {
                                                                    $arrLine = $ordersList["SalesOrderLinesSelectResponseContainerItems"];
                                                                } else {
                                                                    $arrLine[] = $gcLines;
                                                                }
                                                                if (isset ($arrLine)) {
                                                                    foreach ($order->getAllItems() as $orderItem) {
                                                                        // Check if order item has qty to ship or is virtual
                                                                        if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                                                                            continue;
                                                                        }
                                                                        unset($qtyShipped);
                                                                        unset($price);
                                                                        unset($linetotal);
                                                                        foreach ($arrLine as $itemline) {
                                                                            if ($itemline["shipprod"] == $orderItem->getSku()) {

                                                                                // $shipQty[]=array($itemline["shipprod"],$itemline["qtyship"]);

                                                                                $invoiceItems[$orderItem->getOrderItemId()] = $itemline["qtyship"];

                                                                                $this->csd->gwLog('Shipping sku...' . $itemline["shipprod"]);
                                                                                $this->csd->gwLog('Shipping qty...' . $itemline["qtyship"]);
                                                                                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "item id=" . $orderItem->getOrderItemId());
                                                                                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "item qty=" . $orderItem->getQtyOrdered());

                                                                                break;
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                            /*********************************/


                                                            /* if (isset($orderDetail["SalesOrderLinesSelectResponseContainerItems"])) {
                                                                 foreach ($orderDetail["SalesOrderLinesSelectResponseContainerItems"] as $lineitem) {
                                                                     $shipQty[]=array($lineitem["shipprod"],$lineitem["qtyship"]);
                                                                 }
                                                             } else {
                                                                 $shipQty[]=array($orderDetail["shipprod"],$orderDetail["qtyship"]);
                                                             }*/
                                                            // }
                                                        } else {
                                                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "no orders/suffix available to invoice: " + $erpOrderNo);
                                                            continue;
                                                        }
                                                        /*    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "checking shipqty " . count($ordersuflist));      
                                                           if (count($shipQty)>0) {
                                                               $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "setting invoice items");   
                                                               foreach ($order->getAllItems() as $orderItem) {
                                                                   foreach ($shipQty as $shippedItem){
                                                                       if ($shippedItem[0]==$orderItem->getSku()){
                                                                           $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "sku match!");   
                                                                           $invoiceItems[$orderItem->getOrderItemId()] = $shippedItem[1];
                                                                       }
                                                                   }
                                                                   $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "item id=" . $orderItem->getOrderItemId());   
                                                                   $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "item qty=" . $orderItem->getQtyOrdered()); 
                                                                   
                                                                   $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "csd prod=" . $shippedItem[0]);   
                                                                   $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "csd shipped qty =" . $shippedItem[1]);  
                                                                   if (!empty($orderItem->getOrderItemId())){
                                                                       //$invoiceItems[$orderItem->getOrderItemId()] = $orderItem->getQtyOrdered();
                                                                   }
                                                               }
                                                           }*/
                                                    } catch (\Exception $e) {
                                                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "GWS autoinvoice on ship Error: " . $e->getMessage());

                                                    }

                                                    //end get list of orders
                                                    try {
                                                        $invoices = $this->_invoiceCollectionFactory->create()->addAttributeToFilter('order_id', ['eq' => $order->getId()]);
                                                        //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "order " . $order->getId());
                                                        $invoices->getSelect()->limit(1);

                                                        /* if ((int)$invoices->count() !== 0) {
                                                             //return null;
                                                         }*/


                                                        //https://webkul.com/blog/how-to-programmatically-create-invoice-in-magento2/
                                                        try {

                                                            $grandTotal = $invoiceAmount;//$subtotalAmount + $shippingAmount + $taxAmount;
                                                            $this->csd->gwLog('Invoicing total=' . $grandTotal . '...');
                                                            if ($grandTotal > 0) {
                                                                if (count($invoiceItems) > 0) {
                                                                    $this->csd->gwLog('Invoicing with items...');
                                                                    //ob_start();
                                                                    //var_dump($invoiceItems);
                                                                    //$debug_dump = ob_get_clean();
                                                                    //$this->csd->gwLog($debug_dump);
                                                                    $invoice = $this->_invoiceService->prepareInvoice($order, $invoiceItems);
                                                                } else {
                                                                    $this->csd->gwLog('Invoicing order object...');
                                                                    $invoice = $this->_invoiceService->prepareInvoice($order);
                                                                }

                                                                //   $this->csd->gwLog('setnotify');
                                                                $invoice->getOrder()->setCustomerNoteNotify(true);

                                                                $this->csd->gwLog('setisinproc');
                                                                $invoice->getOrder()->setIsInProcess(true);
                                                                $this->csd->gwLog('setting invoice totals');
                                                                $invoice->setShippingAmount($shippingAmount);
                                                                $invoice->setSubtotal($subtotalAmount);
                                                                $invoice->setBaseSubtotal($subtotalAmount);
                                                                $invoice->setTaxAmount($taxAmount);
                                                                $invoice->setBaseTaxAmount($taxAmount);
                                                                $invoice->setGrandTotal($grandTotal);
                                                                $invoice->setBaseGrandTotal($grandTotal);  /* */
                                                                $this->csd->gwLog('Invoicing: sub: ' . $subtotalAmount . ' ship: ' . $shippingAmount . ' tax: ' . $taxAmount . ' grandtotal:' . $grandTotal);
                                                                $this->csd->gwLog('setRequestedCaptureCase');
                                                                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);

                                                                $this->csd->gwLog('register');
                                                                $invoice->register();

                                                                /* $this->csd->gwLog('cancapture');
                                                                if ($payment->getMethodInstance()->canCapture()) {
                                                                    $this->csd->gwLog('Capturing now');
                                                                    $order->addStatusHistoryComment('Capturing now', false);
                                                                    $invoice->capture();
                                                                } else {
                                                                      $this->csd->gwLog('cancapture=false');
                                                                }*/
                                                                $invoice->save();
                                                                $order->addStatusHistoryComment('Automatically INVOICED', false);
                                                                // $order->getPayment()->capture(null);
                                                                $transactionSave = $this->transaction
                                                                    ->addObject($invoice)
                                                                    ->addObject($invoice->getOrder());
                                                                $transactionSave->save();
                                                            }
                                                        } catch (\Exception $e1) {
                                                            $this->csd->gwLog('Exception message:: ' . $e1->getMessage());
                                                            $this->csd->gwLog('Exception trace:: ' . $e1->getTraceAsString());
                                                            $order->addStatusHistoryComment('Exception message:: ' . $e1->getMessage(), false);
                                                            $order->save();
                                                        }
                                                    } catch (\Exception $e) {
                                                        $this->csd->gwLog('Exception message: ' . $e->getMessage());
                                                        $order->addStatusHistoryComment('Exception message:: ' . $e->getMessage(), false);
                                                        $order->save();
                                                        //return null;
                                                    }
                                                    //*********
                                                } else {
                                                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Can't invoice " . $order->getIncrementId() . " / " . $erpOrderNo);
                                                }
                                            }
                                        }
                                    } catch (\Exception $ePO) {
                                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Payment error: " . $ePO->getMessage());
                                    }

                                }//end if autoinvoice==0
                                $this->csd->gwLog('Processing shipping.....');
                                if (1 == 1) { // save! ($order->hasInvoices()) { 
                                    $bRemaining = false;
                                    if ($HasPackage || $shippedStage) {
                                        unset($bRemaining);
                                        if ($shippedStage) {
                                            $shipcarrier = $gcnlOrder['shipviadesc'];
                                        } else {
                                            $shipcarrier = $gcpackage['shipviaty'];
                                        }
                                        if (empty ($shipcarrier)) {
                                            $shipcarrier = "Other";
                                        }
                                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Carrier=" . $shipcarrier);
                                        // save! foreach ($order->getInvoiceCollection() as $invoice) {
                                        try {
                                            // Initialize the order shipment object
                                            $convertOrder = $objectManager->create('Magento\Sales\Model\Convert\Order');
                                            $shipment = $convertOrder->toShipment($order);
                                            $ordertotal = 0;
                                            $qtyShipped = 0;
                                            // Loop through order items
                                            foreach ($order->getAllItems() as $orderItem) {
                                                // Check if order item has qty to ship or is virtual
                                                if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                                                    continue;
                                                }
                                                unset($qtyShipped);
                                                unset($price);
                                                unset($linetotal);
                                                if (isset ($gcLines) && false) {
                                                    $this->csd->gwLog("already have lines");
                                                } else {
                                                    $this->csd->gwLog("getting lines");
                                                    $gcLines = $this->csd->SalesOrderLinesSelect($cono, $erpOrderNo, $CSD_OrderSuf, $moduleName);
                                                }
                                                if (isset ($gcLines)) {
                                                    $this->csd->gwLog('got lines...');
                                                    if (isset ($ordersList["SalesOrderLinesSelectResponseContainerItems"])) {
                                                        $arrLine = $ordersList["SalesOrderLinesSelectResponseContainerItems"];
                                                    } else {
                                                        $arrLine[] = $gcLines;
                                                    }
                                                    if (isset ($arrLine)) {
                                                        foreach ($arrLine as $itemline) {
                                                            if (!isset ($itemline["shipprod"])) {
                                                                $this->csd->gwLog('Item not set');
                                                                break 2;
                                                            }
                                                            // $this->csd->gwLog('looopng lines...');
                                                            // ob_start();
                                                            //// var_dump($itemline);
                                                            // $resultparam = ob_get_clean();
                                                            // $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "itemline is.......*************************************************************");
                                                            //  $this->csd->gwLog($resultparam);

                                                            if ($itemline["shipprod"] == $orderItem->getSku()) {
                                                                $this->csd->gwLog('Shipping sku...' . $itemline["shipprod"]);
                                                                $qtyShipped = $itemline["qtyship"];
                                                                $this->csd->gwLog('Shipping qty...' . $itemline["qtyship"]);
                                                                $price = $itemline["price"];
                                                                //$this->csd->gwLog('got lines...3.2');
                                                                $linetotal = $qtyShipped * $price;
                                                                //$this->csd->gwLog('got lines...3.3');
                                                                $ordertotal = $ordertotal + $linetotal;
                                                                //$this->csd->gwLog('got lines...3.4');
                                                                break;
                                                            }
                                                        }
                                                    } elseif (!isset ($gcLines["errordesc"])) {
                                                        //$this->csd->gwLog('got lines...1');
                                                        foreach ($gcLines["SalesOrderLinesSelectResponseContainerItems"] as $itemline) {
                                                            $this->csd->gwLog('got lines...2');
                                                            //$itemline=$gcLines;
                                                            if ($itemline["shipprod"] == $orderItem->getSku()) {
                                                                $this->csd->gwLog('got lines sku...' . $itemline["shipprod"]);
                                                                $qtyShipped = $itemline["qtyship"];
                                                                $this->csd->gwLog('got lines qty...' . $itemline["qtyship"]);
                                                                $price = $itemline["price"];
                                                                //$this->csd->gwLog('got lines...3.2');
                                                                $linetotal = $qtyShipped * $price;
                                                                //$this->csd->gwLog('got lines...3.3');
                                                                $ordertotal = $ordertotal + $linetotal;
                                                                //$this->csd->gwLog('got lines...3.4');

                                                            }
                                                        }
                                                    } else {
                                                        $this->csd->gwLog('got lines...4');
                                                        $itemline = $gcLines;
                                                        if ($itemline["shipprod"] == $orderItem->getSku()) {
                                                            // $this->csd->gwLog('got lines...5');
                                                            $qtyShipped = $itemline["qtyship"];
                                                            $price = $itemline["price"];
                                                            $linetotal = $qtyShipped * $price;
                                                            $ordertotal = $ordertotal + $linetotal;
                                                        }
                                                    }
                                                }
                                                $this->csd->gwLog('done looking at lines...' . $order->getIncrementId() . ' / ' . $erpOrderNo);
                                                // continue (2); //uncomment this to prevent creating a shipment
                                                if (!isset ($qtyShipped)) {
                                                    $qtyShipped = $orderItem->getQtyToShip();
                                                }
                                                if ($qtyShipped == 0) {
                                                    continue;
                                                }
                                                if ($qtyShipped < $orderItem->getQtyToShip()) {
                                                    $bRemaining = true;
                                                }
                                                $this->csd->gwLog('Creating shipment...');
                                                // Create shipment item with qty
                                                $shipmentItem = $convertOrder->itemToShipmentItem($orderItem)->setQty($qtyShipped);

                                                // Add shipment item to shipment
                                                $shipment->addItem($shipmentItem);
                                            }

                                            // Register shipment
                                            $shipment->register();
                                            $shipment->getOrder()->setIsInProcess(true);
                                            if ($HasPackage) {
                                                $trackingdata = array(
                                                    'carrier_code' => $shipcarrier,
                                                    'title' => $shipcarrier,
                                                    'number' => $gcpackage['trackerno'],
                                                );
                                                $track = $objectManager->create('Magento\Sales\Model\Order\Shipment\TrackFactory')->create()->addData($trackingdata);
                                                $shipment->addTrack($track)->save();
                                            }
                                            try {
                                                // Save created shipment and order
                                                $shipment->save();
                                                $shipment->getOrder()->save();
                                                // Send email
                                                $objectManager->create('Magento\Shipping\Model\ShipmentNotifier')
                                                    ->notify($shipment);
                                                $shipment->save();
                                            } catch (\Exception $e) {
                                                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Shipment error: " . $e->getMessage());
                                                continue;
                                            }
                                        } catch (\Exception $e) {
                                            // $order->addStatusHistoryComment('Exception message: '.$e->getMessage(), false);
                                            $this->csd->gwLog('Shipment exception message: ' . $e->getMessage());
                                            // $order->save();
                                            continue;
                                        }

                                        // save !! }// end invoice collection
                                    }
                                    if (isset ($bRemaining)) {
                                        if ($bRemaining) {
                                            $this->csd->IncrementERPOrderNo($incrementid, $erpOrderNo, $CSD_OrderSuf);
                                        }
                                    }
                                    //} //end for each gcpackage
                                    $checkstage = true;
                                } else {
                                    //  $this->csd->gwLog ("item set fail");
                                    $checkstage = true;
                                } //is set item
                            } else {
                                //$this->csd->gwLog ("No package, check stages");
                                $checkstage = true;
                            }

                        } //if 1==1 main shipment loop
                    }//end canship//end has invoices
                    else {
                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "can ship = false for " . $erpOrderNo);
                        $checkstage = true;
                    }
                    //*************************************************
                    try {
                        if ($checkstage == true || 1 == 1) { //do we need to settle or cancel the order?
                            $this->csd->gwLog("Checking cancel stage for " . $erpOrderNo);

                            if (isset ($item))
                                unset($item);
                            if ($shipbystage == 1 && isset ($gcnlOrder)) {
                                $gcOrder = $gcnlOrder;
                            } else {
                                $gcOrder = $this->csd->SalesOrderSelect($cono, $erpOrderNo, $CSD_OrderSuf, $moduleName);
                            }
                            $this->csd->gwLog("checking csd order exists");
                            if (isset ($gcOrder)) { //
                                $this->csd->gwLog("have CSD order record");
                                if (isset ($gcOrder["cono"])) {
                                    if ($gcOrder["cono"] != "0") {
                                        $item = $gcOrder;
                                    }
                                } else {
                                    if ($gcOrder["SalesOrderSelectResponseContainerItems"][0]["cono"] != "0") {
                                        $item = $gcOrder["SalesOrderSelectResponseContainerItems"][0];
                                    }
                                }
                                if (isset ($item)) {
                                    // $item = $gcOrder;
                                    if (isset ($item["orderno"])) {
                                        if ($item["stagecd"] >= 3 && $item["stagecd"] < 9 && false) {
                                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Order " . $erpOrderNo . " is good to process...stage " . $item["stagecd"]);
                                            //settle order
                                            $cust = $item["custno"];
                                            $invAmount = (isset ($item["totinvamt"]) ? $item["totinvamt"] : 0);
                                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Order " . $erpOrderNo . " is getting settled");
                                            $this->Settlement($order, $cust, $invAmount, $authno);
                                        } elseif ($item["stagecd"] >= 9) {
                                            $orderidnow = $incrementid;

                                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "order canceling " . $erpOrderNo);
                                            // $order1 = $objectManager->create('Magento\Sales\Api\Data\OrderInterface')->load($orderidnow);
                                            $order1 = $objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($orderidnow);
                                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "got order");
                                            //set order to complete
                                            $statusCode = "canceled";
                                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "setting status");
                                            $order1->setState($statusCode)->setStatus($statusCode);
                                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "saving order");
                                            $order1->save();
                                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "order canceled");
                                        }
                                    }
                                }
                            } //if isset gcorder
                        } //end if checkstage                    
                    } catch (\Exception $eSettle) {
                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Failed to process settlement: " . $eSettle->getMessage());
                    }

                    //*************************************************
                }
            } else {
                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "0 results");
            }
        } catch (\Exception $e) {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Failed to process order table: " . $e->getMessage());
        }

        return true;
    }

    //end process tracking

    public function Settlement($order, $cust, $invAmount = 0, $authno = 0)
    {
        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "settlement processing");
        $moduleName = $this->csd->getModuleName(get_class($this));
        $configs = $this->csd->getConfigValue(['cono', 'csdcustomerid']);
        $processor = $this->csd->getConfigValue('payments/payments/processor');
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        extract($configs);

        $orderincid = $order->getIncrementId();
        $orderid = $order->getId();
        $payment = $order->getPayment();

        $sendpaymenttoERP = false;
        // $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "settlement data:");
        $SavedFieldData = $this->GetSavedFieldData($orderincid);
        if ($processor == "Rapid Connect") {
            if (isset ($SavedFieldData)) {
                // $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "settlement proc:");
                if ($processor == "Rapid Connect") {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Rapid Connect:");
                    $obj_GMFMessageVariants = $this->CreateCreditSaleRequest($SavedFieldData);
                    // $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Req created:");

                    $clientRef = $this->GenerateClientRef($obj_GMFMessageVariants);
                    $result = $this->SerializeToXMLString($obj_GMFMessageVariants);
                    $result = str_replace("AddtlAmtGrp2", "AddtlAmtGrp", $result);

                    $TxnResponse = $this->SendMessage($result, $clientRef);
                    $VarResponse = $this->DeSerializeXMLString($TxnResponse);

                    $RespGrp = $VarResponse["CreditResponse"]["RespGrp"];
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "respcode=" . $RespGrp["RespCode"]);

                    if ($RespGrp["RespCode"] != "000") {
                        $this->csd->gwLog('Failed auth request.' . $RespGrp["ErrorData"]);
                        // throw new \Exception('Failed credit card authorization request.');
                        throw new \Magento\Framework\Exception\LocalizedException(__('Failed auth request.'));
                    } else {
                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Auth= " . $RespGrp["AuthID"]);
                        $sendpaymenttoERP = true;
                    }
                } elseif ($processor == "Chase") {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Chase:");
                }
            } //if (isset($SavedFieldData)){
        } //if ($processor=="Rapid Connect"){
        elseif ($processor == "Authorize.NET") {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Settling with Auth.net");
            //  $transactionID = $payment->getData('last_trans_id') . '';
            $transactionID = $order->getPayment()->getData('last_trans_id');
            //      $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "auth no=" . $authno);
            if (isset ($transactionID)) {
                //         $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Transaction ID:" . $transactionID);
                $response = $this->capturePreviouslyAuthorizedAmount($order, $invAmount, $authno);
                if (isset ($response))
                    $pos = strpos($response, "This transaction has been approved.");
                if ($pos === false) { //https://developer.authorize.net/api/reference/index.html#payment-transactions
                    $sendpaymenttoERP == false;
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Payment settlement failed. " . $response);
                } else {
                    $sendpaymenttoERP = true;

                    if ($invAmount) {
                        $settledAmount = $invAmount;
                    } else {
                        $settledAmount = $payment->getData('base_amount_paid');
                    }
                    $csdOrderNo = $order->getData('CSD_OrderNo');
                    $this->csd->SalesOrderNotesInsert($cono, $csdOrderNo, "", "Credit card settled for $" . $settledAmount);
                }
            }
        }
        //    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Payment push:");
        if ($sendpaymenttoERP == true) {
            //status=bad, save for next version
            //TODO: implement new API
            return "";
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Sending payment:");
            if (isset ($cust)) {
                $custno = $cust;
            } else {
                $custno = $csdcustomerid;
            }

            $customerSession2 = $objectManager->get('Magento\Customer\Model\Session');
            $customerData = $customerSession2->getCustomer();
            if ($order->getCustomerIsGuest()) {
                //  $this->csd->gwLog ("customer is guest");
                $custno = $csdcustomerid;
            } else {
                $CustomerID = $order->getCustomerId();

                if (!$custno) {
                    // Not Logged In
                    $custno = $csdcustomerid;

                }
            }
            //status=bad, save for next version
            $gcPay = $this->csd->SalesOrderPaymentInsert($custno, $SavedFieldData["ERPOrderNo"], $SavedFieldData["ERPSuffix"], $invAmount, $moduleName);
            if (isset ($gcPay)) { //
                if ($gcPay["cono"] != "0") {
                    //	$this->csd->gwLog ("pmt valid");
                    if (isset ($gcPay["invno"])) {
                        //     	$this->csd->gwLog ("pmt has order");
                        if ($gcPay["invno"] != "0") {
                            //   	$this->csd->gwLog ("pmt still has order");
                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Order " . $gcPay["invno"] . "  has payment applied");
                            //settle order

                            //insert payment to erp
                            //processing is not done yet.
                            $orderidnow = $SavedFieldData["orderid"];

                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "order closing " . $orderidnow);
                            // $order1 = $objectManager->create('Magento\Sales\Api\Data\OrderInterface')->load($orderidnow);
                            $order1 = $objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($orderidnow);
                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "got order");
                            //set order to complete
                            $statusCode = "complete";
                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "setting status");
                            $order1->setState($statusCode)->setStatus($statusCode);
                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "saving order");
                            $order1->save();
                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "order complete");
                            // $payment->setIsTransactionClosed(1);
                        }
                    }
                }
            }
        } elseif (!isset ($transactionID)) { //not sending a payment, but still need to update the status
            //processing is not done yet.
            $orderidnow = $orderincid;

            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "order closing " . $orderidnow);
            // $order1 = $objectManager->create('Magento\Sales\Api\Data\OrderInterface')->load($orderidnow);
            $order1 = $objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($orderidnow);
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "got order");
            //set order to complete
            $statusCode = "complete";
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "setting status and state by query");
            //$this->csd->UpdateOrderStatusAndState($orderidnow, $statusCode);

            // $this->csd->UpdateOrderStatusAndStateGrid($orderidnow, $statusCode);
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "setting status and state again");
            $order1->setState($statusCode)->setStatus($statusCode);
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "saving order");
            $order1->save();
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "order complete");
        }
    }

    public function GetSavedFieldData($orderincid)
    {
        $moduleName = $this->csd->getModuleName(get_class($this));
        $dbConnection = $this->resourceConnection->getConnection();

        try {
            $sql = "select orderid,ERPOrderNo,ERPSuffix, CCAuthNo, dateentered, dateprocessed, TransactionID, STAN, LocalDateTime, TXNDateTime, CCNo, CCExp, CCCCV, AuthID, TxnAmt, RefNum, ClientRef, CardType, ResponseCode,CardLevelResult,ACI,TXNResponse FROM gws_GreyWolfOrderFieldUpdate WHERE orderid='" . $orderincid . "' and TransactionID is not null";

            //  $this->csd->gwLog($sql);
            $result = $dbConnection->fetchAll($sql);

            if (count($result)) {
                // output data of each row
                $this->csd->gwLog(count($result) . ' CC records found');
                foreach ($result as $row) {
                    unset($CCSaveFields);
                    //$incrementid=$row[""];

                    $CCSaveFields = [
                        'TransactionID' => $row["TransactionID"] . "",
                        'STAN' => $row["STAN"] . "",
                        'LocalDateTime' => $row["LocalDateTime"] . "",
                        'TXNDateTime' => $row["TXNDateTime"] . "",
                        'CCNo' => $row["CCNo"] . "",
                        'CCExp' => $row["CCExp"] . "",
                        'CCCCV' => $row["CCCCV"] . "",
                        'AuthID' => $row["AuthID"] . "",
                        'TxnAmt' => $row["TxnAmt"] . "",
                        'RefNum' => $row["RefNum"] . "",
                        'ClientRef' => $row["ClientRef"] . "",
                        'ResponseCode' => $row["ResponseCode"] . "",
                        'ERPOrderNo' => $row["ERPOrderNo"] . "",
                        'ERPSuffix' => $row["ERPSuffix"] . "",
                        'CardType' => $row["CardType"] . "",
                        'CardLevelResult' => $row["CardLevelResult"],
                        'ACI' => $row["ACI"],
                        'orderid' => $row["orderid"],
                        'TXNResponse' => $row["TXNResponse"]
                    ];
                }

                return $CCSaveFields;
            }
        } catch (\Exception $e) {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Failed to open update order table:: " . $e->getMessage());
        }
    }

    public function GenerateClientRef(\GMFMessageVariants $gmfMesssageObj)
    {
        $rctppid = $this->csd->getConfigValue('payments/rapidconnect/rctppid');
        $length = 12 - strlen('V' . $rctppid);
        $rand = rand(pow(10, $length - 1), pow(10, $length) - 1);
        $clientRef = '00' . $rand . 'V' . $rctppid;

        return $clientRef;
    }

    public function CreateCreditSaleRequest($SavedFieldData)
    {
        /* Based on the GMF Specification, fields that are mandatory or related to
        this transaction should be populated.*/
        $currdatestr = date('Ymdhis', time());
        //$this->csd->gwLog($currdatestr);
        $rctppid = $this->csd->getConfigValue('payments/rapidconnect/rctppid');
        $rcgroupid = $this->csd->getConfigValue('payments/rapidconnect/rcgroupid');
        $rcmerchantid = $this->csd->getConfigValue('payments/rapidconnect/rcmerchantid');
        $rctid = $this->csd->getConfigValue('payments/rapidconnect/rctid');
        $rcdid = $this->csd->getConfigValue('payments/rapidconnect/rddid');

        $VarResponse = $this->DeSerializeXMLString($SavedFieldData["TxnResponse"]);

        //GMF - create object for GMFMessageVariants
        $obj_GMFMessageVariants = new \GMFMessageVariants();

        //Credit Request - create object for CreditRequestDetails
        $obj_CreditRequestDetails = new \CreditRequestDetails();

        //Common Group - create object for CommonGrp
        $obj_CommonGrp = new \CommonGrp();
        $cardname = "";

        switch (strtoupper($SavedFieldData["CardType"])) {
            case "VISA":
                $cardname = "Visa";
                break;
            case "MASTERCARD":
                $cardname = "MasterCard";
                break;
            case "AMERICAN EXPRESS":
                $cardname = "American Express";
                break;
            case "DISCOVER":
                $cardname = "Discover";
                break;
        }

        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "building request:");
        try {
            $stan = rand(pow(10, 6 - 1), pow(10, 6) - 1);

            //populate common transaction fields
            $obj_CommonGrp->setPymtType("Credit");	//Payment Type = Credit
            $obj_CommonGrp->setTxnType("Completion");	//Transaction Type = Sale
            $obj_CommonGrp->setLocalDateTime($currdatestr);	//Local Txn Date-Time
            $obj_CommonGrp->setTrnmsnDateTime($currdatestr);	//Local Transmission Date-Time

            $obj_CommonGrp->setSTAN($stan);	//System Trace Audit Number"100003"
            $obj_CommonGrp->setRefNum($SavedFieldData["RefNum"]);	//Reference Number
            $obj_CommonGrp->setOrderNum($SavedFieldData["orderid"]);
            $obj_CommonGrp->setTPPID($rctppid);	//TPP ID		//This is dummy value. Please use the actual value
            $obj_CommonGrp->setTermID($rctid);	//Terminal ID		//This is dummy value. Please use the actual value
            $obj_CommonGrp->setMerchID($rcmerchantid);	//Merchant ID	//This is dummy value. Please use the actual value x
            $obj_CommonGrp->setMerchCatCode("5965");

            $obj_CommonGrp->setPOSEntryMode("011");	//Entry Mode for the transaction
            $obj_CommonGrp->setPOSCondCode("00");		// POS Cond Code = 00-Normal Presentment
            $obj_CommonGrp->setTermCatCode("01");		// Terminal Category Code = 01-POS
            $obj_CommonGrp->setTermEntryCapablt("04");	// Terminal Entry Capability for the POS
            $obj_CommonGrp->setTxnAmt($SavedFieldData["TxnAmt"]);	//Transaction Amount = $8.68

            $obj_CommonGrp->setTxnCrncy("840");	// Transaction Currency = 840-US Country Code
            $obj_CommonGrp->setTermLocInd("1");	// Location Indicator for the POS
            $obj_CommonGrp->setCardCaptCap("1");	// Card capture capibility for the terminal
            $obj_CommonGrp->setGroupID($rcgroupid);	// Group ID 	//This is dummy value. Please use the actual value x
            //add CommonGrp to CreditRequestDetails object
            //	$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "common request7:");
            $obj_CreditRequestDetails->setCommonGrp($obj_CommonGrp);

            //Card Group - create object for CardGrp
            $obj_CardGrp = new \CardGrp();//cc_number
            $obj_CardGrp->setAcctNum($SavedFieldData["CCNo"]);	//Card Acct Number 4012000033330026
            $obj_CardGrp->setCardExpiryDate($SavedFieldData["CCExp"]);	//Card Exp Date "20200412"
            $obj_CardGrp->setCardType($cardname);	//Card Type
            $obj_CreditRequestDetails->setCardGrp($obj_CardGrp);

            //Additional Amount Group - create object for AddtlAmtGrp
            $obj_AddtlAmtGrp = new \AddtlAmtGrp();
            $obj_AddtlAmtGrp->setAddAmt($SavedFieldData["TxnAmt"]);
            $obj_AddtlAmtGrp->setAddAmtCrncy("840");
            $obj_AddtlAmtGrp->setAddAmtType("FirstAuthAmt");
            //add AddtlAmtGrp to CreditRequestDetails object
            $obj_CreditRequestDetails->setAddtlAmtGrp($obj_AddtlAmtGrp);

            //Additional Amount Group - create object for AddtlAmtGrp
            $obj_AddtlAmtGrp = new \AddtlAmtGrp();
            $obj_AddtlAmtGrp->setAddAmt($SavedFieldData["TxnAmt"]);
            $obj_AddtlAmtGrp->setAddAmtCrncy("840");
            $obj_AddtlAmtGrp->setAddAmtType("TotalAuthAmt");
            //add AddtlAmtGrp to CreditRequestDetails object
            $obj_CreditRequestDetails->setAddtlAmtGrp2($obj_AddtlAmtGrp);

            // ECOMMGrp - create object for ECOMMGrp
            $obj_ECOMMGrp = new \ECOMMGrp();
            $obj_ECOMMGrp->setEcommTxnIndData("03");	//ACI Indicator
            $obj_ECOMMGrp->setEcommURLData("unknown");

            if ($cardname == "Visa") {
                //Visa Group - create object for VisaGrp
                $obj_VisaGrp = new \VisaGrp();
                if (isset ($VarResponse["CreditResponse"]["VisaGrp"]["CardLevelResult"])) {
                    $CLR = $VarResponse["CreditResponse"]["VisaGrp"]["CardLevelResult"];
                }
                if (isset ($VarResponse["CreditResponse"]["VisaGrp"]["TransID"])) {
                    $TransID = $VarResponse["CreditResponse"]["VisaGrp"]["TransID"];
                }
                $obj_VisaGrp->setACI($SavedFieldData["ACI"]);	//ACI Indicator
                if (!empty ($CLR)) {
                    $obj_VisaGrp->setCardLevelResult($CLR);
                }
                if (!empty ($TransID)) {
                    $obj_VisaGrp->setTransID($TransID);
                }
                $obj_VisaGrp->setVisaBID("12345");	//Visa Business ID
                $obj_VisaGrp->setVisaAUAR("111111111111");	//Visa AUAR
                //add VisaGrp to CreditRequestDetails object
                $obj_CreditRequestDetails->setVisaGrp($obj_VisaGrp);
            } elseif ($cardname == "Mastercard") {
                $obj_MCGrp = new \MCGrp();
                if (!empty ($TranIntgClass)) {
                    $obj_MCGrp->setTranIntgClassData("1");
                }
                $obj_CreditRequestDetails->setMCGrp($obj_MCGrp);
            } elseif ($cardname == "Discover") {
                $obj_DSGrp = new \DSGrp();
                if (isset ($VarResponse["CreditResponse"]["DSGrp"])) { //
                    $ds = $VarResponse["CreditResponse"]["DSGrp"];

                    $obj_DSGrp->setDiscProcCodeData($ds["DiscProcCode"]);
                    $obj_DSGrp->setDiscPOSEntrydData($ds["DiscPOSEntry"]);
                    $obj_DSGrp->setDiscRespCodeData($ds["DiscRespCode"]);
                    $obj_DSGrp->setDiscPOSDataData($ds["DiscPOSData"]);
                    $obj_DSGrp->setDiscTransQualifierData($ds["DiscTransQualifier"]);
                    $obj_DSGrp->setDiscNRIDData($ds["DiscNRID"]);
                    $obj_CreditRequestDetails->setDSGrp($obj_DSGrp);
                }
            } elseif ($cardname == "Amex") {

                $AmExPOSData = $VarResponse["CreditResponse"]["AmexGrp"]["AmExPOSData"];
                $AmExTranID = $VarResponse["CreditResponse"]["AmexGrp"]["AmExTranID"];

                $obj_AmexGrp = new \AmexGrp();
                //setAmExPOSDataData
                if (!empty ($AmExPOSData)) {
                    $obj_AmexGrp->setAmExPOSDataData($AmExPOSData);
                }
                if (!empty ($AmExTranID)) {
                    $obj_AmexGrp->setAmExTranIDData($AmExTranID);
                }

                $obj_CreditRequestDetails->setAmexGrp($obj_AmexGrp);
            }
            //   $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "visa built:");

            //Orig Group
            $obj_OrigGrp = new \OrigAuthGrp();//cc_number
            $obj_OrigGrp->setOrigAuthIDData($SavedFieldData["AuthID"]);
            $obj_OrigGrp->setOrigLocalDateTimeData($SavedFieldData["LocalDateTime"]);
            $obj_OrigGrp->setOrigTranDateTimeData($SavedFieldData["TXNDateTime"]);
            $obj_OrigGrp->setOrigSTANData($SavedFieldData["STAN"]);
            $obj_OrigGrp->setOrigRespCodeData($SavedFieldData["ResponseCode"]);

            //   $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "orig built6: " . $SavedFieldData["AuthID"]);

            //add $obj_OrigGrp to CreditRequestDetails object
            $obj_CreditRequestDetails->setOrigAuthGrp($obj_OrigGrp);
            //   $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "orig built7:");
        } catch (\Exception $e) {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Error!!! : " . $e->getMessage());
        }
        //	 $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "setting request:");

        //assign CreditRequest to the GMF object
        $obj_GMFMessageVariants->setCreditRequest($obj_CreditRequestDetails);

        return $obj_GMFMessageVariants;
    }

    //Serialize GMF object to XML payload
    public function SerializeToXMLString(\GMFMessageVariants $gmfMesssageObj)
    {	//create XML serializer instance using PEAR
        $serializer = new \XML_Serializer(["indent" => ""]);

        $serializer->setOption(
            "rootAttributes",
            [
                "xmlns:xsi" => "http://www.w3.org/2001/XMLSchema-instance",
                "xmlns:xsd" => "http://www.w3.org/2001/XMLSchema",
                "xmlns" => "com/firstdata/Merchant/gmfV1.1"
            ]
        );

        //perform serialization
        $result = $serializer->serialize($gmfMesssageObj);

        //check result code and return XML Payload
        if ($result == true) {
            return str_replace("GMFMessageVariants", "GMF", $serializer->getSerializedData());
        } else {
            return "Serizalion Failed";
        }
    }

    //deSerialize response
    public function DeSerializeXMLString($response)
    {	//create XML serializer instance using PEAR

        $arr = explode('<Payload>', $response);
        $important = $arr[1];
        $arr = explode('</Payload>', $important);
        $important = $arr[0];
        $response = trim($important);

        //  $this->csd->gwLog ( $response );
        $serializer = new \XML_Unserializer();

        $serializer->setOption(
            "rootAttributes",
            [
                "xmlns:xsi" => "http://www.w3.org/2001/XMLSchema-instance",
                "xmlns:xsd" => "http://www.w3.org/2001/XMLSchema",
                "xmlns" => "com/firstdata/Merchant/gmfV1.1"
            ]
        );

        //perform serialization
        $result = $serializer->unserialize($response, false);

        //check result code and return XML Payload
        if ($result == true) {
            $response1 = $serializer->getUnserializedData();
            return $response1;
        } else {
            return "Deserizalion Failed";
        }
    }

    //Send GMF transaction to Datawire using HTTP POST
    public function SendMessage($gmfXMLPayload, $clientRef)
    {
        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "sending message");
        //Build GMF XML Payload to be sent to Datawire
        $gmfXMLPayload = '<?xml version="1.0" encoding="UTF-8"?>' . $gmfXMLPayload;
        $gmfXMLPayload = str_replace('&', '&amp;', $gmfXMLPayload);
        $gmfXMLPayload = str_replace('<', '&lt;', $gmfXMLPayload);
        $gmfXMLPayload = str_replace('>', '&gt;', $gmfXMLPayload);

        $rctppid = $this->csd->getConfigValue('payments/rapidconnect/rctppid');
        $rcgroupid = $this->csd->getConfigValue('payments/rapidconnect/rcgroupid');
        $rcmerchantid = $this->csd->getConfigValue('payments/rapidconnect/rcmerchantid');
        $rctid = $this->csd->getConfigValue('payments/rapidconnect/rctid');
        $rcdid = $this->csd->getConfigValue('payments/rapidconnect/rddid');
        $rcurl = $this->csd->getConfigValue('payments/rapidconnect/rdurl');

        $auth = $rcgroupid . $rcmerchantid . '|' . str_pad($rctid, 8, "0", STR_PAD_LEFT);
        //auth 10001RCTST0000056668
        //  $this->csd->gwLog ("00018090839698053142");
        //  $this->csd->gwLog ($rcdid);
        // Build request message
        // DID and App values are dummy values. Please use the actual values
        $theReqData = '<?xml version="1.0" encoding="utf-8"?>
            <Request xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
            xmlns:xsd="http://www.w3.org/2001/XMLSchema" Version="3" ClientTimeout="30"
            xmlns="http://securetransport.dw/rcservice/xml">
            <ReqClientID><DID>' . $rcdid . '</DID><App>RAPIDCONNECTSRS</App><Auth>' . $auth . '</Auth>
            <ClientRef>' . $clientRef . '</ClientRef></ReqClientID><Transaction><ServiceID>160</ServiceID>
            <Payload>' . $gmfXMLPayload . '
            </Payload></Transaction>
            </Request>';

        //Initiate HTTP Post using CURL PHP library
        $url = $rcurl;//'https://stg.dw.us.fdcnet.biz/rc';
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $theReqData); //set POST data
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $response = curl_exec($ch);

        if ($response === false) {
            $resp_error = curl_error($ch);
            $this->csd->gwLog('Curl error: ' . $resp_error);
        } else {
            //Send transaction to Datawire and wait for response
            $this->csd->gwLog($response);

            //Replace XML tags in response payload, for readability
            $response = str_replace('&amp;', '&', $response);
            $response = str_replace('&lt;', '<', $response);
            $response = str_replace('&gt;', '>', $response);
        }

        //Release CURL PHP http handle
        curl_close($ch);

        //Return the XML Response Payload
        return $response;
    }

    /*auth.net function*/
    public function capturePreviouslyAuthorizedAmount($order, $invAmount = 0, $authno = 0)
    {
        /* Create a merchantAuthenticationType object with authentication details
           retrieved from the constants file */
        //$merchantLoginID = $this->csd->getConfigValue('payment/authorizenet_acceptjs/login');
        //$transactionKey = $this->csd->getConfigValue('payment/authorizenet_acceptjs/trans_key');
        //$environment = $this->csd->getConfigValue('payment/authorizenet_acceptjs/environment');
        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "capturing::... " . $authno);

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $merchantLoginID = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payment/authorizenet_acceptjs/login');
        $transactionKey = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payment/authorizenet_acceptjs/trans_key');
        $environment = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payment/authorizenet_acceptjs/environment');

        $merchantLoginID = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('authorize_net/anet_core/login_id');
        $transactionKey = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('authorize_net/anet_core/trans_key');
        $testmode = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payment/anet_core/test_mode');

        if ($testmode == 1)
            $environment = 'sandbox';

        //     $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "merchant login id=" . $merchantLoginID);
        //     $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "transaction Key=" . $transactionKey );
        //     $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "environment=" . $environment );
        //     $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "testmode=" . $testmode );
        //     $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Amount=" . $invAmount);

        //   $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        //   $merchantAuthentication->setName($merchantLoginID);
        //   $merchantAuthentication->setTransactionKey($transactionKey);

        // Set the transaction's refId
        $refId = 'Order #' . $order->getIncrementId();
        $refId .= " / " . $order->getExtOrderId();
        $transactionid = $order->getPayment()->getData('last_trans_id');

        // Now capture the previously authorized  amount
        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Capturing the Authorization with transaction ID : " . $transactionid);
        // $transactionRequestType = new AnetAPI\TransactionRequestType();
        //  $transactionRequestType->setTransactionType//("priorAuthCaptureTransaction");
        //      $transactionRequestType->setRefTransId($transactionid);
        //   $transactionRequestType->setAuthCode($authno);

        if ($invAmount > 0) {
            //         $transactionRequestType->setAmount($invAmount);
        }

        //    $request = new AnetAPI\CreateTransactionRequest();
        //    $request->setMerchantAuthentication($merchantAuthentication);
        //    $request->setTransactionRequest($transactionRequestType);

        //    $controller = new AnetController\CreateTransactionController($request);
        //  if ($environment == 'sandbox') {
        //       $response = $controller->executeWithApiResponse//(\net\authorize\api\constants\ANetEnvironment::SANDBOX);
        //   } else {
        //        $response = $controller->executeWithApiResponse//(\net\authorize\api\constants\ANetEnvironment::PRODUCTION);
        //     }

        if ($response != null) {
            // This transaction has been approved.
            if ($response->getMessages()->getResultCode() == "Ok") {
                $tresponse = $response->getTransactionResponse();

                if ($tresponse != null && $tresponse->getMessages() != null) {
                    return "This transaction has been approved.";
                } else {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Transaction Failed ");
                    if ($tresponse->getErrors() != null) {
                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", " Error code  : " . $tresponse->getErrors()[0]->getErrorCode());
                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", " Error message : " . $tresponse->getErrors()[0]->getErrorText());
                        return "Transaction Failed ";
                    }
                }

            } else {
                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Transaction Failed ");
                $tresponse = $response->getTransactionResponse();
                if ($tresponse != null && $tresponse->getErrors() != null) {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", " Error code  : " . $tresponse->getErrors()[0]->getErrorCode());
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", " Error message : " . $tresponse->getErrors()[0]->getErrorText());
                } else {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", " Error code  : " . $response->getMessages()->getMessage()[0]->getCode());
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", " Error message : " . $response->getMessages()->getMessage()[0]->getText());
                }
                return "Transaction Failed ";
            }
        } else {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "No response returned ");
        }

        return $response;
    }
}
