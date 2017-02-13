Ext.define('Shopware.apps.BlisstributeShipmentMapping', {
    extend: 'Enlight.app.SubApplication',

    name:'Shopware.apps.BlisstributeShipmentMapping',

    loadPath: '{url action=load}',
    bulkLoad: true,

    controllers: [ 'Shipment' ],

    views: [
        'list.Window',
        'list.Shipment'
    ],

    models: [ 'Shipment' ],
    stores: [ 'Shipment' ],

    launch: function() {
        return this.getController('Shipment').mainWindow;
    }
});