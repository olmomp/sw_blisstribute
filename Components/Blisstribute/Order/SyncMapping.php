<?php

require_once __DIR__ . '/../SyncMapping.php';
require_once __DIR__ . '/../Exception/MappingException.php';
require_once __DIR__ . '/../Exception/ValidationMappingException.php';
require_once __DIR__ . '/../Exception/OrderPaymentMappingException.php';
require_once __DIR__ . '/../Exception/OrderShipmentMappingException.php';
require_once __DIR__ . '/../Domain/LoggerTrait.php';

use Shopware\Models\Order\Detail;
use Shopware\Models\Article\Article;
use Shopware\Models\Voucher\Voucher;
use Shopware\Models\Article\Repository as ArticleRepository;
use Shopware\Models\Voucher\Repository as VoucherRepository;

use Doctrine\ORM\NonUniqueResultException;

use Shopware\CustomModels\Blisstribute\BlisstributeOrder;
use Shopware\CustomModels\Blisstribute\BlisstributePaymentRepository;
use Shopware\CustomModels\Blisstribute\BlisstributeShipmentRepository;

/**
 * blisstribute order sync mapping class
 *
 * @author    Julian Engler
 * @package   Shopware\Components\Blisstribute\Order\SyncMapping
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 *
 * @method BlisstributeOrder getModelEntity()
 */
class Shopware_Components_Blisstribute_Order_SyncMapping extends Shopware_Components_Blisstribute_SyncMapping
{
    use Shopware_Components_Blisstribute_Domain_LoggerTrait;

    /**
     * mapped order data
     *
     * @var array
     */
    protected $orderData = array();

    /**
     * order discount
     *
     * @var float
     */
    protected $voucherDiscountValue = 0.00;

    /**
     * used discount
     *
     * @var float
     */
    protected $voucherDiscountUsed = 0.00;

    /**
     * all used vouchers
     *
     * @var Voucher[]
     */
    protected $voucherCollection = array();

    private $container = null;

    /**
     * get blisstribute shipment mapping database repository
     *
     * @return BlisstributeShipmentRepository
     */
    protected function getShipmentMappingRepository()
    {
        return Shopware()->Models()->getRepository('Shopware\CustomModels\Blisstribute\BlisstributeShipment');
    }

    /**
     * return blisstribute payment mapping database repository
     *
     * @return BlisstributePaymentRepository
     */
    protected function getPaymentMappingRepository()
    {
        return Shopware()->Models()->getRepository('Shopware\CustomModels\Blisstribute\BlisstributePayment');
    }

    /**
     * return blisstribute shop mapping database repository
     *
     * @return \Shopware\CustomModels\Blisstribute\BlisstributeShopRepository
     */
    protected function getShopMappingRepository()
    {
        return Shopware()->Models()->getRepository('Shopware\CustomModels\Blisstribute\BlisstributeShop');
    }

    /**
     * return blisstribute coupon mapping database repository
     *
     * @return \Shopware\CustomModels\Blisstribute\BlisstributeCouponRepository
     */
    protected function getCouponMappingRepository()
    {
        return Shopware()->Models()->getRepository('Shopware\CustomModels\Blisstribute\BlisstributeCoupon');
    }
    
    /**
     * return shopware plugin repository
     *
     * @return \Shopware\Models\Plugin\Plugin
     */
    protected function getPluginRepository()
    {
        return Shopware()->Models()->getRepository('Shopware\Models\Plugin\Plugin');
    }

    /**
     * @inheritdoc
     */
    protected function buildBaseData()
    {
        $this->logDebug('buildBaseData start');
        // determine used vouchers
        $this->determineVoucherDiscount();

        $this->orderData = $this->buildBasicOrderData();
        $this->orderData['payment'] = $this->buildPaymentData();
        $this->orderData['advertisingMedium'] = $this->buildAdvertisingMediumData();
        $this->orderData['billAddressData'] = $this->buildInvoiceAddressData();
        $this->orderData['deliveryAddressData'] = $this->buildDeliveryAddressData();
        $this->orderData['orderLines'] = $this->buildArticleData();
        $this->orderData['orderCoupons'] = $this->buildCouponData();

        $this->logDebug('buildBaseData done');

        return $this->orderData;
    }

    public function __construct()
    {
        $this->container = Shopware()->Container();
    }

    /**
     * build basic order data
     *
     * @return array
     *
     * @throws Shopware_Components_Blisstribute_Exception_OrderShipmentMappingException
     */
    protected function buildBasicOrderData()
    {
        $order = $this->getModelEntity()->getOrder();
        $customer = $order->getCustomer();
        $billingAddress = $order->getBilling();

        $orderRemark = array();
        if (trim($order->getCustomerComment()) != '') {
            $orderRemark[] = trim($order->getCustomerComment());
        }

        if ($order->getOrderStatus()->getId() == 8) {
            $orderRemark[] = 'Bestellung prüfen - Bestellstatus Klärung';
        }

        if ($order->getPaymentStatus()->getId() == 21) {
            $orderRemark[] = 'Zahlung prüfen - Shopware Zahlungshinweis';
        }

        // todo change to config decision
//        $customerNumber = $customer->getBilling()->getNumber();
        $customerNumber = $customer->getEmail();

        $isB2BOrder = false;
        if (trim($billingAddress->getCompany()) != '') {
            $isB2BOrder = true;
        }
		
		if (version_compare(Shopware()->Config()->version, '5.2.0', '>=')) {
			$customerBirthday = $customer->getBirthday();
		} else {
			$customerBirthday = $customer->getBilling()->getBirthday();
		}

        return array(
            'externalCustomerNumber' => $customerNumber,
            'externalCustomerEmail' => $customer->getEmail(),
            'externalCustomerPhoneNumber' => $customer->getBilling()->getPhone(),
            'externalCustomerMobilePhoneNumber' => '',
            'externalCustomerFaxNumber' => '',
            'customerBirthdate' => $customerBirthday,
            'externalOrderNumber' => $order->getNumber(),
            'customerOrderNumber' => '',
            'isAnonymousCustomer' => false,
            'orderDate' => $order->getOrderTime()->format('Y-m-d H:i:s'),
            'orderShipLock' => false,
            'orderCurrency' => $order->getCurrency(),
            'orderRemark' => implode(' - ', $orderRemark),
            'isB2BOrder' => $isB2BOrder,
            'advertisingMediumCode' => '',
            'shipmentType' => $this->determineShippingType(),
            'shipmentTotal' => $order->getInvoiceShipping(),
        );
    }

