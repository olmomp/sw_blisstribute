Ext.define('Shopware.apps.BlisstributeShipmentMapping.view.list.Shipment', {
    extend: 'Shopware.grid.Panel',
    alias:  'widget.blisstribute-shipment-mapping-listing-grid',
    region: 'center',

    /**
     * Contains all snippets for the controller
     * @object
     */
    snippets: {
        id: '{s name=blisstribute/shipmentId}ID{/s}',
        shipmentName: '{s name=blisstribute/shipmentName}Versandart{/s}',
        className: '{s name=blisstribute/shipment}Blisstribute Versandart{/s}',
        classNameNone: '{s name=blisstribute/combo_shipment_none}Bitte wählen{/s}',
        classNameDhl: '{s name=blisstribute/combo_shipment_dhl}DHL{/s}',
        classNameHermes: '{s name=blisstribute/combo_shipment_her}Hermes{/s}',
        classNameDhlexpress: '{s name=blisstribute/combo_shipment_dhlexpress}DHL Express{/s}',
        classNameDpd: '{s name=blisstribute/combo_shipment_dpd}DPD{/s}',
        classNameDpde12: '{s name=blisstribute/combo_shipment_dpde12}DPD E12{/s}',
        classNameDpde18: '{s name=blisstribute/combo_shipment_dpde18}DPD E18{/s}',
        classNameDpds12: '{s name=blisstribute/combo_shipment_dpds12}DPD S12{/s}',
        classNameFba: '{s name=blisstribute/combo_shipment_fba}FBA{/s}',
        classNameFedex: '{s name=blisstribute/combo_shipment_fedex}FEDEX{/s}',
        classNamePat: '{s name=blisstribute/combo_shipment_pat}Post AT{/s}',
        classNamePatexpress: '{s name=blisstribute/combo_shipment_patexpress}Post AT Express{/s}',
        classNameSelfcollector: '{s name=blisstribute/combo_shipment_selfcollector}Selbstabholer{/s}',
        classNameLettershipment: '{s name=blisstribute/combo_shipment_lettershipment}Briefversand{/s}',
        classNameSchenker: '{s name=blisstribute/combo_shipment_schenker}Schenker{/s}',
        classNameWeiss: '{s name=blisstribute/combo_shipment_weiss}Gebr. Weiss{/s}',
        className7Senders: '{s name=blisstribute/combo_shipment_sevensenders}Seven Senders{/s}'
    },

    configure: function() {
        var me = this;
        var store = new Ext.data.SimpleStore({
            fields:['id', 'label'],
            data: [
                [null, me.snippets.classNameNone],
                ['Dhl', me.snippets.classNameDhl],
                ['Hermes', me.snippets.classNameHermes],
                ['Dhlexpress', me.snippets.classNameDhlexpress],
                ['Dpd', me.snippets.classNameDpd],
                ['Dpde12', me.snippets.classNameDpde12],
                ['Dpde18', me.snippets.classNameDpde18],
                ['Dpds12', me.snippets.classNameDpds12],
                ['Fba', me.snippets.classNameFba],
                ['Fedex', me.snippets.classNameFedex],
                ['Pat', me.snippets.classNamePat],
                ['Patexpress', me.snippets.classNamePatexpress],
                ['Selfcollector', me.snippets.classNameSelfcollector],
                ['Skr', me.snippets.classNameSchenker],
                ['Gww', me.snippets.classNameWeiss],
                ['Lettershipment', me.snippets.classNameLettershipment]
            ]
        });

        return {
            eventAlias: 'blisstribute-shipment-mapping',
            columns: {
                id: {
                    text: me.snippets.id,
                    flex: 1,
                    editor: null,
                    editable: false,
                    sortable: true,
                    dataIndex: 'id'
                },
                shipmentName: {
                    header: me.snippets.shipmentName,
                    flex: 4,
                    sortable: true,
                    editor: null,
                    editable: false,
                    dataIndex: 'shipmentName'
                },
                className: {
                    header: me.snippets.className,
                    flex: 2,
                    sortable: false,
                    dataIndex: 'className',
                    align: 'left',
                    renderer: function(value) {
                        var data = store.getAt(store.find('id', value));
                        if (data) {
                            return data.get('label');
                        }

                        return value;
                    },
                    editor: Ext.create('Ext.form.field.ComboBox', {
                        store: store,
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
