<?php

/**
 * blisstribute article type backend controller
 *
 * @author    Julian Engler
 * @package   Shopware\Controllers\Backend\BlisstributeArticleType
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class Shopware_Controllers_Backend_BlisstributeArticleType extends Shopware_Controllers_Backend_Application
{
    /**
     * model class
     *
     * @var string
     */
    protected $model = 'Shopware\CustomModels\Blisstribute\BlisstributeArticleType';

    /**
     * model alias name
     *
     * @var string
     */
    protected $alias = 'blisstribute_article_type';

    /**
     * @inheritdoc
     */
    protected function getListQuery()
    {
        $builder = parent::getListQuery();

        $builder->innerJoin('blisstribute_article_type.filter', 'cs');
        $builder->addSelect(array('cs'));

        return $builder;
    }
}