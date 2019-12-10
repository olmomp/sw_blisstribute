<?php

require_once __DIR__ . '/Abstract.php';

/**
 * abstract class for external payments
 *
 * @author    Julian Engler
 * @package   Shopware\Components\Blisstribute\Order\Payment
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
abstract class Shopware_Components_Blisstribute_Order_Payment_AbstractExternalPayment
    extends Shopware_Components_Blisstribute_Order_Payment_Abstract
{
    /**
     * @inheritdoc
     */
    protected function checkPaymentStatus()
    {
        $status = parent::checkPaymentStatus();

        if (trim($this->order->getTransactionId()) == '') {
            throw new Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException('no transaction id given');
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
