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
     * Get Blisstribute shipment mapping database repository.
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
        $orderTotalDeviation = $originalTotal - $newOrderTotal;
        $deviationWatermark = round($this->getConfig()['blisstribute-discount-difference-watermark'], 2);

        if (abs($orderTotalDeviation) > abs($deviationWatermark)) {
            $this->logDebug(sprintf('orderSyncMapping::buildBaseData::amount differs %s to %s', $originalTotal, $newOrderTotal));
            $this->orderData['orderRemark'] .= 'RABATT PRÜFEN! (ORIG ' . $originalTotal .')';
        }

        return $this->orderData;
    }

    /**
     * @inheritdoc
     * @throws NonUniqueResultException
     * @throws Shopware_Components_Blisstribute_Exception_OrderShipmentMappingException
     * @throws Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException
     * @throws Shopware_Components_Blisstribute_Exception_ValidationMappingException
     */
//    protected function buildBaseData()
//    {
//        $this->logDebug('orderSyncMapping::buildBaseData::start');
//        // determine used vouchers
//        $this->determineVoucherDiscount();
//
//        $this->orderData = $this->buildBasicOrderData();
//        $this->orderData['payment'] = $this->buildPaymentData();
//        $this->orderData['advertisingMedium'] = $this->buildAdvertisingMediumData();
//        $this->orderData['billAddressData'] = $this->buildInvoiceAddressData();
//        $this->orderData['deliveryAddressData'] = $this->buildDeliveryAddressData();
//        $this->orderData['orderLines'] = $this->buildArticleData();
//        $this->orderData['orderCoupons'] = $this->buildCouponData();
//
//        $this->logDebug('orderSyncMapping::buildBaseData::done');
//        $this->logDebug('orderSyncMapping::buildBaseData::result:' . json_encode($this->orderData));
//
//        $order = $this->getModelEntity()->getOrder();
//        $originalTotal = round($order->getInvoiceAmount(), 2);
//        $newOrderTotal = round($this->getOrderTotal(), 2);
//        $orderTotalDeviation = $originalTotal - $newOrderTotal;
//        $deviationWatermark = round($this->getConfig()['blisstribute-discount-difference-watermark'], 2);
//
//        if (abs($orderTotalDeviation) > abs($deviationWatermark)) {
//            $this->logDebug(sprintf('orderSyncMapping::buildBaseData::amount differs %s to %s', $originalTotal, $newOrderTotal));
//            $this->orderData['orderRemark'] .= 'RABATT PRÜFEN! (ORIG ' . $originalTotal .')';
//        }
//
//        return $this->orderData;
//    }

    /**
     * @return float
     */
    private function getOrderTotal()
    {
        $orderTotal  = round($this->orderData['payment']['total'], 4);
        $orderTotal += round($this->orderData['shipment']['total'], 4);

        foreach ($this->orderData['items'] as $currentItem) {
            if ($this->orderData['isB2B'] && $this->getConfig()['blisstribute-transfer-b2b-net']) {
                // Convert to price after VAT.
                $orderTotal += round((($currentItem['priceNet'] / $currentItem['quantity']) / 100) * (100 + $currentItem['vatRate']), 4);
            } else {
                $orderTotal += round($currentItem['price'], 4);
            }
        }

        foreach ($this->orderData['vouchers'] as $currentVoucher) {
            if (!$currentVoucher['isMoneyVoucher']) {
                continue;
            }

            $orderTotal -= round($currentVoucher['discount'], 4);
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

        $customerPhone = $customer->getDefaultBillingAddress()->getPhone();
        if (trim($customerPhone) == '') {
            $customerPhone = $this->getAlternativePhoneNumber($order);
        }

        return [
            'externalCustomerNumber' => $customerNumber,
            'externalCustomerEmail' => $customer->getEmail(),
            'externalCustomerPhoneNumber' => $customerPhone,
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
     * @param string $attrName
     * @return string
     */
    private function getAttribute($order, $attrName)
    {
        if (trim($attrName) == '') {
            return null;
        }

        // Build the method name of $fieldNames getter.
        $value = '';
        $method = 'get' . ucfirst($attrName);

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
     * @return string
     *
     * @throws Shopware_Components_Blisstribute_Exception_OrderShipmentMappingException
     * @throws NonUniqueResultException
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
     * Build advertising medium data.
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
        return $orderPayment->getPaymentInformation();
    }

    /**
     * Build invoice address data.
     *
     * @return array
     * @throws Shopware_Components_Blisstribute_Exception_ValidationMappingException
     */
    private function buildInvoiceAddressData()
    {
        $billing = $this->getModelEntity()->getOrder()->getBilling();
        if ($billing == null) {
            throw new Shopware_Components_Blisstribute_Exception_ValidationMappingException(
                'no billing address given for order ' . $this->getModelEntity()->getOrder()->getNumber()
            );
        }

        $salutation = '';
        if ($billing->getSalutation() == 'mr') {
            $salutation = base64_encode('Herr');
        } elseif ($billing->getSalutation() == 'ms') {
            $salutation = base64_encode('Frau');
        }

        $country = $billing->getCountry();
        if ($country == null) {
            throw new Shopware_Components_Blisstribute_Exception_ValidationMappingException('no country given');
        }

        $street = $billing->getStreet();
        $houseNumber = '';
        try {
            $disableAddressSplitting = $this->getConfig()['blisstribute-disable-address-splitting'];
            if (!$disableAddressSplitting) {
                $match = AddressSplitter::splitAddress($street);
                $street = $match['streetName'];
                $houseNumber = $match['houseNumber'];
            }
        } catch (Exception $e) {
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
     * Build delivery address data.
     *
     * @return array
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

        $salutation = '';
        if ($shipping->getSalutation() == 'mr') {
            $salutation = base64_encode('Herr');
        } elseif ($shipping->getSalutation() == 'ms') {
            $salutation = base64_encode('Frau');
        }

        $country = $shipping->getCountry();
        if ($country == null) {
            throw new Shopware_Components_Blisstribute_Exception_ValidationMappingException('no country given');
        }

        $street = $shipping->getStreet();
        $houseNumber = '';
        try {
            $disableAddressSplitting = $this->getConfig()['blisstribute-disable-address-splitting'];
            if (!$disableAddressSplitting) {
                $match = AddressSplitter::splitAddress($street);
                $street = $match['streetName'];
                $houseNumber = $match['houseNumber'];
            }
        } catch (Exception $e) {
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

        $articleDataCollection = $this->applyPromoDiscounts($articleDataCollection, $promotions, $shopwareDiscountsAmount);

        return $articleDataCollection;
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

        if (!empty($article->getMainDetail()->getAttribute()) && trim($article->getMainDetail()->getAttribute()->getBlisstributeArticleShipmentCode()) != '') {
            $configuration[] = array(
                'category_type' => 'shipmentType',
                'category' => trim($article->getMainDetail()->getAttribute()->getBlisstributeArticleShipmentCode())
            );
        }

        if (!empty($article->getMainDetail()->getAttribute()) && trim($article->getMainDetail()->getAttribute()->getBlisstributeArticleAdvertisingMediumCode()) != '') {
            $configuration[] = array(
                'category_type' => 'advertisingMedium',
                'category' => trim($article->getMainDetail()->getAttribute()->getBlisstributeArticleAdvertisingMediumCode())
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


    /**
     * @param $articleDataCollection
     * @param $promotions
     * @param $shopwareDiscountsAmount
     * @return mixed
     * @throws NonUniqueResultException
     */
    public function applyPromoDiscounts($articleDataCollection, $promotions, $shopwareDiscountsAmount)
    {
        // check if Intedia Promotion Recorder plugin is installed
        $intediaPromoPlugin = $this->getPluginRepository()->findOneBy([
            'name' => 'IntediaPromotionRecorder',
            'active' => true
        ]);

        $this->logDebug('apply promo discounts');

        $handledPromotionIds = [];
        $handledVoucherIds = [];
        if ($intediaPromoPlugin) {
            $this->logDebug('apply promo discounts via intedia plugin');
            //1a. handle non-default discounts, most vouchers (Promotion suite etc) by intedia plugin
            $orderDetailIds = array_column($articleDataCollection, 'externalKey');

            $promotionDiscounts = $this->getPromotionDiscountsFromRecorderPlugin($orderDetailIds);

            foreach ($articleDataCollection as $key => $articleData) {
                $detailId = $articleData['externalKey'];

                if (isset($promotionDiscounts[$detailId])) {

                    foreach ($promotionDiscounts[$detailId] as $discount) {

                        if ($discount['promotionId']) {
                            $handledPromotionIds[] = $discount['promotionId']; //remember promotion as handled by plugin
                        }
                        if ($discount['voucherId']) {
                            $handledVoucherIds[] = $discount['voucherId'];
                        }

                        $discountTotal = $discount['promotionValue'];
                        if ($discountTotal > 0) {
                            $quantity = $articleData['quantity'];
                            $newPrice = round($articleData['price'] - $discountTotal, 4);
                            $newPriceSingle = round($articleData['priceAmount'] - ($discountTotal / $quantity), 4);
                            $articleDataCollection[$key]['price'] = $newPrice;
                            $articleDataCollection[$key]['priceAmount'] = $newPriceSingle;
                            $articleDataCollection[$key]['discountTotal'] = $discountTotal;
                        }
                    }
                }
            }

            //1b. split remaining promotions (absolute card discounts etc) to all products, weighted by price
            /** @var Detail $promotion */
            foreach ($promotions as $promotion) {
                $promotionId = $promotion->getAttribute()->getIntediaPromotionRecorderPromotionId();
                if (in_array($promotionId, $handledPromotionIds)) { //handled above
                    continue;
                }
                $articleDataCollection = $this->applyShopwareDiscount($articleDataCollection, $promotion->getPrice());
            }

        }
        else {
            //1c. without plugin: split all promotions to all products, weighted by price
            /** @var Detail $promotion */
            foreach ($promotions as $promotion) {
                $articleDataCollection = $this->applyShopwareDiscount($articleDataCollection, $promotion->getPrice());
            }
        }

        /**
         * 2. split voucher discounts to all allowed products, weighted by price
         * Note: Most vouchers are handled by Intedia Plugin (if present)
         * However: Absolute discount vouchers not specific to certain products/suppliers
         * still need to be split by weight over all articles
         */
        $articleDataCollection = $this->applyVouchers($articleDataCollection, $handledVoucherIds);
        //3. split shopware default discounts to all products, weighted by price (not handled by intedia Plugin)
        $articleDataCollection = $this->applyShopwareDiscount($articleDataCollection, $shopwareDiscountsAmount);

        return $articleDataCollection;
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

    /**
     * @param $articleDataCollection
     * @param array $excludedVoucherIds
     * @return mixed
     * @throws NonUniqueResultException
     */
    public function applyVouchers($articleDataCollection, $excludedVoucherIds = [])
    {
        $couponMappingRepository = $this->getCouponMappingRepository();

        $vouchersData = [];

        // check if plugin CoeExcludeProducerOnVoucher is installed
        $coeExcludePlugin = $this->getPluginRepository()->findOneBy([
            'name' => 'CoeExcludeProducerOnVoucher',
            'active' => true
        ]);

        // check if plugin CoeVoucherOnReducedArticle is installed
        $coeReducedPlugin = $this->getPluginRepository()->findOneBy([
            'name' => 'CoeVoucherOnReducedArticle',
            'active' => true
        ]);

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

                $voucherDiscountPerQuantity = round($voucherDiscount / $product['promoQuantity'], 4);

                $product['priceAmount'] -= $voucherDiscountPerQuantity;
                $product['price'] -= $voucherDiscountPerQuantity * $product['promoQuantity'];
                $product['discountTotal'] += $voucherDiscountPerQuantity;
            }
        }

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
     * @param $vouchersData
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
                    'discountPercentage' => false,
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

            $couponMappingRepository = $this->getCouponMappingRepository();
            $couponMapping           = $couponMappingRepository->findByCoupon($currentVoucher->getId());

            $isMoneyVoucher = false;
            if ($couponMapping != null && $couponMapping->getIsMoneyVoucher()) {
                $isMoneyVoucher = true;
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
                'isMoneyVoucher'     => $isMoneyVoucher,
            ];
        }

        return $voucherData;
    }
}
