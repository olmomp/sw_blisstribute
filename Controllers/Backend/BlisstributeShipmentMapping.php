<?php

use Shopware\CustomModels\Blisstribute\BlisstributeShipmentRepository;

/**
 * blisstribute article controller
 *
 * @author    Julian Engler
 * @package   Shopware\Controllers\Backend\BlisstributeArticle
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 *
 * @method BlisstributeShipmentRepository getRepository()
 */
class Shopware_Controllers_Backend_BlisstributeShipmentMapping extends Shopware_Controllers_Backend_Application
{
    /**
     * model class
     *
     * @var string
     */
    protected $model = 'Shopware\CustomModels\Blisstribute\BlisstributeShipment';

    /**
     * controller alias
     *
     * @var string
     */
    protected $alias = 'blisstribute_shipment_mapping';

    /**
     * @return \Shopware\Components\Model\QueryBuilder
     */
    protected function getListQuery()
    {
        $builder = parent::getListQuery();
        $builder->innerJoin('blisstribute_shipment_mapping.shipment', 's');
        $builder->addSelect(array('s'));

        return $builder;
    }
}