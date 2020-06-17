<?php

require_once __DIR__ . '/AbstractExternalPayment.php';

class Shopware_Components_Blisstribute_Order_Payment_Mollie
    extends Shopware_Components_Blisstribute_Order_Payment_AbstractExternalPayment
{
    /**
     * @inheritdoc
     */
    protected $code = 'mollie';
}
