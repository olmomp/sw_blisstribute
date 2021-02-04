<?php

namespace Shopware\Components\Api\Resource;

use Doctrine\ORM\Query\Expr\Join;
use Shopware\Components\Api\Exception as ApiException;
use Shopware\CustomModels\Blisstribute\BlisstributeShippingRequest;
use Shopware\CustomModels\Blisstribute\BlisstributeShippingRequestItems;
use Shopware\Models\Order\Status;

/**
 * blisstribute custom api order extension resource
 *
 * @author    Conrad Gülzow
 * @package   Shopware\Components\Api\Resource
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class Btorder extends Resource
{
    /**
     * @return \Shopware\Models\Order\Repository
     */
    public function getOrderRepository()
    {
        return $this->getManager()->getRepository('Shopware\Models\Order\Order');
    }

    /**
     * @return \Shopware\Components\Model\ModelRepository
     */
    public function getOrderDetailRepository()
    {
        return $this->getManager()->getRepository('Shopware\Models\Order\Detail');
    }

    /**
     * @param $params array
     *
     * @return array
     *
     * @throws \Shopware\Components\Api\Exception\NotFoundException
     * @throws \Shopware\Components\Api\Exception\ParameterMissingException
     */
    public function getShopwareOrderStatusFromVhsStatus($params)
    {
        $params['paymentStatusId'] = Status::PAYMENT_STATE_OPEN;
        if ($params['partiallyPayed']) {
            $params['paymentStatusId'] = Status::PAYMENT_STATE_PARTIALLY_PAID;
        }

        if ($params['completelyPayed']) {
            $params['paymentStatusId'] = Status::PAYMENT_STATE_COMPLETELY_PAID;
        }

        $vhsOrderStatusId = (int)$params["orderStatusId"];
        if (in_array($vhsOrderStatusId,array(10,15))) {
            $swOrderStatusId = Status::ORDER_STATE_OPEN;
        } elseif (in_array($vhsOrderStatusId, array(20, 21))) {
            $swOrderStatusId = Status::ORDER_STATE_IN_PROCESS;
        } elseif (in_array($vhsOrderStatusId, array(25, 26, 30, 31))) {
            $swOrderStatusId = Status::ORDER_STATE_READY_FOR_DELIVERY;
        } elseif (in_array($vhsOrderStatusId, array(35))) {
            $swOrderStatusId = Status::ORDER_STATE_PARTIALLY_COMPLETED;
        } elseif (in_array($vhsOrderStatusId, array(40))) {
            $swOrderStatusId = Status::ORDER_STATE_COMPLETED;
        } elseif (in_array($vhsOrderStatusId, array(50))) {
            $swOrderStatusId = Status::ORDER_STATE_CANCELLED;
            $params['paymentStatusId'] = Status::PAYMENT_STATE_RE_CREDITING;
        } elseif (in_array($vhsOrderStatusId,array(60,61,62))) {
            $swOrderStatusId = Status::ORDER_STATE_CANCELLED_REJECTED;
            $params['paymentStatusId'] = Status::PAYMENT_STATE_RE_CREDITING;
        } else {
            $swOrderStatusId = Status::ORDER_STATE_CLARIFICATION_REQUIRED;
        }

        $params["orderStatusId"] = $swOrderStatusId;

        return $params;
    }

    /**
     * @param int $orderNumber
     * @param array $params
     *
     * @return \Shopware\Models\Order\Order
     *
     * @throws \Shopware\Components\Api\Exception\ValidationException
     * @throws \Shopware\Components\Api\Exception\NotFoundException
     * @throws \Shopware\Components\Api\Exception\ParameterMissingException
     */
    public function update($orderNumber, array $params)
    {
        $this->checkPrivilege('update');

        if (trim($orderNumber) == '') {
            throw new ApiException\ParameterMissingException();
        }

        $params = $this->getShopwareOrderStatusFromVhsStatus($params);

        /** @var $order \Shopware\Models\Order\Order */
        $filters = array(array('property' => 'orders.number','expression' => '=','value' => $orderNumber));
        $builder = $this->getOrderRepository()->getOrdersQueryBuilder($filters);
        $order = $builder->getQuery()->getOneOrNullResult(self::HYDRATE_OBJECT);

        if ($order == null) {
            throw new ApiException\NotFoundException('order by id ' . $orderNumber . ' not found');
        }

        $this->prepareOrderDetails($params, $orderNumber);

        $orderStatusId = (int)$params['orderStatusId'];
        /** @var Status $orderStatus */
        $orderStatus = Shopware()->Models()->getRepository('Shopware\Models\Order\Status')->findOneBy(array('id' => $orderStatusId, 'group' => 'state'));
        if (empty($orderStatus)) {
            throw new ApiException\NotFoundException('OrderStatus by id ' . $orderStatusId . ' not found');
        }
        $order->setOrderStatus($orderStatus);

        $paymentStatusId = (int)$params['paymentStatusId'];
        /** @var Status $paymentStatus */
        $paymentStatus = Shopware()->Models()->getRepository('Shopware\Models\Order\Status')->findOneBy(array('id' => $paymentStatusId, 'group' => 'payment'));
        if (empty($paymentStatus)) {
            throw new ApiException\NotFoundException('PaymentStatus by id ' . $paymentStatusId . ' not found');
        }
        $order->setPaymentStatus($paymentStatus);

        $order->setTrackingCode(trim($params['trackingCode']));

        $violations = $this->getManager()->validate($order);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->getManager()->persist($order);
        $this->getManager()->flush();

        return $order;
    }

    /**
     * Helper method to prepare the order data
     *
     * @param array $params
     * @param $orderNumber
     *
     * @return void
     * @throws \Shopware\Components\Api\Exception\NotFoundException| ApiException\CustomValidationException
     */
    public function prepareOrderDetails(array $params, $orderNumber)
    {
        $details = $params['details'];
        foreach ($details as $detail) {
            if (!empty($detail['externalKey'])) {
                $detailModels = $this->getOrderDetailRepository()
                    ->createQueryBuilder('details')
                    ->where('details.number = :orderNumber')
                    ->andWhere('details.id = :externalKey') // fyi: could be the old counter values used and transmitted to bliss in the version
                    // before today, but it should not lead to any problems as we use the order number also, so there should be no false matches
                    ->setParameters(
                        array(
                            'orderNumber' => $orderNumber,
                            'externalKey'      => $detail['externalKey'],
                        )
                    )
                    ->getQuery()
                    ->getResult();
            }

            if (empty($detailModels)) {
                $articleNumber = $detail['articleNumber'];
                if (empty($articleNumber)) {
                    throw new ApiException\CustomValidationException(
                        'You need to specify the articleNumber of the order positions you want to modify'
                    );
                }
            }

            if (empty($detailModels)) {
                $detailModels = $this->getOrderDetailRepository()
                    ->createQueryBuilder('details')
                    ->innerJoin('Shopware\Models\Attribute\Article', 'attributes', Join::WITH, 'attributes.articleId = details.articleId')
                    ->where('details.number = :orderNumber')
                    ->andWhere('attributes.blisstributeVhsNumber = :vhsArticleNumber')
                    ->setParameters(
                        array(
                            'orderNumber'      => $orderNumber,
                            'vhsArticleNumber' => $detail['blisstributeVhsNumber'],
                            // todo: ich denke dass sollte so sein:
                            // 'vhsArticleNumber' => $detail['vhsArticleNumber'],
                        )
                    )
                    ->getQuery()
                    ->getResult();
            }

            if (empty($detailModels)) {
                /** @var \Shopware\Models\Order\Detail $detailModel */
                $detailModels = $this->getOrderDetailRepository()
                    ->createQueryBuilder('details')
                    ->where('details.number = :orderNumber')
                    ->andWhere('details.articleNumber = :articleNumber')
                    ->setParameters(array(
                        'orderNumber' => $orderNumber,
                        'articleNumber' => $articleNumber
                    ))
                    ->getQuery()
                    ->getResult();

                if (empty($detailModels)) {
                    throw new ApiException\NotFoundException(
                        "Detail by orderId " . $orderNumber . " and articleNumber " . $articleNumber . " not found"
                    );
                }
            }

            foreach($detailModels as $detailModel) {
                $detailModel->getAttribute()
                    ->setBlisstributeQuantityCanceled($detail['attribute']['blisstributeQuantityCanceled'])
                    ->setBlisstributeQuantityReturned($detail['attribute']['blisstributeQuantityReturned'])
                    ->setBlisstributeQuantityShipped($detail['attribute']['blisstributeQuantityShipped'])
                    ->setBlisstributeDateChanged($detail['attribute']['blisstributeDateChanged']);

                $this->getManager()->persist($detailModel);
            }
        }
    }
}