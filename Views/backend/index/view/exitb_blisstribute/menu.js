//{block name="backend/index/view/menu" append}
Ext.define('Shopware.apps.Index.view.ExitbBlisstribute.Menu', {
    override:'Shopware.apps.Index.view.Menu',

    onMenuLoaded: function(response) {
        var me = this;

        me.callParent(arguments);
        me.getInvalidOrderTransfers();
        me.checkPluginUpToDate();
    },

    getInvalidOrderTransfers: function() {
        var me = this;

        Ext.Ajax.request({
            url: '{url controller="BlisstributeOrder" action="getInvalidOrderTransfers"}',
            async: false,
            success: function (response) {
                var responseData = Ext.decode(response.responseText);

                if (Ext.isEmpty(responseData.data)) {
                    return;
                }

                if (responseData.success == true) {
                    me.displayInvalidOrderTransferNotice(responseData.data);
                }
            }
        });
    },

    displayInvalidOrderTransferNotice: function(invalidTransfers) {
        var text = '';
        Ext.each(invalidTransfers, function(transfer) {
            text += transfer.ordernumber + '<br />';
        });

        Shopware.Notification.createStickyGrowlMessage({
            title : 'Folgende Bestellungen wurden nicht mit Blisstribute synchronisiert',
            text  : text,
            width : 440,
            height: 300
        });
    },

    checkPluginUpToDate: function() {
        Ext.Ajax.request({
            url: '{url controller="BlisstributeOrder" action="checkPluginUpToDate"}',
            async: false,
            success: function (response) {
                var responseData = Ext.decode(response.responseText);
                if (responseData.success == true && responseData.outdated === -1) {
                    Shopware.Notification.createStickyGrowlMessage({
                        title : 'Das Blisstribute Plugin ist nicht aktuell!',
                        text  : 'Ihre Version: ' + responseData.currentVersion + ' | Aktuellste Version: ' + responseData.latestVersion,
                        width : 440,
                        height: 300
                    });
                }
            }
        });
    },
});
//{/block}
