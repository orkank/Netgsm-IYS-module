<?php
namespace IDangerous\NetgsmIYS\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

class PhoneNumber extends AbstractHelper
{
    /**
     * Format phone number to IYS compatible format
     *
     * @param string $phoneNumber
     * @return string|null
     */
    public function format($phoneNumber)
    {
        if (empty($phoneNumber)) {
            return null;
        }

        // Remove all non-numeric characters
        $number = preg_replace('/[^0-9]/', '', $phoneNumber);

        // If number starts with 0, remove it
        if (substr($number, 0, 1) === '0') {
            $number = substr($number, 1);
        }

        // If number doesn't start with 90, add it
        if (substr($number, 0, 2) !== '90') {
            $number = '90' . $number;
        }

        // Check if it's a valid Turkish mobile number
        if (!$this->isValidTurkishNumber($number)) {
            return null;
        }

        return '+' . $number;
    }

    /**
     * Check if number is a valid Turkish mobile number
     *
     * @param string $number
     * @return bool
     */
    public function isValidTurkishNumber($number)
    {
        // Remove + if exists
        $number = ltrim($number, '+');

        // Must be 12 digits (90 + 10 digits)
        if (strlen($number) !== 12) {
            return false;
        }

        // Must start with 90
        if (substr($number, 0, 2) !== '90') {
            return false;
        }

        // Third digit must be 5 (mobile numbers)
        if (substr($number, 2, 1) !== '5') {
            return false;
        }

        return true;
    }

    /**
     * Clean phone number for comparison
     *
     * @param string $phoneNumber
     * @return string
     */
    public function clean($phoneNumber)
    {
        return preg_replace('/[^0-9]/', '', $phoneNumber);
    }

    /**
     * Compare two phone numbers
     *
     * @param string $number1
     * @param string $number2
     * @return bool
     */
    public function compare($number1, $number2)
    {
        return $this->clean($number1) === $this->clean($number2);
    }
}