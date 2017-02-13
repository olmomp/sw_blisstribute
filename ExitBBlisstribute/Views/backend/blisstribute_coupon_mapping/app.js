Ext.define('Shopware.apps.BlisstributeCouponMapping', {
    extend: 'Enlight.app.SubApplication',

    name:'Shopware.apps.BlisstributeCouponMapping',

    loadPath: '{url action=load}',
    bulkLoad: true,

    controllers: [ 'Coupon' ],

    views: [
        'list.Window',
        'list.Coupon'
    ],

    models: [ 'Coupon' ],
    stores: [ 'Coupon' ],

    launch: function() {
        return this.getController('Coupon').mainWindow;
    }
});