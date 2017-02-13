<?php

namespace Shopware\Components\Api\Resource;

use Shopware\Components\Api\Exception as ApiException;
use Shopware\Models\Article\Detail;

/**
 * abstract class for blisstribute custom api article extension resource
 *
 * @author    Julian Engler
 * @package   Shopware\Components\Api\Resource
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
abstract class BtArticleResource extends Resource
{
    /**
     * Little helper function for the ...ByVhsNumber methods
     *
     * @param string $vhsNumber
     * @param string $articleNumber
     * @param string $ean
     *
     * @return int
     *
     * @throws \Shopware\Components\Api\Exception\NotFoundException
     * @throws \Shopware\Components\Api\Exception\ParameterMissingException
     */
    public function getIdFromVhsNumber($vhsNumber, $articleNumber = '', $ean = '')
    {
        if (empty($vhsNumber)) {
            throw new ApiException\ParameterMissingException();
        }

        $articleId = $this->getManager()->getConnection()->fetchColumn(
            "SELECT articledetailsID FROM s_articles_attributes WHERE blisstribute_vhs_number = :vhsNumber",
            array(':vhsNumber' => $vhsNumber)
        );

        if (!empty($articleId)) {
            return $articleId;
        }

        $articleId = $this->getManager()->getConnection()->fetchColumn(
            "SELECT id from s_articles_details WHERE ordernumber = :articleNumber",
            array(':articleNumber' => $articleNumber)
        );

        if (!empty($articleId)) {
            return $articleId;
        }

        $articleId = $this->getManager()->getConnection()->fetchColumn(
            "SELECT id from s_articles_details WHERE ean = :articleEan",
            array(':articleEan' => $ean)
        );

        if (empty($articleId)) {
            throw new ApiException\NotFoundException("Article by vhs number {$vhsNumber} not found");
        }

        return $articleId;
    }

    /**
     * returns article detail
     *
     * @param array $data
     *
     * @return Detail
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Shopware\Components\Api\Exception\NotFoundException
     * @throws \Shopware\Components\Api\Exception\ParameterMissingException
     */
    public function getDetailArticleByData(array $data)
    {
        $id = $this->getIdFromVhsNumber(
            $data['attribute']['blisstributeVhsNumber'],
            $data['articleNumber'],
            $data['ean']
        );

        if (!$id) {
            throw new ApiException\NotFoundException('detail id not found');
        }

        $model = $this->getManager()->find('Shopware\Models\Article\Detail', $id);
        if ($model == null) {
            throw new ApiException\NotFoundException('detail not found for id');
        }

        return $model;
    }

    /**
     * Returns the primary ID of any data set.
     *
     * {@inheritDoc}
     */
    public function getIdByData($data)
    {
        $id = $this->getIdFromVhsNumber(
            $data['attribute']['blisstributeVhsNumber'],
            $data['articleNumber'],
            $data['ean']
        );

        if (!$id) {
            throw new ApiException\NotFoundException('detail id not found');
        }

        $model = $this->getManager()->find('Shopware\Models\Article\Detail', $id);
        if ($model == null) {
            throw new ApiException\NotFoundException('detail not found for id');
        }

        return $model->getId();
    }
}
