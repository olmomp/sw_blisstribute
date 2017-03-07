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
    use Shopware_Components_Blisstribute_Domain_LoggerTrait;

    /**
     * get shopware article for blisstribute mapped article
     *
     * @return Article
     */
    protected function getArticle()
    {
        return $this->getModelEntity()->getArticle();
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
            'customsTariffNumber' => '',
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

        return $articleData;
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
            'classification3' => '',
            'classification4' => '',
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
     * get article category list
     *
     * @return array
     */
    protected function getCategoryCollection()
    {
        $deepLevel = 0;
        $baseCategory = null;

        // get english category to exclude
        $categoryRepository = Shopware()->Models()->getRepository('Shopware\Models\Category\Category');
        /** @var Category|null $englishCategory */
        $englishCategory = $categoryRepository->createQueryBuilder('category')
            ->select('c')
            ->from('Shopware\Models\Category\Category', 'c')
            ->where('c.name = :categoryName')
            ->andWhere('c.active = :categoryActive')
            ->setParameters(array(
                'categoryName' => 'Englisch',
                'categoryActive' => true,
            ))
            ->getQuery()
            ->getOneOrNullResult();

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

            if ($englishCategory != null
                && strpos($currentCategory->getPath(), '|' . $englishCategory->getId() . '|') !== false
            ) {
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
        while ($baseCategory->getParent() != null
            && strtolower($baseCategory->getParent()->getName()) != 'root'
            && strtolower($baseCategory->getParent()->getName()) != 'deutsch'
            && strtolower($baseCategory->getParent()->getName()) != 'englisch'
            && $categoryLimit < 10
        ) {
            $baseCategory = $baseCategory->getParent();
            $categoryNameCollection[] = trim($baseCategory->getName());

            $categoryLimit++;
        }

        $categoryNameCollection = array_reverse($categoryNameCollection);
        return array_slice($categoryNameCollection, 0, 4);
    }

//    /**
//     * @param \Shopware\Models\Article\Article $article
//     * @return string
//     * @throws Exception
//     */
//    protected function _determineArticleType(Shopware\Models\Article\Article $article)
//    {
//        $filter = $article->getPropertyGroup();
//        $query = $this->blisstributeArticleRepository->getFilterQuery($filter->getId());
//        $articleType = $query->execute();
//        if (empty($articleType)) {
//            throw new Exception('invalid blisstribute article type');
//        }
//        /* @var Shopware\CustomModels\Blisstribute\BlisstributeArticleType $articleType */
//        $articleType = $articleType[0];
//        switch ($articleType->getArticleType()) {
//            case 1:
//                return 'MUSIC';
//            case 2:
//                return 'WEAR';
//            case 3:
//                return 'WEAR-ATTIRE';
//            case 4:
//                return 'EQUIPMENT';
//            case 0:
//            default:
//                throw new Exception('invalid blisstribute article type');
//        }
//    }

    /**
     * @todo implement tax rules ??
     * @todo better mapping of vat types??
     *
     * @return array
     */
    protected function buildVatCollection()
    {
        $vatCollection = array();
        $articleTax = $this->getArticle()->getTax()->getTax();
        if ($articleTax > 10) {
            $vatType = 'HIGH';
        } elseif ($articleTax > 5) {
            $vatType = 'LOW';
        } elseif ($articleTax > 0) {
            $vatType = 'VERY-LOW';
        } else {
            $vatType = 'ZERO';
        }

        $countryRepository = Shopware()->Models()->getRepository('Shopware\Models\Country\Country');
        $germany = $countryRepository->findOneBy(array(
            'iso' => 'DE'
        ));

        $vatCollection[] = array(
            'countryIsoCode' => $germany->getIso(),
            'vatType' => $vatType
        );
        //$countryCollection = $countryRepository->getCountriesQuery()->getResult();
//        foreach ($countryCollection as $country) {
//            if($country['iso'] == 'DE') {
//                $vatCollection[] = array(
//                    'countryIsoCode' => $country['iso'],
//                    'vatType' => $vatType
//                );
//            }
//
//        }
        return $vatCollection;
    }

    /**
     * @param Detail $articleDetail
     *
     * @return array
     */
    protected function buildPriceCollection(Detail $articleDetail)
    {
        $price = null;

        /** @var Price[] $priceCollection */
        $priceCollection = $articleDetail->getPrices()->toArray();
        foreach ($priceCollection as $currentPrice) {
            if ($currentPrice->getTo() != 'beliebig'
                || !$currentPrice->getCustomerGroup()->getTaxInput()
            ) {
                continue;
            }

            $price = $currentPrice;
            break;
        }

        if ($price == null) {
            return array();
        }

        $isSpecialPrice = false;
        $mappedPriceCollection = array();
        if ($price->getPseudoPrice() > 0 && $price->getPseudoPrice() > $price->getPrice()) {
            $isSpecialPrice = true;

            $mappedPriceCollection[] = $this->formatPricesFromNetToGross(
                $price->getPseudoPrice(),
                $articleDetail->getArticle()->getTax(),
                false
            );
        }

        $mappedPriceCollection[] =  $this->formatPricesFromNetToGross(
            $price->getPrice(),
            $articleDetail->getArticle()->getTax(),
            $isSpecialPrice
        );

        return $mappedPriceCollection;
    }

    /**
     * Internal helper function to convert gross prices to net prices.
     *
     * @todo implement advertising medium mapping
     * @todo implement country mapping
     *
     * @param float $price
     * @param Tax $tax
     * @param bool $isSpecial
     *
     * @return array
     */
    protected function formatPricesFromNetToGross($price, Tax $tax, $isSpecial = false)
    {
        return array(
            'isoCode' => 'DE',
            'currency' => 'EUR',
            'price' => round($price / 100 * (100 + $tax->getTax()), 6),
            'isSpecial' => $isSpecial,
            'isNetPrice' => false,
            'isRecommendedRetailPrice' => false,
            'advertisingMediumCode' => '',
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
     * @param Detail $articleDetail
     * @return string
     */
    protected function getImageUrl(Detail $articleDetail)
    {
        $imageUrl = '';
        /** @var \Shopware\Bundle\MediaBundle\MediaService $mediaService */
        $mediaService = Shopware()->Container()->get('shopware_media.media_service');
        /** @var \Shopware\Models\Article\Image[] $imageCollection */
        $imageCollection = $articleDetail->getImages();

        $image = null;
        foreach ($imageCollection as $currentImage) {
            if ($currentImage->getMedia() != null) {
                $image = $currentImage;
            }
        }

        if (count($imageCollection) == 0 || $image == null) {
            $this->logDebug('articleSyncMapping::got article collection ' . count($imageCollection));
            $imageCollection = $articleDetail->getArticle()->getImages();
        }

        if (count($imageCollection) > 0) {
            foreach ($imageCollection as $currentImage) {
                if ($currentImage->getMedia() != null) {
                    $image = $currentImage;
                    break;
                }
            }

            if ($image != null) {
                // sync thumbnails?
                /*$thumbnailSizeCollection = $image->getMedia()->getDefaultThumbnails();
                if (count($thumbnailSizeCollection) > 0) {
                    $thumbnailSize = implode('x', $thumbnailSizeCollection[0]);
                    $thumbnail = $image->getMedia()->getThumbnailFilePaths()[$thumbnailSize];

                    if ($mediaService->has($thumbnail)) {
                        $imageUrl = $mediaService->getUrl($thumbnail);
                        $this->logInfo($imageUrl);
                    }
                }*/

                if ($mediaService->has('media/image/' . $image->getMedia()->getFileName())) {
                    $imageUrl = $mediaService->getUrl('media/image/' . $image->getMedia()->getFileName());
                    $this->logInfo($imageUrl);
                }
            }
        }

        return $imageUrl;
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
            'vendorCollection' => array(),
            'priceCollection' => $this->buildPriceCollection($articleDetail),
            'seriesCorrelation' => array($this->buildSeriesCorrelation($articleDetail)),
            'tagCollection' => $this->buildTagCollection($articleDetail),
        );

        return $specificationData;
    }

    /**
     * @param Detail $articleDetail
     * @return string
     */
    private function getArticleVhsNumber($articleDetail)
    {
        $vhsArticleNumber = $articleDetail->getAttribute()->getBlisstributeVhsNumber();
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
        $article = $this->getModelEntity()->getArticle();
        if (!$article->getActive()) {
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

//        // get configurator groups with multiple values
//        $multiTagGroupIds = array();
//        $usedGroups = array();

//        // @todo use article properties ??
//        /* @var Shopware\Models\Article\Configurator\Option $configuratorOption */
//        foreach ($articleDetail->getConfiguratorOptions() as $configuratorOption) {
//            if (in_array($configuratorOption->getGroup()->getId(), $usedGroups)) {
//                $multiTagGroupIds[] = $configuratorOption->getGroup()->getId();
//            } else {
//                $usedGroups[] = $configuratorOption->getGroup()->getId();
//            }
//        }
//
//        /* @var \Shopware\Models\Article\Configurator\Option $configuratorOption */
//        foreach ($articleDetail->getConfiguratorOptions() as $configuratorOption) {
//            $configuratorGroup = $configuratorOption->getGroup();
//            $tagCollection[] = array(
//                'type' => $configuratorGroup->getName(),
//                'value' => $configuratorOption->getName(),
//                'isMultiTag' => in_array($configuratorGroup->getId(), $multiTagGroupIds),
//                'deliverer' => 'foreign'
//            );
//        }

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

        return $tagCollection;
    }
	
	/**
	 * get the base price of the main detail
	 *
	 * @param Detail $articleDetail
	 *
	 * @return float
	 */
	protected function getMainDetailBasePrice(Detail $articleDetail)
	{
		if (version_compare(Shopware()->Config()->version, '5.2.0', '>=')) {
			return $articleDetail->getPurchasePrice();
		}
		
		return $articleDetail->getPrices()->first()->getBasePrice();
	}
}
