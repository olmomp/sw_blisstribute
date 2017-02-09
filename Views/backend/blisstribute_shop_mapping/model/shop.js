Ext.define('Shopware.apps.BlisstributeShopMapping.model.Shop', {
    extend: 'Shopware.data.Model',

    configure: function() {
        return {
            controller: 'BlisstributeShopMapping'
        };
    },

    fields: [
        { name : 'id', type : 'int', useNull : false, mapping : 'id' },
        { name : 'shopName', type : 'string', useNull : false, mapping : 'shop.name' },
        { name : 'advertisingMediumCode', type : 'string', useNull : false, mapping : 'advertisingMediumCode'}
    ]
});