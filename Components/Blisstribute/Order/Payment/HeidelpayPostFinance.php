<?php

require_once __DIR__ . '/AbstractExternalPayment.php';
require_once __DIR__ . '/AbstractHeidelpay.php';

/**
 * heidelpay postfinance implementation
 *
 * @author    Florian Ressel
 * @package   Shopware\Components\Blisstribute\Order\Payment
 * @copyright Copyright (c) 2017
 * @since     1.0.0
 */
class Shopware_Components_Blisstribute_Order_Payment_HeidelpayPostFinance
    extends Shopware_Components_Blisstribute_Order_Payment_AbstractHeidelpay
{
    /**
     * @inheritdoc
     */
    protected $code = 'heidelpayPostFinance';
}