    /**
     * determine blisstribute shipping code
     *
     * @return string
     *
     * @throws Shopware_Components_Blisstribute_Exception_OrderShipmentMappingException
     */
    protected function determineShippingType()
    {
        $shipmentRepository = $this->getShipmentMappingRepository();
        $shipment = $shipmentRepository->findOneByShipment($this->getModelEntity()->getOrder()->getDispatch()->getId());
        if ($shipment === null) {
            throw new Shopware_Components_Blisstribute_Exception_OrderShipmentMappingException(
                'no shipment mapping given for order ' . $this->getModelEntity()->getOrder()->getNumber()
            );
        }

        if (trim($shipment->getClassName()) == '') {
            throw new Shopware_Components_Blisstribute_Exception_OrderShipmentMappingException(
                'no shipment mapping class found for order ' . $this->getModelEntity()->getOrder()->getNumber()
            );
        }

        $shipmentClassFileName = str_replace(' ', '', ucwords(str_replace('_', ' ', $shipment->getClassName())));
        $shipmentClass = 'Shopware_Components_Blisstribute_Order_Shipment_'
            . $shipmentClassFileName;

        /** @noinspection PhpIncludeInspection */
        include_once __DIR__ . '/Shipment/' . $shipmentClassFileName . '.php';
        if (!class_exists($shipmentClass)) {
            throw new Shopware_Components_Blisstribute_Exception_OrderShipmentMappingException(
                'no shipment mapping class found for order ' . $this->getModelEntity()->getOrder()->getNumber()
            );
        }

        /** @var Shopware_Components_Blisstribute_Order_Shipment_Abstract $orderShipment */
        $orderShipment = new $shipmentClass;
        return $orderShipment->getCode();
    }

    /**
     * build advertising medium data
     *
     * @return array
     */
    protected function buildAdvertisingMediumData()
    {
        $advertisingMediumCode = strtoupper(trim(Shopware()->Config()->get('blisstribute-default-advertising-medium')));

        $shopMappingRepository = $this->getShopMappingRepository();
        $shopMapping = $shopMappingRepository->findOneByShop($this->getModelEntity()->getOrder()->getShop()->getId());
        if ($shopMapping != null && trim($shopMapping->getAdvertisingMediumCode())) {
            $advertisingMediumCode = strtoupper(trim($shopMapping->getAdvertisingMediumCode()));
        }

        $advertisingMediumData = array(
            'origin' => 'O',
            'medium' => 'O',
            'code' => $advertisingMediumCode,
            'affiliateSource' => '',
        );

        return $advertisingMediumData;
    }

    /**
     * build payment data
     *
     * @return array
     *
     * @throws Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException
     */
    protected function buildPaymentData()
    {
        $paymentRepository = $this->getPaymentMappingRepository();
        $payment = $paymentRepository->findOneByPayment($this->getModelEntity()->getOrder()->getPayment()->getId());
        if ($payment === null) {
            throw new Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException(
                'no payment mapping given for order ' . $this->getModelEntity()->getOrder()->getNumber()
            );
        }

        if (trim($payment->getClassName()) == '') {
            throw new Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException(
                'no payment mapping class found for order ' . $this->getModelEntity()->getOrder()->getNumber()
            );
        }

        $paymentClassFileName = $payment->getClassName();
        $paymentClass = 'Shopware_Components_Blisstribute_Order_Payment_' . $paymentClassFileName;

        /** @noinspection PhpIncludeInspection */
        include_once __DIR__ . '/Payment/' . $paymentClassFileName . '.php';
        if (!class_exists($paymentClass)) {
            throw new Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException(
                'no payment mapping class found for order ' . $this->getModelEntity()->getOrder()->getNumber()
            );
        }

        /** @var Shopware_Components_Blisstribute_Order_Payment_Abstract $orderPayment */
        $orderPayment = new $paymentClass($this->getModelEntity()->getOrder(), $payment);
        return $orderPayment->getPaymentInformation();
    }

    /**
     * build invoice address data
     *
     * @return array
     *
     * @throws Shopware_Components_Blisstribute_Exception_ValidationMappingException
     */
    protected function buildInvoiceAddressData()
    {
        $billing = $this->getModelEntity()->getOrder()->getBilling();
        if ($billing == null) {
            throw new Shopware_Components_Blisstribute_Exception_ValidationMappingException(
                'no billing address given for order ' . $this->getModelEntity()->getOrder()->getNumber()
            );
        }

        $gender = '';
        $salutation = '';
        if ($billing->getSalutation() == 'mr') {
            $gender = 'm';
            $salutation = base64_encode('Herr');
        } elseif ($billing->getSalutation() == 'ms') {
            $gender = 'f';
            $salutation = base64_encode('Frau');
        }

        $country = $billing->getCountry();
        if ($country == null) {
            throw new Shopware_Components_Blisstribute_Exception_ValidationMappingException('no country given');
        }

        $street = $billing->getStreet();
        $houseNumber = '';
        if (strrpos($street, ' ') !== false) {
            $houseNumber = substr($street, strrpos($street, ' ') + 1);
            $street = substr($street, 0, strrpos($street, ' '));
        }

        $invoiceAddressData = array(
            'addressType' => 'BILL',
            'salutation' => $salutation,
            'title' => '',
            'firstName' => base64_encode($billing->getFirstName()),
            'addName' => base64_encode($billing->getDepartment()),
            'lastName' => base64_encode($billing->getLastName()),
            'company' => base64_encode($billing->getCompany()),
            'gender' => $gender,
            'street' => base64_encode($street),
            'houseNumber' => base64_encode($houseNumber),
            'addressAddition' => base64_encode($billing->getAdditionalAddressLine1()),
            'zipCode' => base64_encode($billing->getZipCode()),
            'city' => base64_encode($billing->getCity()),
            'countryCode' => $country->getIso(),
            'isTaxFree' => (((bool)$this->getModelEntity()->getOrder()->getTaxFree()) ? true : false),
            'taxIdNumber' => trim($billing->getVatId()),
            'remark' => '',
            'stateCode' => '',
        );

        return $invoiceAddressData;
    }

