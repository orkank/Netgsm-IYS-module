<?php
namespace IDangerous\NetgsmIYS\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class IysStatus implements OptionSourceInterface
{
    const PENDING = 0;
    const SYNCED = 1;
    const ERROR = 2;

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::PENDING, 'label' => __('Pending')],
            ['value' => self::SYNCED, 'label' => __('Synced')],
            ['value' => self::ERROR, 'label' => __('Error')]
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            self::PENDING => __('Pending'),
            self::SYNCED => __('Synced'),
            self::ERROR => __('Error')
        ];
    }
}