<?php

namespace Shopware\ExitBBlisstribute\Subscribers;

use \Enlight\Event\SubscriberInterface;
use \Shopware\Components\DependencyInjection\Container;

require_once __DIR__ . '/../Components/Blisstribute/Domain/LoggerTrait.php';
require_once __DIR__ . '/../Components/Blisstribute/Order/Sync.php';

class ModelSubscriber implements SubscriberInterface
{
    use \Shopware_Components_Blisstribute_Domain_LoggerTrait;

    /**
     * @var Container
     */
    private $container;

    /**
     * ModelSubscriber constructor
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
            // shopware models
            'Shopware\Models\Order\Order::preRemove' => 'preRemoveOrder',
            'Shopware\Models\Article\Article::postPersist' => 'postPersistArticle',
            'Shopware\Models\Article\Article::postUpdate' => 'postUpdateArticle',
            'Shopware\Models\Article\Article::preRemove' => 'preRemoveArticle',
            'Shopware\Models\Article\Detail::postPersist' => 'postPersistDetail',
            'Shopware\Models\Article\Detail::postUpdate' => 'postUpdateDetail',
            'Shopware\Models\Article\Detail::preRemove' => 'preRemoveDetail',
            'Shopware\Models\Property\Group::postPersist' => 'postPersistProperty',
            'Shopware\Models\Property\Group::preRemove' => 'preRemoveProperty',
            'Shopware\Models\Voucher\Voucher::postPersist' => 'postPersistVoucher',
            'Shopware\Models\Voucher\Voucher::preRemove' => 'preRemoveVoucher',
            'Shopware\Models\Payment\Payment::postPersist' => 'postPersistPayment',
            'Shopware\Models\Payment\Payment::preRemove' => 'preRemovePayment',

            // blisstribute models
            'Shopware\CustomModels\Blisstribute\BlisstributeOrder::prePersist' => 'prePersistBlisstributeOrder',
            'Shopware\CustomModels\Blisstribute\BlisstributeOrder::preUpdate' => 'preUpdateBlisstributeOrder',
            'Shopware\CustomModels\Blisstribute\BlisstributeShippingRequest::prePersist' => 'prePersistBlisstributeShippingRequest',
            'Shopware\CustomModels\Blisstribute\BlisstributeShippingRequest::preUpdate' => 'preUpdateBlisstributeShippingRequest',
            'Shopware\CustomModels\Blisstribute\BlisstributeShippingRequestItems::prePersist' => 'prePersistBlisstributeShippingRequestItem',
            'Shopware\CustomModels\Blisstribute\BlisstributeShippingRequestItems::preUpdate' => 'preUpdateBlisstributeShippingRequestItem',
            'Shopware\CustomModels\Blisstribute\BlisstributeArticle::preUpdate' => 'preUpdateBlisstributeArticle',
            'Shopware\CustomModels\Blisstribute\BlisstributeArticleType::prePersist' => 'prePersistBlisstributeArticleType',
            'Shopware\CustomModels\Blisstribute\BlisstributeArticleType::preUpdate' => 'preUpdateBlisstributeArticleType',

            // other events
            'Shopware_Modules_Order_SendMail_BeforeSend' => 'onOrderSendMailBeforeSend'
        ];
    }

    /**
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function preRemoveOrder(\Enlight_Event_EventArgs $eventArgs)
    {
        $modelManager = $this->container->get('models');

        /** @var \Shopware\Models\Order\Order $order */
        $order = $eventArgs->get('entity');
        if ($order == null || !($order instanceof \Shopware\Models\Order\Order)) {
            return;
        }

        $repository = $modelManager->getRepository('\Shopware\CustomModels\Blisstribute\BlisstributeOrder');
        $blisstributeOrder = $repository->findByOrder($order);
        if ($blisstributeOrder === null) {
            return;
        }

        $modelManager->remove($blisstributeOrder);
        $modelManager->flush();
    }

    /**
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function postPersistArticle(\Enlight_Event_EventArgs $eventArgs)
    {
        \Shopware()->PluginLogger()->log(\Monolog\Logger::DEBUG, 'modelSubscriber::postPersistArticle start');
        $modelManager = $this->container->get('models');

        /** @var \Shopware\Models\Article\Article $article */
        $article = $eventArgs->get('entity');
        if ($article == null || !($article instanceof \Shopware\Models\Article\Article)) {
            \Shopware()->PluginLogger()->log(\Monolog\Logger::DEBUG, 'modelSubscriber::postPersistArticle done - no article');
            return;
        }

        $repository = $modelManager->getRepository('\Shopware\CustomModels\Blisstribute\BlisstributeArticle');
        $blisstributeArticle = $repository->findOneBy(['article' => $article]);
        if ($blisstributeArticle == null) {
            $blisstributeArticle = new \Shopware\CustomModels\Blisstribute\BlisstributeArticle();
            $blisstributeArticle->setLastCronAt(new \DateTime())
                ->setArticle($article)
                ->setTriggerSync(true)
                ->setTries(0)
                ->setComment(null);
        }

        $blisstributeArticle->setTriggerSync(true)
            ->setTries(0)
            ->setComment(null);

        $modelManager->persist($blisstributeArticle);
        \Shopware()->PluginLogger()->log(\Monolog\Logger::DEBUG, 'modelSubscriber::postPersistArticle done - trigger flush');
        $modelManager->flush();
        \Shopware()->PluginLogger()->log(\Monolog\Logger::DEBUG, 'modelSubscriber::postPersistArticle flush done');
    }

    /**
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function postUpdateArticle(\Enlight_Event_EventArgs $eventArgs)
    {
        \Shopware()->PluginLogger()->log(\Monolog\Logger::DEBUG, 'modelSubscriber::postUpdateArticle start');
        $modelManager = $this->container->get('models');

        /** @var \Shopware\Models\Article\Article $article */
        $article = $eventArgs->get('entity');
        if ($article == null || !($article instanceof \Shopware\Models\Article\Article)) {
            \Shopware()->PluginLogger()->log(\Monolog\Logger::DEBUG, 'modelSubscriber::postUpdateArticle done - no article');
            return;
        }

        $repository = $modelManager->getRepository('\Shopware\CustomModels\Blisstribute\BlisstributeArticle');
        $blisstributeArticle = $repository->findOneBy(['article' => $article]);
        if ($blisstributeArticle == null) {
            $blisstributeArticle = new \Shopware\CustomModels\Blisstribute\BlisstributeArticle();
            $blisstributeArticle->setArticle($article);
        }

        if ($blisstributeArticle->isTriggerSync()) {
            \Shopware()->PluginLogger()->log(\Monolog\Logger::DEBUG, 'modelSubscriber::postUpdateArticle done - trigger sync already set');
            return;
        }

        $blisstributeArticle->setLastCronAt(new \DateTime())
            ->setTriggerSync(true)
            ->setTries(0)
            ->setComment(null);

        $modelManager->persist($blisstributeArticle);
        \Shopware()->PluginLogger()->log(\Monolog\Logger::DEBUG, 'modelSubscriber::postUpdateArticle done - trigger flush');
        $modelManager->flush();
        \Shopware()->PluginLogger()->log(\Monolog\Logger::DEBUG, 'modelSubscriber::postUpdateArticle flush done');
    }

    /**
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function preRemoveArticle(\Enlight_Event_EventArgs $eventArgs)
    {
        $modelManager = $this->container->get('models');

        /** @var \Shopware\Models\Article\Article $article */
        $article = $eventArgs->get('entity');
        if ($article == null || !($article instanceof \Shopware\Models\Article\Article)) {
            return;
        }

        $repository = $modelManager->getRepository('\Shopware\CustomModels\Blisstribute\BlisstributeArticle');
        $blisstributeArticle = $repository->findOneBy(['article' => $article]);
        if ($blisstributeArticle === null) {
            return;
        }

        $modelManager->remove($blisstributeArticle);
        $modelManager->flush();
    }

    /**
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function postPersistDetail(\Enlight_Event_EventArgs $eventArgs)
    {
        $modelManager = $this->container->get('models');

        /** @var \Shopware\Models\Article\Detail $detail */
        $detail = $eventArgs->get('entity');
        if ($detail == null || !($detail instanceof \Shopware\Models\Article\Detail)) {
            return;
        }

        // load article
        $repository = $modelManager->getRepository('\Shopware\CustomModels\Blisstribute\BlisstributeArticle');
        $blisstributeArticle = $repository->findOneBy(['article' => $detail->getArticle()]);
        if ($blisstributeArticle === null) {
            $blisstributeArticle = new \Shopware\CustomModels\Blisstribute\BlisstributeArticle();
            $blisstributeArticle->setArticle($detail->getArticle());
        }

        if ($blisstributeArticle->isTriggerSync()) {
            return;
        }

        $blisstributeArticle->setLastCronAt(new \DateTime())
            ->setTriggerSync(true)
            ->setTries(0)
            ->setComment(null);

        $modelManager->persist($blisstributeArticle);
        $modelManager->flush();
    }

    /**
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function postUpdateDetail(\Enlight_Event_EventArgs $eventArgs)
    {
        \Shopware()->PluginLogger()->log(\Monolog\Logger::DEBUG, 'modelSubscriber::postUpdateDetail start');
        $modelManager = $this->container->get('models');

        /** @var \Shopware\Models\Article\Detail $detail */
        $detail = $eventArgs->get('entity');
        if ($detail == null || !($detail instanceof \Shopware\Models\Article\Detail)) {
            \Shopware()->PluginLogger()->log(\Monolog\Logger::DEBUG, 'modelSubscriber::postUpdateDetail done - no detail');
            return;
        }

        $articleRepository = $modelManager->getRepository('\Shopware\CustomModels\Blisstribute\BlisstributeArticle');

        /* @var Shopware\CustomModels\Blisstribute\BlisstributeArticle $article */
        $blisstributeArticle = $articleRepository->findOneBy(['article' => $detail->getArticle()]);

        if ($blisstributeArticle === null) {
            $blisstributeArticle = new \Shopware\CustomModels\Blisstribute\BlisstributeArticle();
            $blisstributeArticle->setArticle($detail->getArticle());
        }

        if ($blisstributeArticle->isTriggerSync()) {
            \Shopware()->PluginLogger()->log(\Monolog\Logger::DEBUG, 'modelSubscriber::postUpdateDetail done - trigger sync already set');
            return;
        }

        $blisstributeArticle->setLastCronAt(new \DateTime())
            ->setTriggerSync(true)
            ->setTries(0)
            ->setComment(null);

        $modelManager->persist($blisstributeArticle);
        \Shopware()->PluginLogger()->log(\Monolog\Logger::DEBUG, 'modelSubscriber::postUpdateDetail done - trigger flush');
        $modelManager->flush();
        \Shopware()->PluginLogger()->log(\Monolog\Logger::DEBUG, 'modelSubscriber::postUpdateDetail flush done');
    }

    /**
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function preRemoveDetail(\Enlight_Event_EventArgs $eventArgs)
    {
        $modelManager = $this->container->get('models');

        /** @var \Shopware\Models\Article\Detail $detail */
        $detail = $eventArgs->get('entity');
        if ($detail == null || !($detail instanceof \Shopware\Models\Article\Detail)) {
            return;
        }

        $repository = $modelManager->getRepository('\Shopware\CustomModels\Blisstribute\BlisstributeArticle');

        /* @var Shopware\CustomModels\Blisstribute\BlisstributeArticle $blisstributeArticle */
        $blisstributeArticle = $repository->findOneBy(['article' => $detail->getArticle()]);

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
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function postPersistProperty(\Enlight_Event_EventArgs $eventArgs)
    {
        $modelManager = $this->container->get('models');

        /** @var \Shopware\Models\Property\Group $entry */
        $entity = $eventArgs->get('entity');

        $articleType = new \Shopware\CustomModels\Blisstribute\BlisstributeArticleType();
        $articleType->setFilter($entity);

        $modelManager->persist($articleType);
        $modelManager->flush();
    }

    /**
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function preRemoveProperty(\Enlight_Event_EventArgs $eventArgs)
    {
        $modelManager = $this->container->get('models');
        $entity = $eventArgs->get('entity');

        /* @var Shopware\CustomModels\Blisstribute\BlisstributeArticleTypeRepository $articleTypeRepository */
        $articleTypeRepository = $modelManager->getRepository('\Shopware\CustomModels\Blisstribute\BlisstributeArticleType');
        $articleType = $articleTypeRepository->fetchByFilterType($entity->getId());
        if ($articleType === null) {
            return;
        }

        $modelManager->remove($articleType);
        $modelManager->flush();
    }

    /**
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
//    public function postPersistShop(\Enlight_Event_EventArgs $eventArgs)
//    {
//        $modelManager = $this->container->get('models');
//
//        /** @var \Shopware\Models\Shop\Shop $shop */
//        $shop = $eventArgs->get('entity');
//
//        $blisstributeShop = new \Shopware\CustomModels\Blisstribute\BlisstributeShop();
//        $blisstributeShop->setShop($shop)->setAdvertisingMediumCode('');
//
//        $modelManager->persist($blisstributeShop);
//        $modelManager->flush();
//    }

    /**
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
//    public function preRemoveShop(\Enlight_Event_EventArgs $eventArgs)
//    {
//        $modelManager = $this->container->get('models');
//
//        /** @var Shopware\Models\Shop\ $blisstributeShop */
//        $shop = $eventArgs->get('entity');
//
//        $repository = $modelManager->getRepository('\Shopware\CustomModels\Blisstribute\BlisstributeShop');
//        $blisstributeShop = $repository->findOneByShop($shop->getId());
//        if ($blisstributeShop === null) {
//            return;
//        }
//
//        $modelManager->remove($blisstributeShop);
//        $modelManager->flush();
//    }

    /**
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function postPersistVoucher(\Enlight_Event_EventArgs $eventArgs)
    {
        $modelManager = $this->container->get('models');

        /** @var \Shopware\Models\Voucher\Voucher $voucher */
        $voucher = $eventArgs->get('entity');

        $blisstributeCoupon = new \Shopware\CustomModels\Blisstribute\BlisstributeCoupon();
        $blisstributeCoupon->setVoucher($voucher)->setIsMoneyVoucher(false);

        $modelManager->persist($blisstributeCoupon);
        $modelManager->flush();
    }

    /**
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function preRemoveVoucher(\Enlight_Event_EventArgs $eventArgs)
    {
        $modelManager = $this->container->get('models');

        /** @var \Shopware\Models\Voucher\Voucher $voucher */
        $voucher = $eventArgs->get('entity');

        $repository = $modelManager->getRepository('\Shopware\CustomModels\Blisstribute\BlisstributeCoupon');
        $blisstributeCoupon = $repository->findByCoupon($voucher->getId());
        if ($blisstributeCoupon === null) {
            return;
        }

        $modelManager->remove($blisstributeCoupon);
        $modelManager->flush();
    }

    /**
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function postPersistPayment(\Enlight_Event_EventArgs $eventArgs)
    {
        $modelManager = $this->container->get('models');

        /** @var \Shopware\Models\Payment\Payment $payment */
        $payment = $eventArgs->get('entity');

        $blisstributePayment = new \Shopware\CustomModels\Blisstribute\BlisstributePayment();
        $blisstributePayment->setPayment($payment)->setIsPayed(false);

        $modelManager->persist($blisstributePayment);
        $modelManager->flush();
    }

    /**
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function preRemovePayment(\Enlight_Event_EventArgs $eventArgs)
    {
        $modelManager = $this->container->get('models');

        /** @var \Shopware\Models\Payment\Payment $payment */
        $payment = $eventArgs->get('entity');

        $repository = $modelManager->getRepository('\Shopware\CustomModels\Blisstribute\BlisstributePayment');
        $blisstributePayment = $repository->findOneByPayment($payment->getId());
        if ($blisstributePayment === null) {
            return;
        }

        $modelManager->remove($blisstributePayment);
        $modelManager->flush();
    }

    /**
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
//    public function postPersistDispatch(\Enlight_Event_EventArgs $eventArgs)
//    {
//        $modelManager = $this->container->get('models');
//
//        /** @var \Shopware\Models\Dispatch\Dispatch $dispatch */
//        $dispatch = $eventArgs->get('entity');
//
//        $blisstributeShipment = new \Shopware\CustomModels\Blisstribute\BlisstributeShipment();
//        $blisstributeShipment->setShipment($dispatch);
//
//        $modelManager->persist($blisstributeShipment);
//        $modelManager->flush();
//    }

