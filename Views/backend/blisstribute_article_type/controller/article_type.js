Ext.define('Shopware.apps.BlisstributeArticleType.controller.ArticleType', {
    extend: 'Enlight.app.Controller',

    init: function() {
        var me = this;
        me.control({
            'blisstribute-article-type-listing-grid': {
                edit: me.onEdit
            }
        });
        me.mainWindow = me.getView('list.Window').create({ }).show();
    },

    onEdit: function(editor, event) {
        var record = event.record,
            groupStore = event.store,
            groupGrid = event.grid;

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