Ext.define('Shopware.apps.BlisstributeOrder.controller.Order', {
    extend: 'Enlight.app.Controller',

    init: function() {
        var me = this;
        me.control({
            'blisstribute-order-listing-grid': {
                'blisstribute-order-selection-changed': me.onSelectionChange,
                'reset-btorder-lock': me.onResetBtOrderLock,
                'edit': me.onEdit,
                'openOrder': me.onOpenOrder,

                'btorder-sync': me.displaySyncProgress, //triggers blisstribute-order-sync-process

                'reset-btorder-sync': me.displayResetSyncProgress, //triggers blisstribute-order-reset-sync-process
                'update-btorder-sync': me.displayUpdateSyncProgress, //triggers blisstribute-order-update-sync-process

            }
        });

        Shopware.app.Application.on('blisstribute-order-sync-process', me.onSync);
        Shopware.app.Application.on('blisstribute-order-reset-sync-process', me.onResetOrderSync);
        Shopware.app.Application.on('blisstribute-order-update-sync-process', me.onUpdateOrderSync);


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
            grid.resetSyncButton.enable();
            grid.updateSyncButton.enable();
        } else {
            grid.syncButton.disable();
            grid.resetSyncButton.disable();
            grid.updateSyncButton.disable();
        }
    },

    /**
     * event listener for trigger sync batch action
     *
     * @param task
     * @param record
     * @param callback
     */
    onResetBtOrderLock: function(task, record, callback) {
        console.log(task, record, callback);
        Ext.Ajax.request({
            url: '{url controller=BlisstributeOrder action=resetLock}',
            method: 'POST',
            params: { },
            success: function(response, operation) {
                Shopware.Notification.createGrowlMessage('Erfolg','Die Bestell-Sperren wurden erfolgreich aufgehoben');
            }
        });
    },

    /**
     * display progress window for batch actions
     *
     * @param grid
     * @param task
     * @param config
     */
    displayProgressWindow: function(grid, task, config) {
        Ext.create('Shopware.window.Progress', {
            title: config.title,
            configure: function() {
                return {
                    tasks: [task],
                    infoText: config.info
                }
            }
        }).show();
    },

    /**
     * display progress window for sync action
     *
     * @param grid
     */
    displaySyncProgress: function(grid) {
        var selection = grid.getSelectionModel().getSelection();
        if (selection.length <= 0) {
            return;
        }

        this.displayProgressWindow(grid, {
            event: 'blisstribute-order-sync-process',
            data: selection,
            text: 'Sync [0] von [1]'
        }, {
            title: 'Übermittlung der ausgew. Bestellungen zu Blisstribute',
            info: 'Du kannst über den <b><i>`Abbrechen`</i></b> Button die Übermittlung abbrechen.<br /><br />' +
                'Abhängig von den zu übermittelnden Daten, kann dieser Prozess ein bisschen länger dauern.'
        });
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
    displayResetSyncProgress: function(grid) {
        var selection = grid.getSelectionModel().getSelection();
        if (selection.length <= 0) {
            return;
        }

        this.displayProgressWindow(grid, {
            event: 'blisstribute-order-reset-sync-process',
            data: selection,
            text: 'Markierung [0] von [1]'
        }, {
            title: 'Die ausgew. Bestellungen werden als "nicht übertragen" markiert',
            info: 'Du kannst über den <b><i>`Abbrechen`</i></b> Button die Markierung abbrechen.<br /><br />' +
                'Abhängig von den zu übermittelnden Daten, kann dieser Prozess ein bisschen länger dauern.'
        });
    },

    /**
     * @param task
     * @param record
     * @param callback
     */
    onResetOrderSync: function(task, record, callback) {
        Ext.Ajax.request({
            url: '{url controller=BlisstributeOrder action=resetOrderSync}',
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
    displayUpdateSyncProgress: function(grid) {
        var selection = grid.getSelectionModel().getSelection();
        if (selection.length <= 0) {
            return;
        }

        this.displayProgressWindow(grid, {
            event: 'blisstribute-order-update-sync-process',
            data: selection,
            text: 'Markierung [0] von [1]'
        }, {
            title: 'Die ausgew. Bestellungen werden als "übertragen" markiert',
            info: 'Du kannst über den <b><i>`Abbrechen`</i></b> Button die Markierung abbrechen.<br /><br />' +
                'Abhängig von den zu übermittelnden Daten, kann dieser Prozess ein bisschen länger dauern.'
        });
    },

    /**
     * @param task
     * @param record
     * @param callback
     */
    onUpdateOrderSync: function(task, record, callback) {
        Ext.Ajax.request({
            url: '{url controller=BlisstributeOrder action=updateOrderSync}',
            method: 'POST',
            params: {
                id: record.get('id')
            },
            success: function(response, operation) {
                callback(response, operation);
            }
        });
    },


});