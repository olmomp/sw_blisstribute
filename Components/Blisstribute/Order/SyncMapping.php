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
    /**
     * mapped order data
     *
     * @var array
     */
    protected $orderData = [];

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
    protected $voucherCollection = [];

    private $container = null;

    /**
     * @return array
     */
    protected function getConfig()
    {
        $shop = $this->getModelEntity()->getOrder()->getShop();
        if (!$shop || $shop == null) {
            $this->logWarn('orderSyncMapping::getConfig::could not get shop from order');
            $shop = $this->container->get('shop');
        }

        if (!$shop || $shop == null) {
            $this->logWarn('orderSyncMapping::getConfig::could not get shop from container');
            $shop = $this->container->get('models')->getRepository(\Shopware\Models\Shop\Shop::class)->getActiveDefault();
        }

        if (!$shop || $shop == null) {
            $this->logWarn('orderSyncMapping::getConfig::could not get active shop; using fallback default config');
            return $this->container->get('config');
        }

        $this->logInfo('orderSyncMapping::getConfig::using shop ' . $shop->getId() . ' / ' . $shop->getName());
        $config = $this->container->get('shopware.plugin.cached_config_reader')->getByPluginName('ExitBBlisstribute', $shop);
        return $config;
    }

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
        $this->logDebug('orderSyncMapping::buildBaseData::start');
        // determine used vouchers
        $this->determineVoucherDiscount();

        $this->orderData = $this->buildBasicOrderData();
        $this->orderData['payment'] = $this->buildPaymentData();
        $this->orderData['advertisingMedium'] = $this->buildAdvertisingMediumData();
        $this->orderData['billAddressData'] = $this->buildInvoiceAddressData();
        $this->orderData['deliveryAddressData'] = $this->buildDeliveryAddressData();
        $this->orderData['orderLines'] = $this->buildArticleData();
        $this->orderData['orderCoupons'] = $this->buildCouponData();

        $this->logDebug('orderSyncMapping::buildBaseData::done');
        $this->logDebug('orderSyncMapping::buildBaseData::result:' . json_encode($this->orderData));

        $order = $this->getModelEntity()->getOrder();
        $originalTotal = round($order->getInvoiceAmount(), 2);
        $newOrderTotal = round($this->_getOrderTotal(), 2);
        if ($originalTotal != $newOrderTotal) {
            $this->logDebug(sprintf('orderSyncMapping::buildBaseData::amount differs %s to %s', $originalTotal, $newOrderTotal));
            $this->orderData['orderRemark'] .= 'RABATT PRÜFEN! (ORIG ' . $originalTotal .')';
        }

        return $this->orderData;
    }

    protected function _getOrderTotal()
    {
        $orderTotal = round($this->orderData['payment']['total'], 4);
        $orderTotal += round($this->orderData['shipmentTotal'], 4);
        foreach ($this->orderData['orderLines'] as $currentOrderLine) {
            if ($currentOrderLine['isB2BOrder'] && $this->getConfig()['blisstribute-transfer-b2b-net']) {
                $orderTotal += round((($currentOrderLine['priceNet'] / $currentOrderLine['quantity']) / 100) * (100 + $currentOrderLine['vatRate']), 4);
            } else {
                $orderTotal += round($currentOrderLine['price'], 4);
            }
        }

        foreach ($this->orderData['orderCoupons'] as $currentCoupon) {
            if (!$currentCoupon['isMoneyVoucher']) {
                continue;
            }

            $orderTotal -= round($currentCoupon['couponDiscount'], 4);
        }

        return $orderTotal;
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
        if ($billingAddress == null) {
            throw new Exception('invalid billing address data');
        }
        if ($order->getShipping() == null) {
            throw new Exception('invalid shipping address data');
        }

        $orderShipLock = false;
        $orderHold = false;

        $orderRemark = [];
        if (trim($order->getCustomerComment()) != '') {
            $orderRemark[] = trim($order->getCustomerComment());
            $orderHold = true;
        }

        if ($order->getOrderStatus()->getId() == 8) {
            $orderRemark[] = 'Bestellung prüfen - Bestellstatus Klärung';
            $orderShipLock = true;
        }

        if ($order->getPaymentStatus()->getId() == 21) {
            $orderRemark[] = 'Zahlung prüfen - Shopware Zahlungshinweis';
            $orderShipLock = true;
        }

        if ($this->getConfig()['blisstribute-auto-hold-order']) {
            $this->logDebug('orderSyncMapping::buildBasicOrderData::blisstribute auto hold order enabled.');
            $orderHold = true;
            $orderRemark[] = 'SWP - Bestellung angehalten.';
        }

        if ($this->getConfig()['blisstribute-auto-lock-order']) {
            $this->logDebug('orderSyncMapping::buildBasicOrderData::blisstribute auto lock order enabled.');
            $orderShipLock = true;
            $orderRemark[] = 'SWP - Bestellung gesperrt.';
        }

        switch ($this->getConfig()['blisstribute-order-sync-external-customer-number']) {
            case 2:
                $customerNumber = $customer->getNumber();
                break;
            case 1:
            default:
                $customerNumber = $customer->getEmail();
        }

        $isB2BOrder = false;
        $company = trim($billingAddress->getCompany());
        if ($company != '' && $company != 'x' && $company != '*' && $company != '/' && $company != '-') {
            $isB2BOrder = true;
        }

        if (version_compare(Shopware()->Config()->version, '5.2.0', '>=')) {
            $customerBirthday = $customer->getBirthday();
        } else {
            $customerBirthday = $customer->getDefaultBillingAddress()->getBirthday();
        }

        if (!is_null($customerBirthday)) {
            $customerBirthday = $customerBirthday->format('Y-m-d');
        }

        $holdOrderCartAmount = $this->getConfig()['blisstribute-hold-order-cart-amount'];
        if ($holdOrderCartAmount > 0 && $holdOrderCartAmount <= $order->getInvoiceAmount()) {
            $this->logDebug('orderSyncMapping::buildBasicOrderData::blisstribute hold order cart amount enabled.');
            $orderHold = true;
            $orderRemark[] = 'BUHA - Bestellung angehalten.';
        }

        return [
            'externalCustomerNumber' => $customerNumber,
            'externalCustomerEmail' => $customer->getEmail(),
            'externalCustomerPhoneNumber' => $customer->getDefaultBillingAddress()->getPhone(),
            'externalCustomerMobilePhoneNumber' => '',
            'externalCustomerFaxNumber' => '',
            'customerBirthdate' => $customerBirthday,
            'externalOrderNumber' => $order->getNumber(),
            'customerOrderNumber' => '',
            'isAnonymousCustomer' => false,
            'orderDate' => $order->getOrderTime()->format('Y-m-d H:i:s'),
            'orderShipLock' => $orderShipLock,
            'orderHold' => $orderHold,
            'orderCurrency' => $order->getCurrency(),
            'orderRemark' => implode(' - ', $orderRemark),
            'isB2BOrder' => $isB2BOrder,
            'advertisingMediumCode' => '',
            'shipmentType' => $this->determineShippingType(),
            'shipmentTotal' => $order->getInvoiceShipping(),
        ];
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
        $advertisingMediumCode = $this->getConfig()['blisstribute-default-advertising-medium'];
        $advertisingMediumData = [
            'origin' => 'O',
            'medium' => 'O',
            'code' => $advertisingMediumCode,
            'affiliateSource' => '',
        ];

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

        $invoiceAddressData = [
            'addressType' => 'BILL',
            'salutation' => $salutation,
            'title' => '',
            'firstName' => base64_encode($this->_processAddressDataMatching($billing->getFirstName())),
            'addName' => base64_encode($this->_processAddressDataMatching($billing->getDepartment())),
            'lastName' => base64_encode($this->_processAddressDataMatching($billing->getLastName())),
            'company' => base64_encode($this->_processAddressDataMatching($billing->getCompany())),
            'gender' => $gender,
            'street' => base64_encode($this->_processAddressDataMatching($street)),
            'houseNumber' => base64_encode($this->_processAddressDataMatching($houseNumber)),
            'addressAddition' => base64_encode($this->_processAddressDataMatching($billing->getAdditionalAddressLine1())),
            'zipCode' => base64_encode($this->_processAddressDataMatching($billing->getZipCode())),
            'city' => base64_encode($this->_processAddressDataMatching($billing->getCity())),
            'countryCode' => $country->getIso(),
            'isTaxFree' => (((bool)$this->getModelEntity()->getOrder()->getTaxFree()) ? true : false),
            'taxIdNumber' => trim($billing->getVatId()),
            'remark' => '',
            'stateCode' => '',
        ];

        return $invoiceAddressData;
    }

    private function _processAddressDataMatching($addressString)
    {
        $blackListPattern = $this->getConfig()['blisstribute-hold-order-address-pattern'];
        if (trim($blackListPattern) == '') {
            return $addressString;
        }

        if (preg_match('/' . $blackListPattern . '/i', $addressString)) {
            $this->orderData['orderHold'] = true;
            if (!preg_match('/Bestellung prüfen/i', $this->orderData['orderRemark'])) {
                $this->orderData['orderRemark'] = 'Bestellung prüfen (SW Blacklist) - ' . $this->orderData['orderRemark'];
            }
        }

        return $addressString;
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

        $deliveryAddressData = [
            'addressType' => 'DELIVERY',
            'salutation' => $salutation,
            'title' => '',
            'firstName' => base64_encode($this->_processAddressDataMatching($shipping->getFirstName())),
            'addName' => base64_encode($this->_processAddressDataMatching($shipping->getDepartment())),
            'lastName' => base64_encode($this->_processAddressDataMatching($shipping->getLastName())),
            'company' => base64_encode($this->_processAddressDataMatching($shipping->getCompany())),
            'gender' => $gender,
            'street' => base64_encode($this->_processAddressDataMatching($street)),
            'houseNumber' => base64_encode($this->_processAddressDataMatching($houseNumber)),
            'addressAddition' => base64_encode($this->_processAddressDataMatching($shipping->getAdditionalAddressLine1())),
            'zipCode' => base64_encode($this->_processAddressDataMatching($shipping->getZipCode())),
            'city' => base64_encode($this->_processAddressDataMatching($shipping->getCity())),
            'countryCode' => $country->getIso(),
            'isTaxFree' => (((bool)$this->getModelEntity()->getOrder()->getTaxFree()) ? true : false),
            'taxIdNumber' => '',
            'remark' => '',
            'stateCode' => '',
        ];

        return $deliveryAddressData;
    }

    /**
     * build article list
     *
     * @return array
     */
    protected function buildArticleData()
    {
        $articleDataCollection = [];

        $swOrder = $this->getModelEntity()->getOrder();
        $basketItems = $swOrder->getDetails();
        $orderId = $swOrder->getId();

        $isB2BOrder = false;
        $company = trim($swOrder->getBilling()->getCompany());
        if ($company != '' && $company != 'x' && $company != '*' && $company != '/' && $company != '-') {
            $isB2BOrder = true;
        }

        $promotions = [];
        $orderNumbers = [];
        $shopwareDiscountsAmount = 0;

        /** @var ArticleRepository $articleRepository */
        $articleRepository = $this->container->get('models')->getRepository('Shopware\Models\Article\Article');

        $customerGroupId = $swOrder->getCustomer()->getGroup()->getId();
        $shopId =  $swOrder->getShop()->getId();

        /** @var Shopware\Models\Order\Detail $orderDetail */
        foreach ($basketItems as $orderDetail) {
            $priceNet = $price = 0;
            if ($isB2BOrder && $this->getConfig()['blisstribute-transfer-b2b-net']) {
                $priceNet = ($orderDetail->getPrice() / (100 + $orderDetail->getTaxRate())) * 100;
            } else {
                $price = $orderDetail->getPrice();
            }

            $quantity = $orderDetail->getQuantity();
            $mode = $orderDetail->getMode();
            $articleNumber = $orderDetail->getArticleNumber();

            if (in_array($mode, [3, 4]) && $price <= 0) {
                if (in_array($articleNumber, ['sw-payment', 'sw-discount', 'sw-payment-absolute'])) {
                    $shopwareDiscountsAmount += $price;
                } else {
                    $promotions[$orderDetail->getId()] = $orderDetail;
                }
            }

            if ($mode > 0) {
                continue;
            }

            if (array_key_exists($articleNumber, $orderNumbers)) {
                $orderNumbers[$articleNumber] += $quantity;
            } else {
                $orderNumbers[$articleNumber] = $quantity;
            }

            /** @var Article $article */
            $article = $articleRepository->find($orderDetail->getArticleId());

            $articleData = [
                'articleId' => $orderDetail->getArticleId(),
                'lineId' => count($articleDataCollection) + 1,
                'externalKey' => $orderDetail->getId(),
                'erpArticleNumber' => $this->getArticleDetail($orderDetail)->getAttribute()->getBlisstributeVhsNumber(),
                'ean13' => $orderDetail->getEan(),
                'articleNumber' => $orderDetail->getArticleNumber(),
                'mode' => $mode,
                'supplierId' => $article->getSupplier()->getId(),
                'customerGroupId' => $customerGroupId,
                'shopId' => $shopId,
                'originalPriceAmount' => $price,
                'originalPrice' => $price * $orderDetail->getQuantity(),
                'promoQuantity' => $quantity,
                'quantity' => $quantity,
                'priceAmount' => round($price, 4), // single article price
                'priceAmountNet' => round($priceNet, 4), // single article price
                'price' => round(($price * $quantity), 4),
                'priceNet' => round(($priceNet * $quantity), 4),
                'vatRate' => $this->getModelEntity()->getOrder()->getTaxFree() ? 0.0 : round($orderDetail->getTaxRate(), 2),
                'title' => $orderDetail->getArticleName(),
                'discountTotal' => 0,
                'configuration' => '',
            ];

            $articleData = $this->applyCustomProducts($articleData, $orderDetail, $basketItems);
            $articleData = $this->applyStaticAttributeData($articleData, $article);

            $articleDataCollection[] = $articleData;
        }

        $articleDataCollection = $this->applyPromoDiscounts($articleDataCollection, $promotions, $orderNumbers, $shopwareDiscountsAmount, $orderId);

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
        if (class_exists('\Shopware\SwagPromotion\Components\MetaData\FieldInfo')) {
            $info = new \Shopware\SwagPromotion\Components\MetaData\FieldInfo();
        } elseif (class_exists('SwagPromotion\Components\MetaData\FieldInfo')) {
            $info = new SwagPromotion\Components\MetaData\FieldInfo();
        } else {
            $this->logWarn('orderSyncMapping::could not load promostionsuite field info class');
            return array();
        }
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

        FROM s_order_details o
        
        LEFT JOIN s_articles_details details 
        ON details.ordernumber = o.articleordernumber

        LEFT JOIN s_articles_attributes attributes
        ON attributes.articledetailsID = details.id

        LEFT JOIN s_articles articles
        ON articles.id = details.articleID

        LEFT JOIN s_articles_prices prices
        ON prices.articledetailsID = details.id

        LEFT JOIN s_articles_supplier supplier
        ON supplier.id = articles.supplierID

        WHERE o.orderID = ?
        AND o.modus = 0";

        $data = $this->container->get('db')->fetchAssoc(
            $sql, [$orderId]
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

    /**
     * @param array $configurationData
     * @param \Shopware\Models\Article\Article $article
     *
     * @return array
     */
    public function applyStaticAttributeData($configurationData, $article)
    {
        $this->logDebug('orderSyncMapping::applyStaticAttributeData::start');
        $configuration = array();
        if (trim($configurationData['configuration']) != '') {
            $configuration = json_decode($configurationData['configuration'], true);
        }

        if (!empty($article->getAttribute()) && trim($article->getAttribute()->getBlisstributeArticleShipmentCode()) != '') {
            $configuration[] = array(
                'category_type' => 'shipmentType',
                'category' => trim($article->getAttribute()->getBlisstributeArticleShipmentCode())
            );
        }

        if (!empty($article->getAttribute()) && trim($article->getAttribute()->getBlisstributeArticleAdvertisingMediumCode()) != '') {
            $configuration[] = array(
                'category_type' => 'advertisingMedium',
                'category' => trim($article->getAttribute()->getBlisstributeArticleAdvertisingMediumCode())
            );
        }
        else if ($advertisingMedium = $this->getAdvertisingMediumFromCategory($article)) {
            $configuration[] = array(
                'category_type' => 'advertisingMedium',
                'category' => trim($advertisingMedium)
            );
        }

        $configurationData['configuration'] = json_encode($configuration);
        $this->logDebug('orderSyncMapping::applyStaticAttributeData::done ' . json_encode($configuration));

        return $configurationData;
    }

    /**
     * Gets advertising medium from category.
     * Note: Does not yet take category level into account.
     *
     * @param \Shopware\Models\Article\Article $article
     * @return string|null
     */
    protected function getAdvertisingMediumFromCategory($article)
    {
        /** @var Shopware\Models\Category\Category $category */
        foreach ($article->getAllCategories() as $category) {

            if ($category->getAttribute() && $category->getAttribute()->getBlisstributeCategoryAdvertisingMediumCode()) {
                return $category->getAttribute()->getBlisstributeCategoryAdvertisingMediumCode();
            }
        }

        return null;
    }

    /**
     * @param array $articleData
     * @param Shopware\Models\Order\Detail $product
     * @param Shopware\Models\Order\Detail[] $basketItems
     * @return mixed
     */
    public function applyCustomProducts($articleData, $product, $basketItems)
    {
        // check if plugin SwagCustomProducts is installed
        $plugin = $this->getPluginRepository()->findOneBy([
            'name' => 'SwagCustomProducts',
            'active' => true
        ]);

        if (!$plugin) {
            return $articleData;
        }

        if ($product->getAttribute()->getSwagCustomProductsMode() == 1) {
            $hash = $product->getAttribute()->getSwagCustomProductsConfigurationHash();
            $orderLineConfiguration = [];
            $configurationArticles = [];

            foreach ($basketItems as $product) {
                if ($product->getAttribute()->getSwagCustomProductsConfigurationHash() == $hash && in_array($product->getAttribute()->getSwagCustomProductsMode(), [2,3])) {
                    $configurationArticles[] = $product;
                }
            }

            $this->logDebug('customProduct::load configuration by hash %s', $hash);
            $row = $this->container->get('db')->fetchRow(
                "SELECT configuration, template
                          FROM s_plugin_custom_products_configuration_hash
                          WHERE hash = :hash", array('hash' => $hash)
            );

            if (empty($configurationArticles) || !$row) {
                return $articleData;
            }

            $this->logDebug('got configuration ' . $row['configuration']);
            $currentConfigurationData = json_decode($row['configuration'], true);

            $this->logDebug('got template ' . $row['template']);
            $templateCollection = json_decode($row['template'], true);

            $this->logDebug('customProduct::got configuration articles ' . count($configurationArticles));
            foreach ($configurationArticles as $configurationArticle) {
                $price = $configurationArticle->getPrice();
                $quantity = $configurationArticle->getQuantity();

                $articleData['originalPriceAmount'] += $price;
                $articleData['originalPrice'] += $price * $quantity;
                $articleData['priceAmount'] += round($price, 6);
                $articleData['price'] += round(($price * $quantity), 6);

                if ($configurationArticle->getAttribute()->getSwagCustomProductsMode() == 2) {
                    foreach ($templateCollection as $currentTemplate) {
                        if ($currentTemplate['id'] != $configurationArticle->getArticleId()) {
                            continue;
                        }

                        $value = trim($currentConfigurationData[$currentTemplate['id']][0]);
                        if ($value != '') {
                            $orderLineConfiguration[] = array('category_type' => $currentTemplate['name'], 'category' => $value);
                        }
                    }
                }
            }

            if (!empty($orderLineConfiguration)) {
                $articleData['configuration'] = json_encode($orderLineConfiguration);
            }

            return $articleData;
        }

        return $articleData;
    }

    protected $_newPromotionSuite = false;

    public function applyPromoDiscounts($articleDataCollection, $promotions, $orderNumbers, $shopwareDiscountsAmount, $orderId)
    {
        // check if plugin SwagPromotion is installed
        $plugin = $this->getPluginRepository()->findOneBy([
            'name' => 'SwagPromotion',
            'active' => true
        ]);

        $allPromotions = [];

        if ($plugin) {
            /** @var Detail $promotionItem */
            foreach ($promotions as $promotionItem) {
                if (class_exists('\Shopware\CustomModels\SwagPromotion\Promotion')) {
                    /** @var \Shopware\CustomModels\SwagPromotion\Promotion $promotion */
                    $promotion = $this->container->get('models')->getRepository('\Shopware\CustomModels\SwagPromotion\Promotion')->findOneBy(['number' => $promotionItem->getArticleNumber()]);
                } else {
                    $this->_newPromotionSuite = true;
                    /** @var \SwagPromotion\Models\Promotion $promotion */
                    $promotion = $this->container->get('models')->getRepository('\SwagPromotion\Models\Promotion')->findOneBy(['number' => $promotionItem->getArticleNumber()]);
                }

                if (is_null($promotion)) {
                    continue;
                }

                $allPromotions[$promotion->getType()][] = ['promotion' => $promotion, 'promoAmount' => abs($promotionItem->getPrice())];
            }

            $products = $this->getProductContext($orderNumbers, $orderId);

            //First apply free product discount
            if (array_key_exists('product.freegoods', $allPromotions)) {
                $articleDataCollection = $this->applyFreeDiscount($allPromotions['product.freegoods'], $articleDataCollection);
            }

            if (array_key_exists('product.buyxgetyfree', $allPromotions)) {
                $articleDataCollection = $this->applyXYDiscount($allPromotions['product.buyxgetyfree'], $articleDataCollection, $products);
            }
        }

        $articleDataCollection = $this->applyVouchers($articleDataCollection);

        $articleDataCollection = $this->applyShopwareDiscount($articleDataCollection, $shopwareDiscountsAmount);

        if ($plugin) {
            //Apply product absolute discount
            if (array_key_exists('product.absolute', $allPromotions)) {
                $articleDataCollection = $this->applyProductAbsoluteDiscount($allPromotions['product.absolute'], $articleDataCollection, $products);
            }

            //Apply product percent discount
            if (array_key_exists('product.percentage', $allPromotions)) {
                $articleDataCollection = $this->applyProductPercentDiscount($allPromotions['product.percentage'], $articleDataCollection, $products);
            }

            //Apply cart absolute discount
            if (array_key_exists('basket.absolute', $allPromotions)) {
                $articleDataCollection = $this->applyCartAbsoluteDiscount($allPromotions['basket.absolute'], $articleDataCollection);
            }

            //Apply cart percent discount, this discount must be handled in special way
            if (array_key_exists('basket.percentage', $allPromotions)) {
                $articleDataCollection = $this->applyCartPercentDiscount($allPromotions['basket.percentage'], $articleDataCollection);
            }
        }

        return $articleDataCollection;
    }

    public function getPromotionStackedProducts($promotion, $products)
    {
        /** @var \Shopware\SwagPromotion\Components\ProductMatcher $productMatcher */
        $productMatcher = $this->_newPromotionSuite ? $this->container->get('swag_promotion.product_matcher') : $this->container->get('promotion.product_matcher');

        /** @var \Shopware\SwagPromotion\Components\Promotion\ProductStacker\ProductStacker $productStackRegistry */
        $productStackRegistry = $this->_newPromotionSuite ? $this->container->get('swag_promotion.stacker.product_stacker_registry') : $this->container->get('promotion.stacker.registry');

        $promotionProducts = $productMatcher->getMatchingProducts($products, json_decode($promotion->getApplyRules(), true));

        return $productStackRegistry->getStacker($promotion->getStackMode())->getStack(
            $promotionProducts,
            $promotion->getStep(),
            $promotion->getMaxQuantity(),
            'cheapest'
        );
    }

    public function applyFreeDiscount($promotions, $articleDataCollection)
    {
        /** @var \Shopware\CustomModels\SwagPromotion\Promotion $promotion */
        foreach ($promotions as $currentPromotion) {
            $promotion = $currentPromotion['promotion'];
            $freeProducts = [];

            foreach ($promotion->getFreeGoodsArticle() as $article) {
                $freeProducts[] = $article->getId();
            }

            foreach ($articleDataCollection as &$product) {
                if ($product['promoQuantity'] == 0 || $product['priceAmount'] == 0) {
                    continue;
                }

                if (!in_array($product['articleId'], $freeProducts)) {
                    continue;
                }

                if ($product['originalPriceAmount'] == $currentPromotion['promoAmount']) {
                    $countedAmountToDiscount = $product['originalPriceAmount'];
                    $countedAmountToDiscountPerQty = $countedAmountToDiscount / $product['quantity'];

                    $product['promoQuantity'] -= 1;
                    $product['priceAmount'] -= $countedAmountToDiscountPerQty;
                    $product['price'] -= round($countedAmountToDiscount, 4);
                    $product['discountTotal'] += $countedAmountToDiscountPerQty;

                    break;
                }
            }
        }

        return $articleDataCollection;
    }

    public function applyXYDiscount($promotions, $articleDataCollection, $products)
    {
        /** @var \Shopware\CustomModels\SwagPromotion\Promotion $promotion */
        foreach ($promotions as $currentPromotion) {
            $promotion = $currentPromotion['promotion'];
            $stackedProducts = $this->getPromotionStackedProducts($promotion, $products);

            foreach ($stackedProducts as $stack) {
                $amount = $promotion->getAmount();

                $stackProduct = array_map(
                    function ($p) {
                        return $p['ordernumber'];
                    },
                    // get the "free" items
                    array_slice($stack, 0, $amount)
                );

                foreach ($articleDataCollection as &$product) {
                    if ($amount == 0) {
                        break;
                    }

                    if ($product['promoQuantity'] == 0 || $product['priceAmount'] == 0) {
                        continue;
                    }

                    if (in_array($product['articleNumber'], $stackProduct)) {
                        if ($amount > $product['quantity']) {
                            $qty = $product['quantity'];
                        } else {
                            $qty = $amount;
                        }

                        $countedAmountToDiscount = round($qty * $product['originalPriceAmount'], 4);
                        $countedAmountToDiscountPerQty = round($countedAmountToDiscount / $product['quantity'], 4);

                        $product['promoQuantity'] -= 1;
                        $product['priceAmount'] -= $countedAmountToDiscountPerQty;
                        $product['price'] -= $countedAmountToDiscount;
                        $product['discountTotal'] += $countedAmountToDiscountPerQty;

                        $amount -= $qty;
                    }
                }
            }
        }

        return $articleDataCollection;
    }

    public function applyProductAbsoluteDiscount($promotions, $articleDataCollection, $products)
    {
        /** @var \Shopware\CustomModels\SwagPromotion\Promotion $promotion */
        foreach ($promotions as $currentPromotion) {
            $promotion = $currentPromotion['promotion'];
            $stackedProducts = $this->getPromotionStackedProducts($promotion, $products);

            $productWithDiscount = [];
            $basketAmount = 0;

            foreach ($stackedProducts as $stack) {
                $product = array_map(
                    function ($p) {
                        return $p['ordernumber'];
                    },
                    $stack
                );

                if (!array_key_exists($product[0], $productWithDiscount[$product[0]])) {
                    $productWithDiscount[$product[0]] = 1;
                } else {
                    $productWithDiscount[$product[0]]++;
                }
            }

            foreach ($articleDataCollection as $product) {
                if ($product['promoQuantity'] == 0 && $product['price'] <= 0) {
                    continue;
                }

                if (array_key_exists($product['articleNumber'], $productWithDiscount)) {
                    $basketAmount += $productWithDiscount[$product['articleNumber']] * $product['priceAmount'];
                }
            }

            foreach ($articleDataCollection as &$product) {
                if ($product['promoQuantity'] == 0 || $product['priceAmount'] == 0) {
                    continue;
                }

                if (array_key_exists($product['articleNumber'], $productWithDiscount)) {
                    $weight = $productWithDiscount[$product['articleNumber']] * $product['priceAmount'] / $basketAmount;

                    $countedAmountToDiscount = $currentPromotion['promoAmount'] * $weight;
                    $countedAmountToDiscountPerQty = $countedAmountToDiscount / $product['quantity'];

                    $product['priceAmount'] -= $countedAmountToDiscountPerQty;
                    $product['price'] -= $countedAmountToDiscount;
                    $product['discountTotal'] += $countedAmountToDiscountPerQty;
                }
            }
        }

        return $articleDataCollection;
    }

    public function applyProductPercentDiscount($promotions, $articleDataCollection, $products)
    {
        /** @var \Shopware\CustomModels\SwagPromotion\Promotion $promotion */
        foreach ($promotions as $currentPromotion) {
            $promotion = $currentPromotion['promotion'];
            $stackedProducts = $this->getPromotionStackedProducts($promotion, $products);

            $productWithDiscount = [];
            $basketAmount = 0;

            foreach ($stackedProducts as $stack) {
                $product = array_map(
                    function ($p) {
                        return $p['ordernumber'];
                    },
                    $stack
                );

                if (!array_key_exists($product[0], $productWithDiscount[$product[0]])) {
                    $productWithDiscount[$product[0]] = 1;
                } else {
                    $productWithDiscount[$product[0]]++;
                }
            }

            foreach ($articleDataCollection as $product) {
                if ($product['promoQuantity'] == 0 && $product['price'] <= 0) {
                    continue;
                }

                if (array_key_exists($product['articleNumber'], $productWithDiscount)) {
                    $basketAmount += $productWithDiscount[$product['articleNumber']] * $product['priceAmount'];
                }
            }

            foreach ($articleDataCollection as &$product) {
                if ($product['promoQuantity'] == 0 || $product['price'] <= 0) {
                    continue;
                }

                if (array_key_exists($product['articleNumber'], $productWithDiscount)) {
                    $weight = $productWithDiscount[$product['articleNumber']] * $product['priceAmount'] / $basketAmount;

                    $countedAmountToDiscount = $currentPromotion['promoAmount'] * $weight;
                    $countedAmountToDiscountPerQty = $countedAmountToDiscount / $product['quantity'];

                    $product['priceAmount'] -= $countedAmountToDiscountPerQty;
                    $product['price'] -= $countedAmountToDiscount;
                    $product['discountTotal'] += $countedAmountToDiscountPerQty;
                }
            }
        }

        return $articleDataCollection;
    }

    public function applyCartAbsoluteDiscount($promotions, $articleDataCollection)
    {
        $promotionDiscount = 0;
        $basketAmount = 0;

        /** @var \Shopware\CustomModels\SwagPromotion\Promotion $promotion */
        foreach ($promotions as $currentPromotion) {
            $promotionDiscount += $currentPromotion['promotion']->getAmount();
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
            $countedAmountToDiscountPerQty = $countedAmountToDiscount / $product['quantity'];

            $product['priceAmount'] -= $countedAmountToDiscountPerQty;
            $product['price'] -= $countedAmountToDiscount;
            $product['discountTotal'] += $countedAmountToDiscountPerQty;
        }

        return $articleDataCollection;
    }

    public function applyCartPercentDiscount($promotions, $articleDataCollection)
    {
        $promotionDiscount = 0;
        $basketAmount = 0;

        /** @var \Shopware\CustomModels\SwagPromotion\Promotion $promotion */
        foreach ($promotions as $currentPromotion) {
            $promotionDiscount += $currentPromotion['promoAmount'];
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
            $countedAmountToDiscountPerQty = $countedAmountToDiscount / $product['quantity'];

            $product['priceAmount'] -= $countedAmountToDiscountPerQty;
            $product['price'] -= $countedAmountToDiscount;
            $product['discountTotal'] += $countedAmountToDiscountPerQty;
        }

        return $articleDataCollection;
    }

    public function applyShopwareDiscount($articleDataCollection, $shopwareDiscountsAmount)
    {
        $totalProductAmount = 0;
        foreach ($articleDataCollection as $product) {
            if ($product['promoQuantity'] == 0 || $product['price'] <= 0) {
                continue;
            }

            $totalProductAmount += round($product['price'], 4);
        }

        foreach ($articleDataCollection as &$product) {
            if ($product['promoQuantity'] == 0 || $product['price'] <= 0) {
                continue;
            }

            $weight = $product['price'] / $totalProductAmount;

            $countedAmountToDiscountPerQuantity = (abs($shopwareDiscountsAmount) * $weight) / $product['promoQuantity'];

            $product['discountTotal'] += round($countedAmountToDiscountPerQuantity, 4);
            $product['price'] -= round($countedAmountToDiscountPerQuantity * $product['promoQuantity'], 4);
            $product['priceAmount'] -= round($countedAmountToDiscountPerQuantity, 4);
        }

        return $articleDataCollection;
    }

    public function applyVouchers($articleDataCollection)
    {
        $couponMappingRepository = $this->getCouponMappingRepository();

        $basketAmount = 0;
        $productBasket = 0;
        $vouchersData = [];

        foreach ($this->voucherCollection as $currentVoucher) {
            $couponMapping = $couponMappingRepository->findByCoupon($currentVoucher->getId());
            if ($couponMapping != null && $couponMapping->getIsMoneyVoucher()) {
                continue;
            }

            // check if plugin CoeExcludeProducerOnVoucher is installed
            $plugin = $this->getPluginRepository()->findOneBy([
                'name' => 'CoeExcludeProducerOnVoucher',
                'active' => true
            ]);

            $coeExcludeSuppliers = [];
            if ($plugin) {
                if ($currentVoucher->getAttribute() != null) {
                    $coeExcludeSupplier = $currentVoucher->getAttribute()->getCoeExcludeSupplier();

                    if ($coeExcludeSupplier != '') {
                        $coeExcludeSupplier = str_replace(',', '|', $coeExcludeSupplier);
                        $_coeExcludeSuppliers = explode('|', $coeExcludeSupplier);

                        foreach ($_coeExcludeSuppliers as $_coeExcludeSupplier) {
                            if (!empty($_coeExcludeSupplier) && ctype_digit($_coeExcludeSupplier)) {
                                $coeExcludeSuppliers[] = $_coeExcludeSupplier;
                            }
                        }
                    }
                }
            }

            // check if plugin CoeVoucherOnReducedArticle is installed
            $plugin = $this->getPluginRepository()->findOneBy([
                'name' => 'CoeVoucherOnReducedArticle',
                'active' => true
            ]);

            $coeReducedArticle = null;
            if ($plugin) {
                if ($currentVoucher->getAttribute() != null) {
                    $coeReducedArticle = $currentVoucher->getAttribute()->getCoeReducedArticle();
                }
            }

            $vouchersData[$currentVoucher->getId()] = [
                'coeExcludeSuppliers' => $coeExcludeSuppliers,
                'coeReducedArticle' => $coeReducedArticle,
            ];

            foreach ($articleDataCollection as $product) {
                if ($this->isProductBlockedForVoucher($product, $currentVoucher, $vouchersData)) {
                    continue;
                }

                $basketAmount += round($product['originalPrice'], 4);
                $productBasket += round($product['price'], 4);
            }
        }

        foreach ($articleDataCollection as &$product) {
            $price = $product['price'];

            foreach ($this->voucherCollection as $currentVoucher) {
                $couponMapping = $couponMappingRepository->findByCoupon($currentVoucher->getId());
                if ($couponMapping != null && $couponMapping->getIsMoneyVoucher()) {
                    continue;
                }

                $voucherDiscount = round($this->calculateArticleDiscountForVoucher(
                    $product, $currentVoucher, $vouchersData, $price, $articleDataCollection, $basketAmount, $productBasket
                ), 4);

                $voucherDiscountPerQuantity = round($voucherDiscount / $product['promoQuantity'], 4);

                $product['priceAmount'] -= $voucherDiscountPerQuantity;
                $product['price'] -= $voucherDiscountPerQuantity * $product['promoQuantity'];
                $product['discountTotal'] += $voucherDiscountPerQuantity;
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
        $plugin = $this->getPluginRepository()->findOneBy([
            'name' => 'PixupMarkenZahlartenRabattsteuerung',
            'active' => true
        ]);

        $price = $item->getPrice();
        $quantity = $item->getQuantity();

        $discount = 0;

        if ($plugin) {
            // apply weighed discount per supplier
            $paymentMean = $item->getOrder()->getPayment();
            $article = Shopware()->Models()->getRepository('Shopware\Models\Article\Article')->find($item->getArticleId());
            $supplier = $article->getSupplier();

            $discountMapping = Shopware()->Models()->getRepository('Shopware\CustomModels\PixupMarkenZahlartenRabattsteuerung\MarkenZahlartenRabattModel')->findOneBy([
                'supplier' => $supplier,
                'paymentMean' => $paymentMean
            ]);

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
            ->setParameters([
                'articleId' => $orderDetail->getArticleId(),
                'articleNumber' => $orderDetail->getArticleNumber(),
            ])
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
        $this->voucherCollection = [];

        /** @var VoucherRepository $voucherRepository */
        $voucherRepository = Shopware()->Models()->getRepository('Shopware\Models\Voucher\Voucher');
        $couponMappingRepository = $this->getCouponMappingRepository();

        /** @var Detail $currentOrderLine */
        foreach ($this->getModelEntity()->getOrder()->getDetails() as $currentOrderLine) {
            $this->logDebug(sprintf(
                'process order line id %s / article number %s / price %s',
                $currentOrderLine->getId(),
                $currentOrderLine->getArticleNumber(),
                $currentOrderLine->getPrice()
            ));

            // no coupon
            if ($currentOrderLine->getMode() != 2) {
                continue;
            }

            $voucher = null;
            $voucherCollection = $voucherRepository->getValidateOrderCodeQuery($currentOrderLine->getArticleNumber())->getResult();
            if (count($voucherCollection) > 1) {
                /** @var Voucher $currentVoucher */
                foreach ($voucherCollection as $currentVoucher) {
                    if (abs(round($currentVoucher->getValue(), 4, PHP_ROUND_HALF_UP)) != abs(round($currentOrderLine->getPrice(), 4, PHP_ROUND_HALF_UP))) {
                        continue;
                    }

                    $voucher = $currentVoucher;
                    break;
                }

            } elseif (count($voucherCollection) == 1) {
                $voucher = $voucherCollection[0];
            }

            if ($voucher != null) {
                /** @var $voucher Voucher */
                $this->voucherCollection[] = $voucher;

                $couponMapping = $couponMappingRepository->findByCoupon($voucher);
                if ($couponMapping != null && $couponMapping->getIsMoneyVoucher()) {
                    $this->logDebug(sprintf(
                        'order line id %s / is money voucher! %s',
                        $currentOrderLine->getId(),
                        $couponMapping->getId()
                    ));

                    continue;
                }
            }

            $this->voucherDiscountValue += abs(round($currentOrderLine->getPrice(), 4, PHP_ROUND_HALF_UP));
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
        $diff = round(($this->voucherDiscountValue - $this->voucherDiscountUsed) / count($articleDataCollection), 4);

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

            if (round($articleData['quantity'] * 0.01, 4) > abs($diff)) {
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
        if ($this->isProductBlockedForVoucher($product, $voucher, $vouchersData)) {
            return 0.00;
        }

        if ((bool) $voucher->getPercental()) {
            return $this->calculateDiscountForPercentVoucher($articlePrice, $voucher->getValue());
        }

        return $this->calculateDiscountForAbsoluteVoucher(
            $articlePrice,
            $this->getBasketTotal($articleDataCollection, $voucher, $vouchersData),
            $voucher->getValue()
        );
    }


    public function isProductBlockedForVoucher($product, Voucher $voucher, $vouchersData) {
        if ($voucher == null) {
            return true;
        }

        // check if article is valid for voucher
        $restrictedArticles = explode(';', $voucher->getRestrictArticles());
        if (count($restrictedArticles) > 0 && in_array($product['articleNumber'], $restrictedArticles)) {
            return true;
        }

        // check if article manufacturer is valid for voucher
        if ($voucher->getBindToSupplier() > 0 && $voucher->getBindToSupplier() != $product['supplierId']) {
            return true;
        }

        // check if customer group is valid for voucher
        if ($voucher->getCustomerGroup() > 0
            && $voucher->getCustomerGroup() != $product['customerGroupId']
        ) {
            return true;
        }

        // check if shop is valid for voucher
        if ($voucher->getShopId() > 0 && $voucher->getShopId() != $product['shopId']) {
            return true;
        }

        $voucherId = $voucher->getId();

        if (in_array($product['supplierId'], $vouchersData[$voucherId]['coeExcludeSuppliers'])) {
            return true;
        }

        if ($vouchersData[$voucherId]['coeReducedArticle'] == 1) {
            $customer = $this->getModelEntity()->getOrder()->getCustomer();

            if ($customer) {
                $hasPseudoPrice = $this->container->get('db')->fetchOne("SELECT sap.pseudoprice FROM s_articles_prices sap LEFT JOIN s_articles_details sad ON sap.articleDetailsID = sad.id WHERE sad.ordernumber = ? AND sap.pricegroup = ? AND sap.pseudoprice > 0", array($product['articleNumber'], $customer->getGroupKey()));

                if ($hasPseudoPrice) {
                    return true;
                }
            }
        }

        return false;
    }

    /***
     * @param array $articleDataCollection
     * @param Voucher $voucher
     * @param integer $supplierId
     *
     * @return int
     */
    public function getBasketTotal($articleDataCollection, Voucher $voucher, $vouchersData)
    {
        $totalAmount = 0;

        //todo: use price or originalPrice?
        foreach ($articleDataCollection as $product) {
            if ($product['promoQuantity'] == 0 || $product['originalPrice'] <= 0) {
                continue;
            }

            if ($this->isProductBlockedForVoucher($product, $voucher, $vouchersData)) {
                continue;
            }

            $totalAmount += $product['originalPrice'];
        }

        return $totalAmount;
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
    protected function calculateDiscountForAbsoluteVoucher($price, $orderSubtotal, $discount)
    {
        $this->logDebug('orderSyncMapping::calculateDiscountForAbsoluteVoucher::price ' . $price);
        $this->logDebug('orderSyncMapping::calculateDiscountForAbsoluteVoucher::orderSubtotal ' . $orderSubtotal);
        $this->logDebug('orderSyncMapping::calculateDiscountForAbsoluteVoucher::discount ' . $discount);
        $evaluateOrderLine = $price / $orderSubtotal;
        $this->logDebug('orderSyncMapping::calculateDiscountForAbsoluteVoucher::evaluatedOrderLinePrice ' . $evaluateOrderLine);

        // get the discount for the order line
        $orderLineDiscount = $discount / 100 * ($evaluateOrderLine * 100);
        $this->logDebug('orderSyncMapping::calculateDiscountForAbsoluteVoucher::orderLineDiscount ' . $orderLineDiscount);

        return round($orderLineDiscount, 4, PHP_ROUND_HALF_UP);
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
    protected function calculateDiscountForPercentVoucher($price, $discount)
    {
        return round(($price / 100 * $discount), 4, PHP_ROUND_HALF_UP);
    }

    /**
     * build order voucher data
     *
     * @return array
     */
    protected function buildCouponData()
    {
        $voucherData = [];

        foreach ($this->getModelEntity()->getOrder()->getDetails() as $currentDetail) {
            if (($currentDetail->getMode() == 4 || $currentDetail->getMode() == 3) && $currentDetail->getPrice() < 0) {
                $voucherData[] = array(
                    'couponCode' => $currentDetail->getArticleName(),
                    'couponDiscount' => abs(round($currentDetail->getPrice(), 4)),
                    'couponDiscountPercentage' => false,
                    'isMoneyVoucher' => false,
                );
            }
        }

        foreach ($this->voucherCollection as $currentVoucher) {
            $voucherPercentage = 0.00;
            $voucherDiscount = $currentVoucher->getValue();

            if ((bool)$currentVoucher->getPercental()) {
                $voucherPercentage = round($currentVoucher->getValue(), 4);

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
                'couponDiscount' => round($voucherDiscount, 4),
                'couponDiscountPercentage' => $voucherPercentage,
                'isMoneyVoucher' => $isMoneyVoucher,
            );
        }

        return $voucherData;
    }
}
