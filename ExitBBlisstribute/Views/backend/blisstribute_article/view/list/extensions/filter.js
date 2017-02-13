Ext.define('Shopware.apps.BlisstributeArticle.view.list.extensions.Filter', {
    extend: 'Shopware.listing.FilterPanel',
    alias:  'widget.blisstribute-article-listing-filter-panel',
    width: 270,

    configure: function() {
        return {
            controller: 'BlisstributeArticle',
            model: 'Shopware.apps.BlisstributeArticle.model.Article',
            fields: {
                name: {},
                number: {},
                triggerSync: {},
                blisstributeArticleNumber: {}
            }
        };
    }
});
