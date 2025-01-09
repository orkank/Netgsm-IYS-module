<?php
namespace IDangerous\NetgsmIYS\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

class Config extends AbstractHelper
{
    private const XML_PATH_USERNAME = 'netgsm_iys/general/username';
    private const XML_PATH_PASSWORD = 'netgsm_iys/general/password';
    private const XML_PATH_BRAND_CODE = 'netgsm_iys/general/brand_code';
    private const XML_PATH_APP_KEY = 'netgsm_iys/general/app_key';
    private const XML_PATH_ENABLE_LOGGING = 'netgsm_iys/general/enable_logging';
    private const XML_PATH_WEBHOOK_TOKEN = 'netgsm_iys/general/webhook_token';
    private const XML_PATH_WEBHOOK_ALLOWED_HOSTS = 'netgsm_iys/general/webhook_allowed_hosts';

    /**
     * Get config value
     *
     * @param string $path
     * @param string|null $storeId
     * @return mixed
     */
    private function getConfigValue($path, $storeId = null)
    {
        return $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get username
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    public function getUsername(): string
    {
        $value = $this->getConfigValue(self::XML_PATH_USERNAME);
        if (empty($value)) {
            throw new \InvalidArgumentException('Netgsm IYS username is not configured');
        }
        return (string)$value;
    }

    /**
     * Get password
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    public function getPassword(): string
    {
        $value = $this->getConfigValue(self::XML_PATH_PASSWORD);
        if (empty($value)) {
            throw new \InvalidArgumentException('Netgsm IYS password is not configured');
        }
        return (string)$value;
    }

    /**
     * Get brand code
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    public function getBrandCode(): string
    {
        $value = $this->getConfigValue(self::XML_PATH_BRAND_CODE);
        if (empty($value)) {
            throw new \InvalidArgumentException('Netgsm IYS brand code is not configured');
        }
        return (string)$value;
    }

    /**
     * Get app key
     *
     * @return string|null
     */
    public function getAppKey(): ?string
    {
        $value = $this->getConfigValue(self::XML_PATH_APP_KEY);
        return !empty($value) ? (string)$value : null;
    }

    /**
     * Check if logging is enabled
     *
     * @return bool
     */
    public function isLoggingEnabled(): bool
    {
        return (bool)$this->getConfigValue(self::XML_PATH_ENABLE_LOGGING);
    }

    /**
     * Get webhook token
     *
     * @return string|null
     */
    public function getWebhookToken(): ?string
    {
        $value = $this->getConfigValue(self::XML_PATH_WEBHOOK_TOKEN);
        return !empty($value) ? (string)$value : null;
    }

    /**
     * Get webhook allowed hosts
     *
     * @return string|null
     */
    public function getWebhookAllowedHosts(): ?string
    {
        $value = $this->getConfigValue(self::XML_PATH_WEBHOOK_ALLOWED_HOSTS);
        return !empty($value) ? (string)$value : null;
    }

    /**
     * Validate required configuration
     *
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function validateConfig(): bool
    {
        $this->getUsername();
        $this->getPassword();
        $this->getBrandCode();
        return true;
    }
}