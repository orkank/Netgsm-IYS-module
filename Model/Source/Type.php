<?php
namespace IDangerous\NetgsmIYS\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Type implements OptionSourceInterface
{
    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'sms', 'label' => __('SMS')],
            ['value' => 'call', 'label' => __('Call')],
            ['value' => 'email', 'label' => __('Email')]
        ];
    }
}