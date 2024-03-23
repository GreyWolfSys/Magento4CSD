/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * @api
 */

define([
    'Altitude_CSD/js/view/checkout/summary/grand-total'
], function (Component) {
    'use strict';

    return Component.extend({
        /**
         * @override
         */
        isDisplayed: function () {
            return true;
        }
    });
});
