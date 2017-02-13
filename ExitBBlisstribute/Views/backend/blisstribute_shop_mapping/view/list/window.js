Ext.define('Shopware.apps.BlisstributeShopMapping.view.list.Window', {
    extend: 'Shopware.window.Listing',
    alias: 'widget.blisstribute-shop-mapping-list-window',
    height: 450,
    width: 600,
    title : '{s name=window_title}Blisstribute Shop Zuweisung{/s}',

    configure: function() {
        return {
            listingGrid: 'Shopware.apps.BlisstributeShopMapping.view.list.Shop',
            listingStore: 'Shopware.apps.BlisstributeShopMapping.store.Shop'
        };
    }
});