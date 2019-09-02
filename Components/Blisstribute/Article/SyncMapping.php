<?php

require_once __DIR__ . '/../SyncMapping.php';

use Shopware\Models\Article\Detail;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Price;
use Shopware\Models\Category\Category;
use Shopware\CustomModels\Blisstribute\BlisstributeArticle;
use Shopware\Models\Tax\Tax;

/**
 * mapping service to sync article information to blisstribute
 *
 * @author    Julian Engler
 * @package   Shopware_Components_Blisstribute_Article_SyncMapping
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 *
 * @method BlisstributeArticle getModelEntity()
 */
class Shopware_Components_Blisstribute_Article_SyncMapping extends Shopware_Components_Blisstribute_SyncMapping
{
    private $container = null;

    /**
     * @return mixed
     * @throws Exception
     */
    protected function getConfig()
    {
        try {
            $this->logDebug('articleSyncMapping::load config');
            $c = $this->container->get('shopware.plugin.config_reader')->getByPluginName('ExitBBlisstribute');
            $this->logDebug('articleSyncMapping::load config done');

            return $c;
        }catch (Exception $ex) {
            $this->logWarn($ex->getMessage());
            throw $ex;
        }
    }

    /**
     * get shopware article for blisstribute mapped article
     *
     * @return Article
     */
    protected function getArticle()
    {
        return $this->getModelEntity()->getArticle();
    }

    public function __construct()
    {
        $this->container = Shopware()->Container();
    }

    /**
     * @return array
     */
    protected function buildBaseData()
    {
        $releaseDate = null;
        if ($this->getArticle()->getAvailableFrom() !== null) {
            $releaseDate = $this->getArticle()->getAvailableFrom()->format('Y-m-d H:i:s');
        }

        $removeDate = null;
        if ($this->getArticle()->getAvailableTo() !== null) {
            $removeDate = $this->getArticle()->getAvailableTo()->format('Y-m-d H:i:s');
        }

        $articleData = array_merge($this->buildClassificationData(), array(
            'articleType' => 'EQUIPMENT',
            'releaseState' => (bool)$this->getArticle()->getActive(),
            'releaseDate' => $releaseDate,
            'removeDate' => $removeDate,
            'reorder' => true,
            'customsTariffNumber' => $this->_getCustomsTariffNumber($this->getArticle()->getMainDetail()),
            'countryOfOriginCode' => $this->_getCountryOfOrigin($this->getArticle()->getMainDetail()),
            'sex' => 0,
            'sale' => null,
            'priceCode' => '0000',
            'basePrice' => $this->getMainDetailBasePrice($this->getArticle()->getMainDetail()),
            'extractArticleSeries' => false,
            'vatCollection' => $this->buildVatCollection(),
            'vendorCollection' => array(),
            'priceCollection' => $this->buildPriceCollection($this->getArticle()->getMainDetail()),
            'seriesCollection' => array(),
            'specificationCollection' => array(),
        ));

        if ($this->getArticle()->getDetails()->count() > 1) {
            $articleData['seriesCollection'][] = $this->buildSeriesCollection();
        } else {
            $articleData['specificationCollection'] = array($this->buildSpecificationCollection($this->getArticle()->getMainDetail()));
        }

        // Allow plugins to change the data
        return Enlight()->Events()->filter(
            'ExitBBlisstribute_ArticleSyncMapping_AfterBuildBaseData',
            $articleData,
            array(
                'subject' => $this,
                'article' => $this->getArticle()
            )
        );
    }

    /**
     * @param Detail $mainDetail
     *
     * @return string
     */
    protected function _getCustomsTariffNumber($mainDetail)
    {
        return $mainDetail->getAttribute()->getBlisstributeCustomsTariffNumber();
    }

    /**
     * @param Detail $mainDetail
     *
     * @return string
     */
    protected function _getCountryOfOrigin($mainDetail)
    {
        return $mainDetail->getAttribute()->getBlisstributeCountryOfOrigin();
    }

