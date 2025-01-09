<?php
namespace IDangerous\NetgsmIYS\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * IYS Data Model
 */
class IysData extends AbstractModel
{
    /**
     * Status constants
     */
    public const STATUS_NOT_SET = 0;
    public const STATUS_ACCEPTED = 1;
    public const STATUS_USER_REJECTED = 2;
    public const STATUS_IYS_REJECTED = 3;

    /**
     * IYS sync status constants
     */
    public const IYS_STATUS_PENDING = 0;
    public const IYS_STATUS_SYNCED = 1;

    /**
     * @var Json
     */
    private $jsonSerializer;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param Json $jsonSerializer
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        Json $jsonSerializer,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->jsonSerializer = $jsonSerializer;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\IysData::class);
    }

    /**
     * Before save operations
     *
     * @return $this
     */
    public function beforeSave()
    {
        parent::beforeSave();

        if ($this->isObjectNew()) {
            $this->setCreated(date('Y-m-d H:i:s'));
        }

        $this->setModified(date('Y-m-d H:i:s'));

        // Serialize last_iys_result if it's an array
        $lastIysResult = $this->getLastIysResult();
        if (is_array($lastIysResult)) {
            $this->setLastIysResult($this->jsonSerializer->serialize($lastIysResult));
        }

        return $this;
    }

    /**
     * After load operations
     *
     * @return $this
     */
    protected function _afterLoad()
    {
        parent::_afterLoad();

        // Unserialize last_iys_result if it's a string
        $lastIysResult = $this->getLastIysResult();
        if (is_string($lastIysResult) && !empty($lastIysResult)) {
            try {
                $this->setLastIysResult($this->jsonSerializer->unserialize($lastIysResult));
            } catch (\Exception $e) {
                $this->setLastIysResult([]);
            }
        }

        return $this;
    }

    /**
     * Add IYS result to history
     *
     * @param array $result
     * @return $this
     */
    public function addIysResult(array $result)
    {
        $currentResults = $this->getLastIysResult();
        if (!is_array($currentResults)) {
            $currentResults = [];
        }

        // Add new result at the beginning
        array_unshift($currentResults, [
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $result
        ]);

        // Keep only last 5 results
        $currentResults = array_slice($currentResults, 0, 5);

        $this->setLastIysResult($currentResults);
        return $this;
    }

    /**
     * Get type label
     *
     * @return string
     */
    public function getTypeLabel()
    {
        $type = $this->getType();
        switch ($type) {
            case 'sms':
                return 'SMS';
            case 'call':
                return 'Call';
            case 'email':
                return 'Email';
            case 'whatsapp':
                return 'WhatsApp';
            default:
                return ucfirst($type ?? '');
        }
    }

    /**
     * Get status label
     *
     * @return string
     */
    public function getStatusLabel()
    {
        $status = (int)$this->getStatus();
        switch ($status) {
            case self::STATUS_NOT_SET:
                return 'Not Set';
            case self::STATUS_ACCEPTED:
                return 'Accepted';
            case self::STATUS_USER_REJECTED:
                return 'User Rejected';
            case self::STATUS_IYS_REJECTED:
                return 'IYS Rejected';
            default:
                return 'Unknown';
        }
    }

    /**
     * Get IYS status label
     *
     * @return string
     */
    public function getIysStatusLabel()
    {
        $status = (int)$this->getIysStatus();
        switch ($status) {
            case self::IYS_STATUS_PENDING:
                return 'Pending';
            case self::IYS_STATUS_SYNCED:
                return 'Synced';
            default:
                return 'Unknown';
        }
    }

    /**
     * Get sync retries count
     *
     * @return int
     */
    public function getSyncRetries()
    {
        return (int)$this->getData('sync_retries');
    }

    /**
     * Increment sync retries
     *
     * @return $this
     */
    public function incrementSyncRetries()
    {
        $retries = $this->getSyncRetries();
        $this->setData('sync_retries', $retries + 1);
        return $this;
    }
}