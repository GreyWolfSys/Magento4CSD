<?php
declare(strict_types=1);

namespace Altitude\CSD\Cron;

class FetchPrice extends \Magento\Framework\View\Element\Template
{

    protected $logger;

    /*Product collection variable*/
    protected $_productCollection;
    protected $stockFilter;

    /**
     * Constructor
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(\Psr\Log\LoggerInterface $logger,
        \Altitude\CSD\Model\CSD $csd,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollection,
        \Magento\CatalogInventory\Helper\Stock $stockFilter,
        array $data = []
  )
    {
        $this->logger = $logger;
        $this->csd = $csd;
        $this->_productCollection= $productCollection;
        $this->stockFilter = $stockFilter;
        parent::__construct($context, $data);
    }

    public function getProductCollection()
        {

            $collection = $this->_productCollection->create();
            $collection->addAttributeToSelect('*');
            $collection->addAttributeToFilter('status',\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
            $collection->addAttributeToSelect('updated_at');
            $collection->setOrder('updated_at', 'ASC');

            // ADD THIS CODE IF YOU WANT IN-STOCK-PRODUCT
            $this->stockFilter->addInStockFilterToCollection($collection);

            return $collection;
        }

    /**
     * Execute the cron
     *
     * @return void
     */
    public function execute()
    {
        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Cronjob FetchPrice is executing.");
         //$configs = $this->csd->getConfigValue(['apikey', 'cono', 'csdcustomerid', 'whse', 'listorbase']);
        $configs = $this->csd->getConfigValue(['apikey', 'cono', 'csdcustomerid', 'whse', 'listorbase']);
        extract($configs);
        //  // $this->csd->gwLog( $listorbase);
        if (!empty($listorbase)){
         $productCollection = $this->getProductCollection();
           $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "got products");
            foreach ($productCollection as $product) {
               // // $this->csd->gwLog($product->getData());
                // $this->csd->gwLog( $product->getId());
                // $this->csd->gwLog( $product->getName());
                $sku= $product->getSku();

                $this->csd->gwLog( "sku=" . $sku);
                $this->csd->gwLog( "cono=" . $cono);
                $this->csd->gwLog( "whse=" . $whse);
                $this->csd->gwLog( "csdcustomerid=" . $csdcustomerid);
                try {

                    //    public function SalesCustomerPricingSelect($cono, $prod, $whse, $custno, $shipto, $qty, $moduleName = "")
                    $gcnl = $this->csd->SalesCustomerPricingSelect($cono, $sku, $whse, $csdcustomerid, '', '1', 'PriceCache');
                    if (!isset($gcnl) || isset($gcnl["fault"])) {
                         $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "error from pricing");
                        $this->csd->getSession()->setApidown(true);
                    } elseif (!empty($gcnl[$listorbase]) && false) {
                        $price = $gcnl[$listorbase];
                            if (!empty($gcnl["pround"])){
                                switch($gcnl["pround"])
                                {
                                    case 'u';
                                        $price=\ceil($price);
                                        break;
                                    case 'd';
                                        $price=\floor($price);
                                        break;
                                    case 'n';
                                        $price=\round($price);
                                        break;
                                    default;
                                        break;
                                }
                            } //end pround check
                         $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "price=:=" . $price);
                        $product->setPrice($price);
                        $product->setFinalPrice($price);
                        if ($price > 0) {
                            //$product->setSpecialPrice($price);
                        } else {
                            //$product->setSpecialPrice(null);
                        }
                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "saving");
                        $product->save();
                    } else{
                        $price = $gcnl["price"];
                        $qtybrkfl= $gcnl["qtybrkfl"] . "";
                        if (empty($qtybrkfl)){
                            $qtybrkfl='N';
                        }
                        if (!empty($qtybrkfl)){
                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "setting qtybrkfl to " . $qtybrkfl);
                            $product->setData("qtybrkfl", $qtybrkfl);
                        }
                            if (!empty($gcnl["pround"])){
                                switch($gcnl["pround"])
                                {
                                    case 'u';
                                        $price=\ceil($price);
                                        break;
                                    case 'd';
                                        $price=\floor($price);
                                        break;
                                    case 'n';
                                        $price=\round($price);
                                        break;
                                    default;
                                        break;
                                }
                            } //end pround check
                         $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "price=:==" . $price);
                        $product->setData("unitstock", $gcnl["unitsell"]);
                        $product->setPrice($price);
                        $product->setFinalPrice($price);
                        if ($price > 0) {
                            //$product->setSpecialPrice($price);
                        } else {
                            //$product->setSpecialPrice(null);
                        }
                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "saving");
                        $product->save();

                    }
                } catch (\Exception $e1) {
                    $this->csd->gwLog($e1->getMessage());
                }
            }
        }
            try{
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $_cacheTypeList = $objectManager->create('Magento\Framework\App\Cache\TypeListInterface');
                $_cacheFrontendPool = $objectManager->create('Magento\Framework\App\Cache\Frontend\Pool');
                $types = array('config','layout','block_html','collections','reflection','db_ddl','eav','config_integration','config_integration_api','full_page','translate','config_webservice');
                foreach ($types as $type) {
                    $_cacheTypeList->cleanType($type);
                }
                foreach ($_cacheFrontendPool as $cacheFrontend) {
                    $cacheFrontend->getBackend()->clean();
                }
            } catch (\Exception $e1) {
                $this->csd->gwLog($e1->getMessage());
            }
          $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Cronjob FetchPrice is complete.");

          return 1;
    }
}