    /**
     * @param Article $article
     *
     * @return string
     */
    protected function getClassification3($article)
    {
        $fieldName = $this->getConfig()['blisstribute-article-mapping-classification3'];
        $this->logDebug('articleSyncMapping::classification3::fieldName ' . $fieldName);
        return $this->getClassification($article, $fieldName);
    }

    /**
     * @param Article $article
     *
     * @return string
     */
    protected function getClassification4($article)
    {
        $fieldName = $this->getConfig()['blisstribute-article-mapping-classification4'];
        $this->logDebug('articleSyncMapping::classification4::fieldName ' . $fieldName);
        return $this->getClassification($article, $fieldName);
    }

    /**
     * get the base price of the main detail
     *
     * @param Detail $articleDetail
     *
     * @return float
     * @throws Exception
     */
    protected function getMainDetailBasePrice(Detail $articleDetail)
    {
        $fieldName = $this->getConfig()['blisstribute-article-mapping-base-price'];
        if (trim($fieldName) != '') {
            $method = 'get' . ucfirst($fieldName);

            if (method_exists($articleDetail->getAttribute(), $method)) {
                $value = $articleDetail->getAttribute()->$method();
                return round(trim($value), 2);
            }
        }

        if (version_compare(Shopware()->Config()->version, '5.2.0', '>=')) {
            return $articleDetail->getPurchasePrice();
        }

        return $articleDetail->getPrices()->first()->getBasePrice();
    }

    /**
     * @param Article $article
     * @param string $fieldName
     * @return string
     */
    protected function getClassification($article, $fieldName)
    {
        if (trim($fieldName) == '') {
            return null;
        }

        $value = '';
        $method = 'get' . ucfirst($fieldName);

        if (method_exists($article->getAttribute(), $method)) {
            $value = $article->getAttribute()->$method();
        }

        if (trim($value) != '') {
            return $value;
        }

        $mainDetail = $this->_getMainDetail($article);
        if ($mainDetail != null) {
            $this->logDebug('articleSyncMapping::getClassification::got mainDetail ' . $mainDetail->getId());
            $attribute = $mainDetail->getAttribute();
            $this->logDebug('articleSyncMapping::getClassification::got attribute ' . $attribute->getId());
            if ($mainDetail) {
                $this->logDebug('articleSyncMapping::getClassification::mainDetail attribute ' . $method);
                if (method_exists($mainDetail->getAttribute(), $method)) {
                    $value = $mainDetail->getAttribute()->$method();
                }
                $this->logDebug('articleSyncMapping::getClassification::mainDetail attribute value ' . $value);
            }
        } else {
            $this->logDebug('articleSyncMapping::getClassification::main detail not found.');
        }

        return $value;
    }

    /**
     * @param Article $article
     *
     * @return Detail|null
     */
    private function _getMainDetail($article)
    {
        if ($article->getConfiguratorSet() != null) {
            /** @var Detail $currentDetail */
            foreach ($article->getDetails() as $currentDetail) {
                if ($currentDetail->getKind() == 1) {
                    return $currentDetail;
                }
            }
        }

        return null;
    }

    /**
     * build base classification data
     *
     * @return array
     */
    protected function buildClassificationData()
    {
        $classificationData = array(
            'classification1' => $this->getArticle()->getName(),
            'classification2' => $this->getArticle()->getSupplier()->getName(),
            'classification3' => $this->getClassification3($this->getArticle()),
            'classification4' => $this->getClassification4($this->getArticle()),
            'classification5' => '',
            'classification6' => '',
            'classification7' => '',
            'classification8' => '',
            'classification9' => '', // material
            'classification10' => $this->getArticle()->getMainDetail()->getWeight(), // weight
        );

        $key = 5;
        $categoryCollection = $this->getCategoryCollection();
        foreach ($categoryCollection as $currentCategoryName) {
            if ($key > 8) {
                break;
            }

            $classificationData['classification' . $key] = $currentCategoryName;
            $key++;
        }

        return $classificationData;
    }

