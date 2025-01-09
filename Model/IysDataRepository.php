<?php
namespace IDangerous\NetgsmIYS\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotSaveException;
use IDangerous\NetgsmIYS\Model\ResourceModel\IysData as IysDataResource;
use IDangerous\NetgsmIYS\Model\ResourceModel\IysData\CollectionFactory;

class IysDataRepository
{
    private $resource;
    private $iysDataFactory;
    private $collectionFactory;

    public function __construct(
        IysDataResource $resource,
        IysDataFactory $iysDataFactory,
        CollectionFactory $collectionFactory
    ) {
        $this->resource = $resource;
        $this->iysDataFactory = $iysDataFactory;
        $this->collectionFactory = $collectionFactory;
    }

    public function save(IysData $iysData): IysData
    {
        try {
            $this->resource->save($iysData);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save IYS data: %1', $e->getMessage()));
        }
        return $iysData;
    }

    public function getById(int $id): IysData
    {
        $iysData = $this->iysDataFactory->create();
        $this->resource->load($iysData, $id);

        if (!$iysData->getId()) {
            throw new NoSuchEntityException(__('IYS data with ID "%1" does not exist.', $id));
        }

        return $iysData;
    }

    public function getPendingRecords(int $limit = 100): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addPendingSyncFilter();
        $collection->setPageSize($limit);

        return $collection->getItems();
    }

    public function findByValue(string $value, ?string $type = null): array
    {
        return $this->resource->findByValue($value, $type);
    }

    public function markAsSynced(int $id): bool
    {
        return $this->resource->markAsSynced($id);
    }
}