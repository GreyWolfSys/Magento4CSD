/*jshint browser:true jquery:true*/
/*global alert*/
define([
    'jquery',
    'mage/utils/wrapper'
], function ($, wrapper) {
    'use strict';

    return function(targetModule){
		console.log('checking price sss.j...');
        var updatePrice = targetModule.prototype._UpdatePrice;
        targetModule.prototype.configurableSku = $('div.product-info-main .sku .value').html();
        var updatePriceWrapper = wrapper.wrap(updatePrice, function(original) {
            //do extra stuff
            var allSelected = true;
            for(var i = 0; i<this.options.jsonConfig.attributes.length;i++) {
                if (!$('div.product-info-main .product-options-wrapper .swatch-attribute.' + this.options.jsonConfig.attributes[i].code).attr('option-selected')){
                    allSelected = false;
                }
            }

            var simpleSku = this.configurableSku;
            var basePrice = 0;
            var currentCurrencyCode = 'USD';
            var localeCode = 'en-US';
            if (allSelected) {
                $('div.price-final_price .normal-price .price-container .price-wrapper .price:first').html("Checking price");
                var products = this._CalcProducts();
                var url = BASE_URL + "/altitudecsd/index/getajax"
                simpleSku = this.options.jsonConfig.sku[products.slice().shift()];
                console.time('test 1');
                $.ajax({
                    url: url,
                    type: "GET",
                    async: false,
                    data: {
                        sku: simpleSku,
                    }
                    //context: document.body
                }).done(function(msg) {
                    //$( this ).addClass( "done" );

                    basePrice = data.result;//this.options.jsonConfig.prices.basePrice['amount'] ;
                    currentCurrencyCode = data.currentCurrencyCode;
                    localeCode = data.localeCode;
                });
                console.timeEnd('test 1');
            }

            var currencyPrefix = '';
            if(currentCurrencyCode == 'CAD' && localeCode == 'en_US') {
                currencyPrefix = 'CA';
            }

            var formatter = new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD',
                minimumFractionDigits: 2,
            });

            $('div.product-info-price .sku .value:first').html(simpleSku );
            if (basePrice > 0) {
                basePrice = currencyPrefix + formatter.format(basePrice);
                $('div.price-final_price .normal-price .price-container .price-wrapper .price:first').html(basePrice);
                $('.sticky-price .price-box .price-container .price').html(basePrice);
                $('.product-info-price .price-box .price-container .price-wrapper:first ').data('priceAmount',basePrice)
            } else {
                //$('div.price-final_price .normal-price .price-container .price-wrapper .price:first').html("Select option to see price");
                //$('.sticky-price .price-box .price-container .price').html("Select option to see price");
            }
			console.log('done setting price');
            //$(".price").text().replace("$0.00", "Select option to see price");
            $('.price').contents().filter(function() {
                return this.nodeType == 3
            }).each(function(){
                this.textContent = this.textContent.replace('CA$0.00','Select option to see price');
                this.textContent = this.textContent.replace('$0.00','Select option to see price');
            });
            //return original value
            //return original();
        });

        targetModule.prototype._UpdatePrice = updatePriceWrapper;
        return targetModule;
    };
});
