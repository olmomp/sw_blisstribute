Ext.define('Shopware.apps.BlisstributeArticleType.store.ArticleType', {
    extend:'Shopware.store.Listing',

    configure: function() {
        return {
            controller: 'BlisstributeArticleType'
        };
    },
    model: 'Shopware.apps.BlisstributeArticleType.model.ArticleType'
});