    /**
     * @return array
     */
    protected function getMainShopCategories()
    {
        /** @var \Doctrine\DBAL\Query\QueryBuilder $queryBuilder */
        $queryBuilder = Shopware()->Container()->get('dbal_connection')->createQueryBuilder();
        $mainCategories = $queryBuilder->select('s1.category_id as id')
            ->from('s_core_shops', 's1')
            ->leftJoin('s1', 's_core_shops', 's2', 's1.template_id = s2.template_id AND s2.default = 1 AND s1.id != s2.id')
            ->where('s1.active = 1')
            ->andWhere('s1.main_id IS NULL')
            ->andWhere('s2.id IS NULL')
            ->orderBy('s1.id', 'ASC')
            ->execute()->fetchAll();

        return $mainCategories;
    }

    /**
     * @param $category \Shopware\Models\Category\Category
     * @param $mainShopCategories array
     * @return bool
     */
    protected function isMainShopCategory($category, $mainShopCategories)
    {
        foreach ($mainShopCategories as $mainCategory) {
            if (strpos($category->getPath(), "|{$mainCategory['id']}|") !== false)
                return true;
        }

        return false;
    }

    /**
     * get article category list
     *
     * @return array
     */
    protected function getCategoryCollection()
    {
        $deepLevel = 0;
        $baseCategory = null;

        $mainShopCategories = $this->getMainShopCategories();

        /** @var Category[] $categoryCollection */
        $categoryCollection = $this->getArticle()->getCategories()->toArray();
        foreach ($categoryCollection as $currentCategory) {
            if (!$currentCategory->getActive()) {
                continue;
            }

            if (($baseCategory != null && $currentCategory->getLevel() > $deepLevel)
                || !$currentCategory->getActive()
            ) {
                continue;
            }

            if (!$this->isMainShopCategory($currentCategory, $mainShopCategories)) {
                continue;
            }

            $baseCategory = $currentCategory;
            $deepLevel = $currentCategory->getLevel();

            continue;
        }

        if ($baseCategory == null) {
            return array();
        }

        $categoryLimit = 0;
        $categoryNameCollection = array($baseCategory->getName());
        $this->logDebug('articleSyncMapping::getCategoryCollection::baseCategory::' . $baseCategory->getName());
        while ($baseCategory->getParent() != null && $categoryLimit < 10) {
            $baseCategory = $baseCategory->getParent();
            $this->logDebug('articleSyncMapping::getCategoryCollection::new baseCategory::' . $baseCategory->getName());
            if ($baseCategory->getParent() == null || preg_match('/root/i', $baseCategory->getParent()->getName())) {
                break;
            }

            $categoryNameCollection[] = trim($baseCategory->getName());
            $categoryLimit++;
        }

        $categoryNameCollection = array_reverse($categoryNameCollection);
        return array_slice($categoryNameCollection, 0, 4);
    }

    /**
     * @return array
     */
    protected function buildVatCollection()
    {
        $vatCollection = array();
        $tax = $this->getArticle()->getTax();

        $vatPercentage = $tax->getTax();
        if ($vatPercentage > 10 || preg_match('/HIGH/i', $tax->getName())) {
            $vatType = 'H';
        } elseif ($vatPercentage > 5 || preg_match('/LOW/i', $tax->getName())) {
            $vatType = 'L';
        }  else {
            $vatType = 'Z';
        }

        $vatCollection[] = array(
            'countryIsoCode' => 'DE',
            'vatType' => $vatType
        );

        return $vatCollection;
    }

