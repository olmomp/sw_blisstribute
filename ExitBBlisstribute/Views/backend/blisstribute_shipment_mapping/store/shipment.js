Ext.define('Shopware.apps.BlisstributeShipmentMapping.store.Shipment', {
    extend:'Shopware.store.Listing',

    configure: function() {
        return {
            controller: 'BlisstributeShipmentMapping'
        };
    },
    model: 'Shopware.apps.BlisstributeShipmentMapping.model.Shipment'
});