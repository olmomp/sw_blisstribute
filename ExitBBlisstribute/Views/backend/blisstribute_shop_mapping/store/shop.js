Ext.define('Shopware.apps.BlisstributeShopMapping.store.Shop', {
    extend:'Shopware.store.Listing',

    configure: function() {
        return {
            controller: 'BlisstributeShopMapping'
        };
    },

    model: 'Shopware.apps.BlisstributeShopMapping.model.Shop'
});