    /**
     * @param Detail $articleDetail
     *
     * @return array
     */
    protected function buildPriceCollection(Detail $articleDetail)
    {
        $mappedPriceCollection = array();
        $shops = [
            ['advertisingMediumCode' => '', 'currencyCode' => 'EUR', 'currencyFactor' => 1, 'customerGroup' => 'EK']
        ];

        if ($this->getConfig()['blisstribute-transfer-shop-article-prices']) {
            $shops = array_merge($shops, Shopware()->Db()->fetchAll("SELECT spbs.advertising_medium_code AS advertisingMediumCode, scc.currency as currencyCode, scc.factor AS currencyFactor, sccg.groupkey AS customerGroup FROM s_core_shops scs LEFT JOIN s_core_currencies scc ON scs.currency_id = scc.id LEFT JOIN s_core_customergroups sccg ON scs.customer_group_id = sccg.id LEFT JOIN s_plugin_blisstribute_shop spbs ON scs.id = spbs.s_shop_id WHERE scs.active = 1"));
        }

        foreach($shops as $shop) {
            $price = null;

            /** @var Price[] $priceCollection */
            $priceCollection = $articleDetail->getPrices()->toArray();
            foreach ($priceCollection as $currentPrice) {
                if ($currentPrice->getFrom() != '1'
                    or $currentPrice->getCustomerGroup()->getKey() != $shop['customerGroup']
                ) {
                    continue;
                }

                $price = $currentPrice;
                break;
            }

            if ($price == null) {
                continue;
            }

            $tax = $articleDetail->getArticle()->getTax()->getTax();
            if ($price->getPseudoPrice() > 0) {
                $mappedPriceCollection[] = $this->formatPricesFromNetToGross(
                    $shop['advertisingMediumCode'],
                    $price->getPseudoPrice() * $shop['currencyFactor'],
                    $tax,
                    $shop['currencyCode'],
                    true
                );
            }

            $mappedPriceCollection[] = $this->formatPricesFromNetToGross(
                $shop['advertisingMediumCode'],
                $price->getPrice() * $shop['currencyFactor'],
                $tax,
                $shop['currencyCode'],
                false
            );
        }

        return $mappedPriceCollection;
    }

    /**
     * Internal helper function to convert gross prices to net prices.
     *
     * @param string $advertisingMediumCode
     * @param float $netPrice
     * @param float $tax
     * @param string $currency
     * @param bool $isRecommendedRetailPrice
     *
     * @return array
     */
    protected function formatPricesFromNetToGross($advertisingMediumCode, $netPrice, $tax, $currency, $isRecommendedRetailPrice = false)
    {
        return array(
            'isoCode' => 'DE',
            'currency' => $currency,
            'price' => round($netPrice / 100 * (100 + $tax), 6),
            'isRecommendedRetailPrice' => $isRecommendedRetailPrice,
            'advertisingMediumCode' => $advertisingMediumCode,
            'isSpecialPrice' => false
        );
    }

    /**
     * build series mapping
     *
     * @return array
     */
    protected function buildSeriesCollection()
    {
        if ($this->getArticle()->getMainDetail()->getConfiguratorOptions()->count() == 0) {
            return array();
        }

        /** @var \Shopware\Models\Article\Configurator\Option $configuratorOption */
        $configuratorOption = $this->getArticle()->getMainDetail()->getConfiguratorOptions()->first();

        $aSeriesData = array(
            'seriesType' => $configuratorOption->getGroup()->getName(),
            'seriesCollection' => array(),
            'specificationCollection' => array(),
        );

        $specificationCollection = array();
        /** @var Detail $currentArticleDetail */
        foreach ($this->getArticle()->getDetails() as $currentArticleDetail) {
            $specificationCollection[] = $this->buildSpecificationCollection($currentArticleDetail);
        }

        $aSeriesData['specificationCollection'] = $specificationCollection;
        return $aSeriesData;
    }

    /**
     * build series correlation
     *
     * @param Detail $articleDetail
     *
     * @return array
     */
    protected function buildSeriesCorrelation(Detail $articleDetail)
    {
        if ($articleDetail->getConfiguratorOptions()->count() == 0) {
            return array();
        }

        /** @var \Shopware\Models\Article\Configurator\Option $configuratorOption */
        $configuratorOption = $articleDetail->getConfiguratorOptions()->first();
        return array(
            'seriesType' => trim($configuratorOption->getGroup()->getName()),
            'seriesAttribute' => trim($configuratorOption->getName()),
        );
    }

