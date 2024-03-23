<?php

declare(strict_types=1);

namespace Altitude\CSD\Model\Carrier;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Async\CallbackDeferred;
use Magento\Framework\HTTP\AsyncClient\Request;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\Error;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\Result\ProxyDeferredFactory;

class Usps extends \Magento\Usps\Model\Carrier
{
    private $deferredProxyFactory;

    /**
     * Collect and get rates/errors
     *
     * @param RateRequest $request
     * @return Result|Error|bool
     */
    public function collectRates(RateRequest $request)
    {
        $items = $whAvail = $whRates = [];
        $wh = 0;

        $objectManager = ObjectManager::getInstance();
        $this->deferredProxyFactory = $objectManager->get(ProxyDeferredFactory::class);

        $cart = $objectManager->get('\Magento\Checkout\Model\Cart');
        $_helper = $objectManager->create('Altitude\CSD\Helper\Data');
        $itemsCollection = $cart->getQuote()->getAllItems();
        $warehouses = $_helper->getWarehouses($itemsCollection);

        foreach ($warehouses as $whID => $_warehouse) {
            if (count($_warehouse) == count($itemsCollection)) {
                $whAvail[] = $whID;
            }
        }

        if (count($whAvail) == 1) {
            $wh = $whAvail[0];
        } elseif (count($whAvail) > 1) {
            foreach ($warehouses as $whID => $items) {
                $_warehouse = $_helper->getWarehouseInfo($_warehouse);
                $request->setOrigPostcode($_warehouse->zipcd);
                $this->setRequest($request);

                $whRates[$whID] = $this->_getQuotes();
            }

            $wh = $_helper->cheapestWh($whRates);
        } else {
            $wh = $_helper->defaultWh();

            if ($_helper->useShippingCostPerWH()) {
                $itemWh = [];
                $tmpResult = [];

                foreach ($itemsCollection as $_item) {
                    foreach ($warehouses as $_whID => $whItems) {
                        if (in_array($_item->getSku(), $whItems)) {
                            $itemWh[$_item->getSku()] = $_whID;
                        }
                    }
                }

                foreach ($itemWh as $_itemSku => $_whID) {
                    $_warehouse = $_helper->getWarehouseInfo($_warehouse);
                    $request->setOrigPostcode($_warehouse->zipcd);
                    $this->setRequest($request);
                    $_getQuotes = $this->_getQuotes();

                    if (empty($tmpResult)) {
                        foreach ($_getQuotes as $_rate) {
                            $tmpResult[$_rate->getMethod()] = $_rate;
                        }
                    } else {
                        foreach ($_getQuotes as $_rate) {
                            $resultRate = $tmpResult[$_rate->getMethod()];
                            $resultRate->setCost($resultRate->getCost() + $_rate->getCost());
                            $resultRate->setPrice($resultRate->getPrice() + $_rate->getPrice());

                            $tmpResult[$_rate->getMethod()] = $resultRate;
                        }
                    }
                }

                $result = $this->_rateFactory->create();

                foreach ($resultRate as $rate) {
                    $result->append($rate);
                }

                $this->_result = $result;
            }
        }

        $warehouse = $_helper->getWarehouseInfo($wh);

        if (isset($warehouse->zipcd)) {
            $request->setOrigPostcode($warehouse->zipcd);
        }

        if (!$this->canCollectRates()) {
            return $this->getErrorMessage();
        }

        $this->setRequest($request);
        //To use the correct result in the callback.
        if (count($whAvail) == 1) {
            $this->_result = $result = $this->_getQuotes();
        } elseif (count($whAvail) > 1) {
            $this->_result = $result = $whRates[$wh];
        }
		if (isset($result)) {
		        return $this->deferredProxyFactory->create(
		            [
		                'deferred' => new CallbackDeferred(
		                    function () use ($request, $result) {
		                        $this->_result = $result;
		                        $this->_updateFreeMethodQuote($request);

		                        return $this->getResult();
		                    }
		                )
		            ]
		        );
		}
    }
}
