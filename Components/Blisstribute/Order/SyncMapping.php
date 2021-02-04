<?php

require_once __DIR__ . '/../SyncMapping.php';
require_once __DIR__ . '/../Exception/MappingException.php';
require_once __DIR__ . '/../Exception/ValidationMappingException.php';
require_once __DIR__ . '/../Exception/OrderPaymentMappingException.php';
require_once __DIR__ . '/../Exception/OrderShipmentMappingException.php';
require_once __DIR__ . '/../Domain/LoggerTrait.php';

use Shopware\Models\Order\Detail;
use Shopware\Models\Article\Article;
use Shopware\Models\Order\Order;
use Shopware\Models\Voucher\Voucher;
use Shopware\Models\Article\Repository as ArticleRepository;
use Shopware\Models\Voucher\Repository as VoucherRepository;

use Doctrine\ORM\NonUniqueResultException;

use Shopware\CustomModels\Blisstribute\BlisstributeOrder;
use Shopware\CustomModels\Blisstribute\BlisstributePaymentRepository;
//use Shopware\CustomModels\Blisstribute\BlisstributeShipmentRepository;
use VIISON\AddressSplitter\AddressSplitter;

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

    /**
     * all used money vouchers
     *
     * @var array[]
     */
    protected $moneyVoucherCollection = [];

    private $container;

    public function __construct()
    {
        $this->container = Shopware()->Container();
    }

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
     * return blisstribute payment mapping database repository
     *
     * @return BlisstributePaymentRepository
     */
    protected function getPaymentMappingRepository()
    {
        return Shopware()->Models()->getRepository('Shopware\CustomModels\Blisstribute\BlisstributePayment');
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
     * @return \Doctrine\ORM\EntityRepository|\Shopware\Models\Plugin\Plugin
     */
    protected function getPluginRepository()
    {
        return Shopware()->Models()->getRepository('Shopware\Models\Plugin\Plugin');
    }

    /**
     * @throws Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException
     * @throws Shopware_Components_Blisstribute_Exception_OrderShipmentMappingException
     * @throws Shopware_Components_Blisstribute_Exception_ValidationMappingException
     * @throws NonUniqueResultException
     */
    protected function buildBaseData()
    {
        $this->logDebug('orderSyncMapping::buildBaseData::start');

        $this->determineVoucherDiscount();

        $this->orderData = $this->buildBasicOrderData();

        $this->orderData['payment']           = $this->buildPaymentData();
        $this->orderData['advertisingMedium'] = $this->buildAdvertisingMediumData();

        $this->orderData['invoiceAddress']    = $this->buildAddressData('billing');
        $this->orderData['deliveryAddress']   = $this->buildAddressData('shipping');
        $this->orderData['items']             = $this->buildItemsData();

        $this->orderData['vouchers']          = $this->buildVouchersData();

        $order               = $this->getModelEntity()->getOrder();
        $originalTotal       = round($order->getInvoiceAmount(), 2);
        $newOrderTotal       = round($this->getOrderTotal(), 2);
        $orderTotalDeviation = $originalTotal - $newOrderTotal;
        $deviationWatermark  = round($this->getConfig()['blisstribute-discount-difference-watermark'], 2);

        if (abs($orderTotalDeviation) > abs($deviationWatermark)) {
            $this->logWarn(sprintf('orderSyncMapping::buildBaseData::amount differs %s to %s', $originalTotal, $newOrderTotal));
            $this->logWarn(json_encode($this->orderData));
            $this->orderData['customerRemark'] .= 'RABATT PRÜFEN! (ORIG ' . $originalTotal .')';
        }

        // Remove legacy field from items.
        foreach($this->orderData['items'] as &$it) {
            unset($it['legacy']);
        }

        $this->logDebug('orderSyncMapping::buildBaseData::done');
        $this->logDebug('orderSyncMapping::buildBaseData::result:' . json_encode($this->orderData));



        return $this->orderData;
    }

    /**
     * @return float
     */
    private function getOrderTotal()
    {
        $orderTotal  = round($this->orderData['payment']['total'], 2);
        $orderTotal += round($this->orderData['shipment']['total'], 2);

        foreach ($this->orderData['items'] as $currentItem) {
            if ($this->orderData['isB2B'] && $this->getConfig()['blisstribute-transfer-b2b-net']) {
                // Convert to price after VAT.
                $orderTotal += round((($currentItem['priceNet'] / 100) * (100 + $currentItem['vatRate'])) * $currentItem['quantity'], 2);
            } else {
                $orderTotal += round($currentItem['price'] * $currentItem['quantity'], 2);
            }
        }

        foreach ($this->orderData['vouchers'] as $currentVoucher) {
            if (!$currentVoucher['isMoneyVoucher']) {
                continue;
            }

            $orderTotal -= round(min($currentVoucher['discount'], $orderTotal), 2);
        }

        return $orderTotal;
    }

    /**
     * build basic order data
     *
     * @return array
     *
     * @throws Shopware_Components_Blisstribute_Exception_OrderShipmentMappingException
     * @throws NonUniqueResultException
     * @throws Exception
     */
    private function buildBasicOrderData()
    {
        $order          = $this->getModelEntity()->getOrder();
        $customer       = $order->getCustomer();
        $billingAddress = $order->getBilling();

        if ($billingAddress == null) {
            throw new Exception('invalid billing address data');
        }

        if ($order->getShipping() == null) {
            throw new Exception('invalid shipping address data');
        }

        // Transfer the order as locked or on hold if the mapped order attribute contains any value.
        $orderShipLock = $this->getOrderLock($order);
        $orderHold     = $this->getOrderHold($order);

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

        $company = trim($billingAddress->getCompany());
        if ($this->getConfig()['blisstribute-b2c-force']) {
            $isB2B = false;
        } elseif ($this->getConfig()['blisstribute-b2b-force']) {
            $isB2B = true;
        } else {
            $isB2B = $company != '' && !in_array($company, explode(',', $this->getConfig()['blisstribute-b2b-blacklist-pattern']));
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

        $this->logDebug('orderSyncMapping::map phone number');
        $customerPhone = $customer->getDefaultBillingAddress()->getPhone();
        if (trim($customerPhone) == '') {
            $customerPhone = $this->getAlternativePhoneNumber($order);
        }

        $this->logDebug('orderSyncMapping::map priority shipping');
        $isPriority = $order->getDispatch()->getAttribute()->getBlisstributeShipmentIsPriority();

        $this->logDebug('orderSyncMapping::map shipping total');
        $shippingTotal = $order->getInvoiceShipping();
        if ($this->isEasyCouponPluginAvailable()) {
            $shippingTotal += round($order->getAttribute()->getNetiEasyCouponShippingCostReduction(), 2);
        }

        return [
            'number'         => $order->getNumber(),
            'date'           => $order->getOrderTime()->format('Y-m-d H:i:s'),
            'currency'       => $order->getCurrency(),
            'isB2B'          => $isB2B,
            'customerRemark' => implode(' - ', $orderRemark),
            'hold'           => $orderHold,
            'lock'           => $orderShipLock,
            'isPriority'     => (bool)$isPriority,

            'customer' => [
                'number'      => $customerNumber,
                'email'       => $customer->getEmail(),
                'phone'       => $customerPhone,
                'birthday'    => $customerBirthday,
                'isB2B'       => $isB2B,
                'taxIdNumber' => trim($billingAddress->getVatId()),
            ],

            'shipment' => [
                'code'                 => $this->determineShippingType($order),
                'total'                => round($shippingTotal, 2),
                'totalIsNet'           => false,
                'allowPartialDelivery' => true,
            ],
        ];
    }

    /**
     * @param \Shopware\Models\Order\Order $order
     *
     * @return string
     */
    protected function getAlternativePhoneNumber($order)
    {
        $fieldName = $this->getConfig()['blisstribute-alternative-phone-number-order-attribute'];
        $this->logDebug('orderSyncMapping::alternativePhoneNumber::fieldName ' . $fieldName);
        return $this->getAttribute($order, $fieldName);
    }

    /**
     * @param \Shopware\Models\Order\Order $order
     *
     * @return boolean
     */
    private function getOrderHold($order)
    {
        $fieldName = $this->getConfig()['blisstribute-order-hold-mapping'];
        $this->logDebug('orderSyncMapping::orderHold::fieldName ' . $fieldName);

        return !empty($this->getAttribute($order, $fieldName));
    }

    /**
     * @param \Shopware\Models\Order\Order $order
     *
     * @return boolean
     */
    private function getOrderLock($order)
    {
        $fieldName = $this->getConfig()['blisstribute-order-lock-mapping'];
        $this->logDebug('orderSyncMapping::orderLock::fieldName ' . $fieldName);

        return !empty($this->getAttribute($order, $fieldName));
    }

    /**
     * @param \Shopware\Models\Order\Order $order
     * @param string $attrName
     * @return string
     */
    private function getAttribute($order, $attrName)
    {
        if (trim($attrName) == '') {
            return null;
        }

        // Shopware converts field names like shipment_type to getShipmentType.
        // Build the method name of $fieldNames getter.
        $value = '';
        $method = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $attrName)));

        $attribute = $order->getAttribute();
        $this->logDebug('orderSyncMapping::getAttribute::got attribute ' . $attribute->getId());

        // Get the $fieldName by calling the getter using the build method name.
        if (method_exists($attribute, $method)) {
            $value = $attribute->$method();
        }

        $this->logDebug('orderSyncMapping::getAttribute::attribute value ' . $value);

        return $value;
    }

    /**
     * determine blisstribute shipping code
     *
     * @param Order $order
     * @return string
     *
     * @throws Shopware_Components_Blisstribute_Exception_OrderShipmentMappingException
     */
    protected function determineShippingType(Order $order)
    {
        $this->logDebug('orderSyncMapping::determineShippingType');
        $shipmentCode = $order->getDispatch()->getAttribute()->getBlisstributeShipmentCode();

        if (empty(trim($shipmentCode))) {
            $this->logDebug('orderSyncMapping::shipment type code not found');
            throw new Shopware_Components_Blisstribute_Exception_OrderShipmentMappingException(
                'no shipment mapping class found for order ' . $this->getModelEntity()->getOrder()->getNumber()
            );
        }

        return $shipmentCode;
    }

    /**
     * Build advertising medium data.
     *
     * @return array
     */
    protected function buildAdvertisingMediumData()
    {
        $advertisingMediumCode = $this->getConfig()['blisstribute-default-advertising-medium'];

        return [
            'code'            => strtoupper($advertisingMediumCode),
            'origin'          => 'O',
            'medium'          => 'O',
            'affiliateSource' => '',
        ];
    }

    /**
     * Build payment data.
     *
     * @return array
     * @throws Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException
     * @throws NonUniqueResultException
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
        return $orderPayment->getPaymentInformation($payment);
    }

    /**
     * Returns the data for the billing or shipping address.
     *
     * @param $addrType string Either 'billing' or 'shipping'.
     * @return array
     * @throws Shopware_Components_Blisstribute_Exception_ValidationMappingException
     */
    private function buildAddressData($addrType)
    {
        $ent = null;
        if ($addrType == 'billing') {
            $ent = $this->getModelEntity()->getOrder()->getBilling();
        }
        elseif ($addrType == 'shipping') {
            $ent = $this->getModelEntity()->getOrder()->getShipping();
        }

        if ($ent == null) {
            throw new Shopware_Components_Blisstribute_Exception_ValidationMappingException(
                'no ' . $addrType . ' address given for order ' . $this->getModelEntity()->getOrder()->getNumber()
            );
        }

        $salutation = '';
        if ($ent->getSalutation() == 'mr') {
            $salutation = 'Herr';
        }
        elseif ($ent->getSalutation() == 'ms') {
            $salutation = 'Frau';
        }

        $country = $ent->getCountry();
        if ($country == null) {
            throw new Shopware_Components_Blisstribute_Exception_ValidationMappingException('no country given');
        }

        $street = $ent->getStreet();
        $houseNumber = $addition1 = $addition2 = '';
        try {
            if (!$this->getConfig()['blisstribute-disable-address-splitting']) {
                $match       = AddressSplitter::splitAddress($street);
                $street      = $match['streetName'];
                $houseNumber = $match['houseNumber'];
                $addition1 = $match['additionToAddress1'];
                $addition2 = $match['additionToAddress2'];
            }
        } catch (Exception $e) {}

        $addressAddition = [];
        if (trim($addition1) != '') {
            $addressAddition[] = $addition1;
        }

        if (trim($addition2) != '') {
            $addressAddition[] = $addition2;
        }

        if (trim($ent->getAdditionalAddressLine1()) !== '') {
            $addressAddition[] = trim($ent->getAdditionalAddressLine1());
        }
        if (trim($ent->getAdditionalAddressLine2()) !== '') {
            $addressAddition[] = trim($ent->getAdditionalAddressLine2());
        }

        $stateCode = '';
        try {
            if (!empty($ent->getState())) {
                $stateCode = $ent->getState()->getShortCode();
            }
        } catch (Exception $ex) {
            unset($ex);
        }

        return [
            'salutation'      => $salutation,
            'title'           => '',
            'firstName'       => $this->processAddressDataMatching($ent->getFirstName()),
            'lastName'        => $this->processAddressDataMatching($ent->getLastName()),
            'nameAddition'    => $this->processAddressDataMatching($ent->getDepartment()),
            'company'         => $this->processAddressDataMatching($ent->getCompany()),
            'street'          => $this->processAddressDataMatching($street),
            'houseNumber'     => $this->processAddressDataMatching($houseNumber),
            'addressAddition' => $this->processAddressDataMatching(implode(',', $addressAddition)),
            'zip'             => $this->processAddressDataMatching($ent->getZipCode()),
            'city'            => $this->processAddressDataMatching($ent->getCity()),
            'countryCode'     => $country->getIso(),
            'stateCode'       => $stateCode
        ];
    }

    private function processAddressDataMatching($addressString)
    {
        $blackListPattern = $this->getConfig()['blisstribute-hold-order-address-pattern'];
        if (trim($blackListPattern) == '') {
            return $addressString;
        }

        if (preg_match('/' . $blackListPattern . '/i', $addressString)) {
            $this->orderData['hold'] = true;
            if (!preg_match('/Bestellung prüfen/i', $this->orderData['customerRemark'])) {
                $this->orderData['customerRemark'] = 'Bestellung prüfen (SW Blacklist) - ' . $this->orderData['customerRemark'];
            }
        }

        return $addressString;
    }

    /**
     * Build article list.
     *
     * @return array
     * @throws NonUniqueResultException
     * @throws Shopware_Components_Blisstribute_Exception_ValidationMappingException
     */
    protected function buildItemsData()
    {
        $items = [];

        $swOrder     = $this->getModelEntity()->getOrder();
        $basketItems = $swOrder->getDetails();

        $company = trim($swOrder->getBilling()->getCompany());
        if ($this->getConfig()['blisstribute-b2c-force']) {
            $isB2B = false;
        } elseif ($this->getConfig()['blisstribute-b2b-force']) {
            $isB2B = true;
        } else {
            $isB2B = $company != '' && !in_array($company, explode(',', $this->getConfig()['blisstribute-b2b-blacklist-pattern']));
        }

        $promotions = [];
        $shopwareDiscountsAmount = 0;

        /** @var ArticleRepository $articleRepository */
        $articleRepository = $this->container->get('models')->getRepository('Shopware\Models\Article\Article');

        /** @var Shopware\Models\Order\Detail $orderDetail */
        foreach ($basketItems as $orderDetail) {
            $priceNet = $price = 0;

            if ($isB2B && $this->getConfig()['blisstribute-transfer-b2b-net']) {
                if ($swOrder->getNet()) {
                    $priceNet = $orderDetail->getPrice();
                } else {
                    $priceNet = ($orderDetail->getPrice() / (100 + $orderDetail->getTaxRate())) * 100;
                }
            } else {
                $price = $orderDetail->getPrice();
            }

            $quantity      = $orderDetail->getQuantity();
            $mode          = $orderDetail->getMode();
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

            /** @var Article $article */
            $article = $articleRepository->find($orderDetail->getArticleId());

            $articleData = [
                'externalNumber'   => (string) $orderDetail->getId(),
                'vhsArticleNumber' => $this->getArticleDetail($orderDetail)->getAttribute()->getBlisstributeVhsNumber(),
                'ean13'            => $this->getArticleDetail($orderDetail)->getEan(),
                'articleTitle'     => $orderDetail->getArticleName(),
                'quantity'         => $quantity,
                'price'            => round($price, 2), // single article price
                'priceNet'         => round($priceNet, 2), // single article price
                'discount'         => 0,
                'discountNet'      => 0,
                'vatRate'          => $this->getModelEntity()->getOrder()->getTaxFree() ? 0.0 : round($orderDetail->getTaxRate(), 2),
                'configuration'    => [],

                'legacy' => [
                    'promoQuantity' => $quantity,
                    'customerGroupId' => $swOrder->getCustomer()->getGroup()->getId(),
                    'shopId'          => $swOrder->getShop()->getId(),
                    'supplierId'      => $article->getSupplier()->getId(),
                    'articleNumber'   => $orderDetail->getArticleNumber(),
                    'originalPrice'   => $price,
                    'originalPriceAmount' => round(($price * $quantity), 2),
                ],
            ];

            $articleData = $this->applyCustomProducts($articleData, $orderDetail, $basketItems);
            $articleData = $this->applyStaticAttributeData($articleData, $article);

            $items[] = $articleData;
        }

        $items = $this->applyPromoDiscounts($items, $promotions, $shopwareDiscountsAmount);

        if (!$this->getConfig()['blisstribute-order-include-vatrate']) {
            foreach ($items as &$item) {
                $item['vatRate'] = 0;
            }
        }

        return $items;
    }

    /**
     * @param array $articleData
     * @param \Shopware\Models\Article\Article $article
     * @return array
     */
    public function applyStaticAttributeData($articleData, $article)
    {
        $this->logDebug('orderSyncMapping::applyStaticAttributeData::start');
        $configuration = [];
        if (!empty($articleData['configuration'])) {
            $configuration = $articleData['configuration'];
        }

        if (!empty($article->getMainDetail()->getAttribute()) && trim($article->getMainDetail()->getAttribute()->getBlisstributeArticleShipmentCode()) != '') {
            $configuration[] = [
                'key'   => 'shipmentType',
                'value' => trim($article->getMainDetail()->getAttribute()->getBlisstributeArticleShipmentCode())
            ];
        }

        if (!empty($article->getMainDetail()->getAttribute()) && trim($article->getMainDetail()->getAttribute()->getBlisstributeArticleAdvertisingMediumCode()) != '') {
            $configuration[] = [
                'key'   => 'advertisingMedium',
                'value' => trim($article->getMainDetail()->getAttribute()->getBlisstributeArticleAdvertisingMediumCode())
            ];
        }
        else if ($advertisingMedium = $this->getAdvertisingMediumFromCategory($article)) {
            $configuration[] = [
                'key'   => 'advertisingMedium',
                'value' => trim($advertisingMedium)
            ];
        }

        $articleData['configuration'] = $configuration;
        $this->logDebug('orderSyncMapping::applyStaticAttributeData::done ' . json_encode($configuration));

        return $articleData;
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
            'name'   => 'SwagCustomProducts',
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
                     WHERE hash = :hash", ['hash' => $hash]
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
                if ($this->orderData['isB2B'] && $this->getConfig()['blisstribute-transfer-b2b-net']) {
                    if ($product->getOrder()->getNet()) {
                        $articleData['priceNet'] += $price;
                    } else {
                        $articleData['priceNet'] += ($price / (100 + $articleData['vatRate'])) * 100;
                    }

                } else {
                    $articleData['price'] += round($price, 6);
                }


                if ($configurationArticle->getAttribute()->getSwagCustomProductsMode() == 2) {
                    foreach ($templateCollection as $currentTemplate) {
                        if ($currentTemplate['id'] != $configurationArticle->getArticleId()) {
                            continue;
                        }

                        $value = trim($currentConfigurationData[$currentTemplate['id']][0]);
                        if ($value != '') {
                            $orderLineConfiguration[] = ['key' => $currentTemplate['name'], 'value' => $value];
                        }
                    }
                }
            }

            if (!empty($orderLineConfiguration)) {
                $articleData['configuration'] = $orderLineConfiguration;
            }

            return $articleData;
        }

        return $articleData;
    }


    /**
     * @param $items
     * @param $promotions
     * @param $shopwareDiscountsAmount
     * @return mixed
     * @throws NonUniqueResultException
     */
    public function applyPromoDiscounts($items, $promotions, $shopwareDiscountsAmount)
    {
        // Try to get the Intedia Promotion Recorder.
        $intediaPromoPlugin = $this->getPluginRepository()->findOneBy([
            'name' => 'IntediaPromotionRecorder',
            'active' => true
        ]);

        $this->logDebug('apply promo discounts');

        $appliedPromotionIds = [];
        $appliedVoucherIds = [];

        // If the Intedia Promotion Recorder is available, we first apply all article-specific discounts, like a 15%
        // discount on a specific shirt brand. After that, the remaining discounts are applied cart-wide, weighted by
        // the price of the article.
        // In case the plugin is not available, all discounts will be applied cart-wide, weighted by the price.
        if ($intediaPromoPlugin) {
            $this->logDebug('apply promo discounts via intedia plugin');
            //1a. handle non-default discounts, most vouchers (Promotion suite etc) by intedia plugin

            // We begin by filtering out the IDs of the articles in this order, because they're required by
            // getPromotionDiscountsFromRecorderPlugin. Calling getPromotionDiscountsFromRecorderPlugin returns the
            // promotion ID, promotion value, and voucher ID. The promotion value is the monetary discount that will be
            // deducted from the price of each article. The discount data either refers to a promotion or voucher
            // depending on which ID is set.
            $orderDetailIds     = array_column($items, 'externalNumber');
            $promotionDiscounts = $this->getPromotionDiscountsFromRecorderPlugin($orderDetailIds);

            foreach ($items as $key => $articleData) {
                $detailId = $articleData['externalNumber'];

                if (isset($promotionDiscounts[$detailId])) {
                    // A discount for this specific article exists...

                    foreach ($promotionDiscounts[$detailId] as $discount) {

                        // Mark the discount as applied.
                        // TODO: Can a discount be a promotion *and* voucher discount? If not attach the second if with
                        //       elseif.
                        if ($discount['promotionId']) {
                            $appliedPromotionIds[] = $discount['promotionId'];
                        }

                        if ($discount['voucherId']) {
                            $appliedVoucherIds[] = $discount['voucherId'];
                        }

                        $discountTotal = $discount['promotionValue'];
                        if ($discountTotal > 0) {
                            $quantity = $articleData['quantity'];
                            $discountSingle = $discountTotal / $quantity;
                            $newPriceSingle = round($articleData['price'] - $discountSingle, 4);
                            $items[$key]['price'] = $newPriceSingle;
                            $items[$key]['discount'] += $discountSingle;
                            $items[$key]['discountNet'] += $discountSingle / (100 + $articleData['vatRate']) * 100;
                        }
                    }
                }
            }

            //1b. split remaining promotions (absolute card discounts etc) to all products, weighted by price
            /** @var Detail $promotion */
            foreach ($promotions as $promotion) {
                $promotionId = $promotion->getAttribute()->getIntediaPromotionRecorderPromotionId();
                if (in_array($promotionId, $appliedPromotionIds)) { //handled above
                    continue;
                }
                $items = $this->applyShopwareDiscount($items, $promotion->getPrice());
            }

        }
        else {
            //1c. without plugin: split all promotions to all products, weighted by price
            /** @var Detail $promotion */
            foreach ($promotions as $promotion) {
                $items = $this->applyShopwareDiscount($items, $promotion->getPrice());
            }
        }

        /**
         * 2. split voucher discounts to all allowed products, weighted by price
         * Note: Most vouchers are handled by Intedia Plugin (if present)
         * However: Absolute discount vouchers not specific to certain products/suppliers
         * still need to be split by weight over all articles
         */
        $items = $this->applyVouchers($items, $appliedVoucherIds);
        //3. split shopware default discounts to all products, weighted by price (not handled by intedia Plugin)
        $items = $this->applyShopwareDiscount($items, $shopwareDiscountsAmount);

        return $items;
    }

    /**
     * @param int[] $orderDetailIds
     * @return array
     */
    private function getPromotionDiscountsFromRecorderPlugin(array $orderDetailIds) {

        if (!class_exists('IntediaPromotionRecorder\Models\PromotionRecord')) {
            return [];
        }

        $promotionRecordRepo = $this->container->get('models')->getRepository('IntediaPromotionRecorder\Models\PromotionRecord');


        $builder = $promotionRecordRepo->createQueryBuilder('records')
            ->andWhere('records.orderDetailId IN (?1)')
            ->setParameter(1, $orderDetailIds);

        $records = $builder->getQuery()->getArrayResult();

        $discounts = [];

        foreach ($records as $record) {
            $detailId = $record['orderDetailId'];
            if (!isset($discounts[$detailId])) {
                $discounts[$detailId] = [];
            }
            $discounts[$detailId][] = [
                'promotionValue' => $record['promotionValue'],
                'promotionId'   => $record['promotionId'],
                'voucherId'     => $record['voucherId']
            ];
        }

        return $discounts;
    }

    public function applyShopwareDiscount($articleDataCollection, $shopwareDiscountsAmount)
    {
        $this->logDebug('orderSyncMapping::applyShopwareDiscount::start');
        $totalProductAmount = 0;
        foreach ($articleDataCollection as $product) {
            if ($product['quantity'] == 0 || $product['legacy']['originalPrice'] <= 0) {
                continue;
            }

            $totalProductAmount += $product['legacy']['originalPriceAmount'];
        }

        foreach ($articleDataCollection as &$product) {
            if ($product['quantity'] == 0 || $product['legacy']['originalPrice'] <= 0) {
                continue;
            }

            $weight = $product['legacy']['originalPriceAmount'] / $totalProductAmount;
            $singleDiscount = abs($shopwareDiscountsAmount * $weight) / $product['quantity'];

            $product['discount']              += round($singleDiscount, 4);
            $product['price']                 -= round($singleDiscount, 4);
        }

        $this->logDebug('orderSyncMapping::applyShopwareDiscount::done');
        return $articleDataCollection;
    }

    /**
     * @param $articleDataCollection
     * @param array $excludedVoucherIds
     * @return mixed
     * @throws NonUniqueResultException
     */
    public function applyVouchers($articleDataCollection, $excludedVoucherIds = [])
    {
        $this->logDebug('orderSyncMapping::applyVouchers::start');
        $couponMappingRepository = $this->getCouponMappingRepository();

        $vouchersData = [];

        // check if plugin CoeExcludeProducerOnVoucher is installed
        $coeExcludePlugin = $this->getPluginRepository()->findOneBy([
            'name' => 'CoeExcludeProducerOnVoucher',
            'active' => true
        ]);
        $this->logDebug('orderSyncMapping::applyVouchers::CoeExcludeProducerOnVoucher active:' . (int)($coeExcludePlugin === null));

        // check if plugin CoeVoucherOnReducedArticle is installed
        $coeReducedPlugin = $this->getPluginRepository()->findOneBy([
            'name' => 'CoeVoucherOnReducedArticle',
            'active' => true
        ]);
        $this->logDebug('orderSyncMapping::applyVouchers::CoeVoucherOnReducedArticle active:' . (int)($coeReducedPlugin === null));

        foreach ($this->voucherCollection as $currentVoucher) {
            $couponMapping = $couponMappingRepository->findByCoupon($currentVoucher->getId());
            if ($couponMapping != null && $couponMapping->getIsMoneyVoucher()) {
                continue;
            }

            if (in_array($currentVoucher->getId(), $excludedVoucherIds)) {
                continue;
            }

            $coeExcludeSuppliers = [];
            if ($coeExcludePlugin) {
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

            $coeReducedArticle = null;
            if ($coeReducedPlugin) {
                if ($currentVoucher->getAttribute() != null) {
                    $coeReducedArticle = $currentVoucher->getAttribute()->getCoeReducedArticle();
                }
            }

            $vouchersData[$currentVoucher->getId()] = [
                'coeExcludeSuppliers' => $coeExcludeSuppliers,
                'coeReducedArticle' => $coeReducedArticle,
            ];
        }

        foreach ($articleDataCollection as &$product) {
            $price = $product['price'];

            foreach ($this->voucherCollection as $currentVoucher) {
                $couponMapping = $couponMappingRepository->findByCoupon($currentVoucher->getId());
                if ($couponMapping != null && $couponMapping->getIsMoneyVoucher()) {
                    continue;
                }

                //percentile and product specific discount vouchers are handled by intedia plugin (if present)
                if (in_array($currentVoucher->getId(), $excludedVoucherIds)) {
                    continue;
                }

                $voucherDiscount = round($this->calculateArticleDiscountForVoucher(
                    $product, $currentVoucher, $vouchersData, $price, $articleDataCollection
                ), 4);

                $voucherDiscountPerQuantity = round($voucherDiscount, 4);

                $product['price']       -= $voucherDiscountPerQuantity;
                $product['discount']    += $voucherDiscountPerQuantity;
            }
        }

        $this->logDebug('orderSyncMapping::applyVouchers::done');
        return $articleDataCollection;
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
        $this->logDebug('orderSyncMapping::determineVoucherDiscount::start');

        $this->voucherDiscountValue = 0.00;
        $this->voucherDiscountUsed = 0.00;
        $this->voucherCollection = [];

        /** @var VoucherRepository $voucherRepository */
        $voucherRepository = Shopware()->Models()->getRepository('Shopware\Models\Voucher\Voucher');
        $couponMappingRepository = $this->getCouponMappingRepository();

        /** @var Detail $currentOrderLine */
        foreach ($this->getModelEntity()->getOrder()->getDetails() as $currentOrderLine) {
            $this->logDebug(sprintf(
                'orderSyncMapping::determineVoucherDiscount::process order line id %s / article number %s / price %s',
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
            foreach ($voucherCollection as $currentVoucher) {
                // we'll handle easycoupon separately
                if (strtolower($currentVoucher->getDescription()) === 'netieasycoupon') {
                    continue;
                }

                $this->voucherCollection[] = $currentVoucher;
                $this->voucherDiscountValue += abs(round($currentOrderLine->getPrice(), 4, PHP_ROUND_HALF_UP));
            }
        }

        $this->logDebug('orderSyncMapping::determineVoucherDiscount::voucherDiscountValue: ' . $this->voucherDiscountValue);
        $this->logDebug('orderSyncMapping::determineVoucherDiscount::voucherDiscountUsed: ' . $this->voucherDiscountUsed);
        $this->logDebug('orderSyncMapping::determineVoucherDiscount::voucherDiscountCollection: ' . count($this->voucherCollection));

        $easyCoupons = $this->getEasyCouponsByOrderId($this->getModelEntity()->getOrder()->getId());
        foreach ($easyCoupons as $easyCoupon) {
            $this->logDebug('orderSyncMapping::determineVoucherDiscount::processing money voucher: ' . json_encode($easyCoupon));
            $sql = 'SELECT `title`, `voucherCodeID`, `orderId` from `s_neti_easycoupon_details` where `id` = ?';
            $result = Shopware()->Db()->fetchRow($sql, [(int)$easyCoupon['couponId']]);

            $voucherCodeId = (int)$result['voucherCodeID'];
            $voucherName = trim($result['title']);
            $discountUsed = round($easyCoupon['cashValue'], 2);

            $sql = '
                SELECT `voucherId`, `code`
                FROM `s_emarketing_voucher_codes`
                WHERE `id` = ?
            ';
            $result = Shopware()->Db()->fetchRow($sql, [$voucherCodeId]);
            $voucherId = (int)$result['voucherId'];
            $code = trim($result['code']);

            $couponMapping = $couponMappingRepository->findByCoupon($voucherId);
            if ($couponMapping != null && $couponMapping->getIsMoneyVoucher()) {
                $moneyVoucher = [
                    'code' => $code,
                    'discount' => $discountUsed,
                    'discountPercentage' => 0,
                    'isMoneyVoucher' => true,
                ];

                $this->logInfo('orderSyncMapping::determineVoucherDiscount::adding money voucher: ' . json_encode($moneyVoucher));
                $this->moneyVoucherCollection[] = $moneyVoucher;
            }
        }
    }

    protected function getEasyCouponsByOrderId($orderId)
    {
        if (!$this->isEasyCouponPluginAvailable()) {
            return [];
        }

        $sql = '
            SELECT `cashValue`, `couponId`, `Id`
            FROM `s_neti_easycoupon_cashed`
            WHERE `orderID` = ?
        ';

        return Shopware()->Db()->fetchAll($sql, [$orderId]);
    }

    /**
     * checks if netifoundation easy coupon plugin is available
     *
     * @return bool
     */
    protected function isEasyCouponPluginAvailable()
    {
        $plugin = Shopware()->Models()->getRepository('Shopware\Models\Plugin\Plugin')->findOneBy([
            'name' => 'NetiEasyCoupon',
            'active' => true
        ]);

        if (!$plugin) {
            return false;
        }

        return true;
    }

    /**
     * determine article discount for voucher
     *
     * @param array $product
     * @param Voucher $voucher
     * @param array $vouchersData
     * @param float $articlePrice
     * @param array $articleDataCollection
     *
     * @return float
     */
    protected function calculateArticleDiscountForVoucher($product, Voucher $voucher, $vouchersData, $articlePrice, $articleDataCollection)
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


    public function isProductBlockedForVoucher($product, Voucher $voucher, $vouchersData)
    {
        if ($voucher == null) {
            return true;
        }

        // check if article is valid for voucher
        $restrictedArticles = explode(';', $voucher->getRestrictArticles());
        if (count($restrictedArticles) > 0 && in_array($product['legacy']['articleNumber'], $restrictedArticles)) {
            return true;
        }

        // check if article manufacturer is valid for voucher
        if ($voucher->getBindToSupplier() > 0 && $voucher->getBindToSupplier() != $product['legacy']['supplierId']) {
            return true;
        }

        // check if customer group is valid for voucher
        if ($voucher->getCustomerGroup() > 0
            && $voucher->getCustomerGroup() != $product['legacy']['customerGroupId']
        ) {
            return true;
        }

        // check if shop is valid for voucher
        if ($voucher->getShopId() > 0 && $voucher->getShopId() != $product['legacy']['shopId']) {
            return true;
        }

        $voucherId = $voucher->getId();

        if (in_array($product['legacy']['supplierId'], $vouchersData[$voucherId]['coeExcludeSuppliers'])) {
            return true;
        }

        if ($vouchersData[$voucherId]['coeReducedArticle'] == 1) {
            $customer = $this->getModelEntity()->getOrder()->getCustomer();

            if ($customer) {
                $hasPseudoPrice = $this->container->get('db')->fetchOne("SELECT sap.pseudoprice FROM s_articles_prices sap LEFT JOIN s_articles_details sad ON sap.articleDetailsID = sad.id WHERE sad.ordernumber = ? AND sap.pricegroup = ? AND sap.pseudoprice > 0", [$product['articleNumber'], $customer->getGroupKey()]);

                if ($hasPseudoPrice) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array $items
     * @param Voucher $voucher
     * @param $vouchersData
     * @return int
     */
    public function getBasketTotal($items, Voucher $voucher, $vouchersData)
    {
        $totalAmount = 0;

        foreach ($items as $product) {
            if ($product['quantity'] == 0 || $product['legacy']['originalPrice'] <= 0) {
                continue;
            }

            if ($this->isProductBlockedForVoucher($product, $voucher, $vouchersData)) {
                continue;
            }

            $totalAmount += $product['legacy']['originalPriceAmount'];
        }

        return $totalAmount;
    }

    /**
     * calculate the voucher discount for current article price for a absolute value coupon
     *
     * @param float $price
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
        $orderLineDiscount = $discount * $evaluateOrderLine;
        $this->logDebug('orderSyncMapping::calculateDiscountForAbsoluteVoucher::orderLineDiscount ' . $orderLineDiscount);

        return round($orderLineDiscount, 4, PHP_ROUND_HALF_UP);
    }

    /**
     * calculate the voucher discount for current article price for a percentage coupon
     *
     * @param float $price
     * @param float $discount
     * @return float
     */
    protected function calculateDiscountForPercentVoucher($price, $discount)
    {
        return round(($price / 100 * $discount), 4, PHP_ROUND_HALF_UP);
    }

    /**
     * Build order voucher data.
     *
     * @return array
     * @throws NonUniqueResultException
     */
    protected function buildVouchersData()
    {
        $voucherData = [];

        foreach ($this->getModelEntity()->getOrder()->getDetails() as $currentDetail) {
            if (($currentDetail->getMode() == 4 || $currentDetail->getMode() == 3) && $currentDetail->getPrice() < 0) {
                $voucherData[] = [
                    'code'               => $currentDetail->getArticleName(),
                    'discount'           => abs(round($currentDetail->getPrice(), 4)),
                    'discountPercentage' => 0,
                    'isMoneyVoucher'     => false,
                ];
            }
        }

        foreach ($this->voucherCollection as $currentVoucher) {
            $voucherPercentage = 0.00;
            $voucherDiscount   = $currentVoucher->getValue();

            if ((bool)$currentVoucher->getPercental()) {
                $voucherPercentage = round($currentVoucher->getValue(), 4);

                foreach ($this->getModelEntity()->getOrder()->getDetails() as $currentDetail) {
                    if ($currentDetail->getMode() == 2 && $currentDetail->getArticleNumber() == $currentVoucher->getOrderCode()) {
                        $voucherDiscount = abs($currentDetail->getPrice());
                        break;
                    }
                }
            }

            if ($currentVoucher->getModus() == 1) {
                $voucherCode = $currentVoucher->getOrderCode();
            } else {
                $voucherCode = $currentVoucher->getVoucherCode();
            }

            $voucherData[] = [
                'code'               => $voucherCode,
                'discount'           => round($voucherDiscount, 4),
                'discountPercentage' => $voucherPercentage,
                'isMoneyVoucher'     => false,
            ];
        }

        foreach ($this->moneyVoucherCollection as $currentMoneyVoucher) {
            $voucherData[] = $currentMoneyVoucher;
        }

        return $voucherData;
    }
}
