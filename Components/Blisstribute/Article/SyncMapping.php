<?php

require_once __DIR__ . '/../SyncMapping.php';

use Shopware\Models\Article\Detail;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Price;
use Shopware\Models\Category\Category;
use Shopware\CustomModels\Blisstribute\BlisstributeArticle;

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
    private $container;

    public function __construct()
    {
        $this->container = Shopware()->Container();
    }

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
        }catch (Exception $e) {
            $this->logWarn($e->getMessage());
            throw $e;
        }
    }

    /**
     * Returns the corresponding Shopware article for the mapped Blisstribute article.
     *
     * @return Article
     */
    protected function getArticle()
    {
        return $this->getModelEntity()->getArticle();
    }

    /**
     * @return mixed
     * @throws Exception
     */
    protected function buildBaseData()
    {
        // Fetch details of parent article + variants.
        $articleDetails = $this->getArticle()->getDetails();
        $articleData = [];

        foreach ($articleDetails as $articleDetail) {
            $classifications = $this->buildClassificationData();

            $data = [
                // Required fields.
                'classification1'  => $classifications['classification1'],
                'classification2'  => $classifications['classification2'],
                'releaseDate'      => $this->getReleaseDate($articleDetail),
                'identifications'  => $this->getIdentifications($articleDetail),
                'vatRates'         => $this->getVatRates(),
                'prices'           => $this->getPrices($articleDetail),

                // Unavailable fields in Shopware.
                // 'unitType'            => Not used, because it would override on each sync.
                // 'stockLevel'          => Not used, because it would override on each sync.
                // 'isConsignment'       => ...,
                // 'isSet'               => ...,
                // 'calculateStockLevel' => ...,
                // 'isPurchasable'       => ...,
                // 'isSale'              => ...,
                // 'isPackingMaterial'   => ...,
                // 'isVirtual'           => ...,
                // 'hasSerialNumber'     => ...,
                // 'removeDate'          => $this->getRemoveDate(),
            ];

            // Optional fields.
            $optional = [
                'vhsArticleNumber' => $this->getArticleVhsNumber($articleDetail),
                'classification3'  => $classifications['classification3'],
                'classification4'  => $classifications['classification4'],
                'classification5'  => $classifications['classification5'],
                'imageUrl'         => $this->getImageUrl($articleDetail),
                'isActive'         => $this->determineArticleActiveState($articleDetail),
                'tags'             => $this->getTags($articleDetail),
                'customsData'      => $this->getCustomsData($articleDetail),
            ];

            $vendorData = $this->getVendors($articleDetail);
            if (count($vendorData) > 0) {
                $optional['vendors'] = $vendorData;
            }

            // Only add optional fields that are not null to data.
            foreach ($optional as $k => $v) {
                if ($v != null) {
                    $data[$k] = $v;
                }
            }

            $articleData[] = $data;
        }

        // Allow plugins to change the data.
        return Enlight()->Events()->filter('ExitBBlisstribute_ArticleSyncMapping_AfterBuildBaseData', $articleData,
            ['subject' => $this, 'article' => $this->getArticle()]);
    }

    /**
     * @param Detail $articleDetail
     * @return string|null
     */
    private function getReleaseDate(Detail $articleDetail)
    {
        $releaseDate = $articleDetail->getReleaseDate() ?? false;

        if (!$releaseDate) {
            return null;
        }

        return $releaseDate->format('Y-m-d');
    }

    /**
     * @param Detail $articleDetail
     * @return array
     * @throws Exception
     */
    private function getIdentifications(Detail $articleDetail)
    {
        return [
            [
                'identificationType' => 'external-key',
                'identification'     => $this->getArticleVhsNumber($articleDetail),
            ],

            [
                'identificationType' => 'article_number',
                'identification'     => $articleDetail->getNumber(),
            ],

            [
                'identificationType' => 'ean13',
                'identification'     => $articleDetail->getEan(),
            ],

            [
                'identificationType' => 'manufacturer_article_number',
                'identification'     => $this->getManufacturerArticleNumber($articleDetail),
            ],
        ];
    }

    /**
     * @param $articleDetail
     * @return array
     */
    private function getCustomsData($articleDetail)
    {
        return [
            'tariffNumber'        => $this->_getCustomsTariffNumber($articleDetail),
            'countryCodeOfOrigin' => $this->_getCountryOfOrigin($articleDetail)
        ];
    }

    /**
     * @param Detail $articleDetail
     * @return array
     */
    private function getTags(Detail $articleDetail)
    {
        $tags = [];

        if ($articleDetail->getWeight() !== null) {
            $tags[] = [
                'type'  => 'weight',
                'value' => $articleDetail->getWeight(),
            ];
        }

        if ($articleDetail->getLen() !== null) {
            $tags[] = [
                'type'  => 'length',
                'value' => $articleDetail->getLen(),
            ];
        }

        if ($articleDetail->getWidth() !== null) {
            $tags[] = [
                'type'  => 'width',
                'value' => $articleDetail->getWidth(),
            ];
        }

        if ($articleDetail->getHeight() !== null) {
            $tags[] = [
                'type'  => 'height',
                'value' => $articleDetail->getHeight(),
            ];
        }

        /** @var \Shopware\Models\Property\Value $property */
        foreach ($articleDetail->getArticle()->getPropertyValues() as $property) {
            $tags[] = [
                'type'  => $property->getOption()->getName(),
                'value' => $property->getValue(),
            ];
        }

        return $tags;
    }

    /**
     * @param Detail $articleDetail
     * @return array
     */
    private function getStockLevel(Detail $articleDetail)
    {
        return [
            // 'noticeStockLevel'  => (int) $articleDetail->getStockMin(),
            // 'reorderStockLevel' => (int) $articleDetail->getStockMin(),
            'minimumStockLevel' => (int) $articleDetail->getStockMin(),
        ];
    }

    /**
     * @return array
     */
    private function getVatRates()
    {
        $tax           = $this->getArticle()->getTax();
        $vatPercentage = $tax->getTax();

        if ($vatPercentage > 10 || preg_match('/HIGH/i', $tax->getName())) {
            $vatType = 'H';
        } elseif ($vatPercentage > 5 || preg_match('/LOW/i', $tax->getName())) {
            $vatType = 'L';
        }  else {
            $vatType = 'Z';
        }

        $vatRates = [[
            'countryCode' => 'DE',
            'vatType'     => $vatType
        ]];

        return $vatRates;
    }

    /**
     * @param Detail $articleDetail
     * @return array
     * @throws Exception
     */
    private function getPrices(Detail $articleDetail)
    {
        $shops = [['currency' => 'EUR', 'currencyFactor' => 1, 'customerGroup' => 'EK']];

        if ($this->getConfig()['blisstribute-transfer-shop-article-prices']) {
            $shops = array_merge(
                $shops,
                Shopware()->Db()->fetchAll('
                    SELECT spbs.advertising_medium_code AS advertisingMediumCode, scc.currency, scc.factor AS currencyFactor, sccg.groupkey AS customerGroup
                    FROM s_core_shops scs
                    LEFT JOIN s_core_currencies scc ON scs.currency_id = scc.id
                    LEFT JOIN s_core_customergroups sccg ON scs.customer_group_id = sccg.id
                    LEFT JOIN s_plugin_blisstribute_shop spbs ON scs.id = spbs.s_shop_id
                    WHERE scs.active = 1
                '));
        }

        $prices = [];

        foreach($shops as $shop) {
            $price = null;

            /** @var Price[] $priceCollection */
            $priceCollection = $articleDetail->getPrices()->toArray();
            foreach ($priceCollection as $currentPrice) {
                if ($currentPrice->getFrom() != '1' || $currentPrice->getCustomerGroup()->getKey() != $shop['customerGroup']) {
                    continue;
                }

                $price = $currentPrice;
                break;
            }

            if ($price == null) {
                continue;
            }

            $tax = $articleDetail->getArticle()->getTax()->getTax();

            // If a recommended retail price is given, append it to the list of prices separately.
            if ($price->getPseudoPrice() > 0) {
                $prices[] = $this->formatPricesFromNetToGross(
                    $shop['advertisingMediumCode'],
                    $price->getPseudoPrice() * $shop['currencyFactor'],
                    $tax,
                    $shop['currency'],
                    true
                );
            }

            $prices[] = $this->formatPricesFromNetToGross(
                $shop['advertisingMediumCode'],
                $price->getPrice() * $shop['currencyFactor'],
                $tax,
                $shop['currency'],
                false
            );
        }

        return $prices;
    }

    /**
     * @param $articleDetail
     * @return array
     * @throws Exception
     */
    private function getVendors(Detail $articleDetail)
    {
        $supplierCode = $this->getSupplierCode($articleDetail);
        if (trim($supplierCode) == '') {
            return [];
        }

        return [[
            'supplierCode'         => $supplierCode,
            'price'                => (float) $this->getMainDetailBasePrice($articleDetail),
            'packingUnit'          => 1,
            'orderUnit'            => 1,
            'minOrder'             => 1,
            'maxOrder'             => 0,
            'articleNumber'        => $articleDetail->getSupplierNumber(),
            'unitType'             => 'STK',
            'isPurchasable'        => !$articleDetail->getLastStock()
            // 'priceForeignCurrency' => $this->getArticle()->getSupplier()->...,
        ]];
    }

    /**
     * @param Detail $detail
     * @return string
     */
    protected function _getCustomsTariffNumber(Detail $detail)
    {
        return $detail->getArticle()->getMainDetail()->getAttribute()->getBlisstributeCustomsTariffNumber();
    }

    /**
     * @param Detail $detail
     * @return string
     */
    protected function _getCountryOfOrigin(Detail $detail)
    {
        return $detail->getArticle()->getMainDetail()->getAttribute()->getBlisstributeCountryOfOrigin();
    }

    /**
     * @param Article $article
     *
     * @return string
     * @throws Exception
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
     * @throws Exception
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
        $method = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $fieldName)));

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
     * @throws Exception
     */
    protected function buildClassificationData()
    {
        $data = array(
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

            $data['classification' . $key] = $currentCategoryName;
            $key++;
        }

        return $data;
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
     * Internal helper function to convert net prices to gross prices.
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
        return array_filter(
            [
                'countryCode'              => 'DE',
                'currency'                 => $currency,
                'price'                    => round($netPrice / 100 * (100 + $tax), 6),
                'advertisingMediumCode'    => $advertisingMediumCode,
                'isNet'                    => false,
                'isRecommendedRetailPrice' => $isRecommendedRetailPrice,
            ],

            function ($v) {
                return $v != null;
            }
        );
    }

    /**
     * build series mapping
     *
     * @return array
     * @throws Exception
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
    protected function getImageUrl(Detail $detail)
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
     * @throws Exception
     */
    protected function buildSpecificationCollection(Detail $articleDetail)
    {
        $specificationData = array(
            'erpArticleNumber' => $this->getArticleVhsNumber($articleDetail),
            'articleNumber' => $articleDetail->getNumber(),
            'classification1' => $this->determineDetailArticleName($articleDetail),
            'imageUrl' => $this->getImageUrl($articleDetail),
            'releaseState' => (bool)$this->determineArticleActiveState($articleDetail),
            'setCount' => 1,
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
            'priceCollection' => $this->getPrices($articleDetail),
            'seriesCorrelation' => array($this->buildSeriesCorrelation($articleDetail)),
            'tagCollection' => $this->getTags($articleDetail),
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
            $mainDetail = $this->_getMainDetail($articleDetail->getArticle());
            if ($mainDetail != null && $mainDetail->getAttribute() != null) {
                $supplierCode = $mainDetail->getAttribute()->getBlisstributeSupplierCode();
            }
        }

        return $supplierCode;
    }

    /**
     * @param Detail $articleDetail
     * @return mixed
     * @throws Exception
     */
    private function getManufacturerArticleNumber($articleDetail)
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
     * @throws Exception
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
    private function determineArticleActiveState(Detail $articleDetail)
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

}
