<?php

use Shopware\CustomModels\Blisstribute\BlisstributeArticleRepository;
use \Shopware\Models\Shop\Shop;

/**
 * blisstribute article controller
 *
 * @author    Julian Engler
 * @package   Shopware\Controllers\Backend
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 *
 * @method BlisstributeArticleRepository getRepository()
 */
class Shopware_Controllers_Backend_BlisstributeArticle extends Shopware_Controllers_Backend_Application
{
    use Shopware_Components_Blisstribute_Domain_LoggerTrait;

    /**
     * model class
     *
     * @var string
     */
    protected $model = 'Shopware\CustomModels\Blisstribute\BlisstributeArticle';

    /**
     * controller alias
     *
     * @var string
     */
    protected $alias = 'blisstribute_article';

    /**
     * plugin
     *
     * @var
     */
    private $plugin;

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
        $builder->join('blisstribute_article.article', 'article');
        $builder->join('article.mainDetail', 'mainDetail');
        $builder->join('mainDetail.attribute', 'attribute');
        $builder->addSelect(array('article', 'mainDetail', 'attribute'));
        $builder->addOrderBy('blisstribute_article.id', 'DESC');

        // searching
        $filters = $this->Request()->getParam('filter');

        if (!is_null($filters)) {
            foreach ($filters as $filter) {
                if ($filter['property'] == 'search') {
                    $value = $filter['value'];

                    $search = '%' . $value . '%';

                    if (!is_null($value)) {
                        $builder->andWhere('article.name LIKE :search');

                        $builder->setParameter('search', $search);
                    }
                }

                if ($filter['property'] == 'triggerSync') {
                    $value = $filter['value'] === true;
                    $builder->andWhere('blisstribute_article.triggerSync = ' . (int)$value);
                }
            }
        }

        \Shopware()->PluginLogger()->log(Monolog\Logger::INFO, $builder->getQuery()->getSQL());


        return $builder;
    }

    /**
     * @inheritdoc
     */
    protected function getFilterConditions($filters, $model, $alias, $whiteList = array())
    {
        \Shopware()->PluginLogger()->log(Monolog\Logger::INFO, json_encode($filters));

        if (count($filters) == 0) {
            return array();
        }

        $conditionCollection = array();
        foreach ($filters as $currentFilter) {
            if ($currentFilter['property'] === 'triggerSync') {
                $conditionCollection[] = array(
                    'property' => 'blisstribute_article.triggerSync',
                    'operator' => 'AND',
                    'expression' => '=',
                    'value' => (int)$currentFilter['value']
                );
            } else {
                $conditionCollection[] = array(
                    'property' => 'attribute.blisstributeVhsNumber',
                    'operator' => 'OR',
                    'value' => '%' . $filters[0]['value'] . '%'
                );

                $conditionCollection[] = array(
                    'property' => 'mainDetail.number',
                    'operator' => 'OR',
                    'value' => '%' . $filters[0]['value'] . '%'
                );

                $conditionCollection[] = array(
                    'property' => 'mainDetail.ean',
                    'operator' => 'OR',
                    'value' => '%' . $filters[0]['value'] . '%'
                );

                $conditionCollection[] = array(
                    'property' => 'article.name',
                    'operator' => 'OR',
                    'value' => '%' . $filters[0]['value'] . '%'
                );
            }
        }

        return $conditionCollection;
    }

    /**
     * resets the article sync locks
     *
     * @return void
     */
    public function resetLockAction()
    {
        $sql = 'DELETE FROM s_plugin_blisstribute_task_lock WHERE task_name LIKE :taskName';
        Shopware()->Db()->query($sql, array('taskName' => '%article_sync%'));

        $this->View()->assign(array(
            'success' => true
        ));
    }

    /**
     * sets trigger sync for selected articles
     *
     * @return void
     */
    public function triggerSyncAction()
    {
        try {
            $blisstributeArticleId = $this->Request()->getParam('id');
            $blisstributeArticle = $this->getRepository()->find($blisstributeArticleId);
            if ($blisstributeArticle === null) {
                $this->View()->assign(array(
                    'success' => false,
                    'error' => 'unknown blisstribute article'
                ));

                return;
            }

            $blisstributeArticle->setTriggerSync(true);
            $this->getManager()->flush($blisstributeArticle);

            $this->View()->assign(array('success' => true));
        } catch (Exception $e) {
            $this->View()->assign(array(
                'success' => false,
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * @return Enlight_Config
     */
    protected function getConfig()
    {
        $shop = $this->container->get('models')->getRepository(Shop::class)->getActiveDefault();
        if (!$shop || $shop == null) {
            $this->logWarn('orderSyncMapping::getConfig::could not get active shop; using fallback default config');
            return $this->container->get('config');
        }

        $this->logInfo('orderSyncMapping::getConfig::using shop ' . $shop->getId() . ' / ' . $shop->getName());
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
            $blisstributeArticleId = $this->Request()->getParam('id');
            $blisstributeArticle = $this->getRepository()->find($blisstributeArticleId);
            if ($blisstributeArticle === null) {
                $this->View()->assign(array(
                    'success' => false,
                    'error' => 'unknown blisstribute article'
                ));
                return;
            }

            if (!$blisstributeArticle->isTriggerSync()) {
                $blisstributeArticle->setTriggerSync(true)
                    ->setTries(0)
                    ->setSyncHash('');
            }

            require_once __DIR__ . '/../../Components/Blisstribute/Article/Sync.php';

            /** @noinspection PhpUndefinedMethodInspection */
            $articleSync = new Shopware_Components_Blisstribute_Article_Sync($this->getConfig());
            $result = $articleSync->processSingleArticleSync($blisstributeArticle);

            $this->View()->assign(array(
                'success' => $result,
                'error' => trim($articleSync->getLastError()),
            ));
        } catch (Exception $ex) {
            $this->View()->assign(array(
                'success' => false,
                'error' => $ex->getMessage()
            ));
        }
    }

    public function getArticleIdByNumberAction()
    {
        $articleNumber = $this->Request()->getParam('articleNumber');

        $detail = Shopware()->Models()->getRepository('Shopware\Models\Article\Detail')->findOneBy(array(
            'number' => $articleNumber
        ));

        $this->View()->assign(array(
            'success' => true,
            'data' => $detail->getArticleId()
        ));
    }
}
