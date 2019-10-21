<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/Components/Blisstribute/Domain/LoggerTrait.php';
require_once __DIR__ . '/Components/Blisstribute/Command/OrderExport.php';
require_once __DIR__ . '/Components/Blisstribute/Command/ArticleExport.php';
require_once __DIR__ . '/Components/Blisstribute/Article/Sync.php';
require_once __DIR__ . '/Components/Blisstribute/Order/Sync.php';
require_once __DIR__ . '/Models/Blisstribute/BlisstributeShipment.php';

use \Shopware\CustomModels\Blisstribute\BlisstributeCoupon;
use \Shopware\CustomModels\Blisstribute\BlisstributeOrder;
use Doctrine\Common\Collections\ArrayCollection;
use Shopware\CustomModels\Blisstribute\BlisstributeShipment;
use Shopware\ExitBBlisstribute\Subscribers\ControllerSubscriber;
use Shopware\ExitBBlisstribute\Subscribers\ModelSubscriber;
use Shopware\ExitBBlisstribute\Subscribers\ServiceSubscriber;

/**
 * exitb blisstribute plugin bootstrap
 *
 * @author    Pixup Media GmbH
 * @package   Shopware\Plugins\Backend\ExitBBlisstribute
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class Shopware_Plugins_Backend_ExitBBlisstribute_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    use Shopware_Components_Blisstribute_Domain_LoggerTrait;

    /**
     * @return string
     *
     * @throws Exception
     */
    public function getVersion()
    {
        $info = json_decode(file_get_contents(__DIR__ . '/plugin.json'), true);
        if ($info) {
            return $info['currentVersion'];
        } else {
            throw new Exception('The plugin has an invalid plugin definition file.');
        }
    }

    /**
     * @return string
     *
     * @throws Exception
     */
    public function getLabel()
    {
        $info = json_decode(file_get_contents(__DIR__ . '/plugin.json'), true);
        if ($info) {
            return $info['label']['de'];
        } else {
            throw new Exception('The plugin has an invalid plugin definition file.');
        }
    }

    /**
     * @return string
     *
     * @throws Exception
     */
    public function getSupplier()
    {
        $info = json_decode(file_get_contents(__DIR__ . '/plugin.json'), true);
        if ($info) {
            return $info['author'];
        } else {
            throw new Exception('The plugin has an invalid plugin definition file.');
        }
    }

    /**
     * @return string
     *
     * @throws Exception
     */
    public function getDescription()
    {
        $info = file_get_contents(__DIR__ . '/info.txt');
        if ($info) {
            return $info;
        } else {
            throw new Exception('The plugin has an invalid plugin description file.');
        }
    }

    /**
     * @return string
     */
    public function getSupport()
    {
        return '';
    }

    /**
     * @return string
     *
     * @throws Exception
     */
    public function getLink()
    {
        $info = json_decode(file_get_contents(__DIR__ . '/plugin.json'), true);
        if ($info) {
            return $info['link'];
        } else {
            throw new Exception('The plugin has an invalid plugin definition file.');
        }
    }

    /**
     * @return array
     */
    public function getInfo()
    {
        return [
            'version' => $this->getVersion(),
            'label' => $this->getLabel(),
            'author' => $this->getSupplier(),
            'supplier' => $this->getSupplier(),
            'description' => $this->getDescription(),
            'support' => $this->getSupport(),
            'link' => $this->getLink(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function enable()
    {
        $this->logInfo('plugin enabled');
        $this->subscribeEvents();

        $result = $this->installDefaultTableValues();
        $this->createAttributeCollection();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function disable()
    {
        $this->logInfo('plugin disabled');
        return $this->deleteDefaultTableValues();
    }

    /**
     * @return array
     */
    public function install()
    {
        // check the current sw version
        if (!$this->assertMinimumVersion('5.2')) {
            return [
                'success' => false,
                'message' => 'Das Plugin benötigt mindestens Shopware 5.2.'
            ];
        }

        // check needed plugins
        if (!$this->assertRequiredPluginsPresent(['Cron'])) {
            return [
                'success' => false,
                'message' => 'Bitte installieren und aktivieren Sie das Shopware Cron-Plugin.'
            ];
        }

        $this->logDebug('register cron jobs');
        $this->registerCronJobs();
        $this->logDebug('subscribe events');
        $this->subscribeEvents();
        $this->logDebug('creating menu items');
        $this->createMenuItems();
        $this->logDebug('install plugin scheme');
        $this->installPluginSchema();
        $this->logDebug('creating config');
        $this->createConfig();
        $this->logDebug('creating config translations');
        $this->createConfigTranslations();

        try {
            $this->createAttributeCollection();
            $this->createOrderStateCollection();
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }

        return ['success' => true, 'invalidateCache' => ['backend', 'proxy', 'config']];
    }

    /**
     * @inheritdoc
     */
    public function update($version)
    {
        $this->createAttributeCollection();

        if (version_compare($version, '0.10.3', '<')) {
            $form = $this->Form();
            $form->setElement(
                'checkbox',
                'blisstribute-article-sync-sync-last-stock',
                [
                    'label' => 'Abverkauf synchronisieren',
                    'description' => 'Wenn aktiviert, wird das Abverkaufs-Flag (LastStock) am Artikel synchronisiert.',
                    'value' => 1
                ]
            );
            $form->setElement(
                'checkbox',
                'blisstribute-article-sync-sync-sale-price',
                [
                    'label' => 'Werbemittelpreise synchronisieren',
                    'description' => 'Wenn aktiviert, werden übertragene Preise von Blisstribute synchronisiert.',
                    'value' => 1
                ]
            );
        }

        if (version_compare($version, '0.11.2', '<')) {
            $form = $this->Form();
            $form->setElement(
                'text',
                'blisstribute-hold-order-address-pattern',
                [
                    'label' => 'Sperrbegriffe für Adresse',
                    'description' => 'Wenn gesetzt, werden Bestellungen deren Adresse auf dieses Pattern zutrifft, mit Kommentar und angehalten übertragen.',
                    'value' => '',
                    'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
                ]
            );
        }

        if (version_compare($version, '0.11.4', '<')) {
            $form = $this->Form();
            $form->setElement(
                'number',
                'blisstribute-hold-order-cart-amount',
                [
                    'label' => 'Warenwert für Bestell-Halt',
                    'description' => 'Wenn gesetzt, werden Bestellungen deren Warenwert den angegebenen Wertes überschreiten oder gleichen, mit Kommentar und angehalten übertragen. Zum deaktivieren "0" angeben.',
                    'value' => 0.00,
                    'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
                ]
            );
        }

        if (version_compare($version, '0.12.7', '<')) {
            $form = $this->Form();
            $form->setElement(
                'select',
                'blisstribute-order-sync-external-customer-number',
                [
                    'label' => 'Externe Kundennummer',
                    'description' => 'Definiert, welches Feld für die externe Kundennummer im VHS verwendet werden soll.',
                    'store' => [
                        [1, 'E-Mail'],
                        [2, 'Kundennummer']
                    ],
                    'value' => 1
                ]
            );
        }

        if (version_compare($version, '0.14.2', '<')) {
            $form = $this->Form();
            $form->setElement(
                'checkbox',
                'blisstribute-show-sync-widget',
                [
                    'label' => 'Nicht synchr. Bestellungen Widget anzeigen',
                    'description' => 'Wenn aktiviert, wird auf der Backend-Startseite ein Widget angezeigt, welches die nicht synchronisierten Bestellungen auflistet.',
                    'value' => 1
                ]
            );
        }

        if (version_compare($version, '0.14.7', '<')) {
            $form = $this->Form();
            $form->setElement(
                'checkbox',
                'blisstribute-transfer-b2b-net',
                [
                    'label' => 'B2B-Bestellungen Netto übertragen',
                    'description' => 'Wenn aktiviert, werden B2B Bestellungen (Bestellzeilen) Netto an Blisstribute übertragen.',
                    'value' => 0
                ]
            );
            $form->setElement(
                'checkbox',
                'blisstribute-article-sync-manufacturer-article-number',
                [
                    'label' => 'Hersteller-Art.Nr als Identifikation synchronisieren',
                    'description' => 'Wenn aktiviert, wird die Hersteller-Artikel-Nr als Produkt-Identifikation synchronisiert. Sollte nicht aktiviert werden, wenn Artikel mit Lieferanten synchronisiert werden.',
                    'value' => 0
                ]
            );
        }

        if (version_compare($version, '0.14.19', '<')) {
            $form = $this->Form();
            $form->setElement(
                'number',
                'blisstribute-discount-difference-watermark',
                [
                    'label' => 'Schwellwert für Hinweis auf Rabattabweichung',
                    'description' => 'Schwellwert für Hinweis auf Rabattabweichung in Euro',
                    'value' => 0.10
                ]
            );
            $form->setElement(
                'text',
                'blisstribute-error-email-receiver',
                [
                    'label' => 'Empfänger Email für Fehlerhinweise',
                    'description' => 'Empfänger Email für Fehlerhinweise'
                ]
            );
            /*
             * Email Template
             * Name: sBLISSORDERREJECTED
             * Parameter: {$orderNumber}
             */
        }

        if (version_compare($version, '0.15.0', '<')) {
            $form = $this->Form();
            $form->setElement(
                'text',
                'blisstribute-alternative-phone-number-order-attribute',
                [
                    'label' => 'Alternative Kunden-Telefonnummer',
                    'description' => 'Order-Attribut (Freitextfeld) für Kunden-Telefonnummer'
                ]
            );
        }

        if (version_compare($version, '0.15.3', '<')) {
            $form = $this->Form();
            $form->setElement(
                'text',
                'blisstribute-article-mapping-base-price',
                array(
                    'label' => 'HAP Verknüpfung',
                    'description' => '',
                    'value' => ''
                )
            );
        }

        if (version_compare($version, '0.15.6', '<')) {
            $form = $this->Form();
            $form->setElement(
                'text',
                'blisstribute-disable-address-splitting',
                [
                    'label' => 'Deaktiviert die automatische Adressanpassung',
                    'description' => 'Wenn aktiviert, werden Adressdaten nicht durch einen Validator verändert. ',
                    'maxLength' => 3,
                    'value' => 0,
                    'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
                ]
            );
        }

        if (version_compare($version, '0.16.1', '<')) {
            $pluginConfig = Shopware()->Container()->get('plugins')->Backend()->ExitBBlisstribute()->Config();

            // Migrate the SOAP Host to REST Host, if it's set.
            $form            = $this->Form();
            $soapHostElement = $form->getElement('blisstribute-soap-host');
            $soapHost        = $pluginConfig->get('blisstribute-soap-host');
            $restHost        = '';

            if (trim($soapHost) != '') {
                $restHost = str_replace('soap', 'rest', $soapHost);
            }

            // Add REST Host.
            $form->setElement(
                'text',
                'blisstribute-rest-host',
                [
                    'label'       => 'REST Host',
                    'description' => 'REST-Hostname für den Verbindungsaufbau zum Blisstribute-System',
                    'maxLength'   => 255,
                    'value'       => $restHost
                ]
            );

            // Change SOAP Host label to "SOAP Host" for clarity.
            $soapHostElement->setLabel('SOAP Host');
        }

        if (version_compare($version, '0.16.3', '<')) {
            // Migrate the SOAP Host to REST Host, if it's set.
            // $pluginConfig->get('blisstribute-article-sync-enabled')
            $form = $this->Form();

            // Add option to disable article sync.
            $form->setElement(
                'checkbox',
                'blisstribute-article-sync-enabled',
                [
                    'label' => 'Artikel synchronisieren',
                    'description' => 'Wenn deaktiviert werden keine Artikel zwischen diesem Shop und Blisstribute synchronisiert.',
                    'value' => 1,
                    'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
                ]
            );

            // Add option to always send vatRate = 0.
            $form->setElement(
                'checkbox',
                'blisstribute-order-include-vatrate',
                [
                    'label' => 'Steuersatz übertragen',
                    'description' => 'Wenn deaktiviert wird der Steuersatz bei Bestellungen nicht übertragen und im Blisstribute ermittelt.',
                    'value' => 1,
                    'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
                ]
            );

            $form->setElement(
                'text',
                'blisstribute-order-lock-mapping',
                array(
                    'label' => 'Bestellsperre Verknüpfung',
                    'description' => '',
                    'value' => '',
                    'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
                )
            );

            $form->setElement(
                'text',
                'blisstribute-order-hold-mapping',
                array(
                    'label' => 'Bestellhalt Verknüpfung',
                    'description' => '',
                    'value' => '',
                    'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
                )
            );

            $form->setElement(
                'text',
                'blisstribute-article-stock-mapping',
                array(
                    'label' => 'Artikelbestand Verknüpfung',
                    'description' => '',
                    'value' => ''
                )
            );

            $this->get('db')->query(
                "INSERT IGNORE INTO s_premium_dispatch_attributes (blisstribute_shipment_code, dispatchID, blisstribute_shipment_is_priority)
                SELECT
                CASE mapping_class_name
                    WHEN 'Dhl' THEN 'DHL'
                    WHEN 'Dhlexpress' THEN 'DHLEXPRESS'
                    WHEN 'Dpd' THEN 'DPD'
                    WHEN 'Dpde12' THEN 'DPDE12'
                    WHEN 'Dpde18' THEN 'DPDE18'
                    WHEN 'Dpds12' THEN 'DPDS12'
                    WHEN 'Dtpg' THEN 'DTPG'
                    WHEN 'Dtpm' THEN 'DTPM'
                    WHEN 'Fba' THEN 'FBA'
                    WHEN 'Fedex' THEN 'FEDEX'
                    WHEN 'Gls' THEN 'GLS'
                    WHEN 'Gww' THEN 'GWW'
                    WHEN 'Hermes' THEN 'HERMES'
                    WHEN 'Lettershipment' THEN 'LSH'
                    WHEN 'Pat' THEN 'PAT'
                    WHEN 'Patexpress' THEN 'PATEXPRESS'
                    WHEN 'Selfcollector' THEN 'SEL'
                    WHEN 'Sevensenders' THEN '7SENDERS'
                    WHEN 'Skr' THEN 'SKR'
                END AS blisstribute_shipment_code, s_premium_dispatch_id, 0 AS blisstribute_shipment_is_priority
                FROM s_plugin_blisstribute_shipment"
            );

            $this->get('db')->query("DROP TABLE IF EXISTS s_plugin_blisstribute_shipment");
        }

        return ['success' => true, 'invalidateCache' => ['backend', 'proxy', 'config']];
    }

    /**
     * @return array
     */
    public function uninstall()
    {
        return ['success' => true, 'invalidateCache' => ['backend', 'proxy', 'config']];
    }

    /**
     * @return bool
     */
    public function secureUninstall()
    {
        return true;
    }

    /**
     * add new states
     *
     * @return void
     */
    private function createOrderStateCollection()
    {
        $sql = "INSERT IGNORE INTO `s_core_states` (`id`, `description`, `position`, `group`, `mail`)
                VALUES  (60, 'Retoure offen', 101, 'state', 0),
                    (61, 'Retoure abgeschlossen', 102, 'state', 0),
                    (62, 'Retoure teilweise abgeschlossen', 103, 'state', 0)";

        $this->get('db')->query($sql);
    }

    /**
     * @return void
     */
    private function createAttributeCollection()
    {
        /** @var Shopware\Bundle\AttributeBundle\Service\CrudService $crud */
        $crud = $this->get('shopware_attribute.crud_service');

        $crud->update('s_articles_attributes', 'blisstribute_supplier_code', 'combobox', [
            'displayInBackend' => true,
            'label' => 'VHS - Lieferantencode',

            'custom' => 1
        ]);

        $crud->update('s_articles_attributes', 'blisstribute_vhs_number', 'string', [
            'displayInBackend' => true,
            'label' => 'VHS - VHS-Artikel-Nummer',
            'custom' => 1
        ]);

        $crud->update('s_articles_attributes', 'blisstribute_supplier_stock', 'integer', [
            'displayInBackend' => true,
            'label' => 'VHS - Lieferantenbestand',
            'custom' => 1
        ]);

        $crud->update('s_articles_attributes', 'blisstribute_customs_tariff_number', 'string', [
            'displayInBackend' => true,
            'label' => 'VHS - Zolltarifnummer',
            'custom' => 1
        ]);

        $crud->update('s_articles_attributes', 'blisstribute_country_of_origin', 'string', [
            'displayInBackend' => true,
            'label' => 'VHS - Herkunftsland (ISO Alpha 2)',
            'custom' => 1
        ]);

        $crud->update('s_articles_attributes', 'blisstribute_article_shipment_code', 'string', [
            'displayInBackend' => true,
            'label' => 'VHS - Statische Versandart',
            'supportText' => 'Wenn angegeben, wird dieser Code bei einer Bestellung für diese Bestellzeile übergeben.',
            'custom' => 1
        ]);

        $crud->update('s_articles_attributes', 'blisstribute_article_advertising_medium_code', 'string', [
            'displayInBackend' => true,
            'label' => 'VHS - Statischer Werbemittelcode',
            'custom' => 1,
            'supportText' => 'Wenn angegeben, wird dieser Code bei einer Bestellung für diese Bestellzeile übergeben.',
        ]);

        $crud->update('s_categories_attributes', 'blisstribute_category_advertising_medium_code', 'string', [
            'displayInBackend' => true,
            'label' => 'VHS - Statischer Werbemittelcode',
            'custom' => 1,
            'supportText' => 'Wenn angegeben, wird dieser Code bei einer Bestellung für Bestellzeilen aus dieser Kategorie oder einer Subkategorie übergeben.',
        ]);

        $crud->update('s_order_details_attributes', 'blisstribute_quantity_canceled', 'integer');
        $crud->update('s_order_details_attributes', 'blisstribute_quantity_returned', 'integer');
        $crud->update('s_order_details_attributes', 'blisstribute_quantity_shipped', 'integer');
        $crud->update('s_order_details_attributes', 'blisstribute_date_changed', 'date');

        $crud->update('s_order_basket_attributes', 'blisstribute_swag_promotion_is_free_good', 'string');
        $crud->update('s_order_basket_attributes', 'blisstribute_swag_is_free_good_by_promotion_id', 'string');

        $crud->update('s_premium_dispatch_attributes', 'blisstribute_shipment_code', 'string', [
            'custom'           => 1,
            'displayInBackend' => true,
            'label'            => 'VHS - Shipment Code',
            'supportText'      => 'Der Shipment Code für diese Bestellung.',
        ]);
        $crud->update('s_premium_dispatch_attributes', 'blisstribute_shipment_is_priority', 'boolean', [
            'custom' => 1,
            'displayInBackend' => true,
            'label' => 'VHS - Priorisierter Versand'
        ]);

        $this->get('db')->query(
            "INSERT IGNORE INTO `s_core_engine_elements` (`groupID`, `type`, `label`, `required`, `position`, " .
            "`name`, `variantable`, `translatable`) VALUES  (7, 'text', 'VHS Nummer', 0, 101, " .
            "'blisstributeVhsNumber', 0, 0), (7, 'number', 'Bestand Lieferant', 0, 102, " .
            "'blisstributeSupplierStock', 0, 0)"
        );

        $metaDataCache = Shopware()->Models()->getConfiguration()->getMetadataCacheImpl();
        $metaDataCache->deleteAll();
        Shopware()->Models()->generateAttributeModels(['s_articles_attributes', 's_categories_attributes', 's_order_details_attributes', 's_order_basket_attributes', 's_premium_dispatch_attributes']);
    }

    /**
     * add attribute to article
     *
     * @param Enlight_Event_EventArgs $args
     */
    public function postDispatchBackendArticle(Enlight_Event_EventArgs $args)
    {
        $view = $args->getSubject()->View();
        $view->addTemplateDir($this->Path() . 'Views/');

        if ($args->getRequest()->getActionName() === 'load') {
            $view->extendsTemplate('backend/attributes_article/model/attribute.js');
        }
    }

    /**
     * add event listener for blisstribute module
     *
     * @return void
     */
    private function subscribeEvents()
    {
        $this->subscribeEvent('Shopware_Console_Add_Command', 'onAddConsoleCommand');
        $this->subscribeEvent('Enlight_Controller_Front_StartDispatch', 'startDispatch');
        $this->subscribeEvent('Shopware_CronJob_BlisstributeOrderSyncCron', 'onRunBlisstributeOrderSyncCron');
        $this->subscribeEvent('Shopware_CronJob_BlisstributeArticleSyncCron', 'onRunBlisstributeArticleSyncCron');
        $this->subscribeEvent('Shopware_CronJob_BlisstributeEasyCouponMappingCron', 'onRunBlisstributeEasyCouponMappingCron');
        $this->subscribeEvent('Shopware_CronJob_BlisstributeOrderMappingCron', 'onRunBlisstributeOrderMappingCron');
        $this->subscribeEvent('Shopware_CronJob_BlisstributeArticleMappingCron', 'onRunBlisstributeArticleMappingCron');
    }

    /**
     * Blisstribute Order Sync CronJob
     *
     * @param \Shopware_Components_Cron_CronJob $job
     */
    public function onRunBlisstributeOrderSyncCron(\Shopware_Components_Cron_CronJob $job)
    {
        if(is_null($job)) return;

        try {
            $controller = new \Shopware_Components_Blisstribute_Order_Sync(
                Shopware()->Container()->get('plugins')->Backend()->ExitBBlisstribute()->Config()
            );

            $controller->processBatchOrderSync();
        } catch (\Exception $ex) {
            echo "exception while syncing orders " . $ex->getMessage();
            return;
        }
    }

    /**
     * Blisstribute Article Sync CronJob
     *
     * @param \Shopware_Components_Cron_CronJob $job
     */
    public function onRunBlisstributeArticleSyncCron(\Shopware_Components_Cron_CronJob $job)
    {
        if(is_null($job)) return;

        $pluginConfig = Shopware()->Container()->get('plugins')->Backend()->ExitBBlisstribute()->Config();

        // If the user disabled article synchronization, stop here.
        if (!$pluginConfig->get('blisstribute-article-sync-enabled')) {
            echo date('r') . ' - BLISSTRIBUTE article sync is disabled' . PHP_EOL;
            return;
        }

        try {
            $controller = new \Shopware_Components_Blisstribute_Article_Sync($pluginConfig);

            $controller->processBatchArticleSync();
        } catch (\Exception $ex) {
            echo "exception while syncing articles " . $ex->getMessage();
            return;
        }
    }

    /**
     * EasyCoupon Wertgutscheine
     *
     * @param \Shopware_Components_Cron_CronJob $job
     */
    public function onRunBlisstributeEasyCouponMappingCron(\Shopware_Components_Cron_CronJob $job)
    {
        if(is_null($job)) return;

        try {
            // check if plugin SwagPromotion is installed
            $plugin = Shopware()->Models()->getRepository('Shopware\Models\Plugin\Plugin')->findOneBy([
                'name' => 'NetiEasyCoupon',
                'active' => true
            ]);

            if (!$plugin) {
                return;
            }


            $modelManager = Shopware()->Container()->get('models');

            $sqlUnmappedVouchers = "SELECT voucher.id AS id FROM s_emarketing_vouchers voucher 
                                    LEFT JOIN s_plugin_blisstribute_coupon coupon ON coupon.s_voucher_id = voucher.id
                                    LEFT JOIN s_emarketing_vouchers_attributes attributes ON attributes.voucherID = voucher.id
                                    WHERE coupon.id IS NULL AND attributes.neti_easy_coupon = 1";

            $unmappedVouchers = Shopware()->Container()->get('db')->fetchAll($sqlUnmappedVouchers);

            $idArray = [];
            foreach ($unmappedVouchers as $voucher) {
                $idArray[] = $voucher['id'];
            }

            $vouchers = $modelManager->getRepository('\Shopware\Models\Voucher\Voucher')->findById($idArray);

            /** @var \Shopware\Models\Voucher\Voucher $voucher */
            foreach ($vouchers as $voucher) {
                $blisstributeCoupon = new BlisstributeCoupon();
                $blisstributeCoupon->setVoucher($voucher)->setIsMoneyVoucher(true);

                $modelManager->persist($blisstributeCoupon);
            }

            $modelManager->flush();
        } catch (\Exception $ex) {
            echo "exception while syncing easy coupon " . $ex->getMessage();
            return;
        }
    }

    /**
     * Import all orders that might have been added using pure sql
     *
     * @param \Shopware_Components_Cron_CronJob $job
     */
    public function onRunBlisstributeOrderMappingCron(\Shopware_Components_Cron_CronJob $job)
    {
        if(is_null($job)) return;

        try {
            $this->logDebug('onRunBlisstributeOrderMappingCron::start');
            $this->logDebug('onRunBlisstributeOrderMappingCron::cleaning obsolete referenced orders');
            $sql = "DELETE FROM s_plugin_blisstribute_orders WHERE s_order_id NOT IN (SELECT id FROM s_order)";
            $this->get('db')->query($sql);
            $this->logDebug('onRunBlisstributeOrderMappingCron::cleaning obsolete referenced orders done');

            $this->logDebug('onRunBlisstributeOrderMappingCron::creating new order references');
            $sql = "SELECT id FROM s_order WHERE id NOT IN (SELECT s_order_id FROM s_plugin_blisstribute_orders) AND ordernumber != 0";
            $modelManager = Shopware()->Container()->get('models');
            $orders = Shopware()->Container()->get('db')->fetchAll($sql);
            $date = new \DateTime();

            foreach ($orders as $order) {
                $blisstributeOrder = new BlisstributeOrder();
                $blisstributeOrder->setTries(0);
                $blisstributeOrder->setOrder($modelManager->getRepository('\Shopware\Models\Order\Order')->find($order['id']));
                $blisstributeOrder->setCreatedAt($date);
                $blisstributeOrder->setModifiedAt($date);
                $blisstributeOrder->setStatus(1);
                $blisstributeOrder->setLastCronAt($date);

                $modelManager->persist($blisstributeOrder);
            }

            $modelManager->flush();

            $this->logDebug('onRunBlisstributeOrderMappingCron::done');
        } catch (\Exception $ex) {
            echo "exception while syncing orders " . $ex->getMessage();
            return;
        }
    }
    /**
     * Import all orders that might have been added using pure sql
     *
     * @param \Shopware_Components_Cron_CronJob $job
     */
    public function onRunBlisstributeArticleMappingCron(\Shopware_Components_Cron_CronJob $job)
    {
        if(is_null($job)) return;

        try {
            $this->logDebug('onRunBlisstributeArticleMappingCron::start');
            $this->logDebug('onRunBlisstributeArticleMappingCron::cleaning obsolete referenced articles');
            $sql = "DELETE FROM s_plugin_blisstribute_articles WHERE s_article_id NOT IN (SELECT id FROM s_articles)";
            $this->get('db')->query($sql);
            $this->logDebug('onRunBlisstributeArticleMappingCron::cleaning obsolete referenced articles done');

            $this->logDebug('onRunBlisstributeArticleMappingCron::create new article references');
            $blisstributeArticleMappingSql = "INSERT IGNORE INTO s_plugin_blisstribute_articles (created_at, modified_at, last_cron_at, "
                . "s_article_id, trigger_deleted, trigger_sync, tries, comment) "
                . "SELECT CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, a.id, 0, 1, 0, NULL FROM s_articles AS a where a.id not in (select distinct s_article_id from s_plugin_blisstribute_articles)";

            $this->get('db')->query($blisstributeArticleMappingSql);

            $this->logDebug('onRunBlisstributeArticleMappingCron::done');
            return true;
        } catch (\Exception $ex) {
            echo "exception while mapping articles " . $ex->getMessage();
            return false;
        }
    }


    /**
     * add blisstribute cli commands
     *
     * @param Enlight_Event_EventArgs $args
     * @return ArrayCollection
     */
    public function onAddConsoleCommand(Enlight_Event_EventArgs $args)
    {
        $this->registerCustomModels();
        $this->registerNamespaces();

        if (Shopware()->Models()->getRepository('Shopware\Models\Plugin\Plugin')->findOneBy([
            'name' => 'SwagPromotion',
            'active' => true
        ])) {
            $this->get('loader')->registerNamespace('Shopware\CustomModels', Shopware()->DocPath() . 'engine/Shopware/Plugins/Community/Frontend/SwagPromotion/Models/');
            $this->get('loader')->registerNamespace('Shopware\SwagPromotion', Shopware()->DocPath() . 'engine/Shopware/Plugins/Community/Frontend/SwagPromotion/');
            $this->get('loader')->registerNamespace('Shopware\Components', Shopware()->DocPath() . 'engine/Shopware/Plugins/Community/Frontend/SwagPromotion/Components/');
        }

        return new ArrayCollection(array(
            new Shopware_Components_Blisstribute_Command_OrderExport(),
            new Shopware_Components_Blisstribute_Command_ArticleExport()
        ));
    }

    /**
     * Start Dispatch Method
     *
     * Register Subscribers
     */
    public function startDispatch()
    {
        $this->registerTemplateDir();
        $this->registerCustomModels();
        $this->registerNamespaces();
        $this->registerSnippets();

        $subscribers = [
            new ControllerSubscriber(),
            new ModelSubscriber(),
            new ServiceSubscriber()
        ];

        foreach ($subscribers as $subscriber) {
            $this->get('events')->addSubscriber($subscriber);
        }
    }

    /**
     * Register all CronJobs
     */
    protected function registerCronJobs()
    {
        try {
            // order sync cron
            $this->createCronJob(
                'Blisstribute Order Sync',
                'Shopware_CronJob_BlisstributeOrderSyncCron',
                900, // 15 min
                true
            );
        } catch (Exception $e) {
            // do nothing
        }

        try {
            // article sync cron
            $this->createCronJob(
                'Blisstribute Article Sync',
                'Shopware_CronJob_BlisstributeArticleSyncCron',
                900, // 15 min
                true
            );
        } catch (Exception $e) {
            // do nothing
        }

        try {
            // easyCoupon Wertgutscheine
            $this->createCronJob(
                'Blisstribute EasyCoupon Mapping',
                'Shopware_CronJob_BlisstributeEasyCouponMappingCron',
                120, // 2 minutes
                true
            );
        } catch (Exception $e) {
            // do nothing
        }

        // import all orders that might have been added using pure sql
        try {
            $this->createCronJob(
                'Blisstribute Order Mapping',
                'Shopware_CronJob_BlisstributeOrderMappingCron',
                120, // 2 minutes
                true
            );
        } catch (Exception $e) {
            // do nothing
        }

        // import all orders that might have been added using pure sql
        try {
            $this->createCronJob(
                'Blisstribute Article Mapping',
                'Shopware_CronJob_BlisstributeArticleMappingCron',
                120, // 2 min
                true
            );
        } catch (Exception $e) {
            // do nothing
        }
    }

    /**
     * register template directory
     *
     * @return void
     */
    protected function registerTemplateDir()
    {
        $this->get('template')->addTemplateDir($this->Path() . '/Views/', 'blisstribute');
    }

    /**
     * Register all necessary namespaces
     */
    protected function registerNamespaces()
    {
        $this->get('loader')->registerNamespace('Shopware\ExitBBlisstribute', $this->Path());
        $this->get('loader')->registerNamespace('Shopware\Components\Api', $this->Path(). '/Components/Api/');
    }

    /**
     * Register all snippets
     */
    protected function registerSnippets()
    {
        $this->get('snippets')->addConfigDir($this->Path() . '/Snippets/');
    }

    /**
     * Creates database tables
     *
     * @return void
     *
     * @throws \Doctrine\ORM\Tools\ToolsException
     */
    protected function installPluginSchema()
    {
        $this->registerCustomModels();
        foreach ($this->getBlisstributeClassMetadataCollection() as $currentClassMetadata) {
            try {
                $this->handleTableInstall($currentClassMetadata);
            } catch (Exception $e) {}
        }
    }

    /**
     * @return \Doctrine\ORM\Mapping\ClassMetadata[]
     */
    protected function getBlisstributeClassMetadataCollection()
    {
        $modelManager = $this->get('models');

        /** @var \Doctrine\ORM\Mapping\ClassMetadata[] $classMetadataCollection */
        $classMetadataCollection = [
            $modelManager->getClassMetadata('Shopware\CustomModels\Blisstribute\BlisstributeArticle'),
            $modelManager->getClassMetadata('Shopware\CustomModels\Blisstribute\BlisstributeArticleType'),
            $modelManager->getClassMetadata('Shopware\CustomModels\Blisstribute\TaskLock'),
            $modelManager->getClassMetadata('Shopware\CustomModels\Blisstribute\BlisstributeOrder'),
            $modelManager->getClassMetadata('Shopware\CustomModels\Blisstribute\BlisstributeShipment'),
            $modelManager->getClassMetadata('Shopware\CustomModels\Blisstribute\BlisstributePayment'),
            $modelManager->getClassMetadata('Shopware\CustomModels\Blisstribute\BlisstributeShippingRequest'),
            $modelManager->getClassMetadata('Shopware\CustomModels\Blisstribute\BlisstributeShippingRequestItems'),
            $modelManager->getClassMetadata('Shopware\CustomModels\Blisstribute\BlisstributeShop'),
            $modelManager->getClassMetadata('Shopware\CustomModels\Blisstribute\BlisstributeCoupon'),
        ];

        return $classMetadataCollection;
    }

    /**
     * create or update table structure for metadata
     *
     * @param \Doctrine\ORM\Mapping\ClassMetadata $classMetadata
     *
     * @return bool
     *
     * @throws \Exception
     */
    protected function handleTableInstall(\Doctrine\ORM\Mapping\ClassMetadata $classMetadata)
    {
        if ($this->pluginTableExists($classMetadata)) {
            return $this->updateTableStructure($classMetadata);
        } else {
            return $this->createTableStructure($classMetadata);
        }
    }

    /**
     * @param \Doctrine\ORM\Mapping\ClassMetadata $classMetadata
     *
     * @return bool
     * @throws \Doctrine\ORM\ORMException
     */
    protected function pluginTableExists(\Doctrine\ORM\Mapping\ClassMetadata $classMetadata)
    {
        $schemaManager = $this->get('models')->getConnection()->getSchemaManager();
        if (!$schemaManager->tablesExist([$classMetadata->getTableName()])) {
            return false;
        }

        return true;
    }

    /**
     * update table structure
     *
     * @param \Doctrine\ORM\Mapping\ClassMetadata $classMetadata
     *
     * @return bool
     *
     * @throws \Exception
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\DBAL\Schema\SchemaException
     * @throws \Doctrine\ORM\ORMException
     */
    protected function updateTableStructure(\Doctrine\ORM\Mapping\ClassMetadata $classMetadata)
    {
        $modelManager = $this->get('models');
        $schemaManager = $modelManager->getConnection()->getSchemaManager();
        $currentTable = $schemaManager->listTableDetails($classMetadata->getTableName());

        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($modelManager);
        $newSchema = $schemaTool->getSchemaFromMetadata([$classMetadata]);
        $newTable = $newSchema->getTable($classMetadata->getTableName());

        $comparator = new \Doctrine\DBAL\Schema\Comparator();
        $tableDiff = $comparator->diffTable($currentTable, $newTable);
        if (!$tableDiff) {
            return true;
        }

        $databasePlatform = $schemaManager->getDatabasePlatform();
        $tableDiffSqlCollection = $databasePlatform->getAlterTableSQL($tableDiff);

        $databaseConnection = $this->get('db');

        try {
            $databaseConnection->beginTransaction();

            foreach ($tableDiffSqlCollection as $currentTableDiff) {
                $databaseConnection->exec($currentTableDiff);
            }

            $databaseConnection->commit();
        } catch (Exception $ex) {
            $databaseConnection->rollBack();
            throw new Exception('Failure while update database structure');
        }

        return true;
    }

    /**
     * install plugin table
     *
     * @param \Doctrine\ORM\Mapping\ClassMetadata $classMetadata
     *
     * @return bool
     *
     * @throws \Exception
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\DBAL\Schema\SchemaException
     * @throws \Doctrine\ORM\ORMException
     */
    protected function createTableStructure(\Doctrine\ORM\Mapping\ClassMetadata $classMetadata)
    {
        $modelManager = $this->get('models');
        $schemaManager = $modelManager->getConnection()->getSchemaManager();

        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($modelManager);
        $newSchema = $schemaTool->getSchemaFromMetadata([$classMetadata]);
        $newTable = $newSchema->getTable($classMetadata->getTableName());

        $databasePlatform = $schemaManager->getDatabasePlatform();
        $tableCreateSqlCollection = $databasePlatform->getCreateTableSQL($newTable);
        if (empty($tableCreateSqlCollection)) {
            throw new Exception('Failure in create database structure');
        }

        $databaseConnection = $this->get('db');

        try {
            $databaseConnection->beginTransaction();

            foreach ($tableCreateSqlCollection as $currentTableCreateSql) {
                $databaseConnection->exec($currentTableCreateSql);
            }

            $databaseConnection->commit();
        } catch (Exception $ex) {
            $databaseConnection->rollBack();
            throw new Exception('Failure while install database structure');
        }

        return true;
    }

    /**
     * install default table values
     *
     * @return bool
     */
    protected function installDefaultTableValues()
    {
        $this->logInfo('install default table values');

        try {
            $defaultTableData = [
                "INSERT IGNORE INTO s_plugin_blisstribute_articles (created_at, modified_at, last_cron_at, "
                . "s_article_id, trigger_deleted, trigger_sync, tries, comment) SELECT CURRENT_TIMESTAMP, "
                . "CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, a.id, 0, 1, 0, NULL FROM s_articles AS a",
                "INSERT IGNORE INTO s_plugin_blisstribute_article_type (created_at, modified_at, s_filter_id, "
                . "article_type) SELECT CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, id, 4 FROM s_filter",
                "INSERT IGNORE INTO s_plugin_blisstribute_payment (mapping_class_name, flag_payed, "
                . "s_core_paymentmeans_id) SELECT NULL, 0, cp.id FROM s_core_paymentmeans AS cp",
                "INSERT IGNORE INTO s_plugin_blisstribute_coupon (s_voucher_id, flag_money_voucher) "
                . "SELECT v.id, 0 FROM s_emarketing_vouchers AS v",
                "DELETE FROM s_plugin_blisstribute_payment WHERE s_core_paymentmeans_id NOT IN (SELECT id FROM s_core_paymentmeans)",
            ];

            foreach ($defaultTableData as $currentDataSet) {
                $this->get('db')->query($currentDataSet);
            }

            return true;
        } catch (Exception $ex) {
            $this->logInfo('install default table values failed! ' . $ex->getMessage());
        }

        return false;
    }

    /**
     * install default table values
     *
     * @return bool
     */
    protected function deleteDefaultTableValues()
    {
        $this->logInfo('delete default table values');

        try {
            $defaultTableData = [
                'Shopware\CustomModels\Blisstribute\BlisstributeArticle' => "TRUNCATE TABLE s_plugin_blisstribute_articles",
                'Locks' => "TRUNCATE TABLE s_plugin_blisstribute_task_lock",
            ];

            foreach ($defaultTableData as $currentDataSet) {
                $this->get('db')->query($currentDataSet);
            }

            return true;
        } catch (Exception $ex) {
            $this->logInfo('delete default table values failed! ' . $ex->getMessage());
        }

        return false;
    }

    /**
     * creates the plugin configuration
     *
     * @return void
     */
    private function createConfig()
    {
        $form = $this->Form();

        $form->setElement(
            'select',
            'blisstribute-soap-protocol',
            [
                'label' => 'Protokoll',
                'description' => 'SOAP-Protokoll für den Verbindungsaufbau zum Blisstribute-System',
                'store' => [
                    [1, 'http'],
                    [2, 'https']
                ],
                'value' => 1
            ]
        );
        $form->setElement(
            'text',
            'blisstribute-soap-host',
            [
                'label' => 'Host', // SOAP
                'description' => 'SOAP-Hostname für den Verbindungsaufbau zum Blisstribute-System',
                'maxLength' => 255,
                'value' => ''
            ]
        );
        $form->setElement(
            'text',
            'blisstribute-rest-host',
            [
                'label' => 'REST Host',
                'description' => 'REST-Hostname für den Verbindungsaufbau zum Blisstribute-System',
                'maxLength' => 255,
                'value' => ''
            ]
        );
        $form->setElement(
            'number',
            'blisstribute-soap-port',
            [
                'label' => 'Port',
                'description' => 'SOAP-Port für den Verbindungsaufbau zum Blisstribute-System',
                'maxLength' => 4,
                'value' => 80
            ]
        );
        $form->setElement(
            'text',
            'blisstribute-soap-client',
            [
                'label' => 'SOAP-Client',
                'description' => 'SOAP-Klientenkürzel für Ihren Blisstribute-Mandanten',
                'maxLength' => 3,
                'value' => ''
            ]
        );
        $form->setElement(
            'text',
            'blisstribute-soap-username',
            [
                'label' => 'SOAP-Benutzername',
                'description' => 'SOAP-Benutzername für Ihren Blisstribute-Mandanten',
                'maxLength' => 255,
                'value' => ''
            ]
        );
        $form->setElement(
            'text',
            'blisstribute-soap-password',
            [
                'label' => 'SOAP-Passwort',
                'description' => 'SOAP-Passwort für Ihren Blisstribute-Mandanten',
                'maxLength' => 255,
                'value' => ''
            ]
        );
        $form->setElement(
            'text',
            'blisstribute-http-login',
            [
                'label' => 'HTTP-Benutzername',
                'description' => 'HTTP-Benutzername für eine eventuelle .htaccess Authentifizierung',
                'maxLength' => 255
            ]
        );
        $form->setElement(
            'text',
            'blisstribute-http-password',
            [
                'label' => 'HTTP-Passwort',
                'description' => 'HTTP-Passwort für eine eventuelle .htaccess Authentifizierung',
                'maxLength' => 255
            ]
        );

        $form->setElement(
            'checkbox',
            'blisstribute-auto-sync-order',
            [
                'label' => 'Bestellung bei Anlage übermitteln',
                'description' => 'Wenn aktiviert, wird die Bestellung sofort nach Abschluss des Checkout-Prozesses zum Blisstribute System übermittelt. Wenn deaktiviert, müssen die Bestellungen manuell, oder über den Cron übermittelt werden.',
                'maxLength' => 255,
                'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
            ]
        );

        $form->setElement(
            'checkbox',
            'blisstribute-auto-hold-order',
            [
                'label' => 'Bestellung in Blisstribute anhalten',
                'description' => 'Wenn aktiviert, wird die Bestellung sofort nach der Übertragung zu Blisstribute angehalten',
                'maxLength' => 255,
                'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
            ]
        );

        $form->setElement(
            'checkbox',
            'blisstribute-auto-lock-order',
            [
                'label' => 'Bestellung in Blisstribute sperren',
                'description' => 'Wenn aktiviert, wird die Bestellung sofort nach der Übertragung zu Blisstribute gesperrt',
                'maxLength' => 255,
                'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
            ]
        );

        $form->setElement(
            'text',
            'blisstribute-default-advertising-medium',
            [
                'label' => 'Standard Werbemittel',
                'description' => 'Das Standard-Werbemittel für die Bestellanlage, falls kein Werbemittel gefunden werden kann',
                'maxLength' => 3,
                'value' => '',
                'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
            ]
        );
        $form->setElement(
            'text',
            'blisstribute-disable-address-splitting',
            [
                'label' => 'Deaktiviert die automatische Adressanpassung',
                'description' => 'Wenn aktiviert, werden Adressdaten nicht durch einen Validator verändert. ',
                'maxLength' => 3,
                'value' => 0,
                'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
            ]
        );
        $form->setElement(
            'checkbox',
            'blisstribute-google-address-validation',
            [
                'label' => 'Google Maps Address Verifikation',
                'description' => 'Wenn aktiviert, werden Liefer- und Rechnungsadresse bei Bestellübertragung mit der Google Maps API abgeglichen, um eventuelle Adressefehler zu korrigieren.',
                'value' => 0
            ]
        );
        $form->setElement(
            'text',
            'blisstribute-google-maps-key',
            [
                'label' => 'Google Maps Key',
                'description' => 'API-KEY für den Zugang zur Google Maps API.',
                'value' => ''
            ]
        );
        $form->setElement(
            'checkbox',
            'blisstribute-transfer-orders',
            [
                'label' => 'Bestellungen ohne Adressvalidierung übertragen',
                'description' => 'Wenn aktiviert, werden ausschließlich Bestellungen ins Blisstribute-System übertragen, deren Adressen erfolgreich verifiziert werden konnten.',
                'value' => 1
            ]
        );
        $form->setElement(
            'text',
            'blisstribute-hold-order-address-pattern',
            [
                'label' => 'Sperrbegriffe für Adresse',
                'description' => 'Wenn gesetzt, werden Bestellungen deren Adresse auf dieses Pattern zutrifft, mit Kommentar und angehalten übertragen.',
                'value' => '',
                'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
            ]
        );
        $form->setElement(
            'number',
            'blisstribute-hold-order-cart-amount',
            [
                'label' => 'Warenwert für Bestell-Halt',
                'description' => 'Wenn gesetzt, werden Bestellungen deren Warenwert den angegebenen Wertes überschreiten oder gleichen, mit Kommentar und angehalten übertragen. Zum deaktivieren "0" angeben.',
                'value' => 0.00,
                'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
            ]
        );
        $form->setElement(
            'checkbox',
            'blisstribute-transfer-shop-article-prices',
            [
                'label' => 'Artikelpreise von jedem Shop übertragen',
                'description' => 'Wenn aktiviert, werden die Preise eines Artikels anhand der beim Shop hinterlegten Kundengruppe und Währung zusätzlich ins Blisstribute-System übertragen.',
                'value' => 0
            ]
        );
        $form->setElement(
            'text',
            'blisstribute-article-mapping-classification3',
            array(
                'label' => 'Klassifikation 3 Verknüpfung',
                'description' => '',
                'value' => ''
            )
        );
        $form->setElement(
            'text',
            'blisstribute-article-mapping-classification4',
            array(
                'label' => 'Klassifikation 4 Verknüpfung',
                'description' => '',
                'value' => ''
            )
        );
        $form->setElement(
            'text',
            'blisstribute-article-mapping-base-price',
            array(
                'label' => 'HAP Verknüpfung',
                'description' => '',
                'value' => ''
            )
        );
        $form->setElement(
            'checkbox',
            'blisstribute-article-sync-manufacturer-article-number',
            [
                'label' => 'Hersteller-Art.Nr als Identifikation synchronisieren',
                'description' => 'Wenn aktiviert, wird die Hersteller-Artikel-Nr als Produkt-Identifikation synchronisiert. Sollte nicht aktiviert werden, wenn Artikel mit Lieferanten synchronisiert werden.',
                'value' => 0
            ]
        );

        $form->setElement(
            'checkbox',
            'blisstribute-article-sync-sync-active-flag',
            [
                'label' => 'Aktivitätsstatus synchronisieren',
                'description' => 'Wenn aktiviert, werden die Artikel de- bzw. aktiviert.',
                'value' => 1
            ]
        );
        $form->setElement(
            'checkbox',
            'blisstribute-article-sync-sync-ean',
            [
                'label' => 'EAN synchronisieren',
                'description' => 'Wenn aktiviert, wird der EAN am Artikel aktualisiert.',
                'value' => 1
            ]
        );
        $form->setElement(
            'checkbox',
            'blisstribute-article-sync-sync-release-date',
            [
                'label' => 'Veröffentlichungsdatum synchronisieren',
                'description' => 'Wenn aktiviert, wird das Veröffentlichungsdatum am Artikel synchronisiert.',
                'value' => 1
            ]
        );
        $form->setElement(
            'checkbox',
            'blisstribute-article-sync-sync-last-stock',
            [
                'label' => 'Abverkauf synchronisieren',
                'description' => 'Wenn aktiviert, wird das Abverkaufs-Flag (LastStock) am Artikel synchronisiert.',
                'value' => 1
            ]
        );
        $form->setElement(
            'checkbox',
            'blisstribute-show-sync-widget',
            [
                'label' => 'Nicht synchr. Bestellungen Widget anzeigen',
                'description' => 'Wenn aktiviert, wird auf der Backend-Startseite ein Widget angezeigt, welches die nicht synchronisierten Bestellungen auflistet.',
                'value' => 1
            ]
        );
        $form->setElement(
            'select',
            'blisstribute-order-sync-external-customer-number',
            [
                'label' => 'Externe Kundennummer',
                'description' => 'Definiert, welches Feld für die externe Kundennummer im VHS verwendet werden soll.',
                'store' => [
                    [1, 'E-Mail'],
                    [2, 'Kundennummer']
                ],
                'value' => 1
            ]
        );
        $form->setElement(
            'checkbox',
            'blisstribute-transfer-b2b-net',
            [
                'label' => 'B2B-Bestellungen Netto übertragen',
                'description' => 'Wenn aktiviert, werden B2B Bestellungen (Bestellzeilen) Netto an Blisstribute übertragen.',
                'value' => 0
            ]
        );
        $form->setElement(
            'number',
            'blisstribute-discount-difference-watermark',
            [
                'label' => 'Schwellwert für Hinweis auf Rabattabweichung',
                'description' => 'Schwellwert für Hinweis auf Rabattabweichung in Euro',
                'value' => 0.10
            ]
        );
        $form->setElement(
            'text',
            'blisstribute-error-email-receiver',
            [
                'label' => 'Empfänger Email für Fehlerhinweise',
                'description' => 'Empfänger Email für Fehlerhinweise'
            ]
        );
        $form->setElement(
            'text',
            'blisstribute-alternative-phone-number-order-attribute',
            [
                'label' => 'Alternative Kunden-Telefonnummer',
                'description' => 'Order-Attribut (Freitextfeld) für Kunden-Telefonnummer'
            ]
        );

        // Add option to disable article sync.
        $form->setElement(
            'checkbox',
            'blisstribute-article-sync-enabled',
            [
                'label' => 'Artikel synchronisieren',
                'description' => 'Wenn deaktiviert werden keine Artikel zwischen diesem Shop und Blisstribute synchronisiert.',
                'value' => 1,
                'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
            ]
        );

        // Add option to always send vatRate = 0.
        $form->setElement(
            'checkbox',
            'blisstribute-order-include-vatrate',
            [
                'label' => 'Steuersatz übertragen',
                'description' => 'Wenn deaktiviert wird der Steuersatz bei Bestellungen nicht übertragen und im Blisstribute ermittelt.',
                'value' => 1,
                'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
            ]
        );

        $form->setElement(
            'text',
            'blisstribute-order-lock-mapping',
            array(
                'label' => 'Bestellsperre Verknüpfung',
                'description' => '',
                'value' => '',
                'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
            )
        );

        $form->setElement(
            'text',
            'blisstribute-order-hold-mapping',
            array(
                'label' => 'Bestellhalt Verknüpfung',
                'description' => '',
                'value' => '',
                'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
            )
        );

        $form->setElement(
            'text',
            'blisstribute-article-stock-mapping',
            array(
                'label' => 'Artikelbestand Verknüpfung',
                'description' => '',
                'value' => '',
            )
        );
    }

    /**
     * creates the plugin configuration translations
     *
     * @return void
     */
    private function createConfigTranslations()
    {
        $form = $this->Form();

        $shopRepository = Shopware()->Models()->getRepository('\Shopware\Models\Shop\Locale');

        $translations = [
            'en_GB' => [
                'blisstribute-soap-protocol' => 'protocol',
                'blisstribute-soap-host' => 'host',
                'blisstribute-soap-port' => 'port',
                'blisstribute-soap-client' => 'soap-client',
                'blisstribute-soap-username' => 'soap-username',
                'blisstribute-soap-password' => 'soap-password',
                'blisstribute-http-login' => 'http-username',
                'blisstribute-http-password' => 'http-password',
                'blisstribute-auto-sync-order' => 'auto sync order',
                'blisstribute-auto-hold-order' => 'auto hold order',
                'blisstribute-auto-lock-order' => 'auto lock order',
                'blisstribute-default-advertising-medium' => 'default advertising medium',
                'blisstribute-google-address-validation' => 'use google address validation',
                'blisstribute-google-maps-key' => 'google maps key',
                'blisstribute-transfer-orders' => 'transfer orders without verification',
                'blisstribute-transfer-shop-article-prices' => 'transfer article prices of each shop',
                'blisstribute-article-mapping-classification3' => 'Classification 3 mapping',
                'blisstribute-article-mapping-classification4' => 'Classification 4 mapping'
            ],
        ];

        foreach($translations as $locale => $snippets) {
            $localeModel = $shopRepository->findOneBy([
                'locale' => $locale
            ]);

            if($localeModel === null){
                continue;
            }

            foreach($snippets as $element => $snippet) {
                $elementModel = $form->getElement($element);

                if($elementModel === null) {
                    continue;
                }

                $translationModel = new \Shopware\Models\Config\ElementTranslation();
                $translationModel->setLabel($snippet);
                $translationModel->setLocale($localeModel);

                $elementModel->addTranslation($translationModel);
            }
        }
    }

    /**
     * creates menu items for blisstribute module
     *
     * @return void
     */
    private function createMenuItems()
    {
        $position = 0;
        $parent = $this->Menu()->findOneBy(['label' => 'Artikel']);
        foreach ($parent->getChildren() as $child) {
            if ($child->getPosition() > $position) {
                $position = $child->getPosition();
            }
        }

        $this->createMenuItem([
            'label' => 'Blisstribute Artikelexport Übersicht',
            'controller' => 'BlisstributeArticle',
            'class' => 'sprite-arrow-circle-double-135 contents--import-export',
            'action' => 'Index',
            'active' => 1,
            'position' => $position + 1,
            'parent' => $parent
        ]);

        $position = 0;
        $parent = $this->Menu()->findOneBy(['label' => 'Kunden']);
        foreach ($parent->getChildren() as $child) {
            if ($child->getPosition() > $position) {
                $position = $child->getPosition();
            }
        }

        $this->createMenuItem([
            'label' => 'Blisstribute Bestellexport Übersicht',
            'controller' => 'BlisstributeOrder',
            'class' => 'sprite-arrow-circle-double-135 contents--import-export',
            'action' => 'Index',
            'active' => 1,
            'position' => $position + 1,
            'parent' => $parent
        ]);

        $position = 0;
        $parent = $this->Menu()->findOneBy(['label' => 'Einstellungen']);
        foreach ($parent->getChildren() as $child) {
            if ($child->getPosition() > $position) {
                $position = $child->getPosition();
            }
        }

        $position += 1;
        $this->createMenuItem([
            'label' => 'Blisstribute Artikeltypen',
            'controller' => 'BlisstributeArticleType',
            'class' => 'sprite-arrow-circle-315',
            'action' => 'Index',
            'active' => 1,
            'position' => $position,
            'parent' => $parent
        ]);

        $position += 1;
        $mappingItem = $this->createMenuItem([
            'label' => 'Blisstribute Mapping',
            'controller' => '',
            'class' => 'sprite-inbox',
            'action' => '',
            'active' => 1,
            'position' => $position,
            'parent' => $parent
        ]);

        $this->createMenuItem([
            'label' => 'Zahlarten',
            'controller' => 'BlisstributePaymentMapping',
            'class' => 'sprite-credit-cards settings--payment-methods',
            'action' => 'Index',
            'active' => 1,
            'position' => 1,
            'parent' => $mappingItem
        ]);

        $this->createMenuItem([
            'label' => 'Wertgutscheine',
            'controller' => 'BlisstributeCouponMapping',
            'class' => 'sprite-money--pencil',
            'action' => 'Index',
            'active' => 1,
            'position' => 2,
            'parent' => $mappingItem,
        ]);
    }
}
