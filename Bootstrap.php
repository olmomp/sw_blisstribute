<?php

require_once __DIR__ . '/Components/Blisstribute/Domain/LoggerTrait.php';

use Doctrine\Common\Collections\ArrayCollection;
use Shopware\ExitBBlisstribute\Subscribers\CommandSubscriber;
use Shopware\ExitBBlisstribute\Subscribers\CronSubscriber;
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
        return array(
            'version' => $this->getVersion(),
            'label' => $this->getLabel(),
            'author' => $this->getSupplier(),
            'supplier' => $this->getSupplier(),
            'description' => $this->getDescription(),
            'support' => $this->getSupport(),
            'link' => $this->getLink(),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function enable()
    {        
        $this->logInfo('plugin enabled');
        $this->subscribeEvents();
        return $this->installDefaultTableValues();
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
            return array(
                'success' => false,
                'message' => 'Das Plugin benötigt mindestens Shopware 5.2.'
            );
        }
        
        // check needed plugins
        if (!$this->assertRequiredPluginsPresent(array('Cron'))) {
            return array(
                'success' => false,
                'message' => 'Bitte installieren und aktivieren Sie das Shopware Cron-Plugin.'
            );
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

        try {
            $this->createAttributeCollection();
            $this->createOrderStateCollection();
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }

        return array('success' => true, 'invalidateCache' => array('backend', 'proxy', 'config', 'frontend'));
    }

    /**
     * @inheritdoc
     */
    public function update($version)
    {
        if (version_compare($version, '0.2.1', '<=')) {
            $sql = 'UPDATE s_plugin_blisstribute_articles SET last_cron_at = CURRENT_TIMESTAMP';
            $this->get('db')->query($sql);
        }

        $em = $this->get('models');
        if (version_compare($version, '0.2.2', '<=')) {
            $classMetadata = $em->getClassMetadata('Shopware\CustomModels\Blisstribute\BlisstributeArticleType');
            $this->handleTableInstall($classMetadata);

            $sql = 'INSERT IGNORE INTO s_plugin_blisstribute_article_type (created_at, modified_at, s_filter_id, ' .
                'article_type) SELECT CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, id, ' .
                \Shopware\CustomModels\Blisstribute\BlisstributeArticleType::ARTICLE_TYPE_EQUIPMENT . ' FROM s_filter';
            $this->get('db')->query($sql);
        }

        if (version_compare($version, '0.2.3', '<=')) {
            $this->get('models')->removeAttribute('s_articles_attributes', 'blisstribute', 'estimated_delivery_date');

            $this->get('models')->generateAttributeModels(array('s_articles_attributes'));

            $sql = "DELETE FROM s_core_engine_elements WHERE name = 'blisstributeEstimatedDeliveryDate'";
            $this->get('db')->query($sql);
        }

        if (version_compare($version, '0.3.0', '<')) {
            $classMetadata = $em->getClassMetadata('Shopware\CustomModels\Blisstribute\BlisstributeShop');
            $this->handleTableInstall($classMetadata);

            $classMetadata = $em->getClassMetadata('Shopware\CustomModels\Blisstribute\BlisstributeCoupon');
            $this->handleTableInstall($classMetadata);

            $sql = "INSERT IGNORE INTO s_plugin_blisstribute_shop (s_shop_id, advertising_medium_code) "
                . "SELECT s.id, '' FROM s_core_shops AS s";
            $this->get('db')->query($sql);

            $sql = "INSERT INTO s_plugin_blisstribute_coupon (s_voucher_id, flag_money_voucher) "
                . "SELECT v.id, 0 FROM s_emarketing_vouchers AS v";
            $this->get('db')->query($sql);

            $parent = $this->Menu()->findOneBy(['label' => 'Blisstribute Mapping']);
            if ($parent != null) {
                $this->createMenuItem(
                    array(
                        'label' => 'Shops',
                        'controller' => 'BlisstributeShopMapping',
                        'class' => 'sprite-store-share',
                        'action' => 'Index',
                        'active' => 1,
                        'position' => 3,
                        'parent' => $parent,
                    )
                );

                $this->createMenuItem(
                    array(
                        'label' => 'Wertgutscheine',
                        'controller' => 'BlisstributeCouponMapping',
                        'class' => 'sprite-money--pencil',
                        'action' => 'Index',
                        'active' => 1,
                        'position' => 4,
                        'parent' => $parent,
                    )
                );
            }
        }

        if (version_compare($version, '0.3.5', '<')) {
            $this->subscribeEvent(
                'Enlight_Controller_Action_PostDispatchSecure_Backend_Index',
                'onActionPostDispatchSecureBackendIndex'
            );
        }

        if (version_compare($version, '0.3.15', '<')) {
            $paymentMappings = $this->get('db')->fetchAll("SELECT * FROM s_plugin_blisstribute_payment WHERE mapping_class_name != ''");

            foreach($paymentMappings as $paymentMapping)
            {
                $paymentClassName = str_replace(' ', '', ucwords(str_replace('_', ' ', $paymentMapping['mapping_class_name'])));

                $this->get('db')->query("UPDATE s_plugin_blisstribute_payment SET mapping_class_name = ? WHERE id = ?", array($paymentClassName, $paymentMapping['id']));
            }
        }

        if (version_compare($version, '0.5.9', '<')) {
            $this->subscribeEvent('Shopware_Console_Add_Command', 'onAddConsoleCommand');
        }
        
        if (version_compare($version, '0.6.3', '<')) {
            return array('success' => false, 'message' => 'Bitte das Plugin neu installieren.');
        }

        return array('success' => true, 'invalidateCache' => array('backend', 'proxy', 'config', 'frontend'));
    }

    /**
     * @return array
     */
    public function uninstall()
    {
        return array('success' => true, 'invalidateCache' => array('backend', 'proxy', 'config', 'frontend'));
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
        $modelManager = $this->get('models');
        
        $modelManager->addAttribute('s_articles_attributes', 'blisstribute', 'vhs_number', 'varchar(255)', true, 0);
        $modelManager->addAttribute('s_articles_attributes', 'blisstribute', 'supplier_stock', 'int(11)', true, 0);

        $modelManager->addAttribute('s_order_details_attributes', 'blisstribute', 'quantity_canceled', 'int(11)', true, 0);
        $modelManager->addAttribute('s_order_details_attributes', 'blisstribute', 'quantity_returned', 'int(11)', true, 0);
        $modelManager->addAttribute('s_order_details_attributes', 'blisstribute', 'quantity_shipped', 'int(11)', true, 0);
        $modelManager->addAttribute('s_order_details_attributes', 'blisstribute', 'date_changed', 'date', true, 0);

        $modelManager->addAttribute('s_order_basket_attributes', 'blisstribute', 'swag_promotion_is_free_good', 'varchar(255)', true, 0);
        $modelManager->addAttribute('s_order_basket_attributes', 'blisstribute', 'swag_is_free_good_by_promotion_id', 'varchar(255)', true, 0);
        
        $modelManager->generateAttributeModels(array('s_articles_attributes', 's_order_details_attributes', 's_order_basket_attributes'));       

        $this->get('db')->query(
            "INSERT IGNORE INTO `s_core_engine_elements` (`groupID`, `type`, `label`, `required`, `position`, " .
            "`name`, `variantable`, `translatable`) VALUES  (7, 'text', 'VHS Nummer', 0, 101, " .
            "'blisstributeVhsNumber', 0, 0), (7, 'number', 'Bestand Lieferant', 0, 102, " .
            "'blisstributeSupplierStock', 0, 0)"
        );
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
        $this->subscribeEvent('Enlight_Controller_Front_StartDispatch', 'startDispatch');
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
        
        $subscribers = array(
            new CommandSubscriber(),
            new CronSubscriber(),
            new ControllerSubscriber(),
            new ModelSubscriber(),
            new ServiceSubscriber()
        );

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
                3600, // 1 hour
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
                3600, // 1 hour
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
                3600, // 1 hour
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
        $classMetadataCollection = array(
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
        );

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
        if (!$schemaManager->tablesExist(array($classMetadata->getTableName()))) {
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
        $newSchema = $schemaTool->getSchemaFromMetadata(array($classMetadata));
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
        $newSchema = $schemaTool->getSchemaFromMetadata(array($classMetadata));
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
            $defaultTableData = array(
                    "INSERT IGNORE INTO s_plugin_blisstribute_articles (created_at, modified_at, last_cron_at, "
                    . "s_article_id, trigger_deleted, trigger_sync, tries, comment) SELECT CURRENT_TIMESTAMP, "
                    . "CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, a.id, 0, 1, 0, NULL FROM s_articles AS a",
                    "INSERT IGNORE INTO s_plugin_blisstribute_article_type (created_at, modified_at, s_filter_id, "
                    . "article_type) SELECT CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, id, 4 FROM s_filter",
                    "INSERT IGNORE INTO s_plugin_blisstribute_shipment (mapping_class_name, s_premium_dispatch_id) "
                    . "SELECT NULL, pd.id FROM s_premium_dispatch AS pd",
                    "INSERT IGNORE INTO s_plugin_blisstribute_payment (mapping_class_name, flag_payed, "
                    . "s_core_paymentmeans_id) SELECT NULL, 0, cp.id FROM s_core_paymentmeans AS cp",
                    "INSERT IGNORE INTO s_plugin_blisstribute_shop (s_shop_id, advertising_medium_code) "
                    . "SELECT s.id, '' FROM s_core_shops AS s",
                    "INSERT IGNORE INTO s_plugin_blisstribute_coupon (s_voucher_id, flag_money_voucher) "
                    . "SELECT v.id, 0 FROM s_emarketing_vouchers AS v",
                    "DELETE FROM s_plugin_blisstribute_payment WHERE s_core_paymentmeans_id NOT IN (SELECT id FROM s_core_paymentmeans)",
                    "DELETE FROM s_plugin_blisstribute_shipment WHERE s_premium_dispatch_id NOT IN (SELECT id FROM s_premium_dispatch)",
            );

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
            $defaultTableData = array(
                'Shopware\CustomModels\Blisstribute\BlisstributeArticle' => "TRUNCATE TABLE s_plugin_blisstribute_articles",
                'Locks' => "TRUNCATE TABLE s_plugin_blisstribute_task_lock",
            );

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
     * create plugin configuration
     *
     * @return void
     */
    private function createConfig()
    {
        $form = $this->Form();

        $form->setElement(
            'select',
            'blisstribute-soap-protocol',
            array(
                'label' => 'Protocol',
                'description' => 'SOAP-Protokoll für den Verbindungsaufbau zum Blisstribute-System',
                'store' => array(
                    array(1, 'http'),
                    array(2, 'https')
                ),
                'value' => 1
            )
        );
        $form->setElement(
            'text',
            'blisstribute-soap-host',
            array(
                'label' => 'Host',
                'description' => 'SOAP-Hostname für den Verbindungsaufbau zum Blisstribute-System',
                'maxLength' => 255,
                'value' => ''
            )
        );
        $form->setElement(
            'number',
            'blisstribute-soap-port',
            array(
                'label' => 'Port',
                'description' => 'SOAP-Port für den Verbindungsaufbau zum Blisstribute-System',
                'maxLength' => 4,
                'value' => 80
            )
        );
        $form->setElement(
            'text',
            'blisstribute-soap-client',
            array(
                'label' => 'Client',
                'description' => 'SOAP-Klientenkürzel für Ihren Blisstribute-Mandanten',
                'maxLength' => 3,
                'value' => ''
            )
        );
        $form->setElement(
            'text',
            'blisstribute-soap-username',
            array(
                'label' => 'Username',
                'description' => 'SOAP-Benutzername für Ihren Blisstribute-Mandanten',
                'maxLength' => 255,
                'value' => ''
            )
        );
        $form->setElement(
            'text',
            'blisstribute-soap-password',
            array(
                'label' => 'Password',
                'description' => 'SOAP-Passwort für Ihren Blisstribute-Mandanten',
                'maxLength' => 255,
                'value' => ''
            )
        );
        $form->setElement(
            'text',
            'blisstribute-http-login',
            array(
                'label' => 'HTTP Username',
                'description' => 'Zugangsdaten (Benutzername) für eine eventuelle .htaccess Authentifizierung',
                'maxLength' => 255
            )
        );
        $form->setElement(
            'text',
            'blisstribute-http-password',
            array(
                'label' => 'HTTP Password',
                'description' => 'Zugangsdaten (Passwort) für eine eventuelle .htaccess Authentifizierung',
                'maxLength' => 255
            )
        );

        $form->setElement(
            'checkbox',
            'blisstribute-auto-sync-order',
            array(
                'label' => 'Bestellung bei Anlage übermitteln',
                'description' => 'Wenn aktiviert, wird die Bestellung sofort nach Abschluss des Checkout-Prozesses zum Blisstribute System übermittelt. Wenn deaktiviert, müssen die Bestellungen manuell, oder über den Cron übermittelt werden.',
                'maxLength' => 255,
                'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
            )
        );

        $form->setElement(
            'checkbox',
            'blisstribute-auto-hold-order',
            array(
                'label' => 'Bestellung in Blisstribute anhalten',
                'description' => 'Wenn aktiviert, wird die Bestellung sofort nach der Übertragung zu Blisstribute angehalten',
                'maxLength' => 255,
                'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
            )
        );

        $form->setElement(
            'checkbox',
            'blisstribute-auto-lock-order',
            array(
                'label' => 'Bestellung in Blisstribute sperren',
                'description' => 'Wenn aktiviert, wird die Bestellung sofort nach der Übertragung zu Blisstribute gesperrt',
                'maxLength' => 255,
                'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
            )
        );

        $form->setElement(
            'text',
            'blisstribute-default-advertising-medium',
            array(
                'label' => 'Standard Werbemittel',
                'description' => 'Das Standard-Werbemittel für die Bestellanlage',
                'maxLength' => 3,
                'value' => 'cen',
                'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
            )
        );
        $form->setElement(
            'checkbox',
            'googleAddressValidation',
            array(
                'label' => 'Google Maps Address Verification',
                'description' => 'Wenn aktiviert, werden Liefer- und Rechnungsadresse bei Bestellübertragung mit der Google Maps API abgeglichen, um eventuelle Adressefehler zu korrigieren.',
                'value' => 0,
                'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
            )
        );
        $form->setElement(
            'text',
            'googleMapsKey',
            array(
                'label' => 'Google Maps Key',
                'description' => 'API-KEY für den Zugang zur Google Maps API.',
                'value' => '',
                'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
            )
        );
        $form->setElement(
            'checkbox',
            'transferOrders',
            array(
                'label' => 'Transfer Orders without verification',
                'description' => 'Wenn aktiviert, werden ausschließlich Bestellungen ins Blisstribute-System übertragen, deren Adressen erfolgreich verifiziert werden konnten.',
                'value' => 1,
                'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
            )
        );
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

        $this->createMenuItem(array(
            'label' => 'Blisstribute Artikelexport Übersicht',
            'controller' => 'BlisstributeArticle',
            'class' => 'sprite-arrow-circle-double-135 contents--import-export',
            'action' => 'Index',
            'active' => 1,
            'position' => $position + 1,
            'parent' => $parent
        ));

        $position = 0;
        $parent = $this->Menu()->findOneBy(['label' => 'Kunden']);
        foreach ($parent->getChildren() as $child) {
            if ($child->getPosition() > $position) {
                $position = $child->getPosition();
            }
        }

        $this->createMenuItem(array(
            'label' => 'Blisstribute Bestellexport Übersicht',
            'controller' => 'BlisstributeOrder',
            'class' => 'sprite-arrow-circle-double-135 contents--import-export',
            'action' => 'Index',
            'active' => 1,
            'position' => $position + 1,
            'parent' => $parent
        ));

        $position = 0;
        $parent = $this->Menu()->findOneBy(['label' => 'Einstellungen']);
        foreach ($parent->getChildren() as $child) {
            if ($child->getPosition() > $position) {
                $position = $child->getPosition();
            }
        }

        $position += 1;
        $this->createMenuItem(array(
            'label' => 'Blisstribute Artikeltypen',
            'controller' => 'BlisstributeArticleType',
            'class' => 'sprite-arrow-circle-315',
            'action' => 'Index',
            'active' => 1,
            'position' => $position,
            'parent' => $parent
        ));

        $position += 1;
        $mappingItem = $this->createMenuItem(array(
            'label' => 'Blisstribute Mapping',
            'controller' => '',
            'class' => 'sprite-inbox',
            'action' => '',
            'active' => 1,
            'position' => $position,
            'parent' => $parent
        ));

        $this->createMenuItem(array(
            'label' => 'Versandarten',
            'controller' => 'BlisstributeShipmentMapping',
            'class' => 'sprite-envelope--arrow settings--delivery-charges',
            'action' => 'Index',
            'active' => 1,
            'position' => 1,
            'parent' => $mappingItem
        ));

        $this->createMenuItem(array(
            'label' => 'Zahlarten',
            'controller' => 'BlisstributePaymentMapping',
            'class' => 'sprite-credit-cards settings--payment-methods',
            'action' => 'Index',
            'active' => 1,
            'position' => 2,
            'parent' => $mappingItem
        ));

        $this->createMenuItem(array(
            'label' => 'Shops',
            'controller' => 'BlisstributeShopMapping',
            'class' => 'sprite-store-share',
            'action' => 'Index',
            'active' => 1,
            'position' => 3,
            'parent' => $mappingItem,
        ));

        $this->createMenuItem(array(
            'label' => 'Wertgutscheine',
            'controller' => 'BlisstributeCouponMapping',
            'class' => 'sprite-money--pencil',
            'action' => 'Index',
            'active' => 1,
            'position' => 4,
            'parent' => $mappingItem,
        ));
    }
}
