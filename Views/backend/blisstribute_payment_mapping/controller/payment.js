Ext.define('Shopware.apps.BlisstributePaymentMapping.controller.Payment', {
    extend: 'Enlight.app.Controller',

    init: function() {
        var me = this;
        me.control({
            'blisstribute-payment-mapping-listing-grid': {
                edit: me.onEdit
            }
        });

        me.mainWindow = me.getView('list.Window').create({ }).show();
    },

    onEdit: function(editor, event) {
        var record = event.record;
        var groupStore = event.store;
        var groupGrid = event.grid;

        if (!record.dirty) {
            return;
        }

        groupGrid.setLoading(true);
        record.save({
            callback: function(record) {
                groupGrid.setLoading(false);
                groupStore.getProxy().extraParams.optionId = record.get('id');
                record.commit();
            },
            failure: function() {
                if (record.phantom) {
                    event.store.remove(record);
                }
            }
        });
    }
});