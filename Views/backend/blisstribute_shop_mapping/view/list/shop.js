Ext.define('Shopware.apps.BlisstributeShopMapping.view.list.Shop', {
    extend: 'Shopware.grid.Panel',
    alias:  'widget.blisstribute-shop-mapping-listing-grid',
    region: 'center',

    /**
     * Contains all snippets for the controller
     * @object
     */
    snippets: {
        shopId: '{s name=blisstribute/shopId}ID{/s}',
        shopName: '{s name=blisstribute/shopName}Shop{/s}',
        advertisingMediumCode: '{s name=blisstribute/shop}Werbemittel{/s}',
    },

    configure: function() {
        var me = this;
        return {
            eventAlias: 'blisstribute-shop-mapping',
            columns: {
                id: {
                    text: me.snippets.shopId,
                    flex: 1,
                    sortable: true,
                    editor: null,
                    editable: false,
                    dataIndex: 'id'
                },
                shopName: {
                    text: me.snippets.shopName,
                    flex: 1,
                    sortable: true,
                    editor: null,
                    editable: false,
                    dataIndex: 'shopName'
                },
                advertisingMediumCode: {
                    text: me.snippets.advertisingMediumCode,
                    flex: 1,
                    sortable: true,
                    dataIndex: 'advertisingMediumCode'
                }
            },
            rowEditing: true,
            editColumn: false,
            deleteColumn: false,
            addButton: false,
            deleteButton: false
        }
    }
});
