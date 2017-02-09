Ext.define('Shopware.apps.BlisstributeArticle', {
    extend: 'Enlight.app.SubApplication',

    name:'Shopware.apps.BlisstributeArticle',

    loadPath: '{url action=load}',
    bulkLoad: true,

    controllers: [ 'Article' ],

    views: [
        'list.Window',
        'list.Article',
        'list.extensions.Filter'
    ],

    models: [ 'Article' ],
    stores: [ 'Article' ],

    launch: function() {
        return this.getController('Article').mainWindow;
    }
});
