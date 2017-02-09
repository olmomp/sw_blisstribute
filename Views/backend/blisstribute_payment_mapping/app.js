Ext.define('Shopware.apps.BlisstributePaymentMapping', {
    extend: 'Enlight.app.SubApplication',

    name:'Shopware.apps.BlisstributePaymentMapping',

    loadPath: '{url action=load}',
    bulkLoad: true,

    controllers: [ 'Payment' ],

    views: [
        'list.Window',
        'list.Payment'
    ],

    models: [ 'Payment' ],
    stores: [ 'Payment' ],

    launch: function() {
        return this.getController('Payment').mainWindow;
    }
});