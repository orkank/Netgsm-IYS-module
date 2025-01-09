<?php
namespace IDangerous\NetgsmIYS\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Status implements OptionSourceInterface
{
    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 0, 'label' => __('Not Set')],
            ['value' => 1, 'label' => __('Accepted')],
            ['value' => 2, 'label' => __('User Rejected')],
            ['value' => 3, 'label' => __('IYS Rejected')]
        ];
    }
}