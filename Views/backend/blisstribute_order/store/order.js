Ext.define('Shopware.apps.BlisstributeOrder.store.Order', {
    extend:'Shopware.store.Listing',

    configure: function() {
        return {
            controller: 'BlisstributeOrder'
        };
    },
    model: 'Shopware.apps.BlisstributeOrder.model.Order'
});