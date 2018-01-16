Ext.define('Shopware.apps.BlisstributeArticle.model.Article', {
    extend: 'Shopware.data.Model',

    configure: function() {
        return {
            controller: 'BlisstributeArticle'
        };
    },

    fields: [
        { name : 'id', type: 'int', useNull: true },
        { name : 'createdAt', type: 'date', useNull: true },
        { name : 'modifiedAt', type: 'date', useNull: true },
        { name : 'lastCronAt', type: 'date', useNull: true },
        { name : 'articleNumber', type: 'string', useNull: true, mapping: 'article.mainDetail.number' },
        { name : 'articleEan', type: 'string', useNull: true, mapping: 'article.mainDetail.ean' },
        { name : 'articleName', type: 'string', useNull: false, mapping: 'article.name' },
        { name : 'articleVhs', type: 'string', useNull: true, mapping: 'article.mainDetail.attribute.blisstributeVhsNumber' },
        { name : 'articleType', type: 'string', useNull: true },
        { name : 'triggerSync', type: 'boolean', useNull: true },
        { name : 'tries', type: 'int', useNull: true},
        { name : 'comment', type: 'string', useNull: true }
    ]
});