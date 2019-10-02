<?php

require_once __DIR__ . '/AbstractExternalPayment.php';

/**
 * paypal payment implementation
 *
 * @author    Julian Engler
 * @package   Shopware\Components\Blisstribute\Order\Payment
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class Shopware_Components_Blisstribute_Order_Payment_PayPal
    extends Shopware_Components_Blisstribute_Order_Payment_AbstractExternalPayment
{
    /**
     * @inheritdoc
     */
    protected $code = 'paypal';

    /**
     * @inheritdoc
     */
    protected function checkPaymentStatus()
    {
        $status = parent::checkPaymentStatus();

        if (trim($this->order->getTransactionId()) == '') {
            throw new Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException('no transaction id given');
        }

        if (strpos($this->order->getTransactionId(), 'EC-') !== false) {
            throw new Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException('the transaction id is temporary, wait for completed transaction');
        }

        if (strpos($this->order->getTransactionId(), 'PAYID-') !== false) {
            throw new Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException('the transaction id is temporary, wait for completed transaction');
        }

        return $status;
    }

    /**
     * @inheritdoc
     */
    protected function getAdditionalPaymentInformation()
    {
        return array(
            'token' => $this->order->getTransactionId(),
        );
    }
}
