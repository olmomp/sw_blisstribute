Ext.define('Shopware.apps.BlisstributeArticleType.model.ArticleType', {
    extend: 'Shopware.data.Model',

    configure: function() {
        return {
            controller: 'BlisstributeArticleType'
        };
    },

    fields: [
        { name : 'id', type: 'int', useNull: true },
        { name : 'sFilterId', type: 'int' },
        { name : 'sFilterName', type: 'string', mapping: 'filter.name' },
        { name : 'articleType', type: 'int' }
    ]
});