    /**
     * build delivery address data
     *
     * @return array
     *
     * @throws Shopware_Components_Blisstribute_Exception_ValidationMappingException
     */
    protected function buildDeliveryAddressData()
    {
        $shipping = $this->getModelEntity()->getOrder()->getShipping();
        if ($shipping == null) {
            throw new Shopware_Components_Blisstribute_Exception_ValidationMappingException(
                'no shipping address given for order ' . $this->getModelEntity()->getOrder()->getNumber()
            );
        }

        $gender = '';
        $salutation = '';
        if ($shipping->getSalutation() == 'mr') {
            $gender = 'm';
            $salutation = base64_encode('Herr');
        } elseif ($shipping->getSalutation() == 'ms') {
            $gender = 'f';
            $salutation = base64_encode('Frau');
        }

        $country = $shipping->getCountry();
        if ($country == null) {
            throw new Shopware_Components_Blisstribute_Exception_ValidationMappingException('no country given');
        }

        $street = $shipping->getStreet();
        $houseNumber = '';
        if (strrpos($street, ' ') !== false) {
            $houseNumber = substr($street, strrpos($street, ' ') + 1);
            $street = substr($street, 0, strrpos($street, ' '));
        }

        $deliveryAddressData = array(
            'addressType' => 'DELIVERY',
            'salutation' => $salutation,
            'title' => '',
            'firstName' => base64_encode($shipping->getFirstName()),
            'addName' => base64_encode($shipping->getDepartment()),
            'lastName' => base64_encode($shipping->getLastName()),
            'company' => base64_encode($shipping->getCompany()),
            'gender' => $gender,
            'street' => base64_encode($street),
            'houseNumber' => base64_encode($houseNumber),
            'addressAddition' => base64_encode($shipping->getAdditionalAddressLine1()),
            'zipCode' => base64_encode($shipping->getZipCode()),
            'city' => base64_encode($shipping->getCity()),
            'countryCode' => $country->getIso(),
            'isTaxFree' => (((bool)$this->getModelEntity()->getOrder()->getTaxFree()) ? true : false),
            'taxIdNumber' => '',
            'remark' => '',
            'stateCode' => '',
        );

        return $deliveryAddressData;
    }

    /**
     * build article list
     *
     * @return array
     */
    protected function buildArticleData()
    {
        $articleDataCollection = array();

        $swOrder = $this->getModelEntity()->getOrder();
        $basketItems = $swOrder->getDetails();
        $orderId = $swOrder->getId();

        $promotions = array();
        $orderNumbers = array();
        $shopwareDiscountsAmount = 0;

        /** @var ArticleRepository $articleRepository */
        $articleRepository = $this->container->get('models')->getRepository('Shopware\Models\Article\Article');

        foreach ($basketItems as $product) {
            $price = $product->getPrice();
            $quantity = $product->getQuantity();
            $mode = $product->getMode();
            $articleNumber = $product->getArticleNumber();
            $customerGroupId = $product->getOrder()->getCustomer()->getGroup()->getId();
            $shopId =  $product->getOrder()->getShop()->getId();

            if (in_array($mode, array(3, 4))) {
                if (in_array($articleNumber, ['sw-payment', 'sw-discount', 'sw-payment-absolute']) || $mode == 2) {
                    $shopwareDiscountsAmount += $product->getPrice();
                } else {
                    $promotions[$product->getId()] = $product;
                }
            }

            if ($mode > 0) {
                continue;
            }

            if (isset($orderNumbers[$articleNumber])) {
                $orderNumbers[$articleNumber] += $quantity;
            } else {
                $orderNumbers[$articleNumber] = $quantity;
            }

            /** @var Article $article */
            $article = $articleRepository->find($product->getArticleId());

            $supplierId = $article->getSupplier()->getId();

            $articleDataCollection[] = array(
                'articleId' => $product->getArticleId(),
                'lineId' => count($articleDataCollection) + 1,
                'erpArticleNumber' => $this->getArticleDetail($product)->getAttribute()->getBlisstributeVhsNumber(),
                'ean13' => $product->getEan(),
                'articleNumber' => $product->getArticleNumber(),
                'mode' => $mode,
                'supplierId' => $supplierId,
                'customerGroupId' => $customerGroupId,
                'shopId' => $shopId,
                'originalPriceAmount' => $price,
                'originalPrice' => $price * $product->getQuantity(),
                'promoQuantity' => $quantity,
                'quantity' => $quantity,
                'priceAmount' => round($price, 6), // single article price
                'price' => round(($price * $quantity), 6), //round($price * $quantity, 6), // article price with consideration of the quantities
                'vatRate' => round($product->getTaxRate(), 2),
                'title' => $product->getArticleName(),
                'discountTotal' => 0,
                'configuration' => '',
            );
        }

        $articleDataCollection = $this->applyPromoDiscounts($articleDataCollection, $promotions, $orderNumbers, $shopwareDiscountsAmount, $orderId);

        // whats this for?
        foreach ($articleDataCollection as &$product) {
            $priceAmount = ($product['discountTotal']) / $product['quantity'];

            $product['priceAmount'] -= $priceAmount;

            $priceA = $product['priceAmount'];
            $price = $product['price'];

            $product['priceAmount'] = $price;
            $product['price'] = $priceA;

        }

        return $articleDataCollection;
    }

    /**
     * Read out product data
     *
     * @param array $orderNumbers
     * @param integer $orderId
     * @return array
     */
    private function getProductContext(array $orderNumbers, $orderId)
    {
        $products = $this->getBaseProducts($orderNumbers, $orderId);
        $lookup = [];
        foreach ($products as $idx => $product) {
            $lookup[$product['articleID']][] = $idx;
        }

        $categories = $this->getProductCategories(array_column($products, 'articleID'));

        foreach ($this->getBasketAttributes($orderId) as $attributes) {
            foreach ($attributes as $key => $attribute) {
                foreach ($lookup[$attributes['articleID']] as $idx) {
                    $products[$idx]['basketAttribute::' . $key] = $attribute;
                }
            }
        }

        // set categories into base product
        foreach ($categories as $row) {
            foreach ($lookup[$row['articleID']] as $idx) {
                $products[$idx]['categories'][] = $row;
            }
        }

        return $products;
    }

