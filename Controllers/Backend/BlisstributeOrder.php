<?php

use Shopware\CustomModels\Blisstribute\BlisstributeOrderRepository;
use \Shopware\CustomModels\Blisstribute\BlisstributeOrder;
use \Shopware\Models\Shop\Shop;

/**
 * blisstribute order controller
 *
 * @author    Julian Engler
 * @package   Shopware\Controllers\Backend
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 *
 * @method BlisstributeOrderRepository getRepository()
 */
class Shopware_Controllers_Backend_BlisstributeOrder extends Shopware_Controllers_Backend_Application
{
    use Shopware_Components_Blisstribute_Domain_LoggerTrait;
    /**
     * model class
     *
     * @var string
     */
    protected $model = 'Shopware\CustomModels\Blisstribute\BlisstributeOrder';

    /**
     * controller alias
     *
     * @var string
     */
    protected $alias = 'blisstribute_order';

    /**
     * plugin
     *
     * @var
     */
    private $plugin;

    protected $filterFields = ['qqqqqqqq'];

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        $this->plugin = $this->get('plugins')->Backend()->ExitBBlisstribute();
        parent::init();
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        if (version_compare(Shopware()->Config()->version, '4.2.0', '<') && Shopware()->Config()->version != '___VERSION___') {
            $name = ucfirst($name);

            /** @noinspection PhpUndefinedMethodInspection */
            return Shopware()->Bootstrap()->getResource($name);
        }
        return Shopware()->Container()->get($name);
    }

    /**
     * @inheritdoc
     */
    protected function getListQuery()
    {
        $builder = parent::getListQuery();

        $builder->innerJoin('blisstribute_order.order', 'o');
        $builder->addSelect(array('o'));
        $builder->addOrderBy('o.id', 'DESC');

        // searching
        $filters = $this->Request()->getParam('filter');

        if (!is_null($filters)) {
            foreach ($filters as $filter) {
                if ($filter['property'] == 'search') {
                    $value = $filter['value'];

                    $search = '%' . $value . '%';

                    if (!is_null($value)) {
                        $builder->andWhere('o.number LIKE :search');

                        $builder->setParameter('search', $search);
                    }
                }

                if ($filter['property'] == 'status') {
                    $builder->andWhere('blisstribute_order.status = ' . (int)$filter['value']);
                }
            }
        }

        return $builder;
    }

    /**
     * @return Enlight_Config
     */
    protected function getConfig(BlisstributeOrder $blisstributeOrder)
    {
        $shop = $blisstributeOrder->getOrder()->getShop();
        if (!$shop || $shop == null) {
            $this->logWarn('orderSync::getConfig::could not get shop from order');
            $shop = $this->container->get('shop');
        }

        if (!$shop || $shop == null) {
            $this->logWarn('orderSync::getConfig::could not get shop from container');
            $shop = $this->container->get('models')->getRepository(Shop::class)->getActiveDefault();
        }

        if (!$shop || $shop == null) {
            $this->logWarn('orderSync::getConfig::could not get active shop; using fallback default config');
            return $this->container->get('config');
        }

        $this->logInfo('orderSync::getConfig::using shop ' . $shop->getId() . ' / ' . $shop->getName());
        $config = $this->container->get('shopware.plugin.cached_config_reader')->getByPluginName('ExitBBlisstribute', $shop);
        $this->logInfo('articleSync::getConfig::config' . json_encode($config));
        return new \Enlight_Config($config);
    }

    /**
     * starts syncing of selected articles
     *
     * @return void
     */
    public function syncAction()
    {
        try {
            $blisstributeOrderId = $this->Request()->getParam('id');
            $blisstributeOrder = $this->getRepository()->find($blisstributeOrderId);
            if ($blisstributeOrder === null) {
                $this->View()->assign(array(
                    'success' => false,
                    'error' => 'unknown blisstribute order'
                ));

                return;
            }

            require_once __DIR__ . '/../../Components/Blisstribute/Order/Sync.php';

            /** @noinspection PhpUndefinedMethodInspection */
            $orderSync = new Shopware_Components_Blisstribute_Order_Sync($this->getConfig($blisstributeOrder));
            $result = $orderSync->processSingleOrderSync($blisstributeOrder);

            $this->View()->assign(array(
                'success' => $result,
                'error' => (($result == true) ? '' : $orderSync->getLastError()),
            ));
        } catch (Exception $ex) {
            $this->View()->assign(array(
                'success' => false,
                'error' => $ex->getMessage(),
            ));
        }
    }

    /**
     * resets the order sync locks
     *
     * @return void
     */
    public function resetLockAction()
    {
        $sql = 'DELETE FROM s_plugin_blisstribute_task_lock WHERE task_name LIKE :taskName';
        Shopware()->Db()->query($sql, array('taskName' => '%order_sync%'));

        $this->View()->assign(array(
            'success' => true,
            'message' => 'Transfer-Sperren wurden zurückgesetzt.'
        ));
    }

    /**
     * @return void
     */
    public function resetOrderSyncAction()
    {
        $blisstributeOrderId = $this->Request()->getParam('id');
        $sql = 'UPDATE s_plugin_blisstribute_orders SET transfer_status = 1, transfer_tries = 0, transfer_error_comment = NULL WHERE id = :btOrderId';
        Shopware()->Db()->query($sql, array('btOrderId' => $blisstributeOrderId));

        $this->View()->assign(array(
            'success' => true,
            'btOrderId' => $blisstributeOrderId
        ));
    }

    /**
     * @return void
     */
    public function updateOrderSyncAction()
    {
        $blisstributeOrderId = $this->Request()->getParam('id');
        $sql = 'UPDATE s_plugin_blisstribute_orders set transfer_status = 3 WHERE id = :btOrderId';
        Shopware()->Db()->query($sql, array('btOrderId' => $blisstributeOrderId));

        $this->View()->assign(array(
            'success' => true,
            'btOrderId' => $blisstributeOrderId
        ));
    }

    /**
     * Check for invalid order transfers and return warning
     */
    public function getInvalidOrderTransfersAction()
    {
        $config = $this->plugin->Config();
        $invalidTransfers = [];

        if ($config->get('blisstribute-show-sync-widget')) {
            $sqlSelectInvalidOrderTransfers = "SELECT s_order.ordernumber AS ordernumber FROM s_plugin_blisstribute_orders 
                                           LEFT JOIN s_order ON s_order.id = s_order_id
                                           WHERE transfer_status in (10,11,20,21)";

            $invalidTransfers = Shopware()->Db()->fetchAll($sqlSelectInvalidOrderTransfers);
        }

        $this->View()->assign(array(
            'success' => true,
            'data' => $invalidTransfers,
            'count' => count($invalidTransfers)
        ));
    }

    public function getOrderByNumberAction()
    {
        $orderNumber = $this->Request()->getParam('orderNumber');
        $order = Shopware()->Models()->getRepository('Shopware\Models\Order\Order')->findOneBy(array(
            'number' => $orderNumber
        ));

        $this->View()->assign(array(
            'success' => true,
            'data' => $order->getId()
        ));
    }
}
