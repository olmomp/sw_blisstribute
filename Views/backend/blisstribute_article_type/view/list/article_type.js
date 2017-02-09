Ext.define('Shopware.apps.BlisstributeArticleType.view.list.ArticleType', {
    extend: 'Shopware.grid.Panel',
    alias:  'widget.blisstribute-article-type-listing-grid',
    region: 'center',

    /**
     * Contains all snippets for the controller
     * @object
     */
    snippets: {
        articleTypeMedia:      '{s name=blisstribute/combo_article_type_media}Music/Media{/s}',
        articleTypeWear:       '{s name=blisstribute/combo_article_type_wear}Wear{/s}',
        articleTypeWearAttire: '{s name=blisstribute/combo_article_type_wear_attire}Wear Attire{/s}',
        articleTypeEquipment:  '{s name=blisstribute/combo_article_type_equipment}Equipment{/s}',
        configuratorGroupName: '{s name=blisstribute/configuratorGroupName}Name{/s}',
        articleType:           '{s name=blisstribute/articleType}Artikeltyp{/s}',
    },

    configure: function() {
        var me = this;
        return {
            eventAlias: 'blisstribute-article-type',
            columns: {
                sFilterName: {
                    text: me.snippets.configuratorGroupName,
                    flex: 2,
                    sortable: false,
                    dataIndex: 'sFilterName'
                },
                articleType: {
                    text: me.snippets.articleType,
                    flex: 1,
                    dataIndex: 'articleType',
                    align: 'left',
                    renderer: function(value) {
                        switch (value) {
                            case 1:
                                return me.snippets.articleTypeMedia;
                            case 2:
                                return me.snippets.articleTypeWear;
                            case 3:
                                return me.snippets.articleTypeWearAttire;
                            case 4:
                            default:
                                return me.snippets.articleTypeEquipment;
                        }
                    },
                    editor: Ext.create('Ext.form.field.ComboBox', {
                        store: new Ext.data.SimpleStore({
                            fields:['id', 'label'],
                            data: [
                                [1, me.snippets.articleTypeMedia],
                                [2, me.snippets.articleTypeWear],
                                [3, me.snippets.articleTypeWearAttire],
                                [4, me.snippets.articleTypeEquipment]
                            ]
                        }),
                        allowBlank: false,
                        editable: false,
                        mode: 'local',
                        triggerAction: 'all',
                        displayField: 'label',
                        valueField: 'id'
                    })
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
