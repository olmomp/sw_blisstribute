<?php

namespace Shopware\ExitBBlisstribute\Subscribers;

use \Enlight\Event\SubscriberInterface;
use \Shopware\Components\DependencyInjection\Container;

class ControllerPathSubscriber implements SubscriberInterface
{
    /**
     * @var Container
     */
    private $container;

    /**
     * SearchBundleSubscriber constructor.
     * @param Container $container
     */
    public function __construct()
    {
        $this->container = Shopware()->Container();
    }
    
    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributeArticle' => 'getArticleSyncController',
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributeArticleSyncCron' => 'getArticleSyncCronController',
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributeArticleType' => 'getArticleTypeController',
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributeOrder' => 'getOrderController',
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributeOrderSyncCron' => 'getOrderSyncCronController',
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributeShipmentMapping' => 'getShipmentMappingController',
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributePaymentMapping' => 'getPaymentMappingController',
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributeShopMapping' => 'getShopMappingController',
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributeCouponMapping' => 'getCouponMappingController',
            
            // api controllers
            'Enlight_Controller_Dispatcher_ControllerPath_Api_Btorders' => 'onGetBtordersApiController',
            'Enlight_Controller_Dispatcher_ControllerPath_Api_Btarticles' => 'onGetBtarticlesApiController',
            'Enlight_Controller_Dispatcher_ControllerPath_Api_Btarticlestock' => 'onGetBtarticlestockApiController' 
        ];
    }
    
    /**
     * register directories
     *
     * @return void
     */
    protected function registerDirs()
    {
        $this->container->get('template')->addTemplateDir(__DIR__ . '/../Views/', 'blisstribute');
        $this->container->get('snippets')->addConfigDir(__DIR__ . '/../Snippets/');
    }

    /**
     * return article type controller path
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return string
     */
    public function getArticleTypeController(\Enlight_Event_EventArgs $eventArgs)
    {
        $this->registerDirs();
        return __DIR__ . '/../Controllers/Backend/BlisstributeArticleType.php';
    }

    /**
     * return article sync controller path

     *
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return string
     */
    public function getArticleSyncController(\Enlight_Event_EventArgs $eventArgs)
    {
        $this->registerDirs();
        return __DIR__ . '/../Controllers/Backend/BlisstributeArticle.php';
    }

    /**
     * return article sync cron controller path
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return string
     */
    public function getArticleSyncCronController(\Enlight_Event_EventArgs $eventArgs)
    {
        $this->registerDirs();
        return __DIR__ . '/../Controllers/Backend/BlisstributeArticleSyncCron.php';
    }

    /**
     * return order export controller path
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return string
     */
    public function getOrderController(\Enlight_Event_EventArgs $eventArgs)
    {
        $this->registerDirs();
        return __DIR__ . '/../Controllers/Backend/BlisstributeOrder.php';
    }

    /**
     * return order sync cron controller path
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return string
     */
    public function getOrderSyncCronController(\Enlight_Event_EventArgs $eventArgs)
    {
        $this->registerDirs();
        return __DIR__ . '/../Controllers/Backend/BlisstributeOrderSyncCron.php';
    }

    /**
     * return shipment mapping controller path
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return string
     */
    public function getShipmentMappingController(\Enlight_Event_EventArgs $eventArgs)
    {
        $this->registerDirs();
        return __DIR__ . '/../Controllers/Backend/BlisstributeShipmentMapping.php';
    }

    /**
     * return payment mapping controller path
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return string
     */
    public function getPaymentMappingController(\Enlight_Event_EventArgs $eventArgs)
    {
        $this->registerDirs();
        return __DIR__ . '/../Controllers/Backend/BlisstributePaymentMapping.php';
    }

    /**
     * return payment mapping controller path
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return string
     */
    public function getShopMappingController(\Enlight_Event_EventArgs $eventArgs)
    {
        $this->registerDirs();
        return __DIR__ . '/../Controllers/Backend/BlisstributeShopMapping.php';
    }

    /**
     * return payment mapping controller path
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return string
     */
    public function getCouponMappingController(\Enlight_Event_EventArgs $eventArgs)
    {
        $this->registerDirs();
        return __DIR__ . '/../Controllers/Backend/BlisstributeCouponMapping.php';
    }
    
    /**
     * @return string
     */
    public function onGetBtordersApiController()
    {
        return __DIR__ . '/../Controllers/Api/Btorders.php';
    }
    
    /**
     * @return string
     */
    public function onGetBtarticlesApiController()
    {
        return __DIR__ . '/../Controllers/Api/Btarticles.php';
    }

    /**
     * @return string
     */
    public function onGetBtarticlestockApiController()
    {
        return __DIR__ . '/../Controllers/Api/Btarticlestock.php';
    }
}
