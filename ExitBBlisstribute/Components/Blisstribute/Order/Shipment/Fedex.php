<?php

require_once __DIR__ . '/Abstract.php';

/**
 * fedex shipment mapping class
 *
 * @author    Julian Engler
 * @package   Shopware\Components\Blisstribute\Order\Shipment\Fedex
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class Shopware_Components_Blisstribute_Order_Shipment_Fedex
    extends Shopware_Components_Blisstribute_Order_Shipment_Abstract
{
    /**
     * @inheritdoc
     */
    protected $code = 'FEDEX';
}
