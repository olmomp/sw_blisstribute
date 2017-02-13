<?php

namespace Shopware\CustomModels\Blisstribute;

use Doctrine\ORM\NonUniqueResultException;
use Shopware\Components\Model\ModelRepository;

/**
 * blisstribute shipment mapping entity repository
 *
 * @author    Julian Engler
 * @package   Shopware\CustomModels\Blisstribute
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class BlisstributeShipmentRepository extends ModelRepository
{
    /**
     * get shipment mapping by shopware shipment id
     *
     * @param int $shipmentId
     *
     * @return BlisstributeShipment|null
     *
     * @throws NonUniqueResultException
     */
    public function findOneByShipment($shipmentId)
    {
        return $this->createQueryBuilder('bs')
            ->where('bs.shipment = :shipmentId')
            ->setParameter('shipmentId', $shipmentId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
