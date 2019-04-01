<?php

namespace Shopware\Components\Api\Resource;

use Doctrine\ORM\Query\Expr\Join;
use Shopware\Components\Api\Exception as ApiException;
use Shopware\CustomModels\Blisstribute\BlisstributeShippingRequest;
use Shopware\CustomModels\Blisstribute\BlisstributeShippingRequestItems;

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
    public function getStateIdFromVhsId($params)
    {
        $id = (int)$params["orderStatusId"];

        if (in_array($id,array(10,15))) {
            $state = 0;
        } elseif (in_array($id, array(20, 21))) {
            $state = 1;
        } elseif (in_array($id, array(25, 26, 30, 31))) {
            $state = 5;
        } elseif (in_array($id, array(35))) {
            $state = 3;
        } elseif (in_array($id, array(40))) {
            $state = 2;
        } elseif (in_array($id, array(50))) {
            $state = 4;
        } elseif (in_array($id,array(60,61,62))) {
            $state = 4;
        } else {
            $state = 8;
        }

        $params["orderStatusId"] = $state;

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

        $params = $this->getStateIdFromVhsId($params);

        /** @var $order \Shopware\Models\Order\Order */
        $filters = array(array('property' => 'orders.number','expression' => '=','value' => $orderNumber));
        $builder = $this->getOrderRepository()->getOrdersQueryBuilder($filters);
        $order = $builder->getQuery()->getOneOrNullResult(self::HYDRATE_OBJECT);

        if ($order == null) {
            throw new ApiException\NotFoundException('order by id ' . $orderNumber . ' not found');
        }

        $this->prepareOrderDetails($params, $orderNumber);

//        $shippingRequest = new BlisstributeShippingRequest();
//        $shippingRequest->setNumber('test')
//            ->setCarrierCode('DHL')
//            ->setTrackingCode('');
//
//        foreach (array() as $test) {
//            $shippingRequestItem = new BlisstributeShippingRequestItems();
//            $shippingRequestItem->setOrderDetail($detail)
//                ->setQuantityReturned(0);
//
//            $shippingRequest->addShippingRequestItem($shippingRequestItem);
//        }
//
//        $this->getManager()->persist($shippingRequest);

        $statusId = (int)$params['orderStatusId'];
        $status = Shopware()->Models()->getRepository('Shopware\Models\Order\Status')->findOneBy(
            array(
                'id' => $statusId,
                'group' => 'state'
            )
        );

        if (empty($status)) {
            throw new ApiException\NotFoundException("OrderStatus by id " . $statusId . " not found");
        }

        $order->setOrderStatus($status);
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
            $articleNumber = $detail['articleNumber'];
            if (empty($articleNumber)) {
                throw new ApiException\CustomValidationException(
                    'You need to specify the articleNumber of the order positions you want to modify'
                );
            }

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