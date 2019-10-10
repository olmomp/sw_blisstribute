<?php

require_once __DIR__ . '/../Sync.php';
require_once __DIR__ . '/SoapClient.php';
require_once __DIR__ . '/SyncMapping.php';
require_once __DIR__ . '/../Exception/MappingException.php';
require_once __DIR__ . '/../Exception/TransferException.php';

use Monolog\Logger;
use Shopware\Components\Model\ModelEntity;
use Shopware\CustomModels\Blisstribute\BlisstributeOrder;

/**
 * blisstribute order sync
 *
 * @author    Julian Engler
 * @package   Shopware\Components\Blisstribute\Order\Sync
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class Shopware_Components_Blisstribute_Order_Sync extends Shopware_Components_Blisstribute_Sync
{
    /**
     * @inheritdoc
     */
    protected $taskName = 'order_sync';

    /**
     * @inheritdoc
     */
    protected $logBaseName = 'blisstribute_order_sync';

    /**
     * sync all open orders to blisstribute
     *
     * @return bool
     */
    public function processBatchOrderSync()
    {
        $this->taskName .= '::batch';
        $this->lockTask();

        $startDate = new DateTime();
        $this->logMessage('start batch sync', __FUNCTION__);

        // load orders
        $orderRepository = Shopware()->Models()->getRepository('Shopware\CustomModels\Blisstribute\BlisstributeOrder');
        $orderCollection = $orderRepository->findTransferableOrders($startDate);
        foreach ($orderCollection as $currentOrder) {
            try {
                $this->checkStatus($currentOrder);
                $this->processOrderSync($currentOrder);
            } catch (Exception $ex) {
                $this->logMessage('export status check failed::' . $ex->getMessage(), __FUNCTION__, Logger::ERROR);
                continue;
            }
        }

        $this->modelManager->flush();
        $this->unlockTask();

        $this->logMessage('end batch sync', __FUNCTION__);
        return true;
    }

    /**
     * sync single order to blisstribute
     *
     * @param BlisstributeOrder $blisstributeOrder
     * @param bool $force
     *
     * @return bool
     */
    public function processSingleOrderSync(BlisstributeOrder $blisstributeOrder, $force = false)
    {
        try {
            $this->checkStatus($blisstributeOrder);
        } catch (Exception $ex) {
            $this->setLastError($ex->getMessage());

            if (!$force) {
                return false;
            }
        }

        $this->taskName .= '::single::' . $blisstributeOrder->getId();

        try {
            $this->lockTask();
        } catch (Exception $ex) {
            if (!$force) {
                return false;
            }
        }

        $result = $this->processOrderSync($blisstributeOrder);

        $this->unlockTask();

        return $result;
    }

    /**
     * @inheritdoc
     */
    protected function initializeModelMapping(ModelEntity $modelEntity)
    {
        $syncMapping = new Shopware_Components_Blisstribute_Order_SyncMapping();
        $syncMapping->setModelEntity($modelEntity);

        $orderData = $syncMapping->buildMapping();
        return $orderData;
    }

    /**
     * start order sync to blisstribute
     *
     * @param BlisstributeOrder $blisstributeOrder
     *
     * @return bool
     */
    protected function processOrderSync(BlisstributeOrder $blisstributeOrder)
    {
        $result = false;

        $this->logMessage('start sync::blisstribute order id' . $blisstributeOrder->getId(), __FUNCTION__);
        $this->logMessage('start sync::sw order number' . $blisstributeOrder->getOrder()->getNumber(), __FUNCTION__);

        try {
            if ($this->config->get('blisstribute-google-address-validation')) {
                /** @var Shopware_Components_Blisstribute_Order_GoogleAddressValidator $addressValidator */
                $addressValidator = Shopware()->Container()->get('blisstribute.google_address_validator');
                $addressValidatorResponse = $addressValidator->validateAddress($blisstributeOrder, $this->config);

                if (!$addressValidatorResponse && !$this->config->get('blisstribute-transfer-orders')) {
                    throw new Exception('could not validate the order address.');
                }
            }

            Shopware()->Events()->notify('Shopware_Components_Blisstribute_Order_Sync::beforeSyncOrder', [
                'order' => $blisstributeOrder
            ]);

            $orderData = $this->initializeModelMapping($blisstributeOrder);

            $restClient = new Shopware_Components_Blisstribute_RestClient(
                sprintf(
                    '%s://%s',
                    ($this->config->get('blisstribute-soap-protocol') == 1 ? 'http' : 'https'),
                    $this->config->get('blisstribute-rest-host')
                )
            );
            $restClient->authenticateWithClientUserPassword(
                $this->config->get('blisstribute-soap-client'),
                $this->config->get('blisstribute-soap-username'),
                $this->config->get('blisstribute-soap-password')
            );
            $orderResponse = $restClient->createOrder($orderData);

            // Response must be successful.
            if ($orderResponse->getStatusCode() < 200 || $orderResponse->getStatusCode() >= 300) {
                throw new Shopware_Components_Blisstribute_Exception_TransferException(
                    sprintf('Unexpected status code %d', $orderResponse->getStatusCode()));
            }

            $syncWasSuccessful = ($orderResponse->json()['success'] ?? false);
            if ($syncWasSuccessful) {
                $result = true;
                $this->logMessage('order transferred::' . $blisstributeOrder->getOrder()->getNumber(), __FUNCTION__);

                $blisstributeOrder->setStatus(BlisstributeOrder::EXPORT_STATUS_TRANSFERRED)
                    ->setErrorComment(null)
                    ->setTries(0)
                    ->setLastCronAt(new DateTime());
            } else {
                $this->logMessage(
                    'order rejected::' . $blisstributeOrder->getOrder()->getNumber(),
                    __FUNCTION__,
                    Logger::ERROR
                );

                $blisstributeOrder->setStatus(BlisstributeOrder::EXPORT_STATUS_TRANSFER_ERROR)
                    ->setErrorComment('order rejected')
                    ->setTries($blisstributeOrder->getTries() + 1)
                    ->setLastCronAt(new DateTime());

                $this->setLastError('Bestellung wurde von Blisstribute abgelehnt');
            }
        } catch (Shopware_Components_Blisstribute_Exception_MappingException $ex) {
            $this->logMessage(
                'order invalid::' . $blisstributeOrder->getOrder()->getNumber() . $ex->getMessage() . $ex->getTraceAsString(),
                __FUNCTION__,
                Logger::ERROR
            );

            $blisstributeOrder->setStatus(BlisstributeOrder::EXPORT_STATUS_VALIDATION_ERROR)
                ->setErrorComment($ex->getMessage())
                ->setTries($blisstributeOrder->getTries() + 1)
                ->setLastCronAt(new DateTime());

            $this->setLastError(
                'Bestellung kann nicht übermittelt werden, da nicht alle notwendigen Felder gefüllt oder die ' .
                'Zahlungs/Versandart nicht einer Blisstribute zugeordnet ist.'
            );

        } catch (Shopware_Components_Blisstribute_Exception_TransferException $ex) {
            $this->logMessage(
                'transfer error::' . $blisstributeOrder->getOrder()->getNumber() . $ex->getMessage() . $ex->getTraceAsString(),
                __FUNCTION__,
                Logger::ERROR
            );

            $blisstributeOrder->setStatus(BlisstributeOrder::EXPORT_STATUS_VALIDATION_ERROR)
                ->setErrorComment($ex->getMessage())
                ->setTries($blisstributeOrder->getTries() + 1)
                ->setLastCronAt(new DateTime());

            $this->setLastError('Fehler bei der Übermittlung der Bestellung zu Blisstribute.');

        } catch (Exception $ex) {
            $this->logMessage(
                'general sync error::' . $blisstributeOrder->getOrder()->getNumber() . $ex->getMessage() . $ex->getTraceAsString(),
                __FUNCTION__,
                Logger::ERROR
            );

            $blisstributeOrder->setStatus(BlisstributeOrder::EXPORT_STATUS_VALIDATION_ERROR)
                ->setErrorComment($ex->getMessage())
                ->setTries($blisstributeOrder->getTries() + 1)
                ->setLastCronAt(new DateTime());

            $this->setLastError('Fehler bei der Übermittlung der Bestellung zu Blisstribute.');
        }

        if (!$result) {
            $errorReceiver = trim(Shopware()->Config()['blisstribute-error-email-receiver']);
            if ($errorReceiver != '') {
                $email = Shopware()->TemplateMail()->createMail('sBLISSORDERREJECTED', ['orderNumber' => $blisstributeOrder->getOrder()->getNumber()]);
                $email->addTo($errorReceiver);
                $email->send();
            }
        }

        $this->modelManager->persist($blisstributeOrder);
        $this->modelManager->flush();

        $this->logMessage('sync done::' . $blisstributeOrder->getOrder()->getNumber(), __FUNCTION__);
        return $result;
    }

    /**
     * check blisstribute export status and dependent on the order status
     *
     * @param BlisstributeOrder $blisstributeOrder
     *
     * @return bool
     *
     * @throws Shopware_Components_Blisstribute_Exception_ValidationMappingException
     */
    protected function checkStatus(BlisstributeOrder $blisstributeOrder)
    {
        switch ($blisstributeOrder->getStatus()) {
            case BlisstributeOrder::EXPORT_STATUS_TRANSFER_ERROR:
            case BlisstributeOrder::EXPORT_STATUS_VALIDATION_ERROR:
                return true;
                break;

            case BlisstributeOrder::EXPORT_STATUS_NONE:
                $order = $blisstributeOrder->getOrder();
                if ($order->getOrderStatus()->getId() == -1) {
                    $blisstributeOrder->setStatus(BlisstributeOrder::EXPORT_STATUS_ABORTED);

                    $modelManager = Shopware()->Models();
                    $modelManager->persist($blisstributeOrder);
                    $modelManager->flush();

                    $this->logMessage('order aborted', __FUNCTION__, Logger::ERROR);
                    throw new Shopware_Components_Blisstribute_Exception_ValidationMappingException('order aborted');
                }

                return true;
                break;

            case BlisstributeOrder::EXPORT_STATUS_CREATION_PENDING:
                $order = $blisstributeOrder->getOrder();

                if ($order->getOrderStatus()->getId() == -1) {
                    $blisstributeOrder->setStatus(BlisstributeOrder::EXPORT_STATUS_ABORTED);

                    $modelManager = Shopware()->Models();
                    $modelManager->persist($blisstributeOrder);
                    $modelManager->flush();

                    throw new Shopware_Components_Blisstribute_Exception_ValidationMappingException('order aborted');
                }

                return true;
                break;

            case BlisstributeOrder::EXPORT_STATUS_IN_TRANSFER:
            case BlisstributeOrder::EXPORT_STATUS_TRANSFERRED:
                throw new Shopware_Components_Blisstribute_Exception_ValidationMappingException('already in transfer');
                break;

            case BlisstributeOrder::EXPORT_STATUS_ABORTED:
                throw new Shopware_Components_Blisstribute_Exception_ValidationMappingException('order aborted');
                break;

            default:
                throw new Shopware_Components_Blisstribute_Exception_ValidationMappingException('could not determine export status');
                break;
        }
    }
}
