<?php
namespace IDangerous\NetgsmIYS\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class IysData extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('iys_data', 'id');
    }

    public function getPendingRecords(int $limit = 100): array
    {
        $connection = $this->getConnection();

        return $connection->fetchAll(
            $connection->select()
                ->from($this->getMainTable())
                ->where('iys_status = ?', 0)
                ->limit($limit)
        );
    }

    public function markAsSynced(int $id): bool
    {
        $connection = $this->getConnection();

        $result = $connection->update(
            $this->getMainTable(),
            ['iys_status' => 1],
            ['id = ?' => $id]
        );

        return $result > 0;
    }

    public function findByValue(string $value, ?string $type = null): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('value = ?', $value);

        if ($type !== null) {
            $select->where('type = ?', $type);
        }

        return $connection->fetchAll($select);
    }
}