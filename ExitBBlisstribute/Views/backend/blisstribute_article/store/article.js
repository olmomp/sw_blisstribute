Ext.define('Shopware.apps.BlisstributeArticle.store.Article', {
    extend:'Shopware.store.Listing',

    configure: function() {
        return {
            controller: 'BlisstributeArticle'
        };
    },
    model: 'Shopware.apps.BlisstributeArticle.model.Article'
});