    /**
     * @param Detail $detail
     * @return string
     */
    protected function getImageUrl($detail)
    {
        /** @var \Shopware\Models\Article\Repository $respository */
        $repository = Shopware()->Models()->getRepository(\Shopware\Models\Article\Article::class);

        $mainImage = null;

        $variantImages = $repository
            ->getVariantImagesByArticleNumberQuery($detail->getNumber())
            ->getArrayResult();

        if (empty($variantImages)) {

            $articleImages = $repository
                ->getArticleImagesQuery($detail->getArticleId())
                ->getArrayResult();

            $mainImage = !empty($articleImages) ? $articleImages[0] : null;
        }
        else {
            $mainImage = $variantImages[0];
        }

        return $mainImage ? $this->getMediaUrlByImage($mainImage) : null;
    }

    /**
     * @param $image
     * @return null|string
     */
    protected function getMediaUrlByImage($image)
    {
        /** @var \Shopware\Bundle\MediaBundle\MediaService $mediaService */
        $mediaService = $this->container->get('shopware_media.media_service');

        return $mediaService->getUrl('media/image/' . $image['path'] . '.' . $image['extension']);
    }

    /**
     * Build collection for SW variants
     *
     * @param Detail $articleDetail
     *
     * @return array
     */
    protected function buildSpecificationCollection(Detail $articleDetail)
    {
        $releaseDate = '';
        if ($articleDetail->getArticle()->getAvailableFrom() !== null) {
            $releaseDate = $articleDetail->getArticle()->getAvailableFrom()->format('Y-m-d H:i:s');
        }

        $removeDate = '';
        if ($articleDetail->getArticle()->getAvailableTo() !== null) {
            $articleDetail->getArticle()->getAvailableTo()->format('Y-m-d H:i:s');
        }

        $specificationData = array(
            'erpArticleNumber' => $this->getArticleVhsNumber($articleDetail),
            'articleNumber' => $articleDetail->getNumber(),
            'ean13' => $articleDetail->getEan(),
            'manufacturerArticleNumber' => $this->_getManufacturerArticleNumber($articleDetail),
            'ean10' => '',
            'isrc' => '',
            'isbn' => '',
            'classification1' => $this->determineDetailArticleName($articleDetail),
            'imageUrl' => $this->getImageUrl($articleDetail),
            'releaseDate' => $releaseDate,
            'removeDate' => $removeDate,
            'releaseState' => (bool)$this->determineArticleActiveState($articleDetail),
            'setCount' => 1,
            'color' => '',
            'colorCode' => '',
            'size' => '',
            'sortingIndex' => 0,
            'reorder' => true,
            'noticeStockLevel' => (int)$articleDetail->getStockMin(),
            'reorderStockLevel' => (int)$articleDetail->getStockMin(),
            'vendorCollection' => array(
                array(
                    'code' => $this->getSupplierCode($articleDetail),
                    'articleNumber' => $articleDetail->getSupplierNumber(),
                    'purchasePrice' => $this->getMainDetailBasePrice($articleDetail),
                    'isPreferred' => true
                )
            ),
            'priceCollection' => $this->buildPriceCollection($articleDetail),
            'seriesCorrelation' => array($this->buildSeriesCorrelation($articleDetail)),
            'tagCollection' => $this->buildTagCollection($articleDetail),
        );

        return $specificationData;
    }

    /**
     * @param Detail $articleDetail
     * @return mixed
     */
    private function getSupplierCode($articleDetail)
    {
        $supplierCode = '';
        if ($articleDetail->getAttribute() != null) {
            $supplierCode = $articleDetail->getAttribute()->getBlisstributeSupplierCode();
        }

        if (trim($supplierCode) == '') {
            if ($articleDetail->getArticle() != null && $articleDetail->getArticle()->getAttribute() != null)
                $supplierCode = $articleDetail->getArticle()->getAttribute()->getBlisstributeSupplierCode();
        }

        $mainDetail = $this->_getMainDetail($articleDetail->getArticle());
        if (trim($supplierCode) == '') {
             if ($mainDetail != null && $mainDetail->getAttribute() != null) {
                $supplierCode = $mainDetail->getAttribute()->getBlisstributeSupplierCode();
            }
        }

        return $supplierCode;
    }

