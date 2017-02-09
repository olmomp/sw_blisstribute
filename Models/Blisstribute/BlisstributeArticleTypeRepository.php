<?php

namespace Shopware\CustomModels\Blisstribute;

use Shopware\Components\Model\ModelRepository;

/**
 * database repository for article type entity
 *
 * @author    Julian Engler
 * @package   Shopware\CustomModels\Blisstribute
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class BlisstributeArticleTypeRepository extends ModelRepository
{
    /**
     * get article type for filter
     *
     * @param int $filterId
     *
     * @return BlisstributeArticleType|null
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function fetchByFilterType($filterId)
    {
        return $this->createQueryBuilder('at')
            ->where('at.sFilter = :filterId')
            ->setParameter('filterId', $filterId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
