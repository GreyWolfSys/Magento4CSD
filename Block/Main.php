<?php
namespace Altitude\CSD\Block;

use Magento\Framework\View\Element\Template;
use Magento\Customer\CustomerData;
use Magento\Catalog\Model\Product;

// use Magento\Framework\View\Element\Template;

class Main extends Template
{
    protected $_registry;
    private $csd;
    private $helper;
    private $productRepository;
    protected $scopeConfig;
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Altitude\CSD\Model\CSD $csd,
        \Altitude\CSD\Helper\Data $helper,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->_registry = $registry;
        $this->csd = $csd;
        $this->helper = $helper;
        $this->productRepository = $productRepository;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }
    // protected function _prepareLayout()
// {
//     $this->setMessage('Cart Integrate');
// }
// public function getGoodbyeMessage()
// {
//     return 'Goodbye Cart';
// }
// protected function _prepareLayout()
// {
//     $this->setMessage('Get CSD Price');
// }
    public function getGoodbyeMessage()
    {
        return 'Goodbye Price';
    }
    public function getCacheLifetime()
    {
        return null;
    }
    public function _prepareLayout()
    {
        return parent::_prepareLayout();
    }

    public function getCurrentCategory()
    {
        return $this->_registry->registry('current_category');
    }

    public function getCurrentProduct()
    {
        return $this->_registry->registry('current_product');
    }

    public function getProduct($sku)
    {
        if ($sku) {
            $product = $this->productRepository->get($sku);

            if ($product->getId()) {
                return $product;
            }
        }

        return false;
    }

    public function getQtyInfo($product)
    {
        //error_log ("Calling getQtyInfo on line 63 of CSDProducts\Block\Main.php" );
        return $this->helper->getQtyInfo($product);
    }

    public function getQtyInfowithregion($product, $region)
    {
        //error_log ("Calling getQtyInfo on line 69 of CSDProducts\Block\Main.php" );
        return $this->helper->getQtyInfo($product, $region);
    }

    public function getPriceInfo($product)
    {
        return $this->helper->getPriceInfo($product);
    }

    public function getConfigData($field)
    {
        return $this->scopeConfig->getValue(
            $field,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
}
