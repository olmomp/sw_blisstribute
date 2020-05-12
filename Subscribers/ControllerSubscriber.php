<?php

namespace Shopware\ExitBBlisstribute\Subscribers;

use \Enlight\Event\SubscriberInterface;
use \Shopware\Components\DependencyInjection\Container;

class ControllerSubscriber implements SubscriberInterface
{
    /**
     * @var Container
     */
    private $container;

    /**
     * ControllerSubscriber constructor
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
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributeCore' => 'getCoreController',
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributeOrderSyncCron' => 'getOrderSyncCronController',
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributePaymentMapping' => 'getPaymentMappingController',
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_BlisstributeCouponMapping' => 'getCouponMappingController',

            // api controllers
            'Enlight_Controller_Dispatcher_ControllerPath_Api_Btorders' => 'getBtordersApiController',
            'Enlight_Controller_Dispatcher_ControllerPath_Api_Btarticles' => 'getBtarticlesApiController',
            'Enlight_Controller_Dispatcher_ControllerPath_Api_Btarticlestock' => 'getBtarticlestockApiController',

            // others
            'Enlight_Controller_Action_PostDispatchSecure_Backend_Index' => 'onActionPostDispatchSecureBackendIndex',
            'Enlight_Controller_Action_PostDispatch_Backend_Article' => 'onActionPostDispatchBackendArticle'
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
     * @return string
     */
    public function checkPluginAction(\Enlight_Event_EventArgs $eventArgs)
    {
        return true;
    }

    /**
     * return article type controller path
     *
     * @return string
     */
    public function getCoreController(\Enlight_Event_EventArgs $eventArgs)
    {
        return __DIR__ . '/../Controllers/Backend/BlisstributeCore.php';
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
    public function getCouponMappingController(\Enlight_Event_EventArgs $eventArgs)
    {
        $this->registerDirs();
        return __DIR__ . '/../Controllers/Backend/BlisstributeCouponMapping.php';
    }

    /**
     * @return string
     */
    public function getBtordersApiController(\Enlight_Event_EventArgs $eventArg)
    {
        return __DIR__ . '/../Controllers/Api/Btorders.php';
    }

    /**
     * @return string
     */
    public function getBtarticlesApiController(\Enlight_Event_EventArgs $eventArg)
    {
        return __DIR__ . '/../Controllers/Api/Btarticles.php';
    }

    /**
     * @return string
     */
    public function getBtarticlestockApiController(\Enlight_Event_EventArgs $eventArg)
    {
        return __DIR__ . '/../Controllers/Api/Btarticlestock.php';
    }

    /**
     * add plugin menu to backend
     */
    public function onActionPostDispatchSecureBackendIndex(\Enlight_Controller_ActionEventArgs $args)
    {
        /**@var $controller Shopware_Controllers_Frontend_Index */
        $controller = $args->getSubject();

        $view = $controller->View();

        //Add our plugin template directory to load our slogan extension.
        $view->addTemplateDir(__DIR__ . '/../Views/');

        $this->container->get('snippets')->addConfigDir(
            __DIR__ . '/../Snippets/'
        );

        if ($args->getRequest()->getActionName() === 'load') {
            $view->extendsTemplate('backend/index/view/exitb_blisstribute/menu.js');
        }
    }

    /**
     * add attribute to article
     *
     * @param Enlight_Event_EventArgs $args
     */
    public function onActionPostDispatchBackendArticle(\Enlight_Event_EventArgs $args)
    {
        $view = $args->getSubject()->View();
        $view->addTemplateDir(__DIR__ . '/../Views/');

        if ($args->getRequest()->getActionName() === 'load') {
            $view->extendsTemplate('backend/attributes_article/model/attribute.js');
        }
    }
}
