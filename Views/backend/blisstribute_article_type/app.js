Ext.define('Shopware.apps.BlisstributeArticleType', {
    extend: 'Enlight.app.SubApplication',

    name:'Shopware.apps.BlisstributeArticleType',

    loadPath: '{url action=load}',
    bulkLoad: true,

    controllers: [ 'ArticleType' ],

    views: [
        'list.Window',
        'list.ArticleType'
    ],

    models: [ 'ArticleType' ],
    stores: [ 'ArticleType' ],

    launch: function() {
        return this.getController('ArticleType').mainWindow;
    }
});