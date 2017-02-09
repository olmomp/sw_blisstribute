<?php

namespace Shopware\Components\Api\Resource;

use Doctrine\ORM\AbstractQuery;
use Shopware\Components\Api\Exception as ApiException;

/**
 * blisstribute custom api article extension resource
 *
 * @author    Conrad Gülzow
 * @package   Shopware\Components\Api\Resource
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class Btarticlestock extends BtArticleResource
{
    /**
     * @return \Shopware\Models\Article\Repository
     */
    public function getArticleDetailRepository()
    {
        return $this->getManager()->getRepository('Shopware\Models\Article\Detail');
    }

    /**
     * do not support create
     *
     * @param array $params
     *
     * @return void
     *
     * @throws \Shopware\Components\Api\Exception\NotFoundException
     */
    public function create(array $params)
    {
        throw new ApiException\NotFoundException('not supported');
    }

    /**
     * do not support update
     *
     * @param int $detailId
     * @param array $params
     *
     * @return \Shopware\Models\Article\Detail
     *
     * @throws \Shopware\Components\Api\Exception\NotFoundException
     */
    public function update($detailId, array $params)
    {
        throw new ApiException\NotFoundException('not supported');
    }

    /**
     * @inheritdoc
     */
    public function batch($data)
    {
        throw new ApiException\NotFoundException('not supported');
    }

    /**
     * get stock overview for specific article
     *
     * @param string $vhsArticleNumber
     *
     * @return array
     *
     * @throws \Shopware\Components\Api\Exception\NotFoundException
     * @throws \Shopware\Components\Api\Exception\PrivilegeException
     */
    public function getOne($vhsArticleNumber)
    {
        $this->checkPrivilege('read');

        $detail = $this->getDetailArticleByData(array(
            'attribute' => array('blisstributeVhsNumber' => $vhsArticleNumber)
        ));

        /** @noinspection PhpUndefinedMethodInspection */
        $detailVhsArticleNumber = $detail->getAttribute()->getBlisstributeVhsNumber();

        return array(
            'stockData' => array(
                'articleNumber' => $detail->getNumber(),
                'stock' => $detail->getInStock(),
                'vhsArticleNumber' => $detailVhsArticleNumber,
            ),
            'count' => 1
        );
    }

    /**
     * get stock overview
     *
     * @param int $page
     * @param int $limit
     *
     * @return array
     *
     * @throws \Shopware\Components\Api\Exception\PrivilegeException
     */
    public function getList($page = 0, $limit = 25)
    {
        $this->checkPrivilege('read');

        $select = array(
            '(details.number) AS articleNumber',
            '(details.inStock) AS stock',
            '(attribute.blisstributeVhsNumber) AS vhsArticleNumber',
        );

        $builder = $this->getManager()->createQueryBuilder();
        $query = $builder->select($select)
            ->from('Shopware\Models\Article\Detail', 'details')
            ->join('details.attribute', 'attribute')
            ->where('attribute.blisstributeVhsNumber != \'\'')
            ->andWhere('attribute.blisstributeVhsNumber != 0')
            ->andWhere('attribute.blisstributeVhsNumber IS NOT NULL')
            ->addOrderBy('attribute.blisstributeVhsNumber', 'ASC')
            ->setFirstResult($this->calculateOffset($page, $limit))
            ->setMaxResults($limit)
            ->getQuery()
        ;

        $query->setHydrationMode(AbstractQuery::HYDRATE_ARRAY);
        $paginator = $this->getManager()->createPaginator($query);

        $totalResult = $paginator->count();

        $stockCollection = $paginator->getIterator()->getArrayCopy();

        return array(
            'stockData' => $stockCollection,
            'count' => $totalResult
        );
    }

    /**
     * determine page offset
     *
     * @param int $page
     * @param int $limit
     *
     * @return int
     */
    protected function calculateOffset($page, $limit)
    {
        $offset = 0;
        if ((int)$page > 0) {
            $offset = $limit * $page;
        }

        return $offset;
    }
}