    /**
     * Return the base product information
     *
     * @param array $orderNumbers
     * @param integer $orderId
     * @return array
     */
    private function getBaseProducts(array $orderNumbers, $orderId)
    {
        $questionMarks = implode(', ', array_fill(0, count($orderNumbers), '?'));

        $info = new \Shopware\SwagPromotion\Components\MetaData\FieldInfo();
        $info = array_keys($info->get()['product']);

        $mapping = [
            'product' => 'articles',
            'detail' => 'details',
            'productAttribute' => 'attributes',
            'price' => 'prices',
            'supplier' => 'supplier'
        ];

        $fields = implode(
            ', ',
            array_map(
                function ($field) use ($mapping) {
                    list($type, $rest) = explode('::', $field, 2);

                    $table = $mapping[$type];

                    return "{$table}.{$rest} as \"{$field}\"";
                },
                // sort out categories
                array_filter(
                    $info,
                    function ($field) {
                        return strpos($field, 'categories') !== 0;
                    }
                )
            )
        );

        $sql = "SELECT details.ordernumber, {$fields}, o.quantity, o.price, details.articleID

        FROM s_articles_details details

        LEFT JOIN s_order_details o
        on o.ordernumber = details.ordernumber
        AND o.orderID = ?
        AND modus = 0

        LEFT JOIN s_articles_attributes attributes
        ON attributes.articledetailsID = details.id

        LEFT JOIN s_articles articles
        ON articles.id = details.articleID

        LEFT JOIN s_articles_prices prices
        ON prices.articledetailsID = details.id

        LEFT JOIN s_articles_supplier supplier
        ON supplier.id = articles.supplierID

        WHERE details.ordernumber IN ({$questionMarks})";

        $data = $this->container->get('db')->fetchAssoc(
            $sql,
            array_merge([$orderId], array_keys($orderNumbers))
        );

        foreach ($data as $orderNumber => $product) {
            $data[$orderNumber]['quantity'] = $orderNumbers[$orderNumber];
        }

        return $data;
    }

    /**
     * Enrich the product data with basket attributes, if possible
     *
     * @param integer $orderId
     * @return array
     */
    private function getBasketAttributes($orderId)
    {
        $sql = "SELECT o.articleID, attributes.*
                FROM s_order_details o
                LEFT JOIN s_order_details_attributes attributes ON attributes.detailID = o.id
                WHERE o.orderId = :orderId
                  AND o.modus = 0";

        return $this->container->get('db')->fetchAll($sql, ['orderId' => $orderId]);
    }

    /**
     * Return categories for the given articleIds
     *
     * @param array $articleIds
     * @return array
     */
    private function getProductCategories(array $articleIds)
    {
        return $this->container->get('db')->fetchAll(
            "SELECT ro.articleID, attributes.*, categories.*
            FROM s_articles_categories_ro ro

            INNER JOIN s_categories categories
            ON categories.id = ro.categoryID

            INNER JOIN s_categories_attributes attributes
            ON attributes.categoryID = ro.categoryID

            WHERE articleID IN (" . implode(', ', $articleIds) . ")"
        );
    }

    public function applyPromoDiscounts($articleDataCollection, $promotions, $orderNumbers, $shopwareDiscountsAmount, $orderId)
    {
        // check if plugin SwagPromotion is installed
        $plugin = $this->getPluginRepository()->findOneBy(array(
            'name' => 'SwagPromotion',
            'active' => true
        ));

        $allPromotions = array();
        $promoAmounts = array();

        /** @var Detail $promotionItem */
        foreach ($promotions as $promotionItem) {
            if (!$plugin) {
                continue;
            }

            /** @var \Shopware\CustomModels\SwagPromotion\Promotion $promotion */
            $promotion = $this->container->get('models')->getRepository('Shopware\CustomModels\SwagPromotion\Promotion')->findOneBy(array(
                'number' => $promotionItem->getArticleNumber()
            ));

            if (is_null($promotion)) {
                continue;
            }

            $allPromotions[$promotion->getType()][] = $promotion;
            $promoAmounts[$promotion->getType()][] = $promotionItem->getPrice();
        }

        if ($plugin) {
            $products = $this->getProductContext($orderNumbers, $orderId);

            //First apply free product discount
            $articleDataCollection = $this->applyFreeDiscount($allPromotions['product.freegoods'], $articleDataCollection);

            //Apply buy X get Y free second
            $articleDataCollection = $this->applyXYDiscount($allPromotions['product.buyxgetyfree'], $articleDataCollection, $products);
        }

        $articleDataCollection = $this->applyVouchers($articleDataCollection);

        $articleDataCollection = $this->applyShopwareDiscount($articleDataCollection, $shopwareDiscountsAmount);

        if ($plugin) {
            //Apply product absolute discount
            $articleDataCollection = $this->applyProductAbsoluteDiscount($allPromotions['product.absolute'], $articleDataCollection, $products);

            //Apply product percent discount
            $articleDataCollection = $this->applyProductPercentDiscount($allPromotions['product.percentage'], $articleDataCollection, $products);

            //Apply cart absolute discount
            $articleDataCollection = $this->applyCartAbsoluteDiscount($allPromotions['basket.absolute'], $articleDataCollection);

            //Apply cart percent discount, this discount must be handled in special way
            $articleDataCollection = $this->applyCartPercentDiscount($allPromotions['basket.percentage'], $articleDataCollection, $promoAmounts['basket.percentage']);
        }

        return $articleDataCollection;
    }

    public function applyFreeDiscount($promotions, $articleDataCollection)
    {
        $freeArticleList = array();

        /** @var \Shopware\CustomModels\SwagPromotion\Promotion $promotion */
        foreach ($promotions as $promotion) {
            $freeArticles = $promotion->getFreeGoodsArticle();
            foreach ($freeArticles as $freeArticle) {
                $freeArticleList[] = $freeArticle->getMainDetail()->getNumber();
            }
        }

        //todo: only one product can be free in basket?
        foreach ($articleDataCollection as &$product) {
            if ($product['promoQuantity'] == 0 || $product['priceAmount'] == 0) {
                continue;
            }

            if (in_array($product['articleNumber'], $freeArticleList)) {
                $discount = $product['priceAmount'];

                $product['promoQuantity'] -= 1;
                $product['price'] -= $discount;
                $product['priceAmount'] -= $discount;
                $product['discountTotal'] += $discount;
            }
        }

        return $articleDataCollection;
    }

