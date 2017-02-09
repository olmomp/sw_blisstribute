<?php

require_once __DIR__ . '/AbstractExternalPayment.php';

/**
 * Billsafe Integration
 *
 * Class Shopware_Components_Blisstribute_Order_Payment_Billsafe
 *
 * @author    Michael Möhlihs
 */
class Shopware_Components_Blisstribute_Order_Payment_Billsafe
    extends Shopware_Components_Blisstribute_Order_Payment_AbstractExternalPayment
{
    /**
     * @inheritdoc
     */
    protected $code = 'billSafe';
}
