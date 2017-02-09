<?php

require_once __DIR__ . '/AbstractExternalPayment.php';
require_once __DIR__ . '/Payolution.php';

/**
 * payolution installment payment implementation
 *
 * @author    Julian Engler
 * @package   Shopware\Components\Blisstribute\Order\Payment
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class Shopware_Components_Blisstribute_Order_Payment_PayolutionInstallment
    extends Shopware_Components_Blisstribute_Order_Payment_Payolution
{
    /**
     * @inheritdoc
     */
    protected $code = 'payolutionInstallment';

}
