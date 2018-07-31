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
        $paymentData = Shopware()->Models()->getRepository('Shopware\Models\Payment\PaymentInstance')->findOneBy(array(
            'orderId' => $this->order->getId()
        ));

        if ($paymentData == null) {
            throw new Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException('no bank data found');
        }

        $bankOwner = $paymentData->getAccountHolder();
        if (trim($bankOwner) == '') {
            $bankOwner = $paymentData->getFirstname() . ' ' . $paymentData->getLastname();
        }

        return array(
            'bankOwner' => $bankOwner,
            'bankName' => $paymentData->getBankName(),
            'iban' => strtoupper($paymentData->getIban()),
            'bic' => strtoupper($paymentData->getBic())
        );
    }
}
