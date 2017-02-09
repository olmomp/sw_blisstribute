<?php

require_once __DIR__ . '/AbstractExternalPayment.php';

/**
 * sofortuerberweisung payment implementation
 *
 * @author    Julian Engler
 * @package   Shopware\Components\Blisstribute\Order\Payment
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class Shopware_Components_Blisstribute_Order_Payment_Sofort
    extends Shopware_Components_Blisstribute_Order_Payment_AbstractExternalPayment
{
    /**
     * @inheritdoc
     */
    protected $code = 'sofort';

    protected function checkPaymentStatus()
    {
        $status = parent::checkPaymentStatus();
        if ($this->order->getPaymentStatus()->getId() != 12) {
            throw new Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException(
                'payment status not cleared::manual review necessary::current status ' . $this->order->getPaymentStatus()->getId()
            );
        }

        return $status;
    }
}
