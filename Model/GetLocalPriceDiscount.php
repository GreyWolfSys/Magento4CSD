<?php

namespace Altitude\CSD\Model;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use SoapVar;

class GetLocalPriceDiscount implements ObserverInterface
{
    protected $csd;

    protected $request;

    protected $_addressFactory;

    public function __construct(
        \Altitude\CSD\Model\CSD $csd,
        \Magento\Customer\Model\AddressFactory $addressFactory,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress
    ) {
        $this->csd = $csd;
        $this->addressFactory = $addressFactory;
        $this->remoteAddress = $remoteAddress;
        $this->request = $request;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
		if ($this->p21->df_is_admin()) return "";
        $moduleName = $this->csd->getModuleName(get_class($this));
        $configs = $this->csd->getConfigValue(['apikey', 'cono', 'csdcustomerid', 'whse', 'onlycheckproduct','localpriceonly','localpricediscount' ]);
        extract($configs);
        if (empty($localpricediscount) || !isset($localpricediscount) || $localpricediscount==0) {
            $localpricediscount=1;
        } else {
            $localpricediscount=(100-$localpricediscount)/100;
        }

        $url = $this->csd->urlInterface()->getCurrentUrl();
        $ip = $this->remoteAddress->getRemoteAddress();
        $displayText = $observer->getEvent()->getName();
        $controller = $this->request->getControllerName();
        $skipAPI=false;
		if (strpos($url, 'cart/add/') !== false ) return "";
        $products = $productsCollection = [];
        try {
            $singleProduct = $observer->getEvent()->getProduct();
            if (is_null($singleProduct)) {
                $productsCollection = $observer->getCollection();
                $singleitem = "false";
            } else {

                $products = [];
                $productsCollection[] = $singleProduct;
                $singleitem = "true";
            }
        } catch (exception $e) {
        }
     

        foreach ($productsCollection as $product) {
            $price = 0;
            $prod=$product->getSku();
            $price = $product->getPrice();
            
            if ($localpriceonly=="Magento" ) {
                if ($localpricediscount<>1) {
                    $price = $price * $localpricediscount;
                }
                $product->setPrice($price);
                return $price;
            }



            
        }

 
    }
}
