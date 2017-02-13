<?php

namespace Shopware\CustomModels\Blisstribute;

use Doctrine\ORM\NonUniqueResultException;
use Shopware\Components\Model\ModelRepository;

/**
 * blisstribute payment mapping entity repository
 *
 * @author    Julian Engler
 * @package   Shopware\CustomModels\Blisstribute
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class BlisstributePaymentRepository extends ModelRepository
{
    /**
     * get payment mapping by shopware payment id
     *
     * @param int $paymentId
     *
     * @return BlisstributePayment|null
     *
     * @throws NonUniqueResultException
     */
    public function findOneByPayment($paymentId)
    {
        return $this->createQueryBuilder('bp')
            ->where('bp.payment = :paymentId')
            ->setParameter('paymentId', $paymentId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
