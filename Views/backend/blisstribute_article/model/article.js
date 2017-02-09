Ext.define('Shopware.apps.BlisstributeArticle.model.Article', {
    extend: 'Shopware.data.Model',

    configure: function() {
        return {
            controller: 'BlisstributeArticle'
        };
    },

    fields: [
        { name : 'id', type: 'int', useNull: true },
        { name : 'createdAt', type: 'date' },
        { name : 'modifiedAt', type: 'date' },
        { name : 'lastCronAt', type: 'date' },
        { name : 'articleNumber', type: 'string', useNull: true, mapping: 'article.mainDetail.number' },
        { name : 'articleEan', type: 'string', useNull: true, mapping: 'article.mainDetail.ean' },
        { name : 'articleName', type: 'string', useNull: false, mapping: 'article.name' },
        { name : 'articleVhs', type: 'string', useNull: true, mapping: 'article.attribute.blisstributeVhsNumber' },
        { name : 'articleType', type: 'string' },
        { name : 'triggerSync', type: 'boolean' },
        { name : 'tries', type: 'int'},
        { name : 'comment', type: 'string', useNull: true }
    ]
});