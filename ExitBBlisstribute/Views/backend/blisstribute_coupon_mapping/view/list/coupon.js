Ext.define('Shopware.apps.BlisstributeCouponMapping.view.list.Coupon', {
    extend: 'Shopware.grid.Panel',
    alias:  'widget.blisstribute-coupon-mapping-listing-grid',
    region: 'center',

    /**
     * Contains all snippets for the controller
     * @object
     */
    snippets: {
        id : '{s name=blisstribute/couponId}ID{/s}',
        couponDescription : '{s name=blisstribute/couponDescription}Beschreibung{/s}',
        couponCode : '{s name=blisstribute/couponOrderCode}Code{/s}',
        couponOrderNumber : '{s name=blisstribute/couponOrderNumber}Bestellnummer{/s}',
        isMoneyVoucher : '{s name=blisstribute/couponIsMoneyVoucher}Wertgutschein{/s}'
    },

    configure: function() {
        var me = this;
        return {
            eventAlias: 'blisstribute-coupon-mapping',
            columns: {
                id: {
                    text: me.snippets.id,
                    flex: 1,
                    sortable: true,
                    editor: null,
                    editable: false,
                    dataIndex: 'id'
                },
                couponDescription: {
                    text: me.snippets.couponDescription,
                    flex: 3,
                    sortable: true,
                    editor: null,
                    editable: false,
                    dataIndex: 'couponDescription'
                },
                couponCode: {
                    text: me.snippets.couponOrderNumber,
                    flex: 2,
                    sortable: true,
                    editor: null,
                    editable: false,
                    dataIndex: 'couponCode'
                },
                couponOrderNumber: {
                    text: me.snippets.couponOrderNumber,
                    flex: 2,
                    sortable: true,
                    editor: null,
                    editable: false,
                    dataIndex: 'couponOrderNumber'
                },
                isMoneyVoucher: {
                    text: me.snippets.isMoneyVoucher,
                    flex: 1,
                    sortable: true,
                    dataIndex: 'isMoneyVoucher'
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
