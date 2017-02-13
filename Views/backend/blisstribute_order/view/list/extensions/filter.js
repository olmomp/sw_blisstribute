Ext.define('Shopware.apps.BlisstributeOrder.view.list.extensions.Filter', {
    extend: 'Shopware.listing.FilterPanel',
    alias:  'widget.blisstribute-order-listing-filter-panel',
    width: 270,

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
        return {
            controller: 'BlisstributeOrder',
            model: 'Shopware.apps.BlisstributeOrder.model.Order',
            fields: {
                status: this.createStatusFormField
            }
        };
    },

    createStatusFormField: function(model, formField) {
        var me = this;
        Ext.define('Status', {
            extend: 'Ext.data.Model',
            fields: [
                { name: 'id', type: 'int' },
                { name: 'name',  type: 'string' }
            ]
        });

        var statusStore = Ext.create('Ext.data.Store', {
            autoLoad: false,
            model: 'Status',
            data: [
                { id: 1, name: me.snippets.statusNone },
                { id: 2, name: me.snippets.statusInTransfer },
                { id: 3, name: me.snippets.statusTransferred },
                { id: 10, name: me.snippets.statusTransferError },
                { id: 11, name: me.snippets.statusValidationError },
                { id: 20, name: me.snippets.statusCreationPending },
                { id: 21, name: me.snippets.statusAborted },
            ]
        });

        formField.fieldLabel = me.snippets.status;
        formField.store = statusStore;
        formField.queryMode = 'local';

        formField.xtype = 'combo';
        formField.displayField = 'name';
        formField.valueField = 'id';

        return formField;
    }
});




