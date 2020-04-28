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

        $captureNow = Shopware()->Config()->get('captureNow', false);

        if ((bool)$captureNow) {
            $resToken = trim($orderAttribute->getBestitAmazonCaptureId());
        } else {
            $resToken = trim($orderAttribute->getBestitAmazonAuthorizationId());
        }

        if ($resToken == '' || is_null($resToken)) {
            throw new Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException(
                'no token given'
            );
        }

        return array(
            'token' => $resToken,
            'tokenReference' => trim($this->order->getTransactionId()),
        );
    }
}
