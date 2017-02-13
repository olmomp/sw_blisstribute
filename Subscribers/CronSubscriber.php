<?php

namespace ShopwarePlugins\ExitBBlisstribute\Subscribers;

use Enlight\Event\SubscriberInterface;
use Shopware\Components\DependencyInjection\Container;
use Shopware\CustomModels\Blisstribute\BlisstributeCoupon;

use Shopware\CustomModels\Blisstribute\BlisstributeOrder;

require_once __DIR__ . '/../Components/Blisstribute/Article/Sync.php';
require_once __DIR__ . '/../Components/Blisstribute/Order/Sync.php';

class CronSubscriber implements SubscriberInterface
{
    /**
     * @var Container
     */
    private $container;

    /**
     * SearchBundleSubscriber constructor.
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            'Shopware_CronJob_PixupBlisstributeOrderSyncCron' => 'onRunPixupBlisstributeOrderSyncCron', // order sync
            'Shopware_CronJob_PixupBlisstributeArticleSyncCron' => 'onRunPixupBlisstributeArticleSyncCron', // article sync
            'Shopware_CronJob_PixupExitBEasyCouponWertgutscheinCron' => 'onRunPixupExitBEasyCouponWertgutscheinCron', // easyCoupon Wertgutscheine
            'Shopware_CronJob_PixupImportOrdersToExitBCron' => 'onRunPixupImportOrdersToExitBCron' // import all orders
        ];
    }

    /**
     * Import all orders that might have been added using pure sql
     *
     * @param \Shopware_Components_Cron_CronJob $job
     */
    public function onRunPixupImportOrdersToExitBCron(\Shopware_Components_Cron_CronJob $job)
    {
        if(is_null($job)) return;


        // get unmapped orders
        $sql = "SELECT id FROM s_order WHERE id NOT IN (SELECT s_order_id FROM s_plugin_blisstribute_orders) AND ordernumber != 0";

        $modelManager = $this->container->get('models');

        $orders = $this->container->get('db')->fetchAll($sql);

        $date = new \DateTime();

        foreach ($orders as $order) {
            $blisstributeOrder = new BlisstributeOrder();
            $blisstributeOrder->setTries(0);
            $blisstributeOrder->setOrder($modelManager->getRepository('Shopware\Models\Order\Order')->find($order['id']));
            $blisstributeOrder->setCreatedAt($date);
            $blisstributeOrder->setModifiedAt($date);
            $blisstributeOrder->setStatus(1);
            $blisstributeOrder->setLastCronAt($date);

            $modelManager->persist($blisstributeOrder);
        }

        $modelManager->flush();
    }

    /**
     * Blisstribute Order Sync CronJob
     *
     * @param \Shopware_Components_Cron_CronJob $job
     */
    public function onRunPixupBlisstributeOrderSyncCron(\Shopware_Components_Cron_CronJob $job)
    {
        if(is_null($job)) return;

        try {
            $controller = new \Shopware_Components_Blisstribute_Order_Sync(
                $this->container->get('plugins')->Backend()->ExitBBlisstribute()->Config()
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
    public function onRunPixupBlisstributeArticleSyncCron(\Shopware_Components_Cron_CronJob $job)
    {
        if(is_null($job)) return;

        try {
            $controller = new \Shopware_Components_Blisstribute_Article_Sync(
                $this->container->get('plugins')->Backend()->ExitBBlisstribute()->Config()
            );

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
    public function onRunPixupExitBEasyCouponWertgutscheinCron(\Shopware_Components_Cron_CronJob $job)
    {
        if(is_null($job)) return;

        $modelManager = $this->container->get('models');

        $sqlUnmappedVouchers = "SELECT voucher.id AS id FROM s_emarketing_vouchers voucher 
                                LEFT JOIN s_plugin_blisstribute_coupon coupon ON coupon.s_voucher_id = voucher.id
                                LEFT JOIN s_emarketing_vouchers_attributes attributes ON attributes.voucherID = voucher.id
                                WHERE coupon.id IS NULL AND attributes.neti_easy_coupon = 1";

        $unmappedVouchers = $this->container->get('db')->fetchAll($sqlUnmappedVouchers);

        $idArray = array();
        foreach ($unmappedVouchers as $voucher) {
            $idArray[] = $voucher['id'];
        }

        $vouchers = $modelManager->getRepository('Shopware\Models\Voucher\Voucher')->findById($idArray);

        /** @var \Shopware\Models\Voucher\Voucher $voucher */
        foreach ($vouchers as $voucher) {
            $blisstributeCoupon = new BlisstributeCoupon();
            $blisstributeCoupon->setVoucher($voucher)->setIsMoneyVoucher(true);

            $modelManager->persist($blisstributeCoupon);
        }

        $modelManager->flush();
    }
}
