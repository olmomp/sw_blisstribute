<?php

namespace ShopwarePlugins\ExitBBlisstribute\Subscribers;

use \Enlight\Event\SubscriberInterface;
use Shopware\Components\DependencyInjection\Container;

class ControllerPathSubscriber implements SubscriberInterface
{
    public function __construct()
    {
        $this->registerDirs();
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
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributeCouponMapping' => 'getCouponMappingController'            
        ];
    }
    
    /**
     * register directories
     *
     * @return void
     */
    protected function registerDirs()
    {
        $this->container->get('template')->addTemplateDir(__DIR__ . '/Views/', 'blisstribute');
        $this->container->get('snippets')->addConfigDir(__DIR__ . '/Snippets/');
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
        return $this->Path() . '/Controllers/Backend/BlisstributeArticleType.php';
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
        return $this->Path() . '/Controllers/Backend/BlisstributeArticle.php';
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
        return $this->Path() . '/Controllers/Backend/BlisstributeArticleSyncCron.php';
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
        return $this->Path() . '/Controllers/Backend/BlisstributeOrder.php';
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
        return $this->Path() . '/Controllers/Backend/BlisstributeOrderSyncCron.php';
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
        return $this->Path() . '/Controllers/Backend/BlisstributeShipmentMapping.php';
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
        return $this->Path() . '/Controllers/Backend/BlisstributePaymentMapping.php';
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
        return $this->Path() . '/Controllers/Backend/BlisstributeShopMapping.php';
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
        return $this->Path() . '/Controllers/Backend/BlisstributeCouponMapping.php';
    }
}
