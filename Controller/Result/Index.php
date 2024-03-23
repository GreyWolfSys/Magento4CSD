<?php
namespace Altitude\CSD\Controller\Result;

use Magento\Catalog\Model\Layer\Resolver;
use Magento\Catalog\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Search\Model\QueryFactory;
use Magento\Search\Model\PopularSearchTerms;

class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * Catalog session
     *
     * @var Session
     */
    protected $_catalogSession;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var QueryFactory
     */
    private $_queryFactory;

    /**
     * Catalog Layer Resolver
     *
     * @var Resolver
     */
    private $layerResolver;

    protected $csd;

    /**
     * @param Context $context
     * @param Session $catalogSession
     * @param StoreManagerInterface $storeManager
     * @param QueryFactory $queryFactory
     * @param Resolver $layerResolver
     */
    public function __construct(
        Context $context,
        Session $catalogSession,
        StoreManagerInterface $storeManager,
        QueryFactory $queryFactory,
        Resolver $layerResolver,
        \Altitude\CSD\Model\CSD $csd
    ) {
        parent::__construct($context);
        $this->_storeManager = $storeManager;
        $this->_catalogSession = $catalogSession;
        $this->_queryFactory = $queryFactory;
        $this->layerResolver = $layerResolver;
        $this->csd = $csd;
    }

    /**
     * Display search result
     *
     * @return void
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        $this->layerResolver->create(Resolver::CATALOG_LAYER_SEARCH);

        /* @var $query \Magento\Search\Model\Query */
        $query = $this->_queryFactory->get();

        $storeId = $this->_storeManager->getStore()->getId();
        $query->setStoreId($storeId);

        $queryText = $query->getQueryText();

        if ($queryText != '') {
            $partNumber = $this->getERPPartNumber($queryText);

            if ($partNumber != "") {
                if ($partNumber->altprod != $partNumber->prod) {
                    $queryText = $partNumber->prod;
                }
            }

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $catalogSearchHelper = $objectManager->get(\Magento\CatalogSearch\Helper\Data::class);

            $getAdditionalRequestParameters = $this->getRequest()->getParams();
            unset($getAdditionalRequestParameters[QueryFactory::QUERY_VAR_NAME]);

            if (empty($getAdditionalRequestParameters) &&
                $objectManager->get(PopularSearchTerms::class)->isCacheable($queryText, $storeId)
            ) {
                $this->getCacheableResult($catalogSearchHelper, $query);
            } else {
                $this->getNotCacheableResult($catalogSearchHelper, $query);
            }
        } else {
            $this->getResponse()->setRedirect($this->_redirect->getRedirectUrl());
        }
    }

    private function getERPPartNumber($partNumber = "")
    {
        $moduleName='getERPPartNumber';
        $apiname = "ItemsProductxRefSelect";
        $this->csd->LogAPICall($apiname);

        $configs = $this->csd->getConfigValue(['apikey', 'cono','operinit']);
        extract($configs);
        $csdcustomerid = $this->csd->getConfigValue('csdcustomerid');
        $client = $this->csd->createSoapClient($apikey, $apiname);

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');

        if ($customerSession->isLoggedIn()) {
            $customerData = $customerSession->getCustomer();
            $csdcustno = $customerData['csd_custno'];
        } else {
            $csdcustno = $csdcustomerid;
        }

        $params1 = (object) [];
        $params1->cono = $cono;
        $params1->custno = $csdcustno;
        $params1->custprod = $partNumber;
		$params1->operinit = $operinit;
        $params1->APIKey = $apikey;

        $rootparams = (object) [];
        $rootparams->ItemsProductxRefSelectRequestContainer = $params1;
        $result = (object) [];
        $dTime=$this->csd->LogAPITime($apiname,"request", $moduleName ,""); //request/result // //request/result
        $result = $client->ItemsProductxRefSelectResponseContainer($rootparams);
        $this->csd->LogAPITime($apiname,"result", $moduleName,$dTime );
        if (isset($result->errorcd) && $result->errorcd == '000-000') {
            return $result;
        }

        return false;
    }

    /**
     * Return cacheable result
     *
     * @param \Magento\CatalogSearch\Helper\Data $catalogSearchHelper
     * @param \Magento\Search\Model\Query $query
     * @return void
     */
    private function getCacheableResult($catalogSearchHelper, $query)
    {
        if (!$catalogSearchHelper->isMinQueryLength()) {
            $redirect = $query->getRedirect();
            if ($redirect && $this->_url->getCurrentUrl() !== $redirect) {
                $this->getResponse()->setRedirect($redirect);
                return;
            }
        }

        $catalogSearchHelper->checkNotes();

        $this->_view->loadLayout();
        $this->_view->renderLayout();
    }

    /**
     * Return not cacheable result
     *
     * @param \Magento\CatalogSearch\Helper\Data $catalogSearchHelper
     * @param \Magento\Search\Model\Query $query
     * @return void
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getNotCacheableResult($catalogSearchHelper, $query)
    {
        if ($catalogSearchHelper->isMinQueryLength()) {
            $query->setId(0)->setIsActive(1)->setIsProcessed(1);
        } else {
            $query->saveIncrementalPopularity();
            $redirect = $query->getRedirect();
            if ($redirect && $this->_url->getCurrentUrl() !== $redirect) {
                $this->getResponse()->setRedirect($redirect);
                return;
            }
        }

        $catalogSearchHelper->checkNotes();

        $this->_view->loadLayout();
        $this->getResponse()->setNoCacheHeaders();
        $this->_view->renderLayout();
    }
}
