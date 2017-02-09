<?php
use Shopware\CustomModels\Blisstribute\BlisstributePaymentRepository;

/**
 * blisstribute article controller
 *
 * @author    Julian Engler
 * @package   Shopware\Controllers\Backend\BlisstributeArticle
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 *
 * @method BlisstributePaymentRepository getRepository()
 */
class Shopware_Controllers_Backend_BlisstributePaymentMapping extends Shopware_Controllers_Backend_Application
{
    /**
     * model class
     *
     * @var string
     */
    protected $model = 'Shopware\CustomModels\Blisstribute\BlisstributePayment';

    /**
     * controller alias
     *
     * @var string
     */
    protected $alias = 'blisstribute_payment_mapping';

    /**
     * @inheritdoc
     */
    protected function getListQuery()
    {
        $builder = parent::getListQuery();

        $builder->addSelect('o');
        $builder->leftJoin('blisstribute_payment_mapping.payment', 'o');

        return $builder;
    }
}