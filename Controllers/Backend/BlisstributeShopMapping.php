<?php

use Shopware\CustomModels\Blisstribute\BlisstributeShopRepository;

/**
 * blisstribute article controller
 *
 * @author    Julian Engler
 * @package   Shopware\Controllers\Backend\BlisstributeArticle
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 *
 * @method BlisstributeShopRepository getRepository()
 */
class Shopware_Controllers_Backend_BlisstributeShopMapping extends Shopware_Controllers_Backend_Application
{
    /**
     * model class
     *
     * @var string
     */
    protected $model = 'Shopware\CustomModels\Blisstribute\BlisstributeShop';

    /**
     * controller alias
     *
     * @var string
     */
    protected $alias = 'blisstribute_shop_mapping';

    /**
     * @inheritdoc
     */
    protected function getListQuery()
    {
        $builder = parent::getListQuery();

        $builder->innerJoin('blisstribute_shop_mapping.shop', 's');
        $builder->addSelect(array('s'));

        return $builder;
    }
}