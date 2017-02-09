Ext.define('Shopware.apps.BlisstributeCouponMapping.view.list.Window', {
    extend: 'Shopware.window.Listing',
    alias: 'widget.blisstribute-coupon-mapping-list-window',
    height: 500,
    width: 600,
    title : '{s name=window_title}Blisstribute Gutschein Zuweisung{/s}',

    configure: function() {
        return {
            listingGrid: 'Shopware.apps.BlisstributeCouponMapping.view.list.Coupon',
            listingStore: 'Shopware.apps.BlisstributeCouponMapping.store.Coupon'
        };
    }
});