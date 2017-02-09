Ext.define('Shopware.apps.BlisstributeArticleType.view.list.Window', {
    extend: 'Shopware.window.Listing',
    alias: 'widget.blisstribute-article-type-list-window',
    height: 450,
    width: 600,
    title : '{s name=window_title}Blisstribute article type listing{/s}',

    configure: function() {
        return {
            listingGrid: 'Shopware.apps.BlisstributeArticleType.view.list.ArticleType',
            listingStore: 'Shopware.apps.BlisstributeArticleType.store.ArticleType'
        };
    }
});