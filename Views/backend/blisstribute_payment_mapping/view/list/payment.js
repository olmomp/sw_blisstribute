Ext.define('Shopware.apps.BlisstributePaymentMapping.view.list.Payment', {
    extend: 'Shopware.grid.Panel',
    alias:  'widget.blisstribute-payment-mapping-listing-grid',
    region: 'center',

    /**
     * Contains all snippets for the controller
     *
     * @object
     */
    snippets: {
        id: '{s name=blisstribute/id}ID{/s}',
        paymentName: '{s name=blisstribute/paymentName}Zahlart{/s}',
        paymentIsActive: '{s name=blisstribute/paymentIsActive}Zahlart Aktiv?{/s}',
        isPayed: '{s name=blisstribute/isPayed}Bezahlt{/s}',
        className: '{s name=blisstribute/className}Blisstribute Zuweisung{/s}',

        classNameNone: '{s name=blisstribute/none}Bitte wählen{/s}',
        classNamePrePayment: '{s name=blisstribute/paymentPrePayment}Vorkasse{/s}',
        classNamedebitAdvice: '{s name=blisstribute/paymentDebitAdvice}Lastschrift{/s}',
        classNameCashOnDelivery: '{s name=blisstribute/paymentCashOnDelivery}Nachnahme{/s}',
        classNameSofort: '{s name=blisstribute/paymentSofort}Sofortueberweisung{/s}',
        classNamePayPal: '{s name=blisstribute/paymentPayPal}PayPal{/s}',
        classNamePayPalPlus: '{s name=blisstribute/paymentPayPalPlus}PayPalPlus{/s}',
        classNameBill: '{s name=blisstribute/paymentBill}Rechnung (Eigene Abwicklung){/s}',
        classNamePayolution: '{s name=blisstribute/paymentPayolution}Rechnung (Payolution){/s}',
        classNamePayolutionInstallment: '{s name=blisstribute/paymentPayolutionInstallment}Ratenkauf (Payolution){/s}',
        classNamePayolutionELV: '{s name=blisstribute/paymentPayolutionELV}ELV (Payolution){/s}',
        classNameHeidelpayCreditCard: '{s name=blisstribute/paymentHeidelpayCreditCard}Kreditkarte (Heidelpay){/s}',
        classNameHeidelpaySofort: '{s name=blisstribute/paymentHeidelpaySofort}Sofortueberweisung (Heidelpay){/s}',
        classNameHeidelpayIdeal: '{s name=blisstribute/paymentHeidelpayIdeal}iDEAL (Heidelpay){/s}',
        classNameHeidelpayPostFinance: '{s name=blisstribute/paymentHeidelpayPostFinance}PostFinance (Heidelpay){/s}',
        classNameMarketplace: '{s name=blisstribute/paymentMarketplace}Marktplatz{/s}',
        classNameSelfcollectorCash: '{s name=blisstribute/paymentSelfcollectorCash}Bar (Selbstabholer){/s}',
        classNameSelfcollectorCashEc: '{s name=blisstribute/paymentSelfcollectorCashEc}EC (Selbstabholer){/s}',
        classNameSelfcollectorCashCreditCard: '{s name=blisstribute/paymentSelfcollectorCashCreditCard}Kreditkarte (Selbstabholer){/s}',
        classNameWirecardCP: '{s name=blisstribute/paymentWirecardCP}Wirecard CC{/s}',
        classNameVrPayCC: '{s name=blisstribute/paymentVrPayCC}vrPayCC{/s}',
        classNamePayOneCC: '{s name=blisstribute/paymentPayOneCC}PayOneCC{/s}',
        classNamePayOneELV: '{s name=blisstribute/paymentPayOneELV}PayOneELV{/s}',
        classNameKlarna: '{s name=blisstribute/paymentKlarna}Klarna{/s}',
        classNameKlarnaSofort: '{s name=blisstribute/paymentKlarnaSofort}Klarna Sofort{/s}',
		classNameKlarnaRest: '{s name=blisstribute/paymentKlarnaRest}Klarna (Rest API){/s}',
        classNameAmazonPayments: '{s name=blisstribute/paymentAmazonPayments}Amazon Payments{/s}',
        classNameBillsafe: '{s name=blisstribute/paymentBillsafe}Billsafe{/s}',
        classNameAfterPay: '{s name=blisstribute/paymentAfterPay}AfterPay{/s}',
    },

    configure: function() {
        var me = this;
        return {
            eventAlias: 'blisstribute-payment-mapping',
            columns: {
                id: {
                    header: me.snippets.id,
                    flex: 2,
                    sortable: false,
                    editor: null,
                    editable: false,
                    dataIndex: 'id'
                },
                paymentName: {
                    header: me.snippets.paymentName,
                    flex: 4,
                    sortable: true,
                    editor: null,
                    editable: false,
                    dataIndex: 'paymentName'
                },
                paymentIsActive: {
                    header: me.snippets.paymentIsActive,
                    flex: 2,
                    sortable: true,
                    editor: null,
                    editable: false,
                    dataIndex: 'paymentIsActive'
                },
                isPayed: {
                    header: me.snippets.isPayed,
                    flex: 1,
                    sortable: true,
                    dataIndex: 'isPayed'
                },
                className: {
                    header: me.snippets.className,
                    flex: 3,
                    sortable: false,
                    dataIndex: 'className',
                    align: 'left',
                    renderer: function(value) {
                        switch (value) {
                            case 'PrePayment':
                                return me.snippets.classNamePrePayment;

                            case 'DebitAdvice':
                                return me.snippets.classNamedebitAdvice;

                            case 'CashOnDelivery':
                                return me.snippets.classNameCashOnDelivery;

                            case 'PayPal':
                                return me.snippets.classNamePayPal;

                            case 'PayPalPlus':
                                return me.snippets.classNamePayPalPlus;

                            case 'Bill':
                                return me.snippets.classNameBill;

                            case 'Payolution':
                                return me.snippets.classNamePayolution;

                            case 'PayolutionELV':
                                return me.snippets.classNamePayolutionELV;

                            case 'PayolutionInstallment':
                                return me.snippets.classNamePayolutionInstallment;

                            case 'Sofort':
                                return me.snippets.classNameSofort;

                            case 'HeidelpayCreditCard':
                                return me.snippets.classNameHeidelpayCreditCard;
                            
                            case 'HeidelpaySofort':
                                return me.snippets.classNameHeidelpaySofort;
                            
                            case 'HeidelpayIdeal':
                                return me.snippets.classNameHeidelpayIdeal;
                                
                            case 'HeidelpayPostFinance':
                                return me.snippets.classNameHeidelpayPostFinance;

                            case 'Marketplace':
                                return me.snippets.classNameMarketplace;

                            case 'SelfcollectorCash':
                                return me.snippets.classNameSelfcollectorCash;

                            case 'SelfcollectorCashEc':
                                return me.snippets.classNameSelfcollectorCashEc;

                            case 'SelfcollectorCashCreditCard':
                                return me.snippets.classNameSelfcollectorCashCreditCard;

                            case 'VrPayCC':
                                return me.snippets.classNameVrPayCC;

                            case 'WirecardCP':
                                return me.snippets.classNameWirecardCP;

                            case 'PayOneCC':
                                return me.snippets.classNamePayOneCC;

                            case 'PayOneELV':
                                return me.snippets.classNamePayOneELV;

                            case 'Klarna':
                                return me.snippets.classNameKlarna;

                            case 'KlarnaSofort':
                                return me.snippets.classNameKlarnaSofort;
								
							case 'KlarnaRest':
								return me.snippets.classNameKlarnaRest;

                            case 'AmazonPayments':
                                return me.snippets.classNameAmazonPayments;

                            case 'Billsafe':
                                return me.snippets.classNameBillsafe;

                            case 'AfterPay':
                                return me.snippets.classNameAfterPay;

                            default:
                                return me.snippets.classNameNone;
                        }
                    },
                    editor: Ext.create('Ext.form.field.ComboBox', {
                        store: new Ext.data.SimpleStore({
                            fields:['id', 'label'],
                            data: [
                                [null, me.snippets.classNameNone],

                                ['PrePayment', me.snippets.classNamePrePayment],
                                ['DebitAdvice', me.snippets.classNamedebitAdvice],
                                ['CashOnDelivery', me.snippets.classNameCashOnDelivery],
                                ['PayPal', me.snippets.classNamePayPal],
                                ['PayPalPlus', me.snippets.classNamePayPalPlus],
                                ['Bill', me.snippets.classNameBill],
                                ['Payolution', me.snippets.classNamePayolution],
                                ['PayolutionELV', me.snippets.classNamePayolutionELV],
                                ['PayolutionInstallment', me.snippets.classNamePayolutionInstallment],
                                ['Sofort', me.snippets.classNameSofort],
                                ['HeidelpayCreditCard', me.snippets.classNameHeidelpayCreditCard],
                                ['HeidelpaySofort', me.snippets.classNameHeidelpaySofort],
                                ['HeidelpayIdeal', me.snippets.classNameHeidelpayIdeal],
                                ['HeidelpayPostFinance', me.snippets.classNameHeidelpayPostFinance],
                                ['Marketplace', me.snippets.classNameMarketplace],
                                ['SelfcollectorCash', me.snippets.classNameSelfcollectorCash],
                                ['SelfcollectorCashEc', me.snippets.classNameSelfcollectorCashEc],
                                ['SelfcollectorCashCreditCard', me.snippets.classNameSelfcollectorCashCreditCard],
                                ['VrPayCC', me.snippets.classNameVrPayCC],
                                ['WirecardCP', me.snippets.classNameWirecardCP],
                                ['Klarna', me.snippets.classNameKlarna],
                                ['KlarnaSofort', me.snippets.classNameKlarnaSofort],
								['KlarnaRest', me.snippets.classNameKlarnaRest],
                                ['PayOneCC', me.snippets.classNamePayOneCC],
                                ['PayOneELV', me.snippets.classNamePayOneELV],
                                ['AmazonPayments', me.snippets.classNameAmazonPayments],
                                ['Billsafe', me.snippets.classNameBillsafe],
                                ['AfterPay', me.snippets.classNameAfterPay]
                            ]
                        }),
                        allowBlank: false,
                        editable: false,
                        mode: 'local',
                        triggerAction: 'all',
                        displayField: 'label',
                        valueField: 'id'
                    })
                }
            },
            rowEditing: true,
            editColumn: false,
            deleteColumn: false,
            addButton: false,
            deleteButton: false
        }
    }
});
