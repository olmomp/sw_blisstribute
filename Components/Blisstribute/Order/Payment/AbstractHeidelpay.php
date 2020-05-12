<?php

require_once __DIR__ . '/AbstractExternalPayment.php';

/**
 * heidelpay abstract class
 *
 * @author    Florian Ressel
 * @package   Shopware\Components\Blisstribute\Order\Payment
 * @copyright Copyright (c) 2017
 * @since     1.0.0
 */
class Shopware_Components_Blisstribute_Order_Payment_AbstractHeidelpay
    extends Shopware_Components_Blisstribute_Order_Payment_AbstractExternalPayment
{
    /**
     * @inheritdoc
     */
    protected function checkPaymentStatus()
    {
        $status = parent::checkPaymentStatus();

        if (trim($this->order->getTransactionId()) == '') {
            throw new Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException('no card alias id given');
        }

        if (trim($this->order->getTemporaryId()) == '') {
            throw new Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException('no token given');
        }

        return $status;
    }

    /**
     * @inheritdoc
     */
    protected function getAdditionalPaymentInformation()
    {
        return array(
            'token' => trim($this->order->getTemporaryId()),
            'tokenReference' => trim($this->order->getTransactionId()),
        );
    }
}
