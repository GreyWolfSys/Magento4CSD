<?php

namespace Altitude\CSD\Model\Config\Source;

class Processor implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'none', 'label' => __('none')],
            ['value' => 'Chase', 'label' => __('Chase')],
            ['value' => 'Authorize.NET', 'label' => __('Authorize.NET')]
        ];
    }

    public function toArray()
    {
        return [
            'none' => __('none'),
            'Chase' => __('Chase'),
            'Authorize.NET' => __('Authorize.NET')];
    }
}
