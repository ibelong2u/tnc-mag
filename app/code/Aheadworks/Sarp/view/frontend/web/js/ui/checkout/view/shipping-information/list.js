/**
* Copyright 2016 aheadWorks. All rights reserved.
* See LICENSE.txt for license details.
*/

define([
    'jquery',
    'ko',
    'mageUtils',
    'uiComponent',
    'uiLayout',
    'Aheadworks_Sarp/js/ui/checkout/model/shipping-address'
], function ($, ko, utils, Component, layout, shippingAddress) {
    'use strict';

    var defaultRendererTemplate = {
        parent: '${ $.$data.parentName }',
        name: '${ $.$data.name }',
        component: 'Magento_Checkout/js/view/shipping-information/address-renderer/default'
    };

    return Component.extend({
        defaults: {
            template: 'Magento_Checkout/shipping-information/list',
            rendererTemplates: {}
        },

        initialize: function () {
            this._super()
                .initChildren();

            var self = this;
            shippingAddress.address.subscribe(function(address) {
                self.createRendererComponent(address);
            });
            return this;
        },

        initConfig: function () {
            this._super();
            // the list of child components that are responsible for address rendering
            this.rendererComponents = {};
            return this;
        },

        initChildren: function () {
            return this;
        },

        /**
         * Create new component that will render given address in the address list
         *
         * @param address
         */
        createRendererComponent: function (address) {

            $.each(this.rendererComponents, function(index, component) {
                component.visible(false);
            });

            if (this.rendererComponents[address.getType()]) {
                this.rendererComponents[address.getType()].address(address);
                this.rendererComponents[address.getType()].visible(true);
            } else {
                // rendererTemplates are provided via layout
                var rendererTemplate =
                    (address.getType() != undefined && this.rendererTemplates[address.getType()] != undefined)
                    ? utils.extend({}, defaultRendererTemplate, this.rendererTemplates[address.getType()])
                    : defaultRendererTemplate;
                var templateData = {
                    parentName: this.name,
                    name: address.getType()
                };

                var rendererComponent = utils.template(rendererTemplate, templateData);
                utils.extend(
                    rendererComponent,
                    {address: ko.observable(address), visible: ko.observable(true)}
                );
                layout([rendererComponent]);
                this.rendererComponents[address.getType()] = rendererComponent;
            }
        }
    });
});