    public function applyXYDiscount($promotions, $articleDataCollection, $products)
    {
        /** @var \Shopware\SwagPromotion\Components\Promotion\ProductStacker\ProductStacker $productStackRegistry */
        $productStackRegistry = $this->container->get('promotion.stacker.registry');

        //todo: clear foreaches
        /** @var \Shopware\CustomModels\SwagPromotion\Promotion $promotion */
        foreach ($promotions as $promotion) {
            $stackedProducts = $productStackRegistry->getStacker($promotion->getStackMode())->getStack(
                $products,
                $promotion->getStep(),
                $promotion->getMaxQuantity(),
                'cheapest'
            );

            $amount = $promotion->getAmount();

            foreach ($stackedProducts as $stack) {
                $stackProduct = array_map(
                    function ($p) {
                        return $p['ordernumber'];
                    },
                    // get the "free" items
                    array_slice($stack, 0, $amount)
                );


                foreach ($articleDataCollection as &$product) {
                    if ($product['promoQuantity'] == 0 || $product['priceAmount'] == 0) {
                        continue;
                    }

                    if (in_array($product['articleNumber'], $stackProduct)) {
                        $discount = $product['priceAmount'];

                        $product['price'] -= $discount;
                        $product['discountTotal'] += $discount;
                    }
                }
            }
        }

        return $articleDataCollection;
    }

    public function applyProductAbsoluteDiscount($promotions, $articleDataCollection, $products)
    {
        /** @var \Shopware\SwagPromotion\Components\Promotion\ProductStacker\ProductStacker $productStackRegistry */
        $productStackRegistry = $this->container->get('promotion.stacker.registry');

        $productWithDiscount = array();
        $promotionDiscount = 0;
        $basketAmount = 0;

        /** @var \Shopware\CustomModels\SwagPromotion\Promotion $promotion */
        foreach ($promotions as $promotion) {
            $stackedProducts = $productStackRegistry->getStacker($promotion->getStackMode())->getStack(
                $products,
                $promotion->getStep(),
                $promotion->getMaxQuantity(),
                'cheapest'
            );

            $discount = $promotion->getAmount();

            foreach ($stackedProducts as $stack) {
                $stackProduct = array_map(
                    function ($p) {
                        return $p['ordernumber'];
                    },
                    array_slice($stack, 0, $discount)
                );

                $productWithDiscount[] = $stackProduct[0];
                $promotionDiscount += $discount;
            }
        }

        foreach ($articleDataCollection as $product) {
            if ($product['promoQuantity'] == 0 && $product['price'] <= 0) {
                continue;
            }

            if (in_array($product['articleNumber'], $productWithDiscount)) {
                $basketAmount += $product['price'];
            }
        }

        foreach ($articleDataCollection as &$product) {
            if ($product['promoQuantity'] == 0 || $product['priceAmount'] == 0) {
                continue;
            }

            if (in_array($product['articleNumber'], $productWithDiscount)) {
                $weight = $product['price'] / $basketAmount;

                $countedAmountToDiscount = $promotionDiscount * $weight;

                $product['discountTotal'] += $countedAmountToDiscount;
                $product['price'] -= $countedAmountToDiscount;
            }
        }

        return $articleDataCollection;
    }

    public function applyProductPercentDiscount($promotions, $articleDataCollection, $products)
    {
        /** @var \Shopware\SwagPromotion\Components\Promotion\ProductStacker\ProductStacker $productStackRegistry */
        $productStackRegistry = $this->container->get('promotion.stacker.registry');

        $totalProductAmount = 0;
        $promotionDiscount = 0;
        $basketAmount = 0;

        /** @var \Shopware\CustomModels\SwagPromotion\Promotion $promotion */
        foreach ($promotions as $promotion) {
            $discount = $promotion->getAmount();

            $stackedProducts = $productStackRegistry->getStacker($promotion->getStackMode())->getStack(
                $products,
                $promotion->getStep(),
                $promotion->getMaxQuantity(),
                'cheapest'
            );

            foreach ($stackedProducts as $stack) {
                $totalProductAmount += array_sum(
                // return the price of the free items
                    array_map(
                        function ($product) {
                            return $product['price'];
                        },
                        // get the "free" items
                        array_slice($stack, 0, $discount)
                    )
                );
            }

            $promotionDiscount = $totalProductAmount * ($discount / 100);
        }

        foreach ($articleDataCollection as $product) {
            if ($product['promoQuantity'] == 0 && $product['price'] <= 0) {
                continue;
            }

            $basketAmount += $product['price'];
        }

        foreach ($articleDataCollection as &$product) {
            if ($product['promoQuantity'] == 0 || $product['price'] <= 0) {
                continue;
            }

            $weight = $product['price'] / $basketAmount;

            $countedAmountToDiscount = $promotionDiscount * $weight;

            $product['discountTotal'] += $countedAmountToDiscount;
            $product['price'] -= $countedAmountToDiscount;
        }

        
        return $articleDataCollection;
    }

    public function applyCartAbsoluteDiscount($promotions, $articleDataCollection)
    {
        $promotionDiscount = 0;
        $basketAmount = 0;

        /** @var \Shopware\CustomModels\SwagPromotion\Promotion $promotion */
        foreach ($promotions as $promotion) {
            $promotionDiscount += $promotion->getAmount();
        }

        foreach ($articleDataCollection as $product) {
            if ($product['promoQuantity'] == 0 && $product['price'] <= 0) {
                continue;
            }

            $basketAmount += $product['price'];
        }

        foreach ($articleDataCollection as &$product) {
            if ($product['promoQuantity'] == 0 || $product['price'] <= 0) {
                continue;
            }

            $weight = $product['price'] / $basketAmount;

            $countedAmountToDiscount = $promotionDiscount * $weight;

            $product['discountTotal'] += $countedAmountToDiscount;
            $product['price'] -= $countedAmountToDiscount;
        }

        return $articleDataCollection;
    }

