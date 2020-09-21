<?php

require_once __DIR__ . '/AbstractExternalPayment.php';

/**
 * klarna payment implementation
 *
 * @author    Julian Engler
 * @package   Shopware\Components\Blisstribute\Order\Payment
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class Shopware_Components_Blisstribute_Order_Payment_RatePay
    extends Shopware_Components_Blisstribute_Order_Payment_AbstractExternalPayment
{
    /**
     * @inheritdoc
     */
    protected $code = 'ratepay';

    /**
     * @inheritdoc
     */
    protected function checkPaymentStatus()
    {
        $status = parent::checkPaymentStatus();

        $orderAttribute = $this->order->getAttribute();
        if (trim($orderAttribute->getAttribute6()) == '') {
            throw new Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException(
                'no transaction id given'
            );
        }

        if (trim($orderAttribute->getAttribute5()) == '') {
            throw new Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException(
                'no payment narrative given'
            );
        }

        return $status;
    }

    /**
     * @inheritdoc
     */
    protected function getAdditionalPaymentInformation()
    {
        $orderAttribute = $this->order->getAttribute();
        if (trim($orderAttribute->getAttribute5()) == '' || trim($orderAttribute->getAttribute6()) == '') {
            throw new Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException(
                'no transaction id or payment narrative given'
            );
        }

        return array(
            'token' => trim($orderAttribute->getAttribute6()),
            'tokenReference' => trim($orderAttribute->getAttribute5()),
        );
    }
}
