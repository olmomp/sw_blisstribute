Ext.define('Shopware.apps.BlisstributeArticle.view.list.Article', {
    extend: 'Shopware.grid.Panel',
    alias:  'widget.blisstribute-article-listing-grid',
    region: 'center',

    triggerSyncButton: null,
    syncButton: null,

    /**
     * Contains all snippets for the controller
     * @object
     */
    snippets: {
    },

    configure: function() {
        return {
            eventAlias: 'blisstribute-article',
            columns: {
                articleName: {
                    header: 'Artikel Name',
                    flex: 3,
                    sortable: false,
                    editor: null,
                    dataIndex: 'articleName'
                },
                articleNumber: {
                   header: 'Artikel Nummer',
                   flex: 2,
                   sortable: true,
                   editor: null,
                   dataIndex: 'articleNumber'
                },
                articleEan: {
                    header: 'EAN',
                    flex: 2,
                    sortable: true,
                    editor: null,
                    dataIndex: 'articleEan'
                },
                articleVhs: {
                    header: 'VHS',
                    flex: 2,
                    sortable: true,
                    editor: null,
                    dataIndex: 'articleVhs'
                },
                lastCronAt: {
                    header: 'Letzte Synchronisation',
                    flex: 1,
                    sortable: true,
                    editor: null,
                    dataIndex: 'lastCronAt'
                },
                triggerSync: {
                    header: 'Synchronisation Vorgemerkt',
                    flex: 1,
                    sortable: true,
                    dataIndex: 'triggerSync'
                },
                tries: {
                    header: 'Fehlerhafte Versuche',
                    flex: 1,
                    sortable: true,
                    dataIndex: 'tries'
                },
                comment: {
                    header: 'Fehlernachricht',
                    flex: 3,
                    sortable: true,
                    dataIndex: 'comment'
                }
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
                    url: '{url controller=BlisstributeArticle action=getArticleIdByNumber}',
                    params: {
                        articleNumber: record.data.articleNumber
                    },
                    success: function (response) {
                        var result = Ext.JSON.decode(response.responseText);

                        me.fireEvent('openArticle', result.data);
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
            [
                me.createTriggerSyncButton(),
                me.createSyncButton(),
                { xtype: 'tbseparator' },
                me.createResetLockButton()
            ]
        );

        return items;
    },

    createTriggerSyncButton: function() {
        var me = this;
        return this.triggerSyncButton = Ext.create('Ext.button.Button', {
            disabled: true,
            text: 'Übertragung vormerken',
            scope: this,
            handler: function() {
                me.fireEvent('trigger-sync', me);
            }
        });
    },

    createResetLockButton: function() {
        var me = this;
        return this.triggerSyncButton = Ext.create('Ext.button.Button', {
            text: 'Sperren aufheben',
            scope: this,
            handler: function() {
                me.fireEvent('reset-btarticle-lock', me);
            }
        });
    },

    createSyncButton: function() {
        var me = this;
        return this.syncButton = Ext.create('Ext.button.Button', {
            disabled: true,
            text: 'Jetzt übertragen',
            scope: this,
            handler: function() {
                me.fireEvent('btarticle-sync', me);
            }
        });
    }
});
