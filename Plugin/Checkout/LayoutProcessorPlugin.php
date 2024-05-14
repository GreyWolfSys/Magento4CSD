<?php
namespace Altitude\SX\Plugin\Checkout;


class LayoutProcessorPlugin
{
    /**
     * @param \Magento\Checkout\Block\Checkout\LayoutProcessor $subject
     * @param array $jsLayout
     * @return array
     */
    public function afterProcess( \Magento\Checkout\Block\Checkout\LayoutProcessor $subject,
        array  $jsLayout
    ) {
        
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $sx = $objectManager->get('\Altitude\SX\Model\SX');
        $show_order_instructions = $sx->getConfigValue('show_order_instructions');
        //$sx->gwLog("testing", "testing" . $show_order_instructions);
        if ($show_order_instructions==0){
            return $jsLayout;
        }
        $jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']
        ['shippingAddress']['children']['before-form']['children']['order_instructions'] = [
            'component' => 'Magento_Ui/js/form/element/abstract',
            'config' => [
                'customScope' => 'shippingAddress',
                'template' => 'ui/form/field',
                'elementTmpl' => 'ui/form/element/input',
                'options' => [],
                'id' => 'order_instructions'
            ],
            'dataScope' => 'shippingAddress.order_instructions',
            'label' => __('Order Instructions'),
            'provider' => 'checkoutProvider',
            'visible' => true,
            'validation' => [],
            'sortOrder' => 200,
            'id' => 'order_instructions'
        ];

        
        return $jsLayout;
    }
}