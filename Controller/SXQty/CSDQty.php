<?php
namespace Altitude\CSD\Controller\CSDQty;

class CSDQty extends \Magento\Framework\App\Action\Action
{
    protected $pageFactory;
    private $productRepository;
    private $csd;

    /**
     * @var PageFactory
     */
    protected $_resultPageFactory;

    /**
     * @var JsonFactory
     */
    protected $_resultJsonFactory;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Altitude\CSD\Model\CSD $csd
    )
    {
        $this->productRepository = $productRepository;
        $this->_resultPageFactory = $resultPageFactory;
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->csd = $csd;

        return parent::__construct($context);
    }

    public function execute()
    {
        if ($this->csd->botDetector() || !$this->getRequest()->isXmlHttpRequest()) {
            $this->_redirect('/');
            return;
        }

        $result = $this->_resultJsonFactory->create();
        $resultPage = $this->_resultPageFactory->create();
        $sku = $this->getRequest()->getParam('sku');

        $block = $resultPage->getLayout()
                ->createBlock('Altitude\CSD\Block\Main')
                ->setTemplate('Altitude_CSD::qtyajax.phtml')
                ->setData('sku',$sku)
                ->toHtml();

        $result->setData(['output' => $block]);
        return $result;
    }
}