    /**
     * @param Detail $articleDetail
     * @return mixed
     */
    private function _getManufacturerArticleNumber($articleDetail)
    {
        $manufacturerArticleNumber = '';
        if ($articleDetail->getSupplierNumber() && $this->getConfig()['blisstribute-article-sync-manufacturer-article-number']) {
            $manufacturerArticleNumber = $articleDetail->getSupplierNumber();
        }

        return $manufacturerArticleNumber;
    }

    /**
     * @param Detail $articleDetail
     * @return string
     */
    private function getArticleVhsNumber($articleDetail)
    {
        $attribute = $articleDetail->getAttribute();
        if ($attribute == null) {
            $attribute = $articleDetail->getArticle()->getAttribute();
        }

        if ($attribute == null) {
            throw new Exception('malformed article detail detected', $articleDetail->getId());
        }

        $vhsArticleNumber = $attribute->getBlisstributeVhsNumber();
        if (!$vhsArticleNumber) {
            return '';
        }

        return $vhsArticleNumber;
    }

    /**
     * Determine the active state of an article
     *
     * In Shopware, an article might have variants. For ExitB, each Variant is an individual article.
     * If the active state of an article is used for its variants, ExitB is unable to deactivate specific
     * variants.
     *
     * @param Detail $articleDetail
     * @return bool
     */
    private function determineArticleActiveState($articleDetail)
    {
        $mainArticleActive = (bool)$this->getArticle()->getActive();
        if (!$mainArticleActive) {
            return false;
        }

        return $articleDetail->getActive();
    }

    /**
     * determine article detail name (with variant options in brackets)
     *
     * @param Detail $articleDetail
     *
     * @return string
     */
    protected function determineDetailArticleName(Detail $articleDetail)
    {
        $name = $articleDetail->getArticle()->getName();
        if ($articleDetail->getConfiguratorOptions()->count() == 0) {
            return $name;
        }

        $optionValueCollection = array();
        /** @var \Shopware\Models\Article\Configurator\Option $currentConfiguratorOption */
        foreach ($articleDetail->getConfiguratorOptions() as $currentConfiguratorOption) {
            $optionValueCollection[] = $currentConfiguratorOption->getName();
        }

        $name .= ' (' . implode(' | ', $optionValueCollection) . ')';
        return $name;
    }

    /**
     * build tag collection
     *
     * @param Detail $articleDetail
     *
     * @return array
     */
    protected function buildTagCollection(Detail $articleDetail)
    {
        $tagCollection = array();
        if ($articleDetail->getLen() !== null) {
            $tagCollection[] = array(
                'type' => 'length',
                'value' => $articleDetail->getLen(),
                'isMultiTag' => false,
                'deliverer' => 'foreign'
            );
        }

        if ($articleDetail->getWidth() !== null) {
            $tagCollection[] = array(
                'type' => 'width',
                'value' => $articleDetail->getWidth(),
                'isMultiTag' => false,
                'deliverer' => 'foreign'
            );
        }

        if ($articleDetail->getHeight() !== null) {
            $tagCollection[] = array(
                'type' => 'height',
                'value' => $articleDetail->getHeight(),
                'isMultiTag' => false,
                'deliverer' => 'foreign'
            );
        }

        /** @var \Shopware\Models\Property\Value $property */
        foreach ($articleDetail->getArticle()->getPropertyValues() as $property) {
            $tagCollection[] = array(
                'type' => $property->getOption()->getName(),
                'value' => $property->getValue(),
                'isMultiTag' => false,
                'deliverer' => 'foreign'
            );
        }



        return $tagCollection;
    }

}
