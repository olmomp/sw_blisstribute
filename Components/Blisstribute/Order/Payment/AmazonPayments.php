<?php

require_once __DIR__ . '/AbstractExternalPayment.php';

/**
 * amazonpayments payment implementation
 *
 * @author    Julian Engler
 * @package   Shopware\Components\Blisstribute\Order\Payment
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class Shopware_Components_Blisstribute_Order_Payment_AmazonPayments
    extends Shopware_Components_Blisstribute_Order_Payment_AbstractExternalPayment
{
    /**
     * @inheritdoc
     */
    protected $code = 'amazonPayments';

    /**
     * @inheritdoc
     */
    protected function getAdditionalPaymentInformation()
    {
        $orderAttribute = $this->order->getAttribute();

        $authorizationId = trim($orderAttribute->getBestitAmazonAuthorizationId());

        if ($authorizationId == '' || is_null($authorizationId)) {
            throw new Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException(
                'no authorization id given'
            );
        }

        return array(
            'resToken' => $authorizationId,
            'cardAlias' => trim($this->order->getTransactionId()),
        );
    }
}
