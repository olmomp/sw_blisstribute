Ext.define('Shopware.apps.BlisstributeArticle.controller.Article', {
    extend: 'Enlight.app.Controller',

    init: function() {
        var me = this;
        me.control({
            'blisstribute-article-listing-grid': {
                'blisstribute-article-selection-changed': me.onSelectionChange,
                'trigger-sync': me.displayTriggerProgress,
                'sync': me.displaySyncProgress,
                'edit': me.onEdit,
                'openArticle': me.onOpenArticle,
                'reset-btarticle-lock': me.onResetBtArticleLock
            }
        });

        Shopware.app.Application.on('blisstribute-trigger-sync-process', me.onTriggerSync);
        Shopware.app.Application.on('blisstribute-sync-process', me.onSync);

        me.mainWindow = me.getView('list.Window').create({ }).show();
    },


    onOpenArticle:function (id) {
        Shopware.app.Application.addSubApplication({
            name: 'Shopware.apps.Article',
            params: {
                articleId: id
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
            grid.triggerSyncButton.enable();
        } else {
            grid.syncButton.disable();
            grid.triggerSyncButton.disable();
        }
    },

    /**
     * event listener for trigger sync batch action
     *
     * @param task
     * @param record
     * @param callback
     */
    onTriggerSync: function(task, record, callback) {
        Ext.Ajax.request({
            url: '{url controller=BlisstributeArticle action=triggerSync}',
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
     * event listener for trigger sync batch action
     *
     * @param task
     * @param record
     * @param callback
     */
    onResetBtArticleLock: function(task, record, callback) {
        Ext.Ajax.request({
            url: '{url controller=BlisstributeArticle action=resetLock}',
            method: 'POST',
            params: { },
            success: function(response, operation) {
                Shopware.Notification.createGrowlMessage('Erfolg','Die Artikel-Sperren wurden erfolgreich aufgehoben');
            }
        });
    },

    /**
     * event listener for sync batch action
     * @param task
     * @param record
     * @param callback
     */
    onSync: function(task, record, callback) {
        Ext.Ajax.request({
            url: '{url controller=BlisstributeArticle action=sync}',
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
     * display progress window for trigger sync action
     * @param grid
     */
    displayTriggerProgress: function(grid) {
        var selection = grid.getSelectionModel().getSelection();

        if (selection.length <= 0) return;

        this.displayProgressWindow(grid, {
            event: 'blisstribute-trigger-sync-process',
            data: selection,
            text: 'Trigger sync [0] von [1]'
        }, 'Blisstribute trigger sync');
    },

    /**
     * display progress window for sync action
     * @param grid
     */
    displaySyncProgress: function(grid) {
        var selection = grid.getSelectionModel().getSelection();

        if (selection.length <= 0) return;

        this.displayProgressWindow(grid, {
            event: 'blisstribute-sync-process',
            data: selection,
            text: 'Sync [0] von [1]'
        }, 'Blisstribute sync');
    },

    /**
     * display progress window for batch actions
     * @param grid
     * @param task
     */
    displayProgressWindow: function(grid, task, info) {
        Ext.create('Shopware.window.Progress', {
            title: 'Batch processing',
            configure: function() {
                return {
                    tasks: [task],
                    infoText: '<h2>' + info + '</h2>' +
                    'You can use the <b><i>`Cancel process`</i></b> button the cancel the process. ' +
                    'Depending on the amount of the data set, this process might take a while.'
                }
            }
        }).show();
    }
});