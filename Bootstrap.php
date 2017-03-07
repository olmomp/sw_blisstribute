<?php

require_once(__DIR__ . '/Components/Blisstribute/Domain/LoggerTrait.php');

use ShopwarePlugins\ExitBBlisstribute\Subscribers\CronSubscriber;

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
		if (!$this->assertMinimumVersion('5.1')) {
    		return array(
				'success' => false,
				'message' => 'Das Plugin benötigt mindestens Shopware 5.1.'
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
            $this->subscribeEvent('Shopware\Models\Property\Group::postPersist', 'postPersistProperty');
            $this->subscribeEvent('Shopware\Models\Property\Group::preRemove', 'preRemoveProperty');

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

        if (version_compare($version, '0.2.10', '<=')) {
            $this->subscribeEvent('Shopware\Models\Article\Article::postPersist', 'postPersistArticle');
        }

        if (version_compare($version, '0.2.25', '<=')) {
            $this->subscribeEvent('Shopware\Models\Order\Order::postPersist', 'postPersistOrder');
        }

        if (version_compare($version, '0.2.29', '<=')) {
            $this->subscribeEvent(
                'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributeArticleSyncCron',
                'getArticleSyncCronController'
            );
        }

        if (version_compare($version, '0.2.30', '<=')) {
            $this->subscribeEvent(
                'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributeOrderSyncCron',
                'getOrderSyncCronController'
            );
        }

        if (version_compare($version, '0.3.0', '<')) {
            $this->subscribeEvent(
                'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributeShopMapping',
                'getShopMappingController'
            );

            $this->subscribeEvent(
                'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributeCouponMapping',
                'getCouponMappingController'
            );

            $this->subscribeEvent(
                'Enlight_Controller_Dispatcher_ControllerPath_Api_Btarticlestock',
                'onGetBtarticlestockApiController'
            );

            $this->subscribeEvent('Shopware\Models\Shop\Shop::postPersist', 'postPersistShop');
            $this->subscribeEvent('Shopware\Models\Shop\Shop::postRemove', 'postRemoveShop');

            $this->subscribeEvent('Shopware\Models\Voucher\Voucher::postPersist', 'postPersistVoucher');
            $this->subscribeEvent('Shopware\Models\Voucher\Voucher::postRemove', 'postRemoveVoucher');

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

        if (version_compare($version, '0.3.7', '<')) {
            $this->subscribeEvent(
                'Shopware\Models\Attribute\Voucher::postPersist',
                'postPersistVoucherAttribute'
            );
        }

        if (version_compare($version, '0.3.11', '<')) {
            $this->subscribeEvent('Shopware\Models\Order\Order::postRemove', 'onModelsOrderOrderPostRemove');
        }

        if (version_compare($version, '0.3.13', '<')) {
            $this->registerCronJobs();
            $this->subscribeEvent('Enlight_Controller_Front_StartDispatch', 'startDispatch');
        }
        
        if (version_compare($version, '0.3.15', '<')) {
            $paymentMappings = $this->get('db')->fetchAll("SELECT * FROM s_plugin_blisstribute_payment WHERE mapping_class_name != ''");
                        
            foreach($paymentMappings as $paymentMapping)
            {
                $paymentClassName = str_replace(' ', '', ucwords(str_replace('_', ' ', $paymentMapping['mapping_class_name'])));
                
                $this->get('db')->query("UPDATE s_plugin_blisstribute_payment SET mapping_class_name = ? WHERE id = ?", array($paymentClassName, $paymentMapping['id']));    
            }
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
     * @param Enlight_Event_EventArgs $args
     */
    public function onEnlightControllerFrontStartDispatch(Enlight_Event_EventArgs $args)
    {
        $this->Application()->Loader()->registerNamespace('Shopware\Components', $this->Path() . 'Components/');
    }

    /**
     * @return string
     */
    public function onGetBtordersApiController()
    {
        return $this->Path() . 'Controllers/Api/Btorders.php';
    }

    /**
     * @return string
     */
    public function onGetGoogleAddressValidator()
    {
        require_once $this->Path() . 'Components/Blisstribute/Order/GoogleAddressValidator.php';
        return new Shopware_Components_Blisstribute_Order_GoogleAddressValidator();
    }

    /**
     * @return string
     */
    public function onGetBtarticlesApiController()
    {
        return $this->Path() . 'Controllers/Api/Btarticles.php';
    }

    /**
     * @return string
     */
    public function onGetBtarticlestockApiController()
    {
        return $this->Path() . 'Controllers/Api/Btarticlestock.php';
    }

    /**
     * add event listener for blisstribute module
     *
     * @return void
     */
    private function subscribeEvents()
    {
        $this->subscribeEvent(
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributeArticle',
            'getArticleSyncController'
        );

        $this->subscribeEvent(
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributeArticleSyncCron',
            'getArticleSyncCronController'
        );

        $this->subscribeEvent(
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributeArticleType',
            'getArticleTypeController'
        );

        $this->subscribeEvent(
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributeOrder',
            'getOrderController'
        );

        $this->subscribeEvent(
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributeOrderSyncCron',
            'getOrderSyncCronController'
        );

        $this->subscribeEvent(
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributeShipmentMapping',
            'getShipmentMappingController'
        );

        $this->subscribeEvent(
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributePaymentMapping',
            'getPaymentMappingController'
        );

        $this->subscribeEvent(
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributeShopMapping',
            'getShopMappingController'
        );

        $this->subscribeEvent(
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributeCouponMapping',
            'getCouponMappingController'
        );

        $this->subscribeEvent('Shopware_Modules_Order_SaveOrder_ProcessDetails', 'onRegisterOrder');
        $this->subscribeEvent('Shopware_Modules_Order_SendMail_BeforeSend', 'onOrderFinished');

        // model events
        $this->subscribeEvent('Shopware\Models\Order\Order::postPersist', 'postPersistOrder');

        $this->subscribeEvent('Shopware\Models\Article\Article::postPersist', 'postPersistArticle');
        $this->subscribeEvent('Shopware\Models\Article\Article::postUpdate', 'postUpdateArticle');
        $this->subscribeEvent('Shopware\Models\Article\Article::preRemove', 'preRemoveArticle');

        $this->subscribeEvent('Shopware\Models\Article\Detail::postPersist', 'postPersistDetail');
        $this->subscribeEvent('Shopware\Models\Article\Detail::postUpdate', 'postUpdateDetail');
        $this->subscribeEvent('Shopware\Models\Article\Detail::preRemove', 'preRemoveDetail');

        $this->subscribeEvent('Shopware\Models\Property\Group::postPersist', 'postPersistProperty');
        $this->subscribeEvent('Shopware\Models\Property\Group::preRemove', 'preRemoveProperty');

        $this->subscribeEvent('Shopware\Models\Shop\Shop::postPersist', 'postPersistShop');
        $this->subscribeEvent('Shopware\Models\Shop\Shop::postRemove', 'postRemoveShop');

        $this->subscribeEvent('Shopware\Models\Voucher\Voucher::postPersist', 'postPersistVoucher');
        $this->subscribeEvent('Shopware\Models\Voucher\Voucher::postRemove', 'postRemoveVoucher');

        // blisstribute model events
        $this->subscribeEvent(
            'Shopware\CustomModels\Blisstribute\BlisstributeOrder::prePersist',
            'prePersistBlisstributeOrder'
        );

        $this->subscribeEvent(
            'Shopware\CustomModels\Blisstribute\BlisstributeOrder::preUpdate',
            'preUpdateBlisstributeOrder'
        );

        $this->subscribeEvent(
            'Shopware\CustomModels\Blisstribute\BlisstributeShippingRequest::prePersist',
            'prePersistBlisstributeShippingRequest'
        );

        $this->subscribeEvent(
            'Shopware\CustomModels\Blisstribute\BlisstributeShippingRequest::preUpdate',
            'preUpdateBlisstributeShippingRequest'
        );
        $this->subscribeEvent(
            'Shopware\CustomModels\Blisstribute\BlisstributeShippingRequestItems::prePersist',
            'prePersistBlisstributeShippingRequestItem'
        );

        $this->subscribeEvent(
            'Shopware\CustomModels\Blisstribute\BlisstributeShippingRequestItems::preUpdate',
            'preUpdateBlisstributeShippingRequestItem'
        );

        $this->subscribeEvent(
            'Shopware\CustomModels\Blisstribute\BlisstributeArticleType::prePersist',
            'prePersistArticleType'
        );

        $this->subscribeEvent(
            'Shopware\CustomModels\Blisstribute\BlisstributeArticleType::preUpdate',
            'preUpdateArticleType'
        );

        $this->subscribeEvent(
            'Shopware\CustomModels\Blisstribute\BlisstributeArticleType::postUpdate',
            'postUpdateArticleType'
        );

        $this->subscribeEvent(
            'Shopware\CustomModels\Blisstribute\BlisstributeArticle::prePersist',
            'prePersistArticle'
        );

        $this->subscribeEvent(
            'Shopware\CustomModels\Blisstribute\BlisstributeArticle::preUpdate',
            'preUpdateArticle'
        );

        $this->subscribeEvent(
            'Enlight_Controller_Action_PostDispatch_Backend_Article',
            'postDispatchBackendArticle'
        );

        $this->subscribeEvent(
            'Enlight_Controller_Front_StartDispatch',
            'onEnlightControllerFrontStartDispatch'
        );

        $this->subscribeEvent(
            'Enlight_Controller_Dispatcher_ControllerPath_Api_Btorders',
            'onGetBtordersApiController'
        );

        $this->subscribeEvent(
            'Enlight_Controller_Dispatcher_ControllerPath_Api_Btarticles',
            'onGetBtarticlesApiController'
        );

        $this->subscribeEvent(
            'Enlight_Controller_Dispatcher_ControllerPath_Api_Btarticlestock',
            'onGetBtarticlestockApiController'
        );

        $this->subscribeEvent(
            'Enlight_Controller_Action_PostDispatchSecure_Backend_Index',
            'onActionPostDispatchSecureBackendIndex'
        );

        $this->subscribeEvent(
            'Shopware\Models\Attribute\Voucher::postPersist',
            'postPersistVoucherAttribute'
        );

        $this->subscribeEvent('Shopware\Models\Order\Order::postRemove', 'onModelsOrderOrderPostRemove');

        $this->subscribeEvent('Enlight_Controller_Front_StartDispatch', 'startDispatch');

        $this->subscribeEvent('Enlight_Bootstrap_InitResource_blisstribute.google_address_validator', 'onGetGoogleAddressValidator');
    }

    /* ********************************************** Reworked Subscriber *********************************************/

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
            new CronSubscriber(
                Shopware()->Container(),
                $this->Path(),
                $this->Application(),
                $this->get('db')
            )
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
                'Pixup Blisstribute Order Sync CronJob',
                'PixupBlisstributeOrderSyncCron',
                3600, // 1 hour
                true
            );
        } catch (Exception $e) {
            // do nothing
        }

        try {
            // article sync cron
            $this->createCronJob(
                'Pixup Blisstribute Article Sync CronJob',
                'PixupBlisstributeArticleSyncCron',
                3600, // 1 hour
                true
            );
        } catch (Exception $e) {
            // do nothing
        }

        try {
            // easyCoupon Wertgutscheine
            $this->createCronJob(
                'Pixup ExitB EasyCoupon Wertgutschein CronJob',
                'PixupExitBEasyCouponWertgutscheinCron',
                120, // 2 minutes
                true
            );
        } catch (Exception $e) {
            // do nothing
        }

        // import all orders that might have been added using pure sql
        try {
            $this->createCronJob(
                'Pixup Import Orders To ExitB CronJob',
                'PixupImportOrdersToExitBCron',
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
        $this->get('template')->addTemplateDir(__DIR__ . '/Views/', 'blisstribute');
    }

    /**
     * Register all necessary namespaces
     */
    protected function registerNamespaces()
    {
        $this->get('Loader')->registerNamespace('ShopwarePlugins\ExitBBlisstribute',$this->Path());
    }

    /**
     * Register all snippets
     */
    protected function registerSnippets()
    {
        $this->Application()->Snippets()->addConfigDir(__DIR__ . '/Snippets/');
    }

    /* ********************************************** Reworked Subscriber END *****************************************/

    public function onActionPostDispatchSecureBackendIndex(Enlight_Controller_ActionEventArgs $arguments)
    {
        /**@var $controller Shopware_Controllers_Frontend_Index */
        $controller = $arguments->getSubject();

        $view = $controller->View();

        //Add our plugin template directory to load our slogan extension.
        $view->addTemplateDir($this->Path() . 'Views/');

        $this->Application()->Snippets()->addConfigDir(
            $this->Path() . 'Snippets/'
        );

        if ($arguments->getRequest()->getActionName() === 'load') {
            $view->extendsTemplate('backend/index/view/exitb_blisstribute/menu.js');
        }
    }

    /**
     * return article type controller path
     *
     * @param Enlight_Event_EventArgs $eventArgs
     *
     * @return string
     */
    public function getArticleTypeController(Enlight_Event_EventArgs $eventArgs)
    {
        $this->registerTemplateDir();
        $this->Application()->Snippets()->addConfigDir(__DIR__ . '/Snippets/');
        return $this->Path() . 'Controllers/Backend/BlisstributeArticleType.php';
    }

    /**
     * return article sync controller path
     *
     * @param Enlight_Event_EventArgs $eventArgs
     *
     * @return string
     */
    public function getArticleSyncController(Enlight_Event_EventArgs $eventArgs)
    {
        $this->registerTemplateDir();
        $this->Application()->Snippets()->addConfigDir(__DIR__ . '/Snippets/');
        return $this->Path() . 'Controllers/Backend/BlisstributeArticle.php';
    }

    /**
     * return article sync cron controller path
     *
     * @param Enlight_Event_EventArgs $eventArgs
     *
     * @return string
     */
    public function getArticleSyncCronController(Enlight_Event_EventArgs $eventArgs)
    {
        $this->registerTemplateDir();
        $this->Application()->Snippets()->addConfigDir(__DIR__ . '/Snippets/');
        return $this->Path() . 'Controllers/Backend/BlisstributeArticleSyncCron.php';
    }

    /**
     * return order export controller path
     *
     * @param Enlight_Event_EventArgs $eventArgs
     *
     * @return string
     */
    public function getOrderController(Enlight_Event_EventArgs $eventArgs)
    {
        $this->registerTemplateDir();
        $this->Application()->Snippets()->addConfigDir(__DIR__ . '/Snippets/');
        return $this->Path() . 'Controllers/Backend/BlisstributeOrder.php';
    }

    /**
     * return order sync cron controller path
     *
     * @param Enlight_Event_EventArgs $eventArgs
     *
     * @return string
     */
    public function getOrderSyncCronController(Enlight_Event_EventArgs $eventArgs)
    {
        $this->registerTemplateDir();
        $this->Application()->Snippets()->addConfigDir(__DIR__ . '/Snippets/');
        return $this->Path() . 'Controllers/Backend/BlisstributeOrderSyncCron.php';
    }

    /**
     * return shipment mapping controller path
     *
     * @param Enlight_Event_EventArgs $eventArgs
     *
     * @return string
     */
    public function getShipmentMappingController(Enlight_Event_EventArgs $eventArgs)
    {
        $this->registerTemplateDir();
        $this->Application()->Snippets()->addConfigDir(__DIR__ . '/Snippets/');
        return $this->Path() . 'Controllers/Backend/BlisstributeShipmentMapping.php';
    }

    /**
     * return payment mapping controller path
     *
     * @param Enlight_Event_EventArgs $eventArgs
     *
     * @return string
     */
    public function getPaymentMappingController(Enlight_Event_EventArgs $eventArgs)
    {
        $this->registerTemplateDir();
        $this->Application()->Snippets()->addConfigDir(__DIR__ . '/Snippets/');
        return $this->Path() . 'Controllers/Backend/BlisstributePaymentMapping.php';
    }

    /**
     * return payment mapping controller path
     *
     * @param Enlight_Event_EventArgs $eventArgs
     *
     * @return string
     */
    public function getShopMappingController(Enlight_Event_EventArgs $eventArgs)
    {
        $this->registerTemplateDir();
        $this->Application()->Snippets()->addConfigDir(__DIR__ . '/Snippets/');
        return $this->Path() . 'Controllers/Backend/BlisstributeShopMapping.php';
    }

    /**
     * return payment mapping controller path
     *
     * @param Enlight_Event_EventArgs $eventArgs
     *
     * @return string
     */
    public function getCouponMappingController(Enlight_Event_EventArgs $eventArgs)
    {
        $this->registerTemplateDir();
        $this->Application()->Snippets()->addConfigDir(__DIR__ . '/Snippets/');
        return $this->Path() . 'Controllers/Backend/BlisstributeCouponMapping.php';
    }

    /**
     * @param Enlight_Event_EventArgs $eventArgs
     *
     * @return bool
     */
    public function postPersistOrder(Enlight_Event_EventArgs $eventArgs)
    {
        $this->registerOrder($eventArgs);
        return true;
    }

    /**
     * @param Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function postPersistArticle(Enlight_Event_EventArgs $args)
    {
        $modelManager = $this->get('models');

        /** @var \Shopware\Models\Article\Article $article */
        $article = $args->get('entity');

        $blisstributeArticle = new Shopware\CustomModels\Blisstribute\BlisstributeArticle();
        $blisstributeArticle->setLastCronAt(new DateTime())
            ->setArticle($article)
            ->setTriggerSync(true)
            ->setTries(0)
            ->setComment(null);

        $modelManager->persist($blisstributeArticle);
        $modelManager->flush();
    }

    /**
     * @param Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function postUpdateArticle(Enlight_Event_EventArgs $args)
    {
        $modelManager = $this->get('models');

        /** @var \Shopware\Models\Article\Article $article */
        $article = $args->get('entity');

        $repository = $modelManager->getRepository('Shopware\CustomModels\Blisstribute\BlisstributeArticle');
        $blisstributeArticle = $repository->findOneBy(array('article' => $article));
        if ($blisstributeArticle === null) {
            $blisstributeArticle = new Shopware\CustomModels\Blisstribute\BlisstributeArticle();
            $blisstributeArticle->setArticle($article);
        }
		
		if ($blisstributeArticle->isTriggerSync()) {
			return;
		}

        $blisstributeArticle->setLastCronAt(new DateTime())
            ->setTriggerSync(true)
            ->setTries(0)
            ->setComment(null);

        $modelManager->persist($blisstributeArticle);
        $modelManager->flush();
    }

    /**
     * @param Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function preRemoveArticle(Enlight_Event_EventArgs $args)
    {
        $modelManager = $this->get('models');

        /** @var \Shopware\Models\Article\Article $article */
        $article = $args->get('entity');

        $repository = $modelManager->getRepository('Shopware\CustomModels\Blisstribute\BlisstributeArticle');
        $blisstributeArticle = $repository->findOneBy(array('article' => $article));
        if ($blisstributeArticle === null) {
            return;
        }

        $modelManager->remove($blisstributeArticle);
        $modelManager->flush();
    }

    /**
     * @param Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function postPersistDetail(Enlight_Event_EventArgs $args)
    {
		$modelManager = $this->get('models');
		
        /** @var \Shopware\Models\Article\Detail $detail */
        $detail = $args->get('entity');

        // load article
        $repository = $modelManager->getRepository('Shopware\CustomModels\Blisstribute\BlisstributeArticle');
        $blisstributeArticle = $repository->findOneBy(array('article' => $detail->getArticle()));
        if ($blisstributeArticle === null) {
            $blisstributeArticle = new Shopware\CustomModels\Blisstribute\BlisstributeArticle();
            $blisstributeArticle->setArticle($detail->getArticle());
        }
		
		if ($blisstributeArticle->isTriggerSync()) {
			return;
		}

        $blisstributeArticle->setLastCronAt(new DateTime())
            ->setTriggerSync(true)
            ->setTries(0)
            ->setComment(null);

        $modelManager->persist($blisstributeArticle);
        $modelManager->flush();
    }

    /**
     * @param Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function postUpdateDetail(Enlight_Event_EventArgs $args)
    {
        $modelManager = $this->get('models');

        /** @var \Shopware\Models\Article\Detail $detail */
        $detail = $args->get('entity');

        $articleRepository = $modelManager->getRepository('Shopware\CustomModels\Blisstribute\BlisstributeArticle');

        /* @var Shopware\CustomModels\Blisstribute\BlisstributeArticle $article */
        $blisstributeArticle = $articleRepository->findOneBy(array('article' => $detail->getArticle()));

        if ($blisstributeArticle === null) {
            $blisstributeArticle = new Shopware\CustomModels\Blisstribute\BlisstributeArticle();
            $blisstributeArticle->setArticle($detail->getArticle());
        }
		
		if ($blisstributeArticle->isTriggerSync()) {
			return;
		}

        $blisstributeArticle->setLastCronAt(new DateTime())
            ->setTriggerSync(true)
            ->setTries(0)
            ->setComment(null);

        $modelManager->persist($article);
        $modelManager->flush();
    }

    /**
     * @param Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function preRemoveDetail(Enlight_Event_EventArgs $args)
    {
        $modelManager = $this->get('models');

        /** @var \Shopware\Models\Article\Detail $detail */
        $detail = $args->get('entity');

        $repository = $modelManager->getRepository('Shopware\CustomModels\Blisstribute\BlisstributeArticle');

        /* @var Shopware\CustomModels\Blisstribute\BlisstributeArticle $blisstributeArticle */
        $blisstributeArticle = $repository->findOneBy(array('article' => $detail->getArticle()));

        if ($blisstributeArticle === null or $blisstributeArticle->isDeleted()) {
            return;
        }

        $blisstributeArticle->setTriggerSync(true)
            ->setTries(0)
            ->setComment(null)
            ->setDeleted(true);

        $modelManager->persist($blisstributeArticle);
        $modelManager->flush();
    }

    /**
     * @param Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function postPersistProperty(Enlight_Event_EventArgs $args)
    {
        $modelManager = $this->get('models');
        $entity = $args->get('entity');

        $articleType = new Shopware\CustomModels\Blisstribute\BlisstributeArticleType();
        $articleType->setFilter($entity);
        $articleType->setArticleType(0);

        $modelManager->persist($articleType);
        $modelManager->flush();
    }

    /**
     * @param Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function preRemoveProperty(Enlight_Event_EventArgs $args)
    {
        $modelManager = $this->get('models');
        $entity = $args->get('entity');

        /* @var Shopware\CustomModels\Blisstribute\BlisstributeArticleTypeRepository $articleTypeRepository */
        $articleTypeRepository = $modelManager->getRepository('Shopware\CustomModels\Blisstribute\BlisstributeArticleType');
        $articleType = $articleTypeRepository->fetchByFilterType($entity->getId());
        if ($articleType === null) {
            return;
        }

        $modelManager->remove($articleType);
        $modelManager->flush();
    }

    /**
     * blisstribute order event fired before db insert
     *
     * @param Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function prePersistBlisstributeOrder(Enlight_Event_EventArgs $eventArgs)
    {
        $currentTime = new DateTime();

        /** @var \Shopware\CustomModels\Blisstribute\BlisstributeOrder $entity */
        $entity = $eventArgs->get('entity');
        $entity->setCreatedAt($currentTime)
            ->setModifiedAt($currentTime);
    }

    /**
     * blisstribute event fired before db update
     *
     * @param Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function preUpdateBlisstributeOrder(Enlight_Event_EventArgs $eventArgs)
    {
        $entity = $eventArgs->get('entity');
        $entity->setModifiedAt(new DateTime());
    }

    /**
     * blisstribute order event fired before db insert
     *
     * @param Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function prePersistBlisstributeShippingRequest(Enlight_Event_EventArgs $eventArgs)
    {
        $currentTime = new DateTime();

        /** @var \Shopware\CustomModels\Blisstribute\BlisstributeShippingRequest $entity */
        $entity = $eventArgs->get('entity');
        $entity->setCreatedAt($currentTime)
            ->setModifiedAt($currentTime);
    }

    /**
     * blisstribute event fired before db update
     *
     * @param Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function preUpdateBlisstributeShippingRequest(Enlight_Event_EventArgs $eventArgs)
    {
        /** @var \Shopware\CustomModels\Blisstribute\BlisstributeShippingRequest $entity */
        $entity = $eventArgs->get('entity');
        $entity->setModifiedAt(new DateTime());
    }

    /**
     * blisstribute order event fired before db insert
     *
     * @param Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function prePersistBlisstributeShippingRequestItem(Enlight_Event_EventArgs $eventArgs)
    {
        $currentTime = new DateTime();

        /** @var \Shopware\CustomModels\Blisstribute\BlisstributeShippingRequestItems $entity */
        $entity = $eventArgs->get('entity');
        $entity->setCreatedAt($currentTime)
            ->setModifiedAt($currentTime);
    }

    /**
     * blisstribute event fired before db update
     *
     * @param Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function preUpdateBlisstributeShippingRequestItem(Enlight_Event_EventArgs $eventArgs)
    {
        /** @var \Shopware\CustomModels\Blisstribute\BlisstributeShippingRequestItems $entity */
        $entity = $eventArgs->get('entity');
        $entity->setModifiedAt(new DateTime());
    }

    /**
     * article type event fired before create entity
     *
     * @param Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function prePersistArticle(Enlight_Event_EventArgs $eventArgs)
    {
        $currentTime = new DateTime();

        /** @var \Shopware\CustomModels\Blisstribute\BlisstributeArticleType $entity */
        $entity = $eventArgs->get('entity');
        $entity->setCreatedAt($currentTime)
            ->setModifiedAt($currentTime);
    }

    /**
     * article type event fired before db update
     *
     * @param Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function preUpdateArticle(Enlight_Event_EventArgs $args)
    {
        /** @var \Shopware\CustomModels\Blisstribute\BlisstributeArticle $entity */
        $entity = $args->get('entity');
        $entity->setModifiedAt(new DateTime());
    }

    /**
     * article type event fired before create entity
     *
     * @param Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function prePersistArticleType(Enlight_Event_EventArgs $eventArgs)
    {
        $currentTime = new DateTime();

        /** @var \Shopware\CustomModels\Blisstribute\BlisstributeArticleType $entity */
        $entity = $eventArgs->get('entity');
        $entity->setCreatedAt($currentTime)
            ->setModifiedAt($currentTime);
    }

    /**
     * article type event fired before db update
     *
     * @param Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function preUpdateArticleType(Enlight_Event_EventArgs $args)
    {
        /** @var \Shopware\CustomModels\Blisstribute\BlisstributeArticleType $entity */
        $entity = $args->get('entity');
        $entity->setModifiedAt(new DateTime());
    }

    /**
     * article type event fired after update
     *
     * @param Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function postUpdateArticleType(Enlight_Event_EventArgs $args)
    {
        $articleIdCollection = array();

        $modelManager = $this->get('models');

        /* @var Shopware\CustomModels\Blisstribute\BlisstributeArticleType $entity */
        $entity = $args->get('entity');

        /* @var Shopware\CustomModels\Blisstribute\BlisstributeArticleRepository $articleRepository */
        $articleRepository = $modelManager->getRepository('Shopware\CustomModels\Blisstribute\BlisstributeArticle');

        $groupEntity = $entity->getFilter();
        foreach ($groupEntity->getArticles() as $article) {
            if (!in_array($article->getId(), $articleIdCollection)) {
                $articleIdCollection[] = $article->getId();
            }
        }

        $articleCollection = $articleRepository->fetchByArticleIdList($articleIdCollection);
        foreach ($articleCollection as $currentArticle) {
            $currentArticle->setModifiedAt(new DateTime());
            $currentArticle->setTriggerSync(true);
            $currentArticle->setTries(0);

            $modelManager->persist($currentArticle);
        }

        $modelManager->flush();
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function postPersistShop(Enlight_Event_EventArgs $args)
    {
        $modelManager = $this->get('models');

        /** @var \Shopware\Models\Shop\Shop $shop */
        $shop = $args->get('entity');

        $blisstributeShop = new \Shopware\CustomModels\Blisstribute\BlisstributeShop();
        $blisstributeShop->setShop($shop)->setAdvertisingMediumCode('');

        $modelManager->persist($blisstributeShop);
        $modelManager->flush();
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function postRemoveShop(Enlight_Event_EventArgs $args)
    {
        $modelManager = $this->get('models');

        /** @var Shopware\Models\Shop\ $blisstributeShop */
        $shop = $args->get('entity');

        $repository = $this->get('models')->getRepository('Shopware\CustomModels\Blisstribute\BlisstributeShop');
        $blisstributeShop = $repository->findOneByShop($shop->getId());
        if ($blisstributeShop === null) {
            return;
        }

        $modelManager->remove($blisstributeShop);
        $modelManager->flush();
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function postPersistVoucher(Enlight_Event_EventArgs $args)
    {
        $modelManager = $this->get('models');

        /** @var \Shopware\Models\Voucher\Voucher $voucher */
        $voucher = $args->get('entity');

        $blisstributeCoupon = new \Shopware\CustomModels\Blisstribute\BlisstributeCoupon();
        $blisstributeCoupon->setVoucher($voucher)->setIsMoneyVoucher(false);

        $modelManager->persist($blisstributeCoupon);
        $modelManager->flush();
    }

    /**
     * Post Persist Voucher Attribute
     * Automating Money Voucher (de: Wertgutschein) automation
     *
     * @param Enlight_Event_EventArgs $args
     */
    public function postPersistVoucherAttribute(Enlight_Event_EventArgs $args)
    {
        $modelManager = $this->get('models');

        /** @var \Shopware\Models\Attribute\Voucher $attribute */
        $attribute = $args->get('entity');

        if (!is_null($attribute)) {            
            $plugin = $this->get('models')->getRepository('Shopware\Models\Plugin\Plugin')->findOneBy(array(
                'name' => 'NetiEasyCoupon',
                'active' => true
            ));
            
            if ($plugin) {
                $blisstributeCoupon = $modelManager->getRepository('Shopware\CustomModels\Blisstribute\BlisstributeCoupon')->findOneBy(array(
                    'voucher' => $attribute->getVoucher()
                ));

                $blisstributeCoupon->setIsMoneyVoucher($netiEasyCoupon);

                $modelManager->persist($blisstributeCoupon);
                $modelManager->flush();
            }
        }
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function postRemoveVoucher(Enlight_Event_EventArgs $args)
    {
        $modelManager = $this->get('models');

        /** @var \Shopware\Models\Voucher\Voucher $voucher */
        $voucher = $args->get('entity');

        $repository = $this->get('models')->getRepository('Shopware\CustomModels\Blisstribute\BlisstributeCoupon');
        $blisstributeCoupon = $repository->findByCoupon($voucher->getId());
        if ($blisstributeCoupon === null) {
            return;
        }

        $modelManager->remove($blisstributeCoupon);
        $modelManager->flush();
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function onModelsOrderOrderPostRemove(Enlight_Event_EventArgs $args)
    {
        $modelManager = $this->get('models');

        /** @var \Shopware\Models\Order\Order $order */
        $order = $args->get('entity');

        $repository = $this->get('models')->getRepository('Shopware\CustomModels\Blisstribute\BlisstributeOrder');
        $blisstributeOrder = $repository->findByOrder($order);
        if ($blisstributeOrder === null) {
            return;
        }

        $modelManager->remove($blisstributeOrder);
        $modelManager->flush();
    }

    /**
     * transfer all necessary article - event triggered by cron job
     *
     * @param Enlight_Event_EventArgs $args
     *
     * @return bool
     *
     * @deprecated removed in version 0.2.29
     */
    public function onBlisstributeArticleSync(Enlight_Event_EventArgs $args)
    {
        require_once __DIR__ . '/Components/Blisstribute/Article/Sync.php';

        try {
            $this->registerCustomModels();

            $controller = new Shopware_Components_Blisstribute_Article_Sync($this->Config());
            $controller->processBatchArticleSync();
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * transfer all open orders to blisstribute - event triggered by cron job
     *
     * @param Enlight_Event_EventArgs $eventArgs
     *
     * @return bool
     *
     * @deprecated removed in version 0.2.30
     */
    public function onBlisstributeOrderExport(Enlight_Event_EventArgs $eventArgs)
    {
        require_once __DIR__ . '/Components/Blisstribute/Order/Sync.php';

        try {
            $this->registerCustomModels();

            $orderSyncCtrl = new Shopware_Components_Blisstribute_Order_Sync($this->Config());
            $orderSyncCtrl->processBatchOrderSync();
        } catch (Exception $ex) {
            return false;
        }

        return true;
    }

    /**
     * add order reference for export to blisstribute
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return bool
     */
    public function onRegisterOrder(Enlight_Event_EventArgs $eventArgs)
    {
        $this->registerOrder($eventArgs);
        return true;
    }

    /**
     * export order to blisstribute
     *
     * @param Enlight_Event_EventArgs $eventArgs
     *
     * @return bool|string
     */
    public function onOrderFinished(Enlight_Event_EventArgs $eventArgs)
    {
        $blisstributeOrder = $this->registerOrder($eventArgs);
        if ($blisstributeOrder === null || !$blisstributeOrder) {
            $this->logInfo('blisstributeOrder is null! onOrderFinished failed!');
            return false;
        }

        $order = $blisstributeOrder->getOrder();
        if ($order === null || !$order) {
            $this->logInfo('order is null! onOrderFinished failed!');
            return false;
        }

        $this->logInfo('processing order ' . $order->getNumber());
        if ($this->get('config')->get('googleAddressValidation')) {
            /** @var Shopware_Components_Blisstribute_Order_GoogleAddressValidator $addressValidator */
            $addressValidator = $this->get('blisstribute.google_address_validator');
            $addressValidator->validateAddress($blisstributeOrder, $this->get('config'));
        }

        if ($blisstributeOrder->getStatus() == \Shopware\CustomModels\Blisstribute\BlisstributeOrder::EXPORT_STATUS_TRANSFERRED
            || $blisstributeOrder->getStatus() == \Shopware\CustomModels\Blisstribute\BlisstributeOrder::EXPORT_STATUS_IN_TRANSFER
        ) {
            return true;
        }

        if (!$this->get('config')->get('blisstribute-auto-sync-order')) {
            $this->logDebug('order sync cancelled due to disabled automatic sync');
            return true;
        }

        try {
            require_once __DIR__ . '/Components/Blisstribute/Order/Sync.php';
            $orderSync = new Shopware_Components_Blisstribute_Order_Sync($this->Config());
            $result = $orderSync->processSingleOrderSync($blisstributeOrder);
        } catch (Exception $ex) {
            return $ex->getMessage();
        }

        return $result;
    }

    /**
     * register order to blisstribute order export
     *
     * @param Enlight_Event_EventArgs $eventArgs
     *
     * @return \Shopware\CustomModels\Blisstribute\BlisstributeOrder|null
     */
    protected function registerOrder(Enlight_Event_EventArgs $eventArgs)
    {
        $orderProxy = $eventArgs->get('subject');

        $modelManager = $this->get('models');
        $orderRepository = $modelManager->getRepository('Shopware\Models\Order\Order');

        /** @var \Shopware\Models\Order\Order $order */
        $order = $orderRepository->findOneBy(array('number' => $orderProxy->sOrderNumber));
        if ($order === null) {
            return null;
        }

        /** @var \Shopware\CustomModels\Blisstribute\BlisstributeOrderRepository $blisstributeOrderRepository */
        $blisstributeOrderRepository = $modelManager->getRepository('Shopware\CustomModels\Blisstribute\BlisstributeOrder');
        $blisstributeOrder = $blisstributeOrderRepository->findByOrder($order);
        if ($blisstributeOrder === null) {
            $blisstributeOrder = new \Shopware\CustomModels\Blisstribute\BlisstributeOrder();
            $blisstributeOrder->setLastCronAt(new DateTime())
                ->setOrder($order)
                ->setStatus(\Shopware\CustomModels\Blisstribute\BlisstributeOrder::EXPORT_STATUS_CREATION_PENDING)
                ->setTries(0);

            $modelManager->persist($blisstributeOrder);
            $modelManager->flush();
        }

        return $blisstributeOrder;
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
                'Shopware\CustomModels\Blisstribute\BlisstributeArticle' =>
                    "INSERT IGNORE INTO s_plugin_blisstribute_articles (created_at, modified_at, last_cron_at, "
                    . "s_article_id, trigger_deleted, trigger_sync, tries, comment) SELECT CURRENT_TIMESTAMP, "
                    . "CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, a.id, 0, 1, 0, NULL FROM s_articles AS a",
                'Shopware\CustomModels\Blisstribute\BlisstributeArticleType' =>
                    "INSERT IGNORE INTO s_plugin_blisstribute_article_type (created_at, modified_at, s_filter_id, "
                    . "article_type) SELECT CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, id, 4 FROM s_filter",
                'Shopware\CustomModels\Blisstribute\BlisstributeShipment' =>
                    "INSERT IGNORE INTO s_plugin_blisstribute_shipment (mapping_class_name, s_premium_dispatch_id) "
                    . "SELECT NULL, pd.id FROM s_premium_dispatch AS pd",
                'Shopware\CustomModels\Blisstribute\BlisstributePayment' =>
                    "INSERT IGNORE INTO s_plugin_blisstribute_payment (mapping_class_name, flag_payed, "
                    . "s_core_paymentmeans_id) SELECT NULL, 0, cp.id FROM s_core_paymentmeans AS cp",
                'Shopware\CustomModels\Blisstribute\BlisstributeShop' =>
                    "INSERT IGNORE INTO s_plugin_blisstribute_shop (s_shop_id, advertising_medium_code) "
                    . "SELECT s.id, '' FROM s_core_shops AS s",
                'Shopware\CustomModels\Blisstribute\BlisstributeCoupon' =>
                    "INSERT IGNORE INTO s_plugin_blisstribute_coupon (s_voucher_id, flag_money_voucher) "
                    . "SELECT v.id, 0 FROM s_emarketing_vouchers AS v",
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
