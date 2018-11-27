Ext.define('Shopware.apps.BlisstributeOrder.view.list.Window', {
    extend: 'Shopware.window.Listing',
    alias: 'widget.blisstribute-order-list-window',
    height: 450,
    width: 1280,
    title : '{s name=window_title}Blisstribute Bestellexport Übersicht{/s}',

    configure: function() {
        return {
            listingGrid: 'Shopware.apps.BlisstributeOrder.view.list.Order',
            listingStore: 'Shopware.apps.BlisstributeOrder.store.Order',

            extensions: [
                { xtype: 'blisstribute-order-listing-filter-panel' }
            ]
        };
    }
});