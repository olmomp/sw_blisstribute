Ext.define('Shopware.apps.BlisstributePaymentMapping.view.list.Window', {
    extend: 'Shopware.window.Listing',
    alias: 'widget.blisstribute-payment-mapping-list-window',
    height: 450,
    width: 600,
    title : '{s name=window_title}Blisstribute Payment Zuweisung{/s}',

    configure: function() {
        return {
            listingGrid: 'Shopware.apps.BlisstributePaymentMapping.view.list.Payment',
            listingStore: 'Shopware.apps.BlisstributePaymentMapping.store.Payment'
        };
    }
});