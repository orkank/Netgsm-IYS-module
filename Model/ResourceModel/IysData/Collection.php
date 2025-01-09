<?php
namespace IDangerous\NetgsmIYS\Model\ResourceModel\IysData;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use IDangerous\NetgsmIYS\Model\IysData;
use IDangerous\NetgsmIYS\Model\ResourceModel\IysData as IysDataResource;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(IysData::class, IysDataResource::class);
    }

    public function addPendingSyncFilter(): self
    {
        $this->addFieldToFilter('iys_status', ['eq' => 0]);
        return $this;
    }

    public function addTypeFilter(string $type): self
    {
        $this->addFieldToFilter('type', ['eq' => $type]);
        return $this;
    }

    public function addStatusFilter(int $status): self
    {
        $this->addFieldToFilter('status', ['eq' => $status]);
        return $this;
    }
}