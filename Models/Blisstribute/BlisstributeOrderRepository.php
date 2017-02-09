<?php

namespace Shopware\CustomModels\Blisstribute;

use Doctrine\ORM\Mapping\ClassMetadata;
use Shopware\Models\Order\Order;
use Doctrine\ORM\NonUniqueResultException;
use Shopware\Components\Model\ModelRepository;

/**
 * blisstribute order repository
 *
 * @author    Julian Engler
 * @package   Shopware\CustomModels\Blisstribute
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 *
 * @method BlisstributeOrder find($id, $lockMode = null, $lockVersion = null)
 */
class BlisstributeOrderRepository extends ModelRepository
{
    /**
     * max tries for cron jbos
     *
     * @var int
     */
    const MAX_SYNC_TRIES = 5;

    /**
     * page limit for export
     *
     * @var int
     */
    const PAGE_LIMIT = 50;

    /**
     * get blisstribute order mapping by order
     *
     * @param Order $order
     *
     * @return BlisstributeOrder
     *
     * @throws NonUniqueResultException
     */
    public function findByOrder(Order $order)
    {
        $blisstributeOrder = $this->createQueryBuilder('bo')
            ->where('bo.order = :order')
            ->setParameter('order', $order->getId())
            ->getQuery()
            ->getOneOrNullResult();

        return $blisstributeOrder;
    }

    /**
     * get all orders which are not transferred
     *
     * @param \DateTime $exportDate
     *
     * @return array
     */
    public function findTransferableOrders(\DateTime $exportDate)
    {
        $blisstributeOrderCollection = $this->createQueryBuilder('bo')
            ->where('bo.status IN (:statusNew, :statusValidationError, :statusTransferError)')
            ->andWhere('bo.tries < :tries')
            ->andWhere('bo.lastCronAt <= :lastCronAt')
            ->setParameters(array(
                'statusNew' => BlisstributeOrder::EXPORT_STATUS_NONE,
                'statusValidationError' => BlisstributeOrder::EXPORT_STATUS_VALIDATION_ERROR,
                'statusTransferError' => BlisstributeOrder::EXPORT_STATUS_TRANSFER_ERROR,
                'tries' => static::MAX_SYNC_TRIES,
                'lastCronAt' => $exportDate->format('Y-m-d H:i:s'),
            ))
            ->setMaxResults(static::PAGE_LIMIT)
            ->getQuery()
            ->getResult();

        return $blisstributeOrderCollection;
    }
}
