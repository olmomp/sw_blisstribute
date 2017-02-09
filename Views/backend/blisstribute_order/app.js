Ext.define('Shopware.apps.BlisstributeOrder', {
    extend: 'Enlight.app.SubApplication',

    name:'Shopware.apps.BlisstributeOrder',

    loadPath: '{url action=load}',
    bulkLoad: true,

    controllers: [ 'Order' ],

    views: [
        'list.Window',
        'list.Order',
        'list.extensions.Filter',
    ],

    models: [ 'Order' ],
    stores: [ 'Order' ],

    launch: function() {
        return this.getController('Order').mainWindow;
    }
});
