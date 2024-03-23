<?php

namespace Altitude\CSD\Block;

class Orderdetail extends OrderQuery
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

    public function orderDetail()
    {
        $moduleName = $this->csd->getModuleName(get_class($this));
        $customer = $this->csd->getSession()->getCustomer();
        $data = $this->getRequest()->getParams();
        $configs = $this->csd->getConfigValue(['cono', 'csdcustomerid', 'invstartdate']);
        extract($configs);

        if (isset ($data["order"]) && isset ($data["ordersuf"])) {
            $invtodetail = $data["order"] . $data["ordersuf"];
            $invorderno = $data["order"];
            $invordersuf = $data["ordersuf"];
        }

        try {
            $total = 0;
            $moduleName = $this->csd->getModuleName(get_class($this));
            $customer = $this->csd->getSession()->getCustomer();
            $data = $this->getRequest()->getParams();
            $configs = $this->csd->getConfigValue(['cono', 'csdcustomerid', 'invstartdate']);
            extract($configs);

            $Order = $this->csd->SalesOrderSelect($cono, $invorderno, $invordersuf, $moduleName);
            $OrderItem = $Order;
            if (isset ($Order)) {
                if (isset ($Order["custno"])) {
                    $OrderItem = $Order;
                } else {

                    $OrderItem = $Order["SalesOrderSelectResponseContainerItems"][0];
                }
            } else {

            }
            $csdCustomer = $this->csd->SalesCustomerSelect($cono, $OrderItem["custno"], $moduleName);
            $package = $this->csd->SalesPackagesSelect($cono, $invorderno, $invordersuf, $moduleName);
            $addon = $this->csd->SalesOrderAddonsSelect($cono, $OrderItem["custno"], "", $invorderno, $invordersuf, $moduleName);

            if (isset ($OrderItem["custpo"])) {
                $custpo = $OrderItem["custpo"];
            } else {
                $custpo = "";
            }

            if ($customer['csd_custno'] > 0) {
                $csdCustNo = $customer['csd_custno'];
            } else {
                $csdCustNo = $csdcustomerid;
            }

            $orderDetail = $this->csd->SalesOrderLinesSelect($cono, $OrderItem["orderno"], $OrderItem["ordersuf"], $moduleName);

            return [
                'orderhead' => $OrderItem,
                'orderDetail' => $orderDetail,
                'customer' => $csdCustNo,
                'csdCustomer' => $csdCustomer,
                'Order' => $OrderItem,
                'custpo' => $custpo,
                'package' => $package,
                'addon' => $addon
            ];

        } catch (\Exception $eheader) {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "GWS order detail Error: " . $eheader->getMessage());
        }
    }
}
