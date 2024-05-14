<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Altitude\SX\Model\Config\Source;

class Localprice implements \Magento\Framework\Option\ArrayInterface
{

    public function toOptionArray()
    {
        return [['value' => 'SX', 'label' => __('SX')],['value' => 'Magento', 'label' => __('Magento')],['value' => 'Hybrid', 'label' => __('Hybrid')]];
    }

    public function toArray()
    {
        return ['SX' => __('SX'),'Magento' => __('Magento'),'Hybrid' => __('Hybrid')];
    }
}