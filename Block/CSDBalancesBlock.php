<?php

namespace Altitude\CSD\Block;

class CSDBalancesBlock extends OrderQuery
{
    protected $csd;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Altitude\CSD\Model\CSD $csd,
        array $data = []
    ) {
        $this->_context = $context;
        $this->csd = $csd;
        parent::__construct($context, $data);
    }

    public function getBalances()
    {
        $moduleName = $this->csd->getModuleName(get_class($this));
        $customer = $this->csd->getSession()->getCustomer();
        $csdCustNo = $customer->getData('csd_custno');

        $data = $this->getRequest()->getParams();
        $configs = $this->csd->getConfigValue(['cono', 'csdcustomerid']);
        extract($configs);



        $csdCustomer = $this->csd->SalesCustomerSelect($cono, $csdCustNo, $moduleName);

        if (isset ($csdCustomer["errordesc"]) && $csdCustomer["errordesc"] != "") {
            $shipToList = null;
        } else {
            $shipToList = $this->csd->SalesShipToList($cono, $csdCustNo, $moduleName);
        }

        if (isset ($data["shipto"]) && $data["shipto"] != "") {
            $shipTo = $this->csd->SalesShipToSelect($cono, $csdCustNo, $data["shipto"], $moduleName);
            $selectedShipTo = $data["shipto"];
        } else {
            $shipTo = null;
            $selectedShipTo = null;
        }

        $balances = [
            'lastsaleamt' => ['label' => 'Last Sale Amount'],
            'periodbal1' => ['label' => "perioddt1", 'is_index' => true],
            'periodbal2' => ['label' => "perioddt2", 'is_index' => true],
            'periodbal3' => ['label' => "perioddt3", 'is_index' => true],
            'periodbal4' => ['label' => "perioddt4", 'is_index' => true],
            'periodbal5' => ['label' => "perioddt5", 'is_index' => true],
            'futinvbal' => ['label' => 'Future Invoice Balance'],
            'codbal' => ['label' => 'COD Balance'],
            'ordbal' => ['label' => 'Unapplied Credit', 'is_minus' => true],
            'uncashbal' => ['label' => 'Unapplied Cash', 'is_minus' => true],
            'servchgbal' => ['label' => 'Service Charge Balance'],
        ];

        return [
            'csdCustomer' => $csdCustomer,
            'shipToList' => $shipToList,
            'shipTo' => $shipTo,
            'selectedShipTo' => $selectedShipTo,
            'balances' => $balances,
            'csdcustomerid' => $csdcustomerid
        ];
    }
}
