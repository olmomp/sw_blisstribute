<?php

use Shopware\CustomModels\Blisstribute\BlisstributeCouponRepository;

/**
 * blisstribute article controller
 *
 * @author    Julian Engler
 * @package   Shopware\Controllers\Backend\BlisstributeArticle
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 *
 * @method BlisstributeCouponRepository getRepository()
 */
class Shopware_Controllers_Backend_BlisstributeCouponMapping extends Shopware_Controllers_Backend_Application
{
    /**
     * model class
     *
     * @var string
     */
    protected $model = 'Shopware\CustomModels\Blisstribute\BlisstributeCoupon';

    /**
     * controller alias
     *
     * @var string
     */
    protected $alias = 'blisstribute_coupon_mapping';

    /**
     * @inheritdoc
     */
    protected function getListQuery()
    {
        $builder = parent::getListQuery();

        $builder->innerJoin('blisstribute_coupon_mapping.voucher', 'voucher');
        $builder->addSelect(array('voucher'));

        return $builder;
    }
}