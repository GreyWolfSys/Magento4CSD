<?php

namespace Altitude\CSD\Block;

class Invoices extends OrderQuery
{
    protected $_product = null;

    protected $_registry;

    protected $_productFactory;

    protected $csd;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Checkout\Model\Cart $cart,
        \Altitude\CSD\Model\CSD $csd,
        array $data = []
    ) {
        $this->_registry = $registry;
        $this->_productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->_context = $context;
        $this->_cart = $cart;
        $this->csd = $csd;
        parent::__construct($context, $data);
    }

    function array_sort_by_column($arr, $col, $dir = SORT_ASC)
    {
        return $this->csd->array_sort_by_column($arr, $col, $dir);

    }
    public function getInvoices()
    {
        $invtodetail = "";
        $podPdf = "";
        $moduleName = $this->csd->getModuleName(get_class($this));
        $customer = $this->csd->getSession()->getCustomer();
        $data = $this->getRequest()->getParams();
        $configs = $this->csd->getConfigValue(['cono', 'csdcustomerid', 'invstartdate', 'maxrecall', 'hidenegativeinvoice']);
        extract($configs);

        if (isset ($data["order"]) && isset ($data["ordersuf"])) {
            $invtodetail = $data["order"] . $data["ordersuf"];
            $invorderno = $data["order"];
            $invordersuf = $data["ordersuf"];
        }

        $invstartdate = (isset ($data["startdate"]) && $data["startdate"] != "") ? $data["startdate"] : $invstartdate;
        $invenddate = (isset ($data["enddate"]) && $data["enddate"] != "") ? $data["enddate"] : date("m/d/Y", time());
        $pdf = isset ($data["pdf"]) ? $data["pdf"] : "";
        $pod = isset ($data["pod"]) ? $data["pdf"] : "";
        $pay = isset ($data["pay"]) ? $data["pay"] : "";
        $paycart = isset ($data["paycart"]) ? $data["paycart"] : "";
        $custno = ($customer['csd_custno'] > 0) ? $customer['csd_custno'] : $csdcustomerid;
        $custneginvoice = isset ($customer['hidenegativeinvoice']) ? strtoupper($customer['hidenegativeinvoice']) : "";
        $shipTo = isset ($data['shipto']) ? $data['shipto'] : "";

        if ($pod == 'true') {
            $map_url = $maxrecall . "/Viewer/RetrieveDocument/D1097/[{'KeyID':'119','UserValue':'" . $invorderno . "'}]";
            $result1 = $this->csd->makeRESTRequest($map_url, "", $maxrecalluid, $maxrecallpwd);
            $podPdf = str_replace("<object ", "<object style='min-height:750px;' ", $result1);
        } elseif ($pdf == 'true') {
            $map_url = $maxrecall . "/Viewer/RetrieveDocument/D140/[{'KeyID':'119','UserValue':'" . $invorderno . "'}]";
            $result1 = $this->csd->makeRESTRequest($map_url, "", $maxrecalluid, $maxrecallpwd);
            $podPdf = str_replace("<object ", "<object style='min-height:750px;' ", $result1);

        }

        if ($custneginvoice == "Y") {
            $hidenegativeinvoice = 1;
        } elseif ($custneginvoice == "N") {
            $hidenegativeinvoice = 0;
        }

        try {
            $shipToList = $this->csd->SalesShipToList($cono, $custno, $moduleName);
            $invoicesList = $this->csd->SalesCustomerInvoiceList($cono, $custno, $moduleName);

            if (isset ($shipToList["errordesc"]) && !isset ($shipToList["SalesShipToListResponseContainerItems"])) {
                //  $shipToList = [];
            }
        } catch (\Exception $e) {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "GWS Error: " . $e->getMessage());
            $invoicesList = false;
        }
        $ownedOrders = "|";
        if ($custno == $csdcustomerid) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of object manager
            $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
            $dbConnection = $resource->getConnection();
            $sql = "SELECT ext_order_id FROM sales_order WHERE customer_id=" . $customer->getId() . " AND ext_order_id IS NOT null";
            //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "order no query=" . $sql);
            $result = $dbConnection->fetchAll($sql);
            //$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            if (count($result)) {
                foreach ($result as $row) {
                    $ownedOrders .= str_replace("-0", "", $row["ext_order_id"]) . "|";
                }
                //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "order no query result=" . $ownedOrders);
            }
        } else {
            $ownedOrders = "";
        }
        return [
            'maxrecall' => $maxrecall,
            'hidenegativeinvoice' => $hidenegativeinvoice,
            'invstartdate' => $invstartdate,
            'invenddate' => $invenddate,
            'invoicesList' => $invoicesList,
            'shipToList' => $shipToList,
            'shipTo' => $shipTo,
            'podPdf' => $podPdf,
            'ownedOrders' => $ownedOrders
        ];
    }

    public function invoiceShowable($invoice, $shipTo, $invstartdate, $invenddate, $hidenegativeinvoice)
    {
        return true;
        if ($shipTo != "" && $shipTo != "all" && strpos($invoice["shipto"], $shipTo) === false) {
            return false;
        }

        if (
            isset ($invoice["invdt"]) && isset ($invoice["invno"]) && $invoice["invno"] > 0 &&
            (strtotime($invstartdate) <= strtotime($invoice["invdt"])) &&
            (strtotime($invenddate) >= strtotime($invoice["invdt"]))
        ) {
            if ($invoice["seqno"] == 0) {
                return false;
            }
            if ($invoice["amount"] > 0 or $hidenegativeinvoice == 0) {
                return true;
            }
        }

        return false;
    }
}
