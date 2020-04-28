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
class Shopware_Components_Blisstribute_Order_Payment_PayOneELV
    extends Shopware_Components_Blisstribute_Order_Payment_AbstractExternalPayment
{
    /**
     * @inheritdoc
     */
    protected $code = 'payoneELV';

    /**
     * @inheritdoc
     */
    protected function getAdditionalPaymentInformation()
    {
        $orderAttribute = $this->order->getAttribute();
        $sequenceNumber = 0;
        if (method_exists($orderAttribute, 'getMoptPayoneSequencenumber')) {
            if (trim($orderAttribute->getMoptPayoneSequencenumber()) == '' || trim($orderAttribute->getMoptPayoneSequencenumber()) == '') {
                $sequenceNumber = $orderAttribute->getMoptPayoneSequencenumber();
            }
        }

        return array(
            'token' => $this->order->getTransactionId(),
            'tokenReference' => $sequenceNumber,
        );
    }
}
