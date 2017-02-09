Ext.define('Shopware.apps.BlisstributePaymentMapping.model.Payment', {
    extend: 'Shopware.data.Model',

    configure: function() {
        return {
            controller: 'BlisstributePaymentMapping'
        };
    },

    fields: [
        { name : 'id', type: 'int', useNull: false, mapping: 'id' },
        { name : 'paymentName', type: 'string', useNull: false, mapping: 'payment.description' },
        { name : 'paymentIsActive', type: 'boolean', useNull: false, mapping: 'payment.active'},
        { name : 'isPayed', type: 'boolean', useNull: false, mapping: 'isPayed' },
        { name : 'className', type: 'string', useNull: true, mapping: 'className' }
    ]
});