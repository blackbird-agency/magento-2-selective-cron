<?php
declare(strict_types=1);

namespace Blackbird\SelectiveCron\Model;

use Blackbird\SelectiveCron\Api\Enums\ConfigPaths;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{

    public function __construct(
        protected readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Check if selective cron is enabled
     *
     * @param string $scopeType
     * @param string|null $scopeCode
     * @return bool
     */
    public function isEnabled(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        ?string $scopeCode = null
    ): bool {
        return $this->scopeConfig->isSetFlag(
            ConfigPaths::ENABLED->value,
            $scopeType,
            $scopeCode
        );
    }

    /**
     * Get selected cron jobs
     *
     * @param string $scopeType
     * @param string|null $scopeCode
     * @return array
     */
    public function getSelectedJobs(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        ?string $scopeCode = null
    ): array {
        $value = $this->scopeConfig->getValue(
            ConfigPaths::SELECTED_JOBS->value,
            $scopeType,
            $scopeCode
        );

        if (!$value) {
            return [];
        }

        return is_array($value) ? $value : explode(',', $value);
    }
}
