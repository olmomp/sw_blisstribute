Ext.define('Shopware.apps.BlisstributeOrder.controller.Order', {
    extend: 'Enlight.app.Controller',

    init: function() {
        var me = this;
        me.control({
            'blisstribute-order-listing-grid': {
                'blisstribute-order-selection-changed': me.onSelectionChange,
                'sync': me.displaySyncProgress,
                'edit': me.onEdit,
                'openOrder': me.onOpenOrder
            }
        });

        Shopware.app.Application.on('blisstribute-sync-process', me.onSync);

        me.mainWindow = me.getView('list.Window').create({ }).show();
    },

    onOpenOrder:function (id) {
        Shopware.app.Application.addSubApplication({
            name: 'Shopware.apps.Order',
            params: {
                orderId: id
            }
        });
    },

    /**
     * event listener for row editing
     *
     * @param editor
     * @param event
     */
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
    },

    /**
     * event listener for selection change on checkbox selection model
     *
     * @param grid
     * @param selModel
     */
    onSelectionChange: function(grid, selModel) {
        if (selModel.hasSelection()) {
            grid.syncButton.enable();
        } else {
            grid.syncButton.disable();
        }
    },

    /**
     * event listener for sync batch action
     *
     * @param task
     * @param record
     * @param callback
     */
    onSync: function(task, record, callback) {
        Ext.Ajax.request({
            url: '{url controller=BlisstributeOrder action=sync}',
            method: 'POST',
            params: {
                id: record.get('id')
            },
            success: function(response, operation) {
                callback(response, operation);
            }
        });
    },

    /**
     * display progress window for sync action
     *
     * @param grid
     */
    displaySyncProgress: function(grid) {
        var selection = grid.getSelectionModel().getSelection();

        if (selection.length <= 0) return;

        this.displayProgressWindow(grid, {
            event: 'blisstribute-sync-process',
            data: selection,
            text: 'Sync [0] von [1]'
        }, 'Übermittlung zu Blisstribute');
    },

    /**
     * display progress window for batch actions
     *
     * @param grid
     * @param task
     */
    displayProgressWindow: function(grid, task, info) {
        Ext.create('Shopware.window.Progress', {
            title: 'In Übermittlung',
            configure: function() {
                return {
                    tasks: [task],
                    infoText: '<h2>' + info + '</h2>' +
                    'Du kannst über den <b><i>`Abbrechen`</i></b> Button die Übermittlung abbrechen.' +
                    'Abhängig von den zu übermittelnden Daten, kann dieser Prozess ein bisschen länger dauern.'
                }
            }
        }).show();
    }
});