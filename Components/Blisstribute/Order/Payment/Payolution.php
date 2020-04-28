<?php

require_once __DIR__ . '/AbstractExternalPayment.php';

/**
 * payolution bill payment implementation
 *
 * @author    Julian Engler
 * @package   Shopware\Components\Blisstribute\Order\Payment
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class Shopware_Components_Blisstribute_Order_Payment_Payolution
    extends Shopware_Components_Blisstribute_Order_Payment_AbstractExternalPayment
{
    /**
     * @inheritdoc
     */
    protected $code = 'payolution';

    /**
     * @inheritdoc
     */
    protected function checkPaymentStatus()
    {
        $status = parent::checkPaymentStatus();

        $orderAttribute = $this->order->getAttribute();
        if (trim($orderAttribute->getPayolutionUniqueId()) == '') {
            throw new Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException(
                'no unique id given'
            );
        }

        if (trim($orderAttribute->getPayolutionPaymentReferenceId()) == '') {
            throw new Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException(
                'no reference id given'
            );
        }

        return $status;
    }

    /**
     * @inheritdoc
     */
    protected function getAdditionalPaymentInformation()
    {
        $orderAttribute = $this->order->getAttribute();
        if (trim($orderAttribute->getPayolutionUniqueId()) == '' || trim($orderAttribute->getPayolutionPaymentReferenceId()) == '') {
            throw new Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException(
                'no payolution unique id or reference id given'
            );
        }

        return array(
            'token' => trim($orderAttribute->getPayolutionUniqueId()),
            'tokenReference' => trim($orderAttribute->getPayolutionPaymentReferenceId()),
        );
    }
}
