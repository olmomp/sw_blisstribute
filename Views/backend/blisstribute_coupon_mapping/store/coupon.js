Ext.define('Shopware.apps.BlisstributeCouponMapping.store.Coupon', {
    extend:'Shopware.store.Listing',

    configure: function() {
        return {
            controller: 'BlisstributeCouponMapping'
        };
    },

    model: 'Shopware.apps.BlisstributeCouponMapping.model.Coupon'
});