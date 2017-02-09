Ext.define('Shopware.apps.BlisstributeOrder.model.Order', {
    extend: 'Shopware.data.Model',

    configure: function() {
        return {
            controller: 'BlisstributeOrder'
        };
    },

    fields: [
        { name : 'id', type: 'int', useNull: false },
        { name : 'orderNumber', type: 'string', mapping: 'order.number' },
        { name : 'orderDate', type: 'date', mapping: 'order.orderTime' },
        { name : 'status', type: 'int' },
        { name : 'tries', type: 'int' },
        { name : 'errorComment', type: 'string' }
    ]
});