    public function applyCartPercentDiscount($promotions, $articleDataCollection, $promoAmounts)
    {
        $promotionDiscount = 0;
        $totalProductAmount = 0;

        /** @var \Shopware\CustomModels\SwagPromotion\Promotion $promotion */
        foreach ($promotions as $key => $promotion) {
            $promotionDiscount += $promoAmounts[$key];
        }

        foreach ($articleDataCollection as $product) {
            if ($product['promoQuantity'] == 0 && $product['price'] <= 0) {
                continue;
            }

            $totalProductAmount += $product['price'];
        }

        foreach ($articleDataCollection as &$product) {
            if ($product['promoQuantity'] == 0 || $product['price'] <= 0) {
                continue;
            }

            $weight = $product['price'] / $totalProductAmount;

            $countedAmountToDiscount = abs($promotionDiscount) * $weight;

            $product['discountTotal'] += $countedAmountToDiscount;
            $product['price'] -= $countedAmountToDiscount;
        }

        return $articleDataCollection;
    }

    public function applyShopwareDiscount($articleDataCollection, $shopwareDiscountsAmount)
    {
        $totalProductAmount = 0;

        foreach ($articleDataCollection as $product) {
            if ($product['promoQuantity'] == 0 && $product['price'] <= 0) {
                continue;
            }

            $totalProductAmount += $product['price'];
        }

        foreach ($articleDataCollection as &$product) {
            if ($product['promoQuantity'] == 0 || $product['price'] <= 0) {
                continue;
            }

            $weight = $product['price'] / $totalProductAmount;

            $countedAmountToDiscount = abs($shopwareDiscountsAmount) * $weight;

            $product['discountTotal'] += $countedAmountToDiscount;
            $product['price'] -= $countedAmountToDiscount;
        }

        return $articleDataCollection;
    }

    public function applyVouchers($articleDataCollection)
    {
        $couponMappingRepository = $this->getCouponMappingRepository();


        $basketAmount = 0;
        $productBasket = 0;
        $vouchersData = array();

        foreach ($this->voucherCollection as $currentVoucher) {
            $couponMapping = $couponMappingRepository->findByCoupon($currentVoucher->getId());
            if ($couponMapping != null && $couponMapping->getIsMoneyVoucher()) {
                continue;
            }

            // check if plugin CoeExcludeProducerOnVoucher is installed
            $plugin = $this->getPluginRepository()->findOneBy(array(
                'name' => 'CoeExcludeProducerOnVoucher',
                'active' => true
            ));

            $coeExcludeSuppliers = array();
            if ($plugin) {
                if ($currentVoucher->getAttribute() != null) {
                    $coeExcludeSupplier = $currentVoucher->getAttribute()->getCoeExcludeSupplier();

                    if ($coeExcludeSupplier != '') {
                        $coeExcludeSuppliers = explode(',', $coeExcludeSupplier);
                    }
                }
            }

            // check if plugin CoeVoucherOnReducedArticle is installed
            $plugin = $this->getPluginRepository()->findOneBy(array(
                'name' => 'CoeVoucherOnReducedArticle',
                'active' => true
            ));

            $coeReducedArticle = null;
            if ($plugin) {
                if ($currentVoucher->getAttribute() != null) {
                    $coeReducedArticle = $currentVoucher->getAttribute()->getCoeReducedArticle();
                }
            }

            $vouchersData[$currentVoucher->getId()] = [
                'coeExcludeSupplier' => $coeExcludeSuppliers,
                'coeReducedArticle' => $coeReducedArticle,
            ];

            foreach ($articleDataCollection as $product) {
                if ($currentVoucher->getBindToSupplier() > 0 && $currentVoucher->getBindToSupplier() != $product['supplierId']) {
                    continue;
                }

                if (in_array($product['supplierId'], $coeExcludeSuppliers)) {
                    continue;
                }

                if ($coeReducedArticle == 1) {
                    continue;
                }

                $basketAmount += $product['originalPrice'];

                $restrictedArticles = explode(';', $currentVoucher->getRestrictArticles());
                if (!in_array($product['articleNumber'], $restrictedArticles)) {
                    $productBasket += $product['price'];
                }
            }
        }

        foreach ($articleDataCollection as &$product) {
            $price = $product['price'];

            foreach ($this->voucherCollection as $currentVoucher) {
                $couponMapping = $couponMappingRepository->findByCoupon($currentVoucher->getId());
                if ($couponMapping != null && $couponMapping->getIsMoneyVoucher()) {
                    continue;
                }

                $voucherDiscount = round(
                    $this->calculateArticleDiscountForVoucher($product, $currentVoucher, $vouchersData, $price, $articleDataCollection, $basketAmount, $productBasket),
                    2,
                    PHP_ROUND_HALF_DOWN
                );

                $product['price'] -= $voucherDiscount;
                $product['discountTotal'] += $voucherDiscount;
            }
        }

        return $articleDataCollection;
    }

    /**
     * If there is a discount applied to an order, this function makes sure to
     * apply the discount to all products in the order equally, in order to guarantee
     * that refunds do not exceed the amount the customer paid
     *
     * @param Detail $item
     * @param $totalPriceNoDiscount
     * @param $totalPaymentDiscount
     * @param $totalDiscountUsed
     * @return float|int
     */
    protected function getPaymentDiscountForBasketItem($item, $totalPriceNoDiscount, $totalPaymentDiscount, $totalDiscountUsed)
    {
        // is pixup supplier payment discount installed ?
        $plugin = $this->getPluginRepository()->findOneBy(array(
            'name' => 'PixupMarkenZahlartenRabattsteuerung',
            'active' => true
        ));

        $price = $item->getPrice();
        $quantity = $item->getQuantity();

        $discount = 0;

        if ($plugin) {
            // apply weighed discount per supplier
            $paymentMean = $item->getOrder()->getPayment();
            $article = Shopware()->Models()->getRepository('Shopware\Models\Article\Article')->find($item->getArticleId());
            $supplier = $article->getSupplier();

            $discountMapping = Shopware()->Models()->getRepository('Shopware\CustomModels\PixupMarkenZahlartenRabattsteuerung\MarkenZahlartenRabattModel')->findOneBy(array(
                'supplier' => $supplier,
                'paymentMean' => $paymentMean
            ));

            if ($discountMapping->isActive()) {
                $discount = $discountMapping->getDiscount();
                $amountToDiscount = $price * ($discount / 100);

                $discount = $amountToDiscount;
            }
        } else {
            // apply weighed discount simple

            // current price weighed against total
            $weight = ($price * $quantity) / $totalPriceNoDiscount;
            $weighedDiscount = ($weight * $totalPaymentDiscount);
            $leftOver = $totalPaymentDiscount - $totalDiscountUsed;

            if ($leftOver < $weighedDiscount) { // errors through rounding might occur
                $weighedDiscount = $leftOver;
            }

            $discount = $weighedDiscount;
        }

        return $discount;
    }

    /**
     * get article detail for order line
     *
     * @param \Shopware\Models\Order\Detail $orderDetail
     *
     * @return \Shopware\Models\Article\Detail
     *
     * @throws NonUniqueResultException
     * @throws Shopware_Components_Blisstribute_Exception_ValidationMappingException
     */
    protected function getArticleDetail(Detail $orderDetail)
    {
        $articleDetailRepository = Shopware()->Models()->getRepository('Shopware\Models\Article\Detail');
        $detail = $articleDetailRepository->createQueryBuilder('article_detail')
            ->select('ad')
            ->from('Shopware\Models\Article\Detail', 'ad')
            ->where('ad.articleId = :articleId')
            ->andWhere('ad.number = :articleNumber')
            ->setParameters(array(
                'articleId' => $orderDetail->getArticleId(),
                'articleNumber' => $orderDetail->getArticleNumber(),
            ))
            ->getQuery()
            ->getOneOrNullResult();

        if ($detail == null) {
            throw new Shopware_Components_Blisstribute_Exception_ValidationMappingException(sprintf(
                'could not find article detail - order number: %s - article number: %s',
                $orderDetail->getOrder()->getNumber(),
                $orderDetail->getArticleNumber()
            ));
        }

        return $detail;
    }

    /**
     * determine voucher discount and return voucher list
     *
     * @return void
     *
     * @throws NonUniqueResultException
     */
    protected function determineVoucherDiscount()
    {
        $this->logDebug('determineVoucherDiscount start');

        $this->voucherDiscountValue = 0.00;
        $this->voucherDiscountUsed = 0.00;
        $this->voucherCollection = array();

        /** @var VoucherRepository $voucherRepository */
        $voucherRepository = Shopware()->Models()->getRepository('Shopware\Models\Voucher\Voucher');
        $couponMappingRepository = $this->getCouponMappingRepository();

        /** @var Detail $currentDetail */
        foreach ($this->getModelEntity()->getOrder()->getDetails() as $currentDetail) {
            // no coupon
            if ($currentDetail->getMode() != 2) {
                continue;
            }

            $voucher = $voucherRepository->getValidateOrderCodeQuery($currentDetail->getArticleNumber())
                ->getOneOrNullResult();

            if ($voucher != null) {
                $this->voucherCollection[] = $voucher;

                $couponMapping = $couponMappingRepository->findByCoupon($voucher);
                if ($couponMapping != null && $couponMapping->getIsMoneyVoucher()) {
                    continue;
                }
            }

            $this->voucherDiscountValue += abs(round($currentDetail->getPrice(), 2, PHP_ROUND_HALF_DOWN));
        }

        $this->logDebug('voucherDiscountValue: ' . $this->voucherDiscountValue);
        $this->logDebug('voucherDiscountUsed: ' . $this->voucherDiscountUsed);
        $this->logDebug('voucherDiscountCollection: ' . count($this->voucherCollection));

        $this->logDebug('determineVoucherDiscount done');
    }

    /**
     * check voucher discount used and voucher discount totals
     *
     * @param array $articleDataCollection
     *
     * @return array
     */
    protected function checkVoucherDiscountUsed(array $articleDataCollection)
    {
        $this->logDebug('checkVoucherDiscountUsed start');
        $this->logDebug('before ' . json_encode($articleDataCollection));
        $diff = round(($this->voucherDiscountValue - $this->voucherDiscountUsed) / count($articleDataCollection), 2);

        if (abs($diff) > 0.01) {
            $orderRemark = $this->orderData['orderRemark'];
            if (trim($orderRemark) != '') {
                $orderRemark .= ' | ';
            }

            $orderRemark .= sprintf(
                'Manuelle Gutscheinüberprüfung notwendig. Gutscheinwert: %s / Verteilt: %s',
                $this->voucherDiscountValue,
                $this->voucherDiscountUsed
            );

            $this->orderData['orderRemark'] = $orderRemark;

            return $articleDataCollection;
        }

        foreach ($articleDataCollection as $key => $articleData) {
            if ($diff == 0.00) {
                break;
            }

            if ($articleData['discountTotal'] >= 0.00) {
                continue;
            }

            if (round($articleData['quantity'] * 0.01, 2) > abs($diff)) {
                continue;
            }

            if ($diff > 0) {
                $articleData['discountTotal'] -= 0.01;
                $diff -= 0.01;
            } elseif ($diff < 0) {
                $articleData['discountTotal'] += 0.01;
                $diff += 0.01;
            }

            $articleDataCollection[$key] = $articleData;
        }

        if ($diff != 0) {
            $orderRemark = $this->orderData['orderRemark'];
            if (trim($orderRemark) != '') {
                $orderRemark .= ' | ';
            }

            $orderRemark .= sprintf(
                'Manuelle Gutscheinüberprüfung notwendig. Gutscheinwert: %s / Verteilt: %s',
                $this->voucherDiscountValue,
                $this->voucherDiscountUsed
            );

            $this->orderData['orderRemark'] = $orderRemark;
            return $articleDataCollection;
        }

        $this->logDebug('after ' . json_encode($articleDataCollection));
        return $articleDataCollection;
    }

    /**
     * determine article discount for voucher
     *
     * @param array $product
     * @param Voucher $voucher
     * @param array $vouchersData
     * @param float $articlePrice
     * @param array $articleDataCollection
     * @param integer $basketAmount
     * @param integer $productBasket
     *
     * @return float
     */
    protected function calculateArticleDiscountForVoucher($product, Voucher $voucher, $vouchersData, $articlePrice, $articleDataCollection, $basketAmount, $productBasket)
    {        
        if ($voucher == null) {
            return 0.00;
        }

        // check if article is valid for voucher
        $restrictedArticles = explode(';', $voucher->getRestrictArticles());
        if (count($restrictedArticles) > 0 && in_array($product['articleNumber'], $restrictedArticles)) {
            return 0.00;
        }

        // check if article manufacturer is valid for voucher
        if ($voucher->getBindToSupplier() > 0 && $voucher->getBindToSupplier() != $product['supplierId']) {
            return 0.00;
        }

        // check if customer group is valid for voucher
        if ($voucher->getCustomerGroup() > 0
            && $voucher->getCustomerGroup() != $product['customerGroupId']
        ) {
            return 0.00;
        }

        // check if shop is valid for voucher
        if ($voucher->getShopId() > 0 && $voucher->getShopId() != $product['shopId']) {
            return 0.00;
        }

        $voucherId = $voucher->getId();

        if (in_array($product['supplierId'], $vouchersData[$voucherId]['coeExcludeSuppliers'])) {
            return 0.00;
        }

        if ($vouchersData[$voucherId]['coeReducedArticle'] == 1) {
            $customer = $this->getModelEntity()->getOrder()->getCustomer();

            if ($customer) {
                $hasPseudoPrice = $this->container->get('db')->fetchOne("SELECT sap.pseudoprice FROM s_articles_prices sap LEFT JOIN s_articles_details sad ON sap.articleDetailsID = sad.id WHERE sad.ordernumber = ? AND sap.pricegroup = ? AND sap.pseudoprice > 0", array($product['articleNumber'], $customer->getGroupKey()));

                if ($hasPseudoPrice) {
                    return 0.00;
                }
            }
        }

        if ((bool) $voucher->getPercental()) {
            return $this->calculateDiscountForPercentVoucher($articlePrice, $basketAmount, $productBasket, $voucher->getValue());
        }

        return $this->calculateDiscountForAbsoluteVoucher(
            $articlePrice,
            $product['quantity'],
            $this->getBasketTotal($articleDataCollection, $voucher, $product['supplierId']),
            $voucher->getValue()
        );
    }

    /***
     * @param array $articleDataCollection
     * @param Voucher $voucher
     * @param integer $supplierId
     *
     * @return int
     */
    public function getBasketTotal($articleDataCollection, $voucher, $supplierId)
    {
        $restrictedArticles = explode(';', $voucher->getRestrictArticles());

        $totalAmount = 0;

        //todo: use price or originalPrice?
        foreach ($articleDataCollection as $product) {
            if ($product['promoQuantity'] == 0 || $product['price'] <= 0) {
                continue;
            }

            if (in_array($product['articleNumber'], $restrictedArticles) && count($restrictedArticles) > 0) {
                $totalAmount += $product['price'];
            } else if ($supplierId == $voucher->getBindToSupplier()) {
                $totalAmount += $product['price'];
            }
        }

        return $totalAmount;
    }

    /**
     * get order subtotal
     *
     * @return float
     */
    protected function getOrderSubtotal()
    {
        $articleAmount = 0.00;

        /** @var Detail $currentDetail */
        foreach ($this->getModelEntity()->getOrder()->getDetails() as $currentDetail) {
            if ($currentDetail->getMode() > 0) {
                continue;
            }

            $articleAmount += $currentDetail->getPrice() * $currentDetail->getQuantity();
        }

        return $articleAmount;
    }

    /**
     * calculate the voucher discount for current article price for a percentage coupon
     *
     * @param float $price
     * @param int $quantity
     * @param float $orderSubtotal
     * @param float $discount
     *
     * @return float
     */
    protected function calculateDiscountForAbsoluteVoucher($price, $quantity, $orderSubtotal, $discount)
    {
        $orderLineTotal = ($price * $quantity);
        $evaluateOrderLine = $orderLineTotal / $orderSubtotal;

        // get the discount for the order line
        $orderLineDiscount = $discount / 100 * ($evaluateOrderLine * 100);

        return round($orderLineDiscount, 2, PHP_ROUND_HALF_UP);
    }

    /**
     * calculate the voucher discount for current article price for a percentage coupon
     *
     * @param float $price
     * @param float $basketAmount
     * @param float $productBasketAmount
     * @param float $discount
     *
     * @return float
     */
    protected function calculateDiscountForPercentVoucher($price, $basketAmount, $productBasketAmount, $discount)
    {
        $voucherDiscount = round(($basketAmount / 100 * $discount), 2, PHP_ROUND_HALF_UP);

        $weight = $price / $productBasketAmount;

        $countedAmountToDiscount = $voucherDiscount * $weight;

        return $countedAmountToDiscount;
    }

    /**
     * build order voucher data
     *
     * @return array
     */
    protected function buildCouponData()
    {
        $voucherData = array();

        foreach ($this->getModelEntity()->getOrder()->getDetails() as $currentDetail) {
            if (($currentDetail->getMode() == 4 || $currentDetail->getMode() == 3) && $currentDetail->getPrice() < 0) {
                $voucherData[] = array(
                    'couponCode' => $currentDetail->getArticleName(),
                    'couponDiscount' => abs(round($currentDetail->getPrice(), 6)),
                    'couponDiscountPercentage' => false,
                    'isMoneyVoucher' => false,
                );
            }
        }
        
        foreach ($this->voucherCollection as $currentVoucher) {
            $voucherPercentage = 0.00;
            $voucherDiscount = $currentVoucher->getValue();

            if ((bool)$currentVoucher->getPercental()) {
                $voucherPercentage = round($currentVoucher->getValue(), 6);

                foreach ($this->getModelEntity()->getOrder()->getDetails() as $currentDetail) {
                    if ($currentDetail->getMode() == 2 && $currentDetail->getArticleNumber() == $currentVoucher->getOrderCode()) {
                        $voucherDiscount = abs($currentDetail->getPrice());
                        break;
                    }
                }
            }

            $couponMappingRepository = $this->getCouponMappingRepository();
            $couponMapping = $couponMappingRepository->findByCoupon($currentVoucher->getId());

            $isMoneyVoucher = false;
            if ($couponMapping != null && $couponMapping->getIsMoneyVoucher()) {
                $isMoneyVoucher = true;
            }

            if ($currentVoucher->getModus() == 1) {
                $voucherCode = $currentVoucher->getOrderCode();
            } else {
                $voucherCode = $currentVoucher->getVoucherCode();
            }

            $voucherData[] = array(
                'couponCode' => $voucherCode,
                'couponDiscount' => round($voucherDiscount, 6),
                'couponDiscountPercentage' => $voucherPercentage,
                'isMoneyVoucher' => $isMoneyVoucher,
            );
        }

        return $voucherData;
    }
}
