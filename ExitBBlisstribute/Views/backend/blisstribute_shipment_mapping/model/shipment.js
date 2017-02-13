Ext.define('Shopware.apps.BlisstributeShipmentMapping.model.Shipment', {
    extend: 'Shopware.data.Model',

    configure: function() {
        return {
            controller: 'BlisstributeShipmentMapping'
        };
    },

    fields: [
        { name : 'id', type: 'int', useNull: false, mapping: 'id' },
        { name : 'shipmentName', type: 'string', useNull: false, mapping: 'shipment.name' },
        { name : 'className', type: 'string', useNull: true, mapping: 'className' }
    ]
});