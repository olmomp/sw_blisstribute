<?php

require_once __DIR__ . '/Abstract.php';

/**
 * debit advice payment implementation
 *
 * @author    Julian Engler
 * @package   Shopware\Components\Blisstribute\Order\Payment
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class Shopware_Components_Blisstribute_Order_Payment_DebitAdvice
    extends Shopware_Components_Blisstribute_Order_Payment_Abstract
{
    /**
     * @inheritdoc
     */
    protected $code = 'debitAdvice';

    /**
     * @inheritdoc
     */
    protected function getAdditionalPaymentInformation()
    {
        $customer = $this->order->getCustomer();
        $paymentData = Shopware()->Models()->getRepository('Shopware\Models\Payment\PaymentInstance')->findOneBy(array(
            'customer' => $customer->getId()
        ));

        if ($paymentData == null) {
            throw new Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException('no bank data found');
        }

        return array(
            'bankOwner' => $paymentData->getAccountHolder(),
            'bankName' => $paymentData->getBankName(),
            'iban' => $paymentData->getIban(),
            'bic' => $paymentData->getBic()
        );
    }
}