//    /**
//     * @param \Enlight_Event_EventArgs $eventArgs
//     *
//     * @return void
//     */
//    public function preRemoveDispatch(\Enlight_Event_EventArgs $eventArgs)
//    {
//        $modelManager = $this->container->get('models');
//
//        /** @var \Shopware\Models\Dispatch\Dispatch $dispatch */
//        $dispatch = $eventArgs->get('entity');
//
//        $repository = $modelManager->getRepository('\Shopware\CustomModels\Blisstribute\BlisstributeShipment');
//        $blisstributeShipment = $repository->findOneByShipment($dispatch->getId());
//        if ($blisstributeShipment === null) {
//            return;
//        }
//
//        $modelManager->remove($blisstributeShipment);
//        $modelManager->flush();
//    }

    /**
     * blisstribute order event fired before db insert
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function prePersistBlisstributeOrder(\Enlight_Event_EventArgs $eventArgs)
    {
        $currentTime = new \DateTime();

        /** @var \Shopware\CustomModels\Blisstribute\BlisstributeOrder $entity */
        $entity = $eventArgs->get('entity');
        $entity->setCreatedAt($currentTime)
            ->setModifiedAt($currentTime);
    }

    /**
     * blisstribute event fired before db update
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function preUpdateBlisstributeOrder(\Enlight_Event_EventArgs $eventArgs)
    {
        $entity = $eventArgs->get('entity');
        $entity->setModifiedAt(new \DateTime());
    }

    /**
     * blisstribute order event fired before db insert
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function prePersistBlisstributeShippingRequest(\Enlight_Event_EventArgs $eventArgs)
    {
        $currentTime = new \DateTime();

        /** @var \Shopware\CustomModels\Blisstribute\BlisstributeShippingRequest $entity */
        $entity = $eventArgs->get('entity');
        $entity->setCreatedAt($currentTime)
            ->setModifiedAt($currentTime);
    }

    /**
     * blisstribute event fired before db update
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function preUpdateBlisstributeShippingRequest(\Enlight_Event_EventArgs $eventArgs)
    {
        /** @var \Shopware\CustomModels\Blisstribute\BlisstributeShippingRequest $entity */
        $entity = $eventArgs->get('entity');
        $entity->setModifiedAt(new \DateTime());
    }

    /**
     * blisstribute order event fired before db insert
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function prePersistBlisstributeShippingRequestItem(\Enlight_Event_EventArgs $eventArgs)
    {
        $currentTime = new \DateTime();

        /** @var \Shopware\CustomModels\Blisstribute\BlisstributeShippingRequestItems $entity */
        $entity = $eventArgs->get('entity');
        $entity->setCreatedAt($currentTime)
            ->setModifiedAt($currentTime);
    }

    /**
     * blisstribute event fired before db update
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function preUpdateBlisstributeShippingRequestItem(\Enlight_Event_EventArgs $eventArgs)
    {
        /** @var \Shopware\CustomModels\Blisstribute\BlisstributeShippingRequestItems $entity */
        $entity = $eventArgs->get('entity');
        $entity->setModifiedAt(new \DateTime());
    }

    /**
     * article type event fired before db update
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function preUpdateBlisstributeArticle(\Enlight_Event_EventArgs $eventArgs)
    {
        \Shopware()->PluginLogger()->log(\Monolog\Logger::DEBUG, 'modelSubscriber::postUpdateBlisstributeArticle start');
        /** @var \Shopware\CustomModels\Blisstribute\BlisstributeArticle $entity */
        $entity = $eventArgs->get('entity');
        $entity->setModifiedAt(new \DateTime());
        \Shopware()->PluginLogger()->log(\Monolog\Logger::DEBUG, 'modelSubscriber::postUpdateBlisstributeArticle done');
    }

    /**
     * article type event fired before create entity
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function prePersistBlisstributeArticleType(\Enlight_Event_EventArgs $eventArgs)
    {
        $currentTime = new \DateTime();

        /** @var \Shopware\CustomModels\Blisstribute\BlisstributeArticleType $entity */
        $entity = $eventArgs->get('entity');
        $entity->setCreatedAt($currentTime)
            ->setModifiedAt($currentTime);
    }

    /**
     * article type event fired before update
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function preUpdateBlisstributeArticleType(\Enlight_Event_EventArgs $eventArgs)
    {
        $articleIdCollection = [];

        $modelManager = $this->container->get('models');

        /* @var Shopware\CustomModels\Blisstribute\BlisstributeArticleType $entity */
        $entity = $eventArgs->get('entity');
        $entity->setModifiedAt(new \DateTime());

        $articles = Shopware()->Db()->query('UPDATE s_plugin_blisstribute_articles SET trigger_sync = 1, modified_at = NOW(), tries = 0 WHERE s_article_id IN (SELECT articleID FROM s_filter_articles WHERE valueID = ?)', [$entity->getFilter()->getId()]);
    }

    /**
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return bool
     */
    public function onOrderSendMailBeforeSend(\Enlight_Event_EventArgs $eventArgs)
    {
        $orderProxy = $eventArgs->get('subject');

        $pluginConfig = $this->container->get('plugins')->Backend()->ExitBBlisstribute()->Config();

        $modelManager = $this->container->get('models');
        $orderRepository = $modelManager->getRepository('\Shopware\Models\Order\Order');

        /** @var \Shopware\Models\Order\Order $order */
        $order = $orderRepository->findOneBy(['number' => $orderProxy->sOrderNumber]);
        if ($order === null) {
            $this->logDebug('order not found');
            return null;
        }

        /** @var \Shopware\CustomModels\Blisstribute\BlisstributeOrderRepository $blisstributeOrderRepository */
        $blisstributeOrderRepository = $modelManager->getRepository('\Shopware\CustomModels\Blisstribute\BlisstributeOrder');
        $blisstributeOrder = $blisstributeOrderRepository->findByOrder($order);
        if ($blisstributeOrder === null) {
            $status = \Shopware\CustomModels\Blisstribute\BlisstributeOrder::EXPORT_STATUS_CREATION_PENDING;

            if (!$pluginConfig->get('blisstribute-auto-sync-order')) {
                $status = \Shopware\CustomModels\Blisstribute\BlisstributeOrder::EXPORT_STATUS_NONE;
            }

            $blisstributeOrder = new \Shopware\CustomModels\Blisstribute\BlisstributeOrder();
            $blisstributeOrder->setLastCronAt(new \DateTime())
                ->setOrder($order)
                ->setStatus($status)
                ->setTries(0);

            $modelManager->persist($blisstributeOrder);
            $modelManager->flush();
        }

        if($pluginConfig->get('blisstribute-auto-sync-order'))
        {
            $this->transferOrder($blisstributeOrder);
            return true;
        }

        $this->logDebug('order sync cancelled due to disabled automatic sync');
    }

    /**
     * export order to blisstribute
     *
     * @param \Shopware\CustomModels\Blisstribute\BlisstributeOrder $blisstributeOrder
     *
     * @return bool|string
     */
    private function transferOrder(\Shopware\CustomModels\Blisstribute\BlisstributeOrder $blisstributeOrder)
    {
        $pluginConfig = $this->container->get('plugins')->Backend()->ExitBBlisstribute()->Config();

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

        if ($blisstributeOrder->getStatus() == \Shopware\CustomModels\Blisstribute\BlisstributeOrder::EXPORT_STATUS_TRANSFERRED
            || $blisstributeOrder->getStatus() == \Shopware\CustomModels\Blisstribute\BlisstributeOrder::EXPORT_STATUS_IN_TRANSFER
        ) {
            return true;
        }

        try {
            $orderSync = new \Shopware_Components_Blisstribute_Order_Sync($pluginConfig);
            $result = $orderSync->processSingleOrderSync($blisstributeOrder);
        } catch (Exception $ex) {
            return $ex->getMessage();
        }

        return $result;
    }
}
