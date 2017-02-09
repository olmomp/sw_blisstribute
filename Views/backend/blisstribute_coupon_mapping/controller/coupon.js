Ext.define('Shopware.apps.BlisstributeCouponMapping.controller.Coupon', {
    extend: 'Enlight.app.Controller',

    init: function() {
        var me = this;
        me.control({
            'blisstribute-coupon-mapping-listing-grid': {
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
                groupStore.getProp.extraParams.optionId = record.get('id');
                record.commit();
            },
            failure: function() {
                groupGrid.setLoading(false);
                if (record.phantom) {
                    event.store.remove(record);
                }
            }
        });
    }
});