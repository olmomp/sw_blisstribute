<?php

use Shopware\CustomModels\Blisstribute\BlisstributeArticleRepository;

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
        if (version_compare(Shopware::VERSION, '4.2.0', '<') && Shopware::VERSION != '___VERSION___') {
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

        $builder->innerJoin('blisstribute_article.article', 'article');
        $builder->innerJoin('article.mainDetail', 'mainDetail');
        $builder->innerJoin('article.attribute', 'attribute');
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
            }
        }

        return $builder;
    }

    /**
     * @inheritdoc
     */
    protected function getFilterConditions($filters, $model, $alias, $whiteList = array())
    {
        $conditions = parent::getFilterConditions($filters, $model, $alias, $whiteList);

        foreach ($filters as $condition) {
            if ($condition['property'] === 'name' || $condition['property'] === 'number') {
                // check if the developer limited the filterable fields and the passed property defined in the filter fields parameter.
                if (!empty($whiteList) && !in_array($condition['property'], $whiteList)) {
                    continue;
                }

                if ($condition['property'] === 'name') {
                    $fields = $this->getModelFields('\Shopware\Models\Article\Article', 'base_article');
                } else {
                    $fields = $this->getModelFields('\Shopware\Models\Article\Detail', 'detail');
                }

                $field = $fields[$condition['property']];
                $value = $this->formatSearchValue($condition['value'], $field);

                $conditions[] = array(
                    'property' => $field['alias'],
                    'operator' => $condition['operator'],
                    'value' => $value,
                    'expression' => $condition['expression']
                );
            }
        }

        return $conditions;
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
            $articleSync = new Shopware_Components_Blisstribute_Article_Sync($this->plugin->Config());
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