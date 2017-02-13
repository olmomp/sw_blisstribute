<?php

require_once __DIR__ . '/Abstract.php';

/**
 * self collector cash ec payment implementation
 *
 * @author    Julian Engler
 * @package   Shopware\Components\Blisstribute\Order\Payment
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class Shopware_Components_Blisstribute_Order_Payment_SelfcollectorCashEc
    extends Shopware_Components_Blisstribute_Order_Payment_Abstract
{
    /**
     * @inheritdoc
     */
    protected $code = 'cash';
}
