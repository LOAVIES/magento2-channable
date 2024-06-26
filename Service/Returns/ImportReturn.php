<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Channable\Service\Returns;

use Exception;
use Magento\Framework\App\ResourceConnection;
use Magmodules\Channable\Api\Returns\RepositoryInterface as ReturnsRepository;

class ImportReturn
{

    /**
     * @var ReturnsRepository
     */
    private $returnsRepository;
    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @param ResourceConnection $resource
     * @param ReturnsRepository $returnsRepository
     */
    public function __construct(
        ResourceConnection $resource,
        ReturnsRepository $returnsRepository
    ) {
        $this->returnsRepository = $returnsRepository;
        $this->resource = $resource;
    }

    /**
     * Import return data
     *
     * @param array $returnData
     * @param int $storeId
     *
     * @return array
     */
    public function execute(array $returnData, int $storeId): array
    {
        $response = [];
        $item = $returnData['item'] ?? [];
        $customer = $returnData['customer'] ?? [];
        $address = $returnData['address'] ?? [];
        $orderIncrementId = $item['order_id'] ?? null;

        $returns = $this->returnsRepository->create();
        $returns->setStoreId($storeId)
            ->setOrderId((int)$item['order_id'])
            ->setChannableId((int)$returnData['channable_id'])
            ->setChannelName($returnData['channel_name'])
            ->setChannelId($returnData['channel_id'])
            ->setCustomerName(trim($customer['first_name'] . ' ' . $customer['last_name']))
            ->setItem($item)
            ->setCustomer($customer)
            ->setAddress($address)
            ->setStatus($returnData['status'])
            ->setReason($item['reason'])
            ->setComment($item['comment'])
            ->setMagentoIncrementId((string)$orderIncrementId);

        if ($orderIncrementId && $salesOrderGridData = $this->getMagentoOrder((string)$orderIncrementId)) {
            $returns->setMagentoOrderId((int)$salesOrderGridData['entity_id']);
        }

        try {
            $returns = $this->returnsRepository->save($returns);
            $response['validated'] = 'true';
            $response['return_id'] = $returns->getEntityId();
        } catch (Exception $e) {
            $response['validated'] = 'false';
            $response['errors'] = $e->getMessage();
        }

        return $response;
    }

    /**
     * Get Magento order by increment id
     *
     * @param string $incrementId
     *
     * @return mixed
     */
    public function getMagentoOrder(string $incrementId)
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('sales_order_grid'))
            ->where('increment_id = :increment_id');
        $bind = [':increment_id' => $incrementId];
        return $connection->fetchRow($select, $bind);
    }
}
