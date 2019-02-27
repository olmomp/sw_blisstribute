<?php

require_once __DIR__ . '/AbstractExternalPayment.php';

/**
 * klarna payment over rest implementation
 */
class Shopware_Components_Blisstribute_Order_Payment_KlarnaRest
    extends Shopware_Components_Blisstribute_Order_Payment_AbstractExternalPayment
{
    /**
     * @inheritdoc
     */
    protected $code = 'klarnaRest';
}
