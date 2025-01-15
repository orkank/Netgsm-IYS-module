<?php
namespace IDangerous\NetgsmIYS\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Status implements OptionSourceInterface
{
    const NOT_SET = 0;
    const ACCEPTED = 1;
    const USER_REJECTED = 2;
    const IYS_REJECTED = 3;

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::NOT_SET, 'label' => __('Not Set')],
            ['value' => self::ACCEPTED, 'label' => __('Accepted')],
            ['value' => self::USER_REJECTED, 'label' => __('User Rejected')],
            ['value' => self::IYS_REJECTED, 'label' => __('IYS Rejected')]
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
            self::NOT_SET => __('Not Set'),
            self::ACCEPTED => __('Accepted'),
            self::USER_REJECTED => __('User Rejected'),
            self::IYS_REJECTED => __('IYS Rejected')
        ];
    }
}