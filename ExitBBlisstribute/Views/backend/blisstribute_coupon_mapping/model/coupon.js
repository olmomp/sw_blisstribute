Ext.define('Shopware.apps.BlisstributeCouponMapping.model.Coupon', {
    extend: 'Shopware.data.Model',

    configure: function() {
        return {
            controller: 'BlisstributeCouponMapping'
        };
    },

    fields: [
        { name : 'id', type : 'int', useNull : false, mapping : 'id' },
        { name : 'couponDescription', type : 'string', useNull : false, mapping: 'voucher.description' },
        { name : 'couponCode', type : 'string', useNull : false, mapping : 'voucher.voucherCode' },
        { name : 'couponOrderNumber', type : 'string', useNull : false, mapping : 'voucher.orderCode' },
        { name : 'isMoneyVoucher', type : 'boolean', useNull : false, mapping : 'isMoneyVoucher'}
    ]
});