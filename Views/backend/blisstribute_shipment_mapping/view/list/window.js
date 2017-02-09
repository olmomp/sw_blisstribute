Ext.define('Shopware.apps.BlisstributeShipmentMapping.view.list.Window', {
    extend: 'Shopware.window.Listing',
    alias: 'widget.blisstribute-shipment-mapping-list-window',
    height: 450,
    width: 600,
    title : '{s name=window_title}Blisstribute Versandart Zuweisung{/s}',

    configure: function() {
        return {
            listingGrid: 'Shopware.apps.BlisstributeShipmentMapping.view.list.Shipment',
            listingStore: 'Shopware.apps.BlisstributeShipmentMapping.store.Shipment'
        };
    }
});