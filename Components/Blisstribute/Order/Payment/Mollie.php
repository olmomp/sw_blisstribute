<?php

require_once __DIR__ . '/AbstractExternalPayment.php';

class Shopware_Components_Blisstribute_Order_Payment_Mollie
    extends Shopware_Components_Blisstribute_Order_Payment_AbstractExternalPayment
{
    /**
     * @inheritdoc
     */
    protected $code = 'mollie';

    /**
     * @inheritdoc
     */
    protected function checkPaymentStatus()
    {
        $status = parent::checkPaymentStatus();
        $transactionToken = trim($this->order->getTransactionId());
        if (!preg_match('/^(tr|ord)_[A-Za-z0-9]{6,10}$/', $transactionToken)) {
            throw new Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException('invalid transaction id given');
        }

        return $status;
    }
}
