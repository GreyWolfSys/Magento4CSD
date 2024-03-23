<?php

namespace Altitude\CSD\Controller\Customer;

class Orderdetail extends \Altitude\CSD\Controller\CustomerAbstract
{
    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;
    
    private $checkoutSession;
    private $cartRepository;
    
    protected $productRepository;

    protected $_cart;

    protected $csd;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Checkout\Model\Cart $cart,
        \Altitude\CSD\Model\CSD $csd,
       \Magento\Checkout\Model\SessionFactory $checkoutSession,
       \Magento\Quote\Api\CartRepositoryInterface $cartRepository
    ) {
        $this->_customerSession = $customerSession;
        parent::__construct($context, $customerSession);
        $this->csd = $csd;
        $this->productRepository = $productRepository;
        $this->_cart = $cart;
        $this->checkoutSession = $checkoutSession;
        $this->cartRepository = $cartRepository;
    }

    public function execute()
    {
        $data = $this->getRequest()->getPost();
        $configs = $this->csd->getConfigValue(['apikey', 'cono', 'csdcustomerid', 'whse', 'shipto2erp', 'slsrepin', 'defaultterms', 'operinit' ]);
        extract($configs);

        if (isset($data["reorderitems"]) && $data["reorderitems"] == "yes") {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $customerSession = $objectManager->get('Magento\Customer\Model\Session');
            $customer = $customerSession->getCustomer();
            $custno = $customer['csd_custno'];

            $iTotal = $data["totalitems"];
            $paramsHead = new \ArrayObject();
            $itemsadded = 0;
            $lineno = 0;
            #$gotocart = $this->csd->getConfigValue('gotocart');

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
                        $this->_redirect('checkout/cart');
                    }
                }
            }

            //if ($gotocart == true || 1==1) {
            if ( 1==2) {
                $this->_cart->save();
                $this->_redirect('checkout/cart');
                return;
            }
        }

        $this->_view->loadLayout();
        $this->_view->renderLayout();
    }
}
