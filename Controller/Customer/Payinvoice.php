<?php

namespace Altitude\CSD\Controller\Customer;

class Payinvoice extends \Altitude\CSD\Controller\CustomerAbstract
{
    protected $_product = null;

    protected $_registry;

    protected $_productFactory;

    protected $io;

    protected $csd;

    protected $dir;

    protected $checkoutSession;

    protected $storeManager;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Registry $registry,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Framework\Filesystem\Io\File $io,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\Filesystem\DirectoryList $dir,
        \Altitude\CSD\Helper\Data $helper,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Altitude\CSD\Model\CSD $csd
    ) {
        parent::__construct($context, $customerSession);
        $this->_registry = $registry;
        $this->_productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->_context = $context;
        $this->_cart = $cart;
        $this->csd = $csd;
        $this->directoryList = $dir;
        $this->io = $io;
        $this->helper = $helper;
        $this->checkoutSession = $checkoutSession;
        $this->storeManager = $storeManager;
    }

    public function execute()
    {
        $formater = new \NumberFormatter('en_US', \NumberFormatter::CURRENCY);
        $moduleName = $this->csd->getModuleName(get_class($this));
        $customer = $this->csd->getSession()->getCustomer();
        $data = $this->getRequest()->getPost();
        $configs = $this->csd->getConfigValue(['apikey', 'cono', 'csdcustomerid', 'invstartdate', 'whse', 'shipto2erp', 'slsrepin', 'defaultterms', 'operinit']);
        extract($configs);
        
        
        if (isset($data["reorderitems"]) && $data["reorderitems"] == "yes") {
            $this->csd->gwLog('Adding invoice item to cart');
            $iTotal = $data["totalitems"];
            $itemsadded = 0;
            $lineno = 0;
            for ($i = 1; $i <= $iTotal; $i++) {
                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Checking: " . $i);
                if (isset($producttoadd)) {
                    unset($producttoadd);
                }
                if (isset($data['reorder' . $i])) {
                    $lineno = $lineno + 1;
                    $itemsadded += 1;

                    $type = $data["reorderitem" . $i];
                    $qty = $data["reorderqty" . $i];
                    if ($qty<1){
                        $qty=1;
                    }
                    $unit = $data["reorderunit" . $i];
                    $price= $data["reorderprice" . $i];
                    try {
                        $producttoadd = $this->productRepository->get($type);

                        if ($producttoadd->getParentItem()) {
                            $producttoadd = $producttoadd->getParentItem();
                        }

                        $this->csd->gwLog($i . "reorder product::: " . $producttoadd->getId());
                    } catch (\Magento\Framework\Exception\NoSuchEntityException $e5) {
                        $this->csd->gwLog('Product Error: ' . $e5->getMessage());
                        $this->messageManager->addErrorMessage( __('Product is not found in the catalog.') );
                    }
                    $testprice = $price;
                    if (isset($producttoadd)) {
                        $this->csd->gwLog('Getting prod id ' . $producttoadd->getId());

                        $producttoadd->setPrice($testprice);
                        $producttoadd->setBasePrice($testprice);
                        $producttoadd->setCustomPrice($testprice);
                        $producttoadd->setOriginalCustomPrice($testprice);
                        $producttoadd->setIsSuperMode(true);
                        $producttoadd->save();
                        //$this->csd->gwLog('Getting cart params ' . $producttoadd->getId());

                        $params = [
                            'product' => $producttoadd->getId(),
                            'price' => $testprice,
                            'qty' => $qty
                        ];

                        $this->_cart->setIsMultiShipping(false);

                        //$producttoadd->setPrice($testprice);
                        //$producttoadd->setCustomPrice($testprice);
                        //$producttoadd->setOriginalCustomPrice($testprice);
                        //$producttoadd->setBasePrice($testprice);
                        $this->csd->gwLog('about to Save cart ' . $producttoadd->getId());

                        $this->_cart->addProduct($producttoadd, $params);
                        $this->csd->gwLog('about to Save cart1 ' . $producttoadd->getId());
                        $this->_cart->save();
                      
                        
                        $this->csd->gwLog('Saved cart ' . $producttoadd->getId());
                        $testprice = $price;
                        foreach ($this->_cart->getQuote()->getAllVisibleItems() as $item) {
                            if ($item->getParentItem()) {
                                $item = $item->getParentItem();
                            }

                            try {
                                $gcnl = $this->csd->SalesCustomerPricingSelect($cono, $item->getSku(), $whse, $custno, '', $qty);
                                if (isset($gcnl["price"])) {
                                    $testprice = $gcnl["price"];
                                    if (!empty($gcnl["pround"])){
                                        switch($gcnl["pround"])
                                        {
                                            case 'u';
                                                $testprice=\ceil($testprice);
                                                break;
                                            case 'd';
                                                $testprice=\floor($testprice);
                                                break;
                                            case 'n';
                                                $testprice=\round($testprice);
                                                break;
                                            default;
                                                break;
                                        }
                                    } //end pround check
                                }
                            } catch (\Exception $e1) {
                                $this->csd->gwLog($e1->getMessage());
                                $testprice = 0;
                            }

                            $item->setPrice($testprice);
                            $item->setBasePrice($testprice);
                            $item->setCustomPrice($testprice);
                            $item->setOriginalCustomPrice($testprice);
                            $item->getProduct()->setIsSuperMode(true);

                            $item->getQuote()->collectTotals();
                            $subtotal = $item->getQuote()->getSubtotal();
                            $grandTotal = $item->getQuote()->getGrandTotal();
                            $updatedSubtotal = $item->getQuote()->setSubtotal($subtotal);
                            $updatedGrandTotal = $item->getQuote()->setGrandTotal($grandTotal);
                        }
                        $this->_cart->save();
                        
                    }
                }
                
                
            }  ///for $i
            
            $this->_redirect('checkout/cart');
            return;
        }    
  $this->csd->gwLog('PayInvoice launching');
        if (isset($data["payinvoiceno1"]) && isset($data["paysuf1"])) {
            $invorderno = $data["payinvoiceno1"];
            $invordersuf = $data["paysuf1"];
            $csdCustNo = ($customer['csd_custno'] > 0) ? $customer['csd_custno'] : $csdcustomerid;
            $suffix = str_pad($invordersuf, 2, "0", STR_PAD_LEFT);
            $sku = $csdCustNo . '-' . $invorderno . '-' . $suffix;
            $emptyCart = $this->csd->getConfigValue('defaults/shoppingcart/emptyallnoninvoice');
            $webID = $this->storeManager->getStore()->getWebsiteId();
  $this->csd->gwLog('fetching invoice' . $data["payinvoiceno1"] . $data["paysuf1"]);
            $invoicesList = $this->csd->SalesCustomerInvoiceList($cono, $csdCustNo, $moduleName);
            $invoice = null;

            if (isset($invoicesList["SalesCustomerInvoiceListResponseContainerItems"])) {
                foreach ($invoicesList["SalesCustomerInvoiceListResponseContainerItems"] as $_item) {
                    if ($_item["invno"] . $_item["invsuf"] == $data["payinvoiceno1"] . $data["paysuf1"]) {
                        $invoice = $_item;
                        break;
                    }
                }
            }

            if ($invoice == null) {
                  $this->csd->gwLog('no invoice, leaving');
                $this->_redirect("/");
                return;
            }

            $amount = $invoice['amount'];

            try {
                $_product = $this->productRepository->get($sku);
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                $_product = $this->_objectManager->create('Magento\Catalog\Model\Product');
            }

            $this->csd->gwLog('Adding to cart');
            $this->csd->gwLog('Customer ' . $csdCustNo . ' Invoice ' . $invorderno . '-' . $suffix);
            $this->csd->gwLog('Amount ' . $amount);

            $_product->setName('Customer ' . $csdCustNo . ' Invoice ' . $invorderno . '-' . $suffix);
            $_product->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE);
            $_product->setAttributeSetId(4);
            $_product->setSku($sku);
            $_product->setWebsiteIds([$webID]);
            $_product->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE);
            $_product->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
            $_product->setPrice($amount);

            $imageData = $this->helper->getProductImageData();
            $imageFile = $this->directoryList->getPath('media') . '/import/paid_invoice.jpg';

            if (!$this->io->fileExists($imageFile)) {
                $this->io->write($imageFile, $this->helper->getProductImageData(), 0644);
            }

            $_product->addImageToMediaGallery($imageFile, ['image', 'small_image', 'thumbnail'], false, false);

            $params = [
                'product' => $sku,
                'price' => $amount,
                'qty' => 1
            ];

            $_product->setStockData([
                'use_config_manage_stock' => 0,
                'manage_stock' => 1,
                'min_sale_qty' => 1,
                'max_sale_qty' => 1,
                'is_in_stock' => 1,
                'qty' => 1
            ]);

            $_product->setPrice($amount);
            $_product->setIsSuperMode(true);
            $_product->setCustomPrice($amount);
            $_product->setOriginalCustomPrice($amount);
            $_product->save();
            #$_product->setPrice($amount);

            try {
                $this->_cart->addProduct($_product, $params);
                #$_product->setCustomPrice($amount);
                #$_product->setOriginalCustomPrice($amount);
                #$_product->setPrice($amount);

                if ($emptyCart) {
                    $cart = $this->_cart;
                    $quoteItems = $this->checkoutSession->getQuote()->getItemsCollection();
                    foreach ($quoteItems as $item) {
                        if (strpos($item->getName(), "Invoice") === false) {
                            $cart->removeItem($item->getId())->save();
                        }
                    }
                    #$this->_cart->save();
                }
            } catch (\Exception $e) {
            }

            $this->_cart->save();
            $this->_redirect('checkout/cart');
            return;
        }

        $this->_redirect("/");
        return;
    }
}
