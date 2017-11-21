<?php

namespace Shopware\Components\Api\Resource;

use Shopware\Components\Api\Exception as ApiException;
use Shopware\Components\Api\BatchInterface;
use Shopware\Models\Article\Detail;
use Shopware_Components_Blisstribute_Domain_LoggerTrait;

require_once __DIR__ . '/../../Blisstribute/Domain/LoggerTrait.php';

/**
 * blisstribute custom api article extension resource
 *
 * @author    Conrad Gülzow
 * @package   Shopware\Components\Api\Resource
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class Btarticle extends BtArticleResource implements BatchInterface
{
    use Shopware_Components_Blisstribute_Domain_LoggerTrait;

    /**
     * @return \Shopware\Models\Article\Repository
     */
    public function getArticleRepository()
    {
        return $this->getManager()->getRepository('Shopware\Models\Article\Article');
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
     * @inheritdoc
     */
    public function batch($data)
    {
        $results = array();
        foreach ($data as $key => $currentData) {
            try {
                $id = $this->getIdByData($currentData);
                if (!$id) {
                    throw new ApiException\NotFoundException('entity identifier not found');
                }

                $results[$key] = array(
                    'success' => true,
                    'operation' => 'update',
                    'data' => $this->update($id, $currentData)
                );

                if ($this->getResultMode() == self::HYDRATE_ARRAY) {
                    $results[$key]['data'] = Shopware()->Models()->toArray($results[$key]['data']);
                }
            } catch (\Exception $ex) {
                if (!$this->getManager()->isOpen()) {
                    $this->resetEntityManager();
                }

                $message = $ex->getMessage();
                if ($ex instanceof ApiException\ValidationException) {
                    $message = implode("\n", $ex->getViolations()->getIterator()->getArrayCopy());
                }

                $results[$key] = array(
                    'success' => false,
                    'message' => $message,
                    'trace' => $ex->getTraceAsString()
                );
            }
        }

        return $results;
    }

    /**
     * @param $detailId
     * @param array $params
     *
     * @return \Shopware\Models\Article\Detail
     *
     * @throws \Shopware\Components\Api\Exception\ValidationException
     * @throws \Shopware\Components\Api\Exception\NotFoundException
     * @throws \Shopware\Components\Api\Exception\ParameterMissingException
     */
    public function update($detailId, array $params)
    {
        $this->checkPrivilege('update');
        $this->logDebug('beginning update');

        if (empty($detailId)) {
            throw new ApiException\ParameterMissingException();
        }


        $builder = $this->getManager()->createQueryBuilder();
        $builder->select(array('detail', 'attribute'))
            ->from('Shopware\Models\Article\Detail', 'detail')
            ->leftJoin('detail.attribute', 'attribute')
            ->where('detail.id = :detailId')
            ->setParameters(array('detailId' => $detailId));

        /** @var $detail \Shopware\Models\Article\Detail */
        $detail = $builder->getQuery()->getOneOrNullResult(self::HYDRATE_OBJECT);
        if (!$detail) {
            throw new ApiException\NotFoundException("Article by id $detailId not found");
        }

        $status = $params['attribute']['blisstributeArticleStatus'];
        $timeFrom = $params['attribute']['blisstributeDeliveryTimeFrom'];
        $timeTo = $params['attribute']['blisstributeDeliveryTimeTo'];

        switch ($status) {
            case 0: {
                $params['active'] = false;
                break;
            }
            case 1:
            case 2:
            case 3:
            default: {
                $params['active'] = true;
                break;
            }
        }

        $params['shippingTime'] = ($timeFrom + 1) . ' - ' . ($timeTo + 1);

        if (trim($params['attribute']['blisstributeEstimatedDeliveryDate']) == '0000-00-00') {
            $params['attribute']['blisstributeEstimatedDeliveryDate'] = null;
        } else {
            $params['attribute']['blisstributeEstimatedDeliveryDate'] = trim($params['attribute']['blisstributeEstimatedDeliveryDate']);
        }

        $detail->setInStock($params['inStock']);
        $detail->setActive($params['active']);
        $detail->setEan($params['ean']);
        $detail->setStockMin($params['stockMin']);
        $detail->setShippingTime($params['shippingTime']);
        $detail->setReleaseDate($params['attribute']['blisstributeEstimatedDeliveryDate']);

        if (version_compare(\Shopware::VERSION, '5.2.1') >= 0) {
            $detail->setPurchasePrice(round($params['evaluatedStockPrice'], 2));
        }

        // reimplement for price sync from blisstribute
        /*$prices = $detail->getPrices();
        /** @var Price $price * /
        foreach($prices as $price) {
            $price->setBasePrice($params['evaluatedStockPrice']);
            $this->getManager()->persist($price);
        }*/

        $attributes = $detail->getAttribute();
        /** @noinspection PhpUndefinedMethodInspection */
        $attributes->setBlisstributeSupplierStock($params['attribute']['blisstributeSupplierStock']);

        $article = $detail->getArticle();
        $article->setLastStock($params['lastStock']);

        $violations = $this->getManager()->validate($detail);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->getManager()->persist($detail);
        $this->getManager()->persist($attributes);
        $this->getManager()->persist($article);
        $this->flush();

        if ($article->getConfiguratorSet() != null) {
            if ($detail->getKind() == 1 && $detail->getActive() == 0) {
                /** @var Detail $currentNewDetail */
                foreach ($article->getDetails() as $currentNewDetail) {
                    if (!$currentNewDetail->getActive()) {
                        continue;
                    }

                    if ($currentNewDetail->getInStock() == 0) {
                        continue;
                    }

                    $detail->setKind(2);
                    $currentNewDetail->setKind(1);
                    $this->getManager()->persist($currentNewDetail);
                    $article->setMainDetail($currentNewDetail);
                    break;
                }
            }

            $this->getManager()->persist($detail);
            $this->getManager()->persist($attributes);
            $this->getManager()->persist($article);
            $this->flush();
        }

        return $detail;
    }
}
