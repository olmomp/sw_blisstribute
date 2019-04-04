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
            case 0: { // komplett offline
                $params['active'] = false;
                if ($syncLastStock) {
                    $params['lastStock'] = true;
                }
                break;
            }
            case 1: { // nachbeschaffbar
                $params['active'] = true;
                if ($syncLastStock) {
                    $params['lastStock'] = false;
                }
                break;
            }
            case 2: // nicht verfügbar
                $params['active'] = true;
                if ($syncLastStock) {
                    $params['lastStock'] = true;
                }
                break;
            case 3: // offline / nicht nachbeschaffbar
            default: {
                if ($params['inStock'] > 0) {
                    $params['active'] = true;
                    if ($syncLastStock) {
                        $params['lastStock'] = true;
                    }
                } else {
                    $params['active'] = true; // avoid loop with vhs
                    if ($syncLastStock) {
                        $params['lastStock'] = true;
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

        $article = $detail->getArticle();
        if ($syncLastStock) {
            if (version_compare(Shopware()->Config()->version, '5.4.0', '<')) {
                $this->logDebug(sprintf('%s - set article lastStock %s', $article->getId(), (int)$params['lastStock']));
                $article->setLastStock($params['lastStock']);

                $this->getManager()->persist($article);
                $this->logDebug(sprintf('%s - article saved', $article->getId()));
            } else {
                $this->logDebug(sprintf('%s - set detail lastStock %s', $detailId, (int)$params['lastStock']));
                $detail->setLastStock($params['lastStock']);

                $this->getManager()->persist($detail);
                $this->logDebug(sprintf('%s - detail saved', $detailId));
            }
        }

        if ($syncActive) {
            $this->setArticleActivationState($article);
        }

        $this->switchMainVariantIfRequired($detail, $article);

        try {
            $this->logDebug(sprintf('%s - persist and flush article', $article->getId()));
            $this->getManager()->persist($article);
            $this->getManager()->flush($article);

            $this->logDebug(sprintf('%s - persist and flush detail', $detail->getId()));
            $this->getManager()->persist($detail);
            $this->getManager()->flush($detail);

            $this->logDebug(sprintf('%s - persist and flush attributes', $detail->getId()));
            $this->getManager()->persist($attributes);
            $this->getManager()->flush($attributes);

            $this->logDebug(sprintf('%s - update done', $detailId));
        } catch (\Exception $ex) {
            $this->logWarn('save failed - ' . $ex);
        }

        return $detail;
    }

    /**
     * @param \Shopware\Models\Article\Detail $detail
     * @param \Shopware\Models\Article\Article $article
     */
    protected function switchMainVariantIfRequired($detail, $article)
    {
        if ($article->getConfiguratorSet() != null) {

            if ($detail->getKind() == 1 && !$this->isDetailAvailable($detail)) {

                /** @var Detail $articleDetail */
                foreach ($article->getDetails() as $articleDetail) {

                    if ($this->isDetailAvailable($articleDetail)) {

                        $this->switchMainDetail($article, $detail, $articleDetail);
                        $this->getManager()->persist($articleDetail);
                        $this->getManager()->flush($articleDetail);

                        break;
                    }
                }
            }
            else if ($detail->getKind() != 1 && (!$this->isDetailAvailable($detail->getArticle()->getMainDetail()) || $detail->getArticle()->getMainDetail()->getKind() != 1) && $this->isDetailAvailable($detail)) {

                $oldMainDetail = $article->getMainDetail();

                $this->switchMainDetail($article, $oldMainDetail, $detail);
                $this->getManager()->persist($oldMainDetail);
                $this->getManager()->flush($oldMainDetail);
            }
        }
    }

    /**
     * @param \Shopware\Models\Article\Article $article
     * @param \Shopware\Models\Article\Detail $oldDetail
     * @param \Shopware\Models\Article\Detail $newDetail
     */
    protected function switchMainDetail($article, $oldDetail, $newDetail)
    {
        $this->logDebug(sprintf('%s - set new main detail %s', $oldDetail->getId(), $newDetail->getId()));

        $oldDetail->setKind(2);
        $newDetail->setKind(1);

        $article->setMainDetail($newDetail);

        Shopware()->Events()->notify(
            'ExitBBlisstribute_ApiResourceArticle_SwitchMainDetail',
            [
                'subject' => $this,
                'old'     => $oldDetail,
                'new'     => $newDetail
            ]
        );
    }

    /**
     * @param \Shopware\Models\Article\Detail $detail
     * @return bool
     */
    protected function isDetailAvailable($detail)
    {
        if (version_compare(Shopware()->Config()->version, '5.4.0', '<')) {
            return $detail->getActive() == 1 && ($detail->getInStock() > 0 || !$detail->getArticle()->getLastStock());
        }
        else {
            return $detail->getActive() == 1 && ($detail->getInStock() > 0 || !$detail->getLastStock());
        }
    }

    /**
     * @param \Shopware\Models\Article\Article $article
     */
    protected function setArticleActivationState($article)
    {
        $formerState = $article->getActive();
        $article->setActive(0);

        /** @var Detail $articleDetail */
        foreach ($article->getDetails() as $articleDetail) {

            if ($articleDetail->getActive()) {
                $article->setActive(1);
            }
        }

        if (!$formerState && $article->getActive()) {
            Shopware()->Events()->notify(
                'ExitBBlisstribute_ApiResourceArticle_ActivateArticle',
                [
                    'subject' => $this,
                    'article' => $article
                ]
            );
        }
        else if ($formerState && !$article->getActive()) {
            Shopware()->Events()->notify(
                'ExitBBlisstribute_ApiResourceArticle_DeactivateArticle',
                [
                    'subject' => $this,
                    'article' => $article
                ]
            );
        }
    }
}
