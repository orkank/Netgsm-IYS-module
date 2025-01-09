<?php
namespace IDangerous\NetgsmIYS\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class IysStatus implements OptionSourceInterface
{
    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 0, 'label' => __('Pending')],
            ['value' => 1, 'label' => __('Synced')]
        ];
    }
}