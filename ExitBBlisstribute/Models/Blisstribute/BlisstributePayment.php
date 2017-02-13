<?php

namespace Shopware\CustomModels\Blisstribute;

use Doctrine\ORM\Mapping as ORM;
use Shopware\Models\Payment\Payment;
use Shopware\Components\Model\ModelEntity;

/**
 * blisstribute shipment mapping entity
 *
 * @author    Julian Engler
 * @package   BlisstributePayment
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 *
 * @ORM\Entity(repositoryClass="Shopware\CustomModels\Blisstribute\BlisstributePaymentRepository")
 * @ORM\Table(name="s_plugin_blisstribute_payment")
 */
class BlisstributePayment extends ModelEntity
{
    /**@+
     * payment mapping constants
     *
     * @var string
     */
    const PAYMENT_NAME_PRE_PAYMENT = 'pre_payment';
    const PAYMENT_NAME_DEBIT_ADVICE = 'debit_advice';
    const PAYMENT_NAME_CASH_ON_DELIVERY = 'cash_on_delivery';
    const PAYMENT_NAME_PAYPAL = 'paypal';
    const PAYMENT_BILL = 'bill';
    const PAYMENT_NAME_PAYOLUTION = 'payolution';
    const PAYMENT_NAME_PAYOLUTION_INSTALLMENT = 'payolution_installment';
    const PAYMENT_NAME_SOFORT = 'sofort';
    const PAYMENT_NAME_HEIDELPAY_CREDIT_CARD = 'heidelpay_credit_card';
    const PAYMENT_NAME_MARKETPLACE = 'marketplace';
    const PAYMENT_NAME_SELFCOLLECTOR_CASH = 'selfcollector_cash';
    const PAYMENT_NAME_SELFCOLLECTOR_CASH_EC = 'selfcollector_cash_ec';
    const PAYMENT_NAME_SELFCOLLECTOR_CASH_CREDIT_CARD = 'selfcollector_cash_credit_card';
    const PAYMENT_NAME_VR_PAY_CC = 'vrPayCC';
    const PAYMENT_NAME_AMAZON_PAYMENTS = 'AmazonPayments';
    const PAYMENT_NAME_BILLSAFE = 'billsafe';
    /**@-*/

    /**
     * entity identifier
     *
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * shopware payment
     *
     * @var Payment
     *
     * @ORM\OneToOne(targetEntity="Shopware\Models\Payment\Payment")
     * @ORM\JoinColumn(name="s_core_paymentmeans_id", referencedColumnName="id")
     */
    private $payment;

    /**
     * blisstribute payment code
     *
     * @var string
     *
     * @ORM\Column(name="mapping_class_name", type="string", nullable=true)
     */
    private $className;

    /**
     * indicates if order will be transfered as payed
     *
     * @var bool
     *
     * @ORM\Column(name="flag_payed", type="boolean", nullable=false, )
     */
    private $isPayed;

    /**
     * set entity identifier
     *
     * @param int $id
     *
     * @return BlisstributePayment
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * return entity identifier
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * set shopware payment
     *
     * @param Payment $payment
     *
     * @return BlisstributePayment
     */
    public function setPayment($payment)
    {
        $this->payment = $payment;
        return $this;
    }

    /**
     * return shopware payment
     *
     * @return Payment
     */
    public function getPayment()
    {
        return $this->payment;
    }

    /**
     * set blisstribute payment mapping class name
     *
     * @param string $className
     *
     * @return BlisstributePayment
     */
    public function setClassName($className)
    {
        $this->className = $className;
        return $this;
    }

    /**
     * return blisstribute payment mapping class name
     *
     * @return string
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * set if order will be transferred as payed
     *
     * @param boolean $isPayed
     *
     * @return BlisstributePayment
     */
    public function setIsPayed($isPayed)
    {
        $this->isPayed = $isPayed;
        return $this;
    }

    /**
     * return if order will be transferred as payed
     *
     * @return boolean
     */
    public function getIsPayed()
    {
        return $this->isPayed;
    }
}
