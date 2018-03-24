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
        $this->logDebug(sprintf('%s - begin update (%s)', $detailId, json_encode($params)));

        $config = Shopware()->Container()->get('shopware.plugin.config_reader')->getByPluginName('ExitBBlisstribute');
        $this->logDebug('plugin config loaded');
        
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

        $syncLastStock = false;
        if ($config['blisstribute-article-sync-sync-last-stock']) {
            $this->logDebug('last stock sync active');
            $syncLastStock = true;
        }

        switch ($status) {
            case 0: {
                $params['active'] = false;
                if ($syncLastStock) {
                    $params['lastStock'] = false;
                }
                break;
            }
            case 1: {
                $params['active'] = true;
                if ($syncLastStock) {
                    $params['lastStock'] = false;
                }
                break;
            }
            case 2:
                $params['active'] = true;
                if ($syncLastStock) {
                    $params['lastStock'] = true;
                }
                break;
            case 3:
            default: {
                if ($params['inStock'] > 0) {
                    $params['active'] = true;
                    if ($syncLastStock) {
                        $params['lastStock'] = true;
                    }
                } else {
                    $params['active'] = true;
                    if ($syncLastStock) {
                        $params['lastStock'] = false;
                    }
                }

                break;
            }
        }

        $params['shippingTime'] = ($timeFrom + 1) . ' - ' . ($timeTo + 1);

        if (trim($params['attribute']['blisstributeEstimatedDeliveryDate']) == '0000-00-00') {
            $params['attribute']['blisstributeEstimatedDeliveryDate'] = null;
        } else {
            $params['attribute']['blisstributeEstimatedDeliveryDate'] = trim($params['attribute']['blisstributeEstimatedDeliveryDate']);
        }

        $syncActive = false;
        if ($config['blisstribute-article-sync-sync-active-flag']) {
            $this->logDebug(sprintf('%s - set detail active %s', $detailId, (int)$params['active']));
            $detail->setActive($params['active']);
            $syncActive = true;
        }

        if ($config['blisstribute-article-sync-sync-ean']) {
            $this->logDebug(sprintf('%s - set detail ean %s', $detailId, $params['ean']));
            $detail->setEan($params['ean']);
        }

        if ($config['blisstribute-article-sync-sync-release-date']) {
            $this->logDebug(sprintf('%s - set detail release date %s', $detailId, $params['attribute']['blisstributeEstimatedDeliveryDate']));
            $detail->setReleaseDate($params['attribute']['blisstributeEstimatedDeliveryDate']);
        }

        $detail->setInStock($params['inStock']);
        $this->logDebug(sprintf('%s - set detail stock %s', $detailId, $params['inStock']));

        $detail->setStockMin($params['stockMin']);
        $this->logDebug(sprintf('%s - set detail min stock %s', $detailId, $params['stockMin']));

        $detail->setShippingTime($params['shippingTime']);
        $this->logDebug(sprintf('%s - set detail shipping time %s', $detailId, $params['shippingTime']));

        if (version_compare(\Shopware::VERSION, '5.2.1') >= 0) {
            $detail->setPurchasePrice(round($params['evaluatedStockPrice'], 2));
            $this->logDebug(sprintf('%s - set detail purchase price %s', $detailId, round($params['evaluatedStockPrice'], 2)));
        }

        $attributes = $detail->getAttribute();
        /** @noinspection PhpUndefinedMethodInspection */
        $attributes->setBlisstributeSupplierStock($params['attribute']['blisstributeSupplierStock']);
        $this->logDebug(sprintf('%s - set attribute supplier stock %s', $attributes->getId(), $params['attribute']['blisstributeSupplierStock']));

        $violations = $this->getManager()->validate($detail);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->getManager()->persist($detail);
        $this->getManager()->persist($attributes);

        $article = $detail->getArticle();
        if ($syncLastStock) {
            $this->logDebug(sprintf('%s - set article lastStock %s', $article->getId(), (int)$params['lastStock']));
            $article->setLastStock($params['lastStock']);

            $this->getManager()->persist($article);
            $this->logDebug(sprintf('%s - article saved', $article->getId()));
        }

        if ($article->getConfiguratorSet() != null) {
            $anythingActive = false;
            $this->logDebug('configurator set is not null - scanning for active variant');

            if ($detail->getKind() == 1 && $detail->getActive() == 0) {
                $this->logDebug(sprintf('%s - detail inactive - searching for new kind 1', $detail->getId()));
                /** @var Detail $currentNewDetail */
                foreach ($article->getDetails() as $currentNewDetail) {
                    if (!$currentNewDetail->getActive()) {
                        continue;
                    }

                    if ($currentNewDetail->getInStock() == 0) {
                        continue;
                    }

                    $this->logDebug(sprintf('%s - detail inactive - set new main detail %s', $detail->getId(), $currentNewDetail->getId()));
                    $detail->setKind(2);
                    $currentNewDetail->setKind(1);
                    $this->getManager()->persist($currentNewDetail);
                    $article->setMainDetail($currentNewDetail);
                    $anythingActive = true;
                    break;
                }
            } else {
                $this->logDebug('search for any active variant');
                /** @var Detail $currentNewDetail */
                foreach ($article->getDetails() as $currentNewDetail) {
                    if ($currentNewDetail->getActive()) {
                        $anythingActive = true;
                        break;
                    }
                }
            }

            if ($syncActive) {
                if (($anythingActive || $detail->getActive())) {
                    $this->logDebug(sprintf('%s - found anything active - set article active', $article->getId()));
                    $article->setActive(true);
                } else {
                    $this->logDebug(sprintf('%s - did\'nt found anything active - set article inactive', $article->getId()));
                    $article->setActive(false);
                }
            }

        } else {
            if ($syncActive) {
                $this->logDebug('configurator set is null');
                $article->setActive($detail->getActive());
                $this->logDebug(sprintf('%s - set single article active = %s', $article->getId(), (int)$detail->getActive()));
                $this->logDebug('set article to ' . (int)$detail->getActive());
            }
        }

        $this->getManager()->persist($detail);
        $this->getManager()->persist($attributes);
        $this->getManager()->persist($article);

        $this->flush();
        $this->logDebug(sprintf('%s - update done', $detailId));

        return $detail;
    }
}
