<?php
declare(strict_types=1);

namespace Blackbird\SelectiveCron\Model\Config\Source;

use Magento\Cron\Model\ConfigInterface;
use Magento\Framework\Data\OptionSourceInterface;

class CronJobs implements OptionSourceInterface
{
    public function __construct(
        protected readonly ConfigInterface $cronConfig
    ) {
    }

    /**
     * Return array of options as value-label pairs
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];
        $cronJobs = $this->getAllCronJobs();

        foreach ($cronJobs as $jobCode => $jobConfig) {
            $options[] = [
                'value' => $jobCode,
                'label' => $jobCode
            ];
        }

        // Sort options alphabetically by label
        usort($options, function ($a, $b) {
            return strcmp($a['label'], $b['label']);
        });

        return $options;
    }

    /**
     * Get all cron jobs from all groups
     *
     * @return array
     */
    protected function getAllCronJobs(): array
    {
        $jobs = [];
        $jobGroups = $this->cronConfig->getJobs();

        foreach ($jobGroups as $groupId => $groupJobs) {
            foreach ($groupJobs as $jobCode => $jobConfig) {
                $jobs[$jobCode] = $jobConfig;
            }
        }

        return $jobs;
    }
}
