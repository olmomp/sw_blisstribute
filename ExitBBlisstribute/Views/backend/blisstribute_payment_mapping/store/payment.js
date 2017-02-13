Ext.define('Shopware.apps.BlisstributePaymentMapping.store.Payment', {
    extend:'Shopware.store.Listing',

    configure: function() {
        return {
            controller: 'BlisstributePaymentMapping'
        };
    },

    model: 'Shopware.apps.BlisstributePaymentMapping.model.Payment'
});