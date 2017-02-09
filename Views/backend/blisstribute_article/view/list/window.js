Ext.define('Shopware.apps.BlisstributeArticle.view.list.Window', {
    extend: 'Shopware.window.Listing',
    alias: 'widget.blisstribute-article-list-window',
    height: 450,
    title : '{s name=window_title}Blisstribute article listing{/s}',

    configure: function() {
        return {
            listingGrid: 'Shopware.apps.BlisstributeArticle.view.list.Article',
            listingStore: 'Shopware.apps.BlisstributeArticle.store.Article',
            extensions: [
                { xtype: 'blisstribute-article-listing-filter-panel' }
            ]
        };
    }
});