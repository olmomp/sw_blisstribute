Ext.define('Shopware.apps.BlisstributeOrder.view.list.Order', {
    extend: 'Shopware.grid.Panel',
    alias:  'widget.blisstribute-order-listing-grid',
    region: 'center',
    syncButton: null,

    /**
     * contains all snippets for the controller
     *
     * @object
     */
    snippets : {
        id: '{s name=blisstribute/id}ID{/s}',
        orderNumber: '{s name=blisstribute/orderNumber}Bestellnummer{/s}',
        orderDate: '{s name=blisstribute/orderDate}Bestelldatum{/s}',
        status: '{s name=blisstribute/status}Übermittlungsstatus{/s}',
        tries: '{s name=blisstribute/tries}Versuche{/s}',
        errorComment: '{s name=blisstribute/errorComment}Fehlernachricht{/s}',

        statusNone: '{s name=blisstribute/statusNone}Angenommen{/s}',
        statusInTransfer: '{s name=blisstribute/statusInTransfer}In Übermittlung{/s}',
        statusTransferred: '{s name=blisstribute/statusTransferred}Übermittelt{/s}',
        statusTransferError: '{s name=blisstribute/statusTransferError}Übermittlungsfehler{/s}',
        statusValidationError: '{s name=blisstribute/statusValidationError}Bestellung unvollständig{/s}',
        statusCreationPending: '{s name=blisstribute/statusCreationPending}Bestellung in Erstellung{/s}',
        statusAborted: '{s name=blisstribute/statusAborted}Bestellung abgebrochen{/s}'
    },

    configure: function() {
        var me = this;
        return {
            eventAlias: 'blisstribute-order',
            columns: {
                id: {
                    header: me.snippets.id,
                    flex: 1,
                    sortable: true,
                    editor: null,
                    dataIndex: 'id'
                },
                orderNumber: {
                    header: me.snippets.orderNumber,
                    flex: 2,
                    sortable: true,
                    editor: null,
                    dataIndex: 'orderNumber'
                },
                orderDate: {
                    header: me.snippets.orderDate,
                    flex: 2,
                    sortable: true,
                    editor: null,
                    dataIndex: 'orderDate'
                },
                status: {
                    header: me.snippets.status,
                    flex: 2,
                    sortable: true,
                    renderer: function(value) {
                        switch (value) {
                            case 1:
                                return me.snippets.statusNone;

                            case 2:
                                return me.snippets.statusInTransfer;

                            case 3:
                                return me.snippets.statusTransferred;

                            case 10:
                                return me.snippets.statusTransferError;

                            case 11:
                                return me.snippets.statusValidationError;

                            case 20:
                                return me.snippets.statusCreationPending;

                            case 21:
                                return me.snippets.statusAborted;

                            default:
                                return me.snippets.statusNone;
                        }
                    },
                    editor: null,
                    dataIndex: 'status'
                },
                tries: {
                    header: me.snippets.tries,
                    flex: 1,
                    sortable: true,
                    dataIndex: 'tries'
                },
                errorComment: {
                    header: me.snippets.errorComment,
                    flex: 3,
                    sortable: false,
                    editor: null,
                    dataIndex: 'errorComment'
                },
            },
            rowEditing: true,
            editColumn: false,
            deleteColumn: false,
            addButton: false,
            deleteButton: false
        }
    },

    createActionColumnItems: function () {
        var me = this,
            items = me.callParent(arguments);

        items.push({
            action: 'notice',
            iconCls: 'sprite-sticky-notes-pin',
            handler: function (view, rowIndex, colIndex, item) {
                var store = view.getStore(),
                    record = store.getAt(rowIndex);

                Ext.Ajax.request({
                    url: '{url controller=BlisstributeOrder action=getOrderByNumber}',
                    params: {
                        orderNumber: record.data.orderNumber
                    },
                    success: function (response) {
                        var result = Ext.JSON.decode(response.responseText);
                        console.log(result.data);

                        me.fireEvent('openOrder', result.data);
                    }
                });
            }
        });
        return items;
    },

    createToolbarItems: function() {
        var me = this,
            items = me.callParent(arguments);

        items = Ext.Array.insert(
            items,
            0,
            [ me.createSyncButton() ]
        );

        return items;
    },

    createSyncButton: function() {
        var me = this;
        return this.syncButton = Ext.create('Ext.button.Button', {
            disabled: true,
            text: 'Bestellung übermitteln',
            scope: this,
            handler: function() {
                me.fireEvent('sync', me);
            }
        });
    }
});
