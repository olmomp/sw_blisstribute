//{block name="backend/index/view/menu" append}
Ext.define('Shopware.apps.Index.view.ExitbBlisstribute.Menu', {
    override:'Shopware.apps.Index.view.Menu',

    onMenuLoaded: function(response) {
        var me = this;

        me.callParent(arguments);
        me.getInvalidOrderTransfers();
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
        var me = this;
        var text = 'Folgende Bestellungen sind fehlerhaft:' + '<br/>';

        Ext.each(invalidTransfers, function(transfer){
            var orderNumberText = transfer.ordernumber;
            orderNumberText += '<br />';
            text += orderNumberText;
        });

        Shopware.Notification.createStickyGrowlMessage({
            title : '{s name=invalid_orders_warning_title}Ungültige Bestellungen in ExitB{/s}',
            text  : text,
            width : 440,
            height: 300
        });
    },
});
//{/block}
