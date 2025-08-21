<?php
declare(strict_types=1);

namespace Blackbird\SelectiveCron\Observer;

use Blackbird\SelectiveCron\Service\ScheduleService;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class ConfigChangeObserver implements ObserverInterface
{
    public function __construct(
        protected readonly ScheduleService $scheduleService,
        protected readonly LoggerInterface $logger
    ) {
    }

    /**
     * Observer for config changes
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            $changedPaths = $observer->getEvent()->getData('changed_paths');
            
            // Check if selective cron configuration has changed
            if (is_array($changedPaths) && 
                (in_array('system/selective_cron/enabled', $changedPaths) || 
                 in_array('system/selective_cron/selected_jobs', $changedPaths))) {
                
                $this->logger->info('Selective cron configuration changed. Updating schedule.');
                
                // Truncate the schedule table
                $this->scheduleService->truncateSchedule();
                
                // Repopulate the schedule table
                $this->scheduleService->populateSchedule();
                
                $this->logger->info('Selective cron schedule updated successfully.');
            }
        } catch (\Exception $e) {
            $this->logger->error('Error updating selective cron schedule: ' . $e->getMessage());
        }
    }
}
