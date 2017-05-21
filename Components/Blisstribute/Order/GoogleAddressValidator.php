<?php

use Shopware\Models\Order\Order;
use \Shopware\CustomModels\Blisstribute\BlisstributeOrder;

/**
 * blisstribute order address validation by google maps service
 *
 * @author    Roman Robel
 * @package   Shopware\Components\Blisstribute\Order
 * @copyright Copyright (c) 2017
 * @since     1.0.0
 */
class Shopware_Components_Blisstribute_Order_GoogleAddressValidator
{
    use Shopware_Components_Blisstribute_Domain_LoggerTrait;

    public function __construct()
    {

    }

    private function _isPackingStation(\Shopware\Models\Order\Billing $billingAddress, \Shopware\Models\Order\Shipping $shippingAddress)
    {
        $blackList = array(
            'pack', 'station', 'packstation', 'packing', 'packing station', 'packingstation'
        );

        foreach ($blackList as $currentBlackListItem) {
            if (preg_match('/' . $currentBlackListItem . '/i', $billingAddress->getStreet())) {
                $this->logDebug('found packing station in billing address ' . $billingAddress->getStreet());
                return true;
            }

            if (preg_match('/' . $currentBlackListItem . '/i', $billingAddress->getAdditionalAddressLine1())) {
                $this->logDebug('found packing station in billing address address line 1' . $billingAddress->getAdditionalAddressLine1());
                return true;
            }

            if (preg_match('/' . $currentBlackListItem . '/i', $billingAddress->getAdditionalAddressLine2())) {
                $this->logDebug('found packing station in billing address address line 2' . $billingAddress->getAdditionalAddressLine2());
                return true;
            }

            if (preg_match('/' . $currentBlackListItem . '/i', $shippingAddress->getStreet())) {
                $this->logDebug('found packing station in delivery address ' . $shippingAddress->getStreet());
                return true;
            }

            if (preg_match('/' . $currentBlackListItem . '/i', $shippingAddress->getAdditionalAddressLine1())) {
                $this->logDebug('found packing station in delivery address address line 1' . $shippingAddress->getAdditionalAddressLine1());
                return true;
            }

            if (preg_match('/' . $currentBlackListItem . '/i', $shippingAddress->getAdditionalAddressLine2())) {
                $this->logDebug('found packing station in delivery address address line 2' . $shippingAddress->getAdditionalAddressLine2());
                return true;
            }
        }

        return false;
    }

    /**
     * @param BlisstributeOrder $blisstributeOrder
     * @param mixed $config
     *
     * @return bool
     */
    public function validateAddress(BlisstributeOrder $blisstributeOrder, $config)
    {
        $this->logInfo('validating address on order ' . $blisstributeOrder->getOrder()->getNumber());
        $container = Shopware()->Container();
        $models = $container->get('models');
        $order = $blisstributeOrder->getOrder();

        $mapping = [
            'route' => 'streetName',
            'street_number' => 'streetNumber',
            'locality' => 'setCity',
            'postal_code' => 'setZipCode'
        ];

        $billing = $order->getBilling();
        $shipping = $order->getShipping();

        if ($this->_isPackingStation($billing, $shipping)) {
            return false;
        }

        $customerAddress = $shipping->getStreet() . ',' . $shipping->getZipCode() . ',' . $shipping->getCity() . ',' . $order->getShipping()->getCountry()->getName();
        $customerAddress = rawurlencode($customerAddress);

        $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . $customerAddress ."&key=" . $config->get('googleMapsKey');

        $codeResult = file_get_contents($url);
        $decodedResult = json_decode($codeResult, true);

        if ($decodedResult['status'] == 'OK') {
            $street = array();
            $data = array();

            $forUpdate = false;
            $googleResult = $decodedResult['results'][0]['address_components'];
            foreach ($googleResult as $k => $address) {
                foreach ($address['types'] as $type) {
                    if (in_array($type, array_keys($mapping))) {
                        $forUpdate = true;
                        if (method_exists($shipping, $mapping[$type])) {
                            $data[$mapping[$type]] = $googleResult[$k]['long_name'];
                        } elseif (in_array($mapping[$type], array('streetNumber', 'streetName'))) {
                            $street[$mapping[$type]] = $googleResult[$k]['long_name'];
                        }
                    }
                }
            }

            if ($forUpdate) {
                foreach ($data as $key => $val) {
                    $shipping->{$key}($val);

                    if ($billing->getId() == $shipping->getId()) {
                        $billing->{$key}($val);
                    }
                }

                if ($street) {
                    $shipping->setStreet(implode(array_reverse($street), ' '));

                    if ($billing->getId() == $shipping->getId()) {
                        $billing->setStreet(implode(array_reverse($street), ' '));
                    }
                }

                $models->persist($billing);
                $models->persist($shipping);
                $models->flush();
            }

            return true;
        }

        if (!$config->get('transferOrders')) {
            $hint = 'No address verification possible';

            $blisstributeOrder
                ->setStatus(\Shopware\CustomModels\Blisstribute\BlisstributeOrder::EXPORT_STATUS_VALIDATION_ERROR)
                ->setErrorComment($hint)
                ->setTries(0);

            $models->persist($blisstributeOrder);
            $models->flush();
        }

        return false;
    }


}