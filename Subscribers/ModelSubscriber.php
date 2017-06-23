<?php

namespace Shopware\ExitBBlisstribute\Subscribers;

use \Enlight\Event\SubscriberInterface;
use \Shopware\Components\DependencyInjection\Container;

class ModelSubscriber implements SubscriberInterface
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
            // shopware models
            'Shopware\Models\Order\Order::postPersist' => 'postPersistOrder',
            'Shopware\Models\Order\Order::preRemove' => 'preRemoveOrder',
            'Shopware\Models\Article\Article::postPersist' => 'postPersistArticle',
            'Shopware\Models\Article\Article::preUpdate' => 'preUpdateArticle',
            'Shopware\Models\Article\Article::preRemove' => 'preRemoveArticle',
            'Shopware\Models\Article\Detail::postPersist' => 'postPersistDetail',
            'Shopware\Models\Article\Detail::preUpdate' => 'preUpdateDetail',
            'Shopware\Models\Article\Detail::preRemove' => 'preRemoveDetail',
            'Shopware\Models\Property\Group::postPersist' => 'postPersistProperty',
            'Shopware\Models\Property\Group::preRemove' => 'preRemoveProperty',
            'Shopware\Models\Shop\Shop::postPersist' => 'postPersistShop',
            'Shopware\Models\Shop\Shop::preRemove' => 'preRemoveShop',
            'Shopware\Models\Voucher\Voucher::postPersist' => 'postPersistVoucher',
            'Shopware\Models\Voucher\Voucher::preRemove' => 'preRemoveVoucher',
            
            // blisstribute models
            'Shopware\CustomModels\Blisstribute\BlisstributeOrder::postPersist' => 'postPersistBlisstributeOrder',
            'Shopware\CustomModels\Blisstribute\BlisstributeOrder::preUpdate' => 'preUpdateBlisstributeOrder',
            'Shopware\CustomModels\Blisstribute\BlisstributeShippingRequest::postPersist' => 'postPersistBlisstributeShippingRequest',
            'Shopware\CustomModels\Blisstribute\BlisstributeShippingRequest::preUpdate' => 'preUpdateBlisstributeShippingRequest',
            'Shopware\CustomModels\Blisstribute\BlisstributeShippingRequestItems::postPersist' => 'postPersistBlisstributeShippingRequestItem',
            'Shopware\CustomModels\Blisstribute\BlisstributeShippingRequestItems::preUpdate' => 'preUpdateBlisstributeShippingRequestItem',
            'Shopware\CustomModels\Blisstribute\BlisstributeArticle::postPersist' => 'postPersistBlisstributeArticle',
            'Shopware\CustomModels\Blisstribute\BlisstributeArticle::preUpdate' => 'preUpdateBlisstributeArticle',
            'Shopware\CustomModels\Blisstribute\BlisstributeArticleType::postPersist' => 'postPersistBlisstributeArticleType',
            'Shopware\CustomModels\Blisstribute\BlisstributeArticleType::preUpdate' => 'preUpdateBlisstributeArticleType'            
        ];
    }
    
    /**
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return bool
     */
    public function postPersistOrder(\Enlight_Event_EventArgs $eventArgs)
    {
        $this->onOrderFinished($eventArgs);
        return true;
    }
    
    /**
     * @param \\Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function preRemoveOrder(\Enlight_Event_EventArgs $args)
    {
        $modelManager = $this->container->get('models');

        /** @var \Shopware\Models\Order\Order $order */
        $order = $args->get('entity');

        $repository = $this->container->get('models')->getRepository('Shopware\CustomModels\Blisstribute\BlisstributeOrder');
        $blisstributeOrder = $repository->findByOrder($order);
        if ($blisstributeOrder === null) {
            return;
        }

        $modelManager->remove($blisstributeOrder);
        $modelManager->flush();
    }
    
    /**
     * @param \Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function postPersistArticle(\Enlight_Event_EventArgs $args)
    {
        $modelManager = $this->container->get('models');

        /** @var \Shopware\Models\Article\Article $article */
        $article = $args->get('entity');

        $blisstributeArticle = new Shopware\CustomModels\Blisstribute\BlisstributeArticle();
        $blisstributeArticle->setLastCronAt(new \DateTime())
            ->setArticle($article)
            ->setTriggerSync(true)
            ->setTries(0)
            ->setComment(null);

        $modelManager->persist($blisstributeArticle);
        $modelManager->flush();
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function preUpdateArticle(\Enlight_Event_EventArgs $args)
    {
        $modelManager = $this->container->get('models');

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

        $blisstributeArticle->setLastCronAt(new \DateTime())
            ->setTriggerSync(true)
            ->setTries(0)
            ->setComment(null);

        $modelManager->persist($blisstributeArticle);
        $modelManager->flush();
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function preRemoveArticle(\Enlight_Event_EventArgs $args)
    {
        $modelManager = $this->container->get('models');

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
     * @param \Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function postPersistDetail(\Enlight_Event_EventArgs $args)
    {
        $modelManager = $this->container->get('models');
        
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

        $blisstributeArticle->setLastCronAt(new \DateTime())
            ->setTriggerSync(true)
            ->setTries(0)
            ->setComment(null);

        $modelManager->persist($blisstributeArticle);
        $modelManager->flush();
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function preUpdateDetail(\Enlight_Event_EventArgs $args)
    {
        $modelManager = $this->container->get('models');

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

        $blisstributeArticle->setLastCronAt(new \DateTime())
            ->setTriggerSync(true)
            ->setTries(0)
            ->setComment(null);

        $modelManager->persist($blisstributeArticle);
        $modelManager->flush();
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function preRemoveDetail(\Enlight_Event_EventArgs $args)
    {
        $modelManager = $this->container->get('models');

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
     * @param \Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function postPersistProperty(\Enlight_Event_EventArgs $args)
    {
        $modelManager = $this->container->get('models');
        $entity = $args->get('entity');

        $articleType = new Shopware\CustomModels\Blisstribute\BlisstributeArticleType();
        $articleType->setFilter($entity);
        $articleType->setArticleType(0);

        $modelManager->persist($articleType);
        $modelManager->flush();
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function preRemoveProperty(\Enlight_Event_EventArgs $args)
    {
        $modelManager = $this->container->get('models');
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
     * @param \\Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function postPersistShop(\Enlight_Event_EventArgs $args)
    {
        $modelManager = $this->container->get('models');

        /** @var \Shopware\Models\Shop\Shop $shop */
        $shop = $args->get('entity');

        $blisstributeShop = new \Shopware\CustomModels\Blisstribute\BlisstributeShop();
        $blisstributeShop->setShop($shop)->setAdvertisingMediumCode('');

        $modelManager->persist($blisstributeShop);
        $modelManager->flush();
    }

    /**
     * @param \\Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function preRemoveShop(\Enlight_Event_EventArgs $args)
    {
        $modelManager = $this->container->get('models');

        /** @var Shopware\Models\Shop\ $blisstributeShop */
        $shop = $args->get('entity');

        $repository = $this->container->get('models')->getRepository('Shopware\CustomModels\Blisstribute\BlisstributeShop');
        $blisstributeShop = $repository->findOneByShop($shop->getId());
        if ($blisstributeShop === null) {
            return;
        }

        $modelManager->remove($blisstributeShop);
        $modelManager->flush();
    }

    /**
     * @param \\Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function postPersistVoucher(\Enlight_Event_EventArgs $args)
    {
        $modelManager = $this->container->get('models');

        /** @var \Shopware\Models\Voucher\Voucher $voucher */
        $voucher = $args->get('entity');

        $blisstributeCoupon = new \Shopware\CustomModels\Blisstribute\BlisstributeCoupon();
        $blisstributeCoupon->setVoucher($voucher)->setIsMoneyVoucher(false);

        $modelManager->persist($blisstributeCoupon);
        $modelManager->flush();
    }

    /**
     * @param \\Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function preRemoveVoucher(\Enlight_Event_EventArgs $args)
    {
        $modelManager = $this->container->get('models');

        /** @var \Shopware\Models\Voucher\Voucher $voucher */
        $voucher = $args->get('entity');

        $repository = $this->container->get('models')->getRepository('Shopware\CustomModels\Blisstribute\BlisstributeCoupon');
        $blisstributeCoupon = $repository->findByCoupon($voucher->getId());
        if ($blisstributeCoupon === null) {
            return;
        }

        $modelManager->remove($blisstributeCoupon);
        $modelManager->flush();
    }
    
    /**
     * blisstribute order event fired before db insert
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function postPersistBlisstributeOrder(\Enlight_Event_EventArgs $eventArgs)
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
    public function postPersistBlisstributeShippingRequest(\Enlight_Event_EventArgs $eventArgs)
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
    public function postPersistBlisstributeShippingRequestItem(\Enlight_Event_EventArgs $eventArgs)
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
     * article type event fired before create entity
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function postPersistBlisstributeArticle(\Enlight_Event_EventArgs $eventArgs)
    {
        $currentTime = new \DateTime();

        /** @var \Shopware\CustomModels\Blisstribute\BlisstributeArticleType $entity */
        $entity = $eventArgs->get('entity');
        $entity->setCreatedAt($currentTime)
            ->setModifiedAt($currentTime);
    }

    /**
     * article type event fired before db update
     *
     * @param \Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function preUpdateBlisstributeArticle(\Enlight_Event_EventArgs $args)
    {
        /** @var \Shopware\CustomModels\Blisstribute\BlisstributeArticle $entity */
        $entity = $args->get('entity');
        $entity->setModifiedAt(new \DateTime());
    }   

    /**
     * article type event fired before create entity
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return void
     */
    public function postPersistBlisstributeArticleType(\Enlight_Event_EventArgs $eventArgs)
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
     * @param \Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function preUpdateBlisstributeArticleType(\Enlight_Event_EventArgs $args)
    {
        $articleIdCollection = array();

        $modelManager = $this->container->get('models');

        /* @var Shopware\CustomModels\Blisstribute\BlisstributeArticleType $entity */
        $entity = $args->get('entity');
        $entity->setModifiedAt(new \DateTime());

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
            $currentArticle->setModifiedAt(new \DateTime());
            $currentArticle->setTriggerSync(true);
            $currentArticle->setTries(0);

            $modelManager->persist($currentArticle);
        }

        $modelManager->flush();
    }
    
    /**
     * add order reference for export to blisstribute
     *
     * @param \\Enlight_Event_EventArgs $eventArgs
     *
     * @return bool
     */
    private function onRegisterOrder(\Enlight_Event_EventArgs $eventArgs)
    {
        $this->registerOrder($eventArgs);
        return true;
    }
    
    /**
     * export order to blisstribute
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return bool|string
     */
    public function onOrderFinished(\Enlight_Event_EventArgs $eventArgs)
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
        if ($this->container->get('config')->get('googleAddressValidation')) {
            /** @var Shopware_Components_Blisstribute_Order_GoogleAddressValidator $addressValidator */
            $addressValidator = $this->container->get('blisstribute.google_address_validator');
            $addressValidator->validateAddress($blisstributeOrder, $this->container->get('config'));
        }

        if ($blisstributeOrder->getStatus() == \Shopware\CustomModels\Blisstribute\BlisstributeOrder::EXPORT_STATUS_TRANSFERRED
            || $blisstributeOrder->getStatus() == \Shopware\CustomModels\Blisstribute\BlisstributeOrder::EXPORT_STATUS_IN_TRANSFER
        ) {
            return true;
        }

        if (!$this->container->get('config')->get('blisstribute-auto-sync-order')) {
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
     * @param \Enlight_Event_EventArgs $eventArgs
     *
     * @return \Shopware\CustomModels\Blisstribute\BlisstributeOrder|null
     */
    protected function registerOrder(\Enlight_Event_EventArgs $eventArgs)
    {
        $orderProxy = $eventArgs->get('subject');

        $modelManager = $this->container->get('models');
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
            $status = \Shopware\CustomModels\Blisstribute\BlisstributeOrder::EXPORT_STATUS_CREATION_PENDING;

            if (!$this->container->get('config')->get('blisstribute-auto-sync-order')) {
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

        return $blisstributeOrder;
    }
}
