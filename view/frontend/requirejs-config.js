var config = {
    map: {
        '*': {
            'Magento_Tax/js/view/checkout/cart/totals/grand-total':
                'Altitude_CSD/js/view/checkout/cart/totals/grand-total',
            'Magento_Tax/js/view/checkout/summary/grand-total':
                'Altitude_CSD/js/view/checkout/summary/grand-total',
			'Magento_Checkout/template/shipping.html':
                'Altitude_CSD/template/shipping.html'
        }
    },
	config: {
        mixins: {
            'Magento_ConfigurableProduct/js/configurable': {
                'Altitude_CSD/js/model/skuswitch': true
            },
			'Magento_Swatches/js/swatch-renderer': {
                'Altitude_CSD/js/model/swatch-skuswitch': true
            }
        }
    }
};
