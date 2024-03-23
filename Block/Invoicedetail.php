<?php

namespace Altitude\CSD\Block;

class Invoicedetail extends OrderQuery
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

    public function invoiceDetail()
    {
        $moduleName = $this->csd->getModuleName(get_class($this));
        $customer = $this->csd->getSession()->getCustomer();
        $data = $this->getRequest()->getParams();
        $configs = $this->csd->getConfigValue(['cono', 'csdcustomerid', 'invstartdate', 'simplifyinvoice', 'maxrecall', 'maxrecalluid', 'maxrecallpwd']);
        extract($configs);
        $podPdf = null;

        if (isset ($data["invoice"]) && isset ($data["invoicesuf"])) {
            $invtodetail = $data["invoice"] . $data["invoicesuf"];
            $invorderno = $data["invoice"];
            $invordersuf = $data["invoicesuf"];
        }

        try {
            if (isset ($data['pod']) && $data['pod'] == '1') {
                $map_url = $maxrecall . "/Viewer/RetrieveDocument/D1097/[{'KeyID':'119','UserValue':'" . $invorderno . "'}]";
                $result1 = $this->csd->makeRESTRequest($map_url, "", $maxrecalluid, $maxrecallpwd);
                $podPdf = str_replace("<object ", "<object style='min-height:750px;' ", $result1);
            } elseif (isset ($data['pdf']) && $data['pdf'] == '1') {
                $map_url = $maxrecall . "/Viewer/RetrieveDocument/D140/[{'KeyID':'119','UserValue':'" . $invorderno . "'}]";
                $result1 = $this->csd->makeRESTRequest($map_url, "", $maxrecalluid, $maxrecallpwd);
                $podPdf = str_replace("<object ", "<object style='min-height:750px;' ", $result1);
            }
            $pay = isset ($data["pay"]) ? $data["pay"] : "";
            $paycart = isset ($data["paycart"]) ? $data["paycart"] : "";
            if ($podPdf != null) {
                return ['podPdf' => $podPdf];
            } else {
                $total = 0;
                $moduleName = $this->csd->getModuleName(get_class($this));
                $customer = $this->csd->getSession()->getCustomer();
                $data = $this->getRequest()->getParams();
                $configs = $this->csd->getConfigValue(['cono', 'csdcustomerid', 'invstartdate']);
                extract($configs);

                if ($customer['csd_custno'] > 0) {
                    $csdCustNo = $customer['csd_custno'];
                } else {
                    $csdCustNo = $csdcustomerid;
                }

                $invoicesList = $this->csd->SalesCustomerInvoiceList($cono, $csdCustNo, $moduleName);
                $invoice = null;

                if (isset ($invoicesList["SalesCustomerInvoiceListResponseContainerItems"])) {
                    foreach ($invoicesList["SalesCustomerInvoiceListResponseContainerItems"] as $_item) {
                        $invoice = $_item;

                        if ($_item["invno"] . $_item["invsuf"] == $invtodetail) {
                            $Order = $this->csd->SalesOrderSelect($cono, $invoice["invno"], $invoice["invsuf"], $moduleName);
                            $csdCustomer = $this->csd->SalesCustomerSelect($cono, $invoice["custno"], $moduleName);
                            $Package = $this->csd->SalesPackagesSelect($cono, $invoice["invno"], $invoice["invsuf"], $moduleName);

                            if (!isset ($Order["stagedesc"])) {
                                foreach ($Order["SalesOrderSelectResponseContainerItems"] as $_order) {
                                    if ($_order["orderno"] == $invoice["invno"] && $_order["ordersuf"] == $invoice["invsuf"]) {
                                        $Order = $_order;
                                        break;
                                    }
                                }
                            }
                            break;
                        }
                    }
                }

                $orderDetail = $this->csd->SalesOrderLinesSelect($cono, $Order["orderno"], $Order["ordersuf"], $moduleName);

                if (isset ($Order["custpo"])) {
                    $custpo = $Order["custpo"];
                } else {
                    $custpo = "";
                }


                return [
                    'invoice' => $invoice,
                    'orderDetail' => $orderDetail,
                    'orderhead' => $Order,
                    'customer' => $csdCustNo,
                    'csdCustomer' => $csdCustomer,
                    'Order' => $Order,
                    'custpo' => $custpo,
                    'simplifyinvoice' => $simplifyinvoice,
                    'podPdf' => $podPdf
                ];
            }

        } catch (\Exception $eheader) {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "GWS Error: " . $eheader->getMessage());
        }

        return [];
    }
}
