Ext.define('Shopware.apps.BlisstributeShopMapping', {
    extend: 'Enlight.app.SubApplication',

    name:'Shopware.apps.BlisstributeShopMapping',

    loadPath: '{url action=load}',
    bulkLoad: true,

    controllers: [ 'Shop' ],

    views: [
        'list.Window',
        'list.Shop'
    ],

    models: [ 'Shop' ],
    stores: [ 'Shop' ],

    launch: function() {
        return this.getController('Shop').mainWindow;
    }
});