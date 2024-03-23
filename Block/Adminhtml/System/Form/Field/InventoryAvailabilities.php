<?php

namespace Altitude\CSD\Block\Adminhtml\System\Form\Field;

class InventoryAvailabilities extends \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
{
    protected $_columns = [];

    /**
     * @var Methods
     */
    protected $_typeRenderer;

    protected $_searchFieldRenderer;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        array $data = []
    )
    {
        parent::__construct($context, $data);
    }


    /**
     * Prepare to render
     *
     * @return void
     */
    protected function _prepareToRender()
    {
        $this->_typeRenderer        = null;
        $this->_searchFieldRenderer = null;
        $this->addColumn('province', ['label' => __('Province')]);
        $this->addColumn('warehouses', ['label' => __('Warehouses')]);
        $this->_addAfter       = false;
        $this->_addButtonLabel = __('Add Inventory Availability');
    }
}
