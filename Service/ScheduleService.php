<?php
declare(strict_types=1);

namespace Blackbird\SelectiveCron\Service;

use Blackbird\SelectiveCron\Api\Enums\ScheduleEnum;
use Blackbird\SelectiveCron\Api\Enums\StatusEnum;
use Blackbird\SelectiveCron\Model\Config;
use Blackbird\SelectiveCron\Model\ResourceModel\Schedule as ScheduleResource;
use Blackbird\SelectiveCron\Model\Schedule;
use Blackbird\SelectiveCron\Model\ResourceModel\Schedule\CollectionFactory as ScheduleCollectionFactory;
use Blackbird\SelectiveCron\Model\ScheduleFactory;
use Magento\Cron\Model\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

class ScheduleService
{
    public function __construct(
        protected readonly Config $config,
        protected readonly ConfigInterface $cronConfig,
        protected readonly ScheduleResource $scheduleResource,
        protected readonly ScheduleFactory $scheduleFactory,
        protected readonly ScheduleCollectionFactory $scheduleCollectionFactory,
        protected readonly DateTime $dateTime,
        protected readonly LoggerInterface $logger,
        protected readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Truncate the selective cron schedule table
     *
     * @return void
     */
    public function truncateSchedule(): void
    {
        try {
            $this->scheduleResource->truncateSchedule();
            $this->logger->info('Selective cron schedule table truncated.');
        } catch (\Exception $e) {
            $this->logger->error('Error truncating selective cron schedule table: ' . $e->getMessage());
        }
    }

    /**
     * Populate the selective cron schedule table with selected cron jobs
     *
     * @return void
     */
    public function populateSchedule(): void
    {
        if (!$this->config->isEnabled()) {
            $this->logger->info('Selective Cron is disabled. Schedule not populated.');
            return;
        }

        $selectedJobs = $this->config->getSelectedJobs();
        if (empty($selectedJobs)) {
            $this->logger->info('No cron jobs selected. Schedule not populated.');
            return;
        }

        $this->logger->info('Populating selective cron schedule table.');
        $this->logger->info('Selected jobs: ' . implode(', ', $selectedJobs));

        $jobsScheduled = 0;
        $jobsSkipped = 0;

        $jobGroups = $this->cronConfig->getJobs();
        foreach ($jobGroups as $groupId => $jobs) {
            foreach ($jobs as $jobCode => $jobConfig) {
                if (!in_array($jobCode, $selectedJobs)) {
                    $jobsSkipped++;
                    continue;
                }

                try {
                    $this->scheduleJob($jobCode, $jobConfig);
                    $jobsScheduled++;
                } catch (\Exception $e) {
                    $this->logger->error("Error scheduling job {$jobCode}: " . $e->getMessage());
                }
            }
        }

        $this->logger->info("Selective cron schedule populated. Jobs scheduled: {$jobsScheduled}, skipped: {$jobsSkipped}");
    }
    
    /**
     * Schedule new job instances for selected cron jobs
     * 
     * This method checks for jobs that need new instances and adds them to the schedule table.
     * It should be called periodically to ensure that jobs continue to run according to their periodicities.
     *
     * @return void
     */
    public function scheduleNewJobInstances(): void
    {
        if (!$this->config->isEnabled()) {
            $this->logger->info('Selective Cron is disabled. No new job instances scheduled.');
            return;
        }

        $selectedJobs = $this->config->getSelectedJobs();
        if (empty($selectedJobs)) {
            $this->logger->info('No cron jobs selected. No new job instances scheduled.');
            return;
        }

        // Clean up any duplicate jobs before scheduling new ones
        $this->cleanupDuplicateJobs();

        $this->logger->info('Checking for jobs that need new instances.');
        
        $jobsScheduled = 0;
        $jobsSkipped = 0;
        $now = $this->dateTime->gmtTimestamp();
        $aheadTime = 3600; // Schedule jobs 1 hour ahead
        
        $jobGroups = $this->cronConfig->getJobs();
        foreach ($jobGroups as $groupId => $jobs) {
            foreach ($jobs as $jobCode => $jobConfig) {
                if (!in_array($jobCode, $selectedJobs)) {
                    continue;
                }
                
                try {
                    // First check if there's a pending instance for this job
                    $pendingInstance = $this->getLastPendingInstance($jobCode);
                    
                    if ($pendingInstance && (strtotime($pendingInstance->getScheduledAt()) - $now) >= $aheadTime) {
                        // There's a pending instance scheduled far enough in the future
                        $jobsSkipped++;
                        continue;
                    }
                    
                    // If there's no pending instance or it's scheduled to run soon,
                    // check the last instance regardless of status
                    $lastInstance = $this->getLastInstance($jobCode);
                    
                    if (!$lastInstance) {
                        // No instances at all, create a new one
                        $this->scheduleJob($jobCode, $jobConfig);
                        $jobsScheduled++;
                    } else {
                        // Get the cron expression for this job
                        $cronExpr = $jobConfig['schedule'] ?? '* * * * *';
                        
                        // Calculate when the next instance should be scheduled based on the last instance
                        $lastScheduledTime = strtotime($lastInstance->getScheduledAt());
                        $nextScheduledTime = $this->getScheduledAtTime($cronExpr, $lastScheduledTime);
                        
                        // If the next scheduled time is in the past or less than the ahead time from now,
                        // create a new instance
                        if ($nextScheduledTime && ($nextScheduledTime <= $now || ($nextScheduledTime - $now) < $aheadTime)) {
                            $this->scheduleJob($jobCode, $jobConfig);
                            $jobsScheduled++;
                        } else {
                            $jobsSkipped++;
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger->error("Error scheduling job {$jobCode}: " . $e->getMessage());
                }
            }
        }
        
        if ($jobsScheduled > 0) {
            $this->logger->info("New job instances scheduled. Jobs scheduled: {$jobsScheduled}, skipped: {$jobsSkipped}");
        } else {
            $this->logger->info("No new job instances needed at this time. Jobs skipped: {$jobsSkipped}");
        }
    }
    
    /**
     * Get the last pending instance of a job
     *
     * @param string $jobCode
     *
     * @return \Blackbird\SelectiveCron\Model\Schedule|null
     */
    protected function getLastPendingInstance(string $jobCode): ?\Blackbird\SelectiveCron\Model\Schedule
    {
        try {
            $collection = $this->scheduleCollectionFactory->create();
            $collection->addFieldToFilter('job_code', $jobCode)
                ->addFieldToFilter('status', StatusEnum::PENDING->value)
                ->setOrder('scheduled_at', 'DESC')
                ->setPageSize(1);
                
            if ($collection->getSize() > 0) {
                return $collection->getFirstItem();
            }
            
            return null;
        } catch (\Exception $e) {
            $this->logger->error("Error getting last pending instance for job {$jobCode}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get the last instance of a job regardless of status
     *
     * @param string $jobCode
     *
     * @return \Blackbird\SelectiveCron\Model\Schedule|null
     */
    protected function getLastInstance(string $jobCode): ?\Blackbird\SelectiveCron\Model\Schedule
    {
        try {
            $collection = $this->scheduleCollectionFactory->create();
            $collection->addFieldToFilter('job_code', $jobCode)
                ->setOrder('scheduled_at', 'DESC')
                ->setPageSize(1);
                
            if ($collection->getSize() > 0) {
                return $collection->getFirstItem();
            }
            
            return null;
        } catch (\Exception $e) {
            $this->logger->error("Error getting last instance for job {$jobCode}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if a job with the same job code is already scheduled for the specified time
     *
     * @param string $jobCode
     * @param string $scheduledAt
     * @return bool
     */
    protected function jobExistsAtTime(string $jobCode, string $scheduledAt): bool
    {
        try {
            $collection = $this->scheduleCollectionFactory->create();
            $collection->addFieldToFilter('job_code', $jobCode)
                ->addFieldToFilter('scheduled_at', $scheduledAt);
                
            return $collection->getSize() > 0;
        } catch (\Exception $e) {
            $this->logger->error("Error checking if job {$jobCode} exists at time {$scheduledAt}: " . $e->getMessage());
            // In case of error, assume the job doesn't exist to allow scheduling
            return false;
        }
    }
    
    /**
     * Clean up duplicate jobs in the selective_cron_schedule table
     * 
     * This method finds all jobs with the same job code and scheduled time,
     * keeps only one instance (the oldest one), and deletes the rest.
     *
     * @return void
     */
    protected function cleanupDuplicateJobs(): void
    {
        try {
            $connection = $this->scheduleResource->getConnection();
            $tableName = $this->scheduleResource->getTable('selective_cron_schedule');
            
            // Find all job_code and scheduled_at combinations that have more than one entry
            $query = $connection->select()
                ->from($tableName, ['job_code', 'scheduled_at', 'count' => 'COUNT(*)'])
                ->group(['job_code', 'scheduled_at'])
                ->having('COUNT(*) > 1');
                
            $duplicates = $connection->fetchAll($query);
            
            if (empty($duplicates)) {
                $this->logger->info('No duplicate jobs found.');
                return;
            }
            
            $jobsRemoved = 0;
            
            foreach ($duplicates as $duplicate) {
                $jobCode = $duplicate['job_code'];
                $scheduledAt = $duplicate['scheduled_at'];
                
                // Get all schedule IDs for this job_code and scheduled_at
                $query = $connection->select()
                    ->from($tableName, ['schedule_id'])
                    ->where('job_code = ?', $jobCode)
                    ->where('scheduled_at = ?', $scheduledAt)
                    ->order('created_at ASC'); // Order by created_at to keep the oldest one
                    
                $scheduleIds = $connection->fetchCol($query);
                
                // Keep the first one (oldest) and delete the rest
                if (count($scheduleIds) > 1) {
                    $keepId = array_shift($scheduleIds);
                    $deleteIds = $scheduleIds;
                    
                    if (!empty($deleteIds)) {
                        $connection->delete(
                            $tableName,
                            ['schedule_id IN (?)' => $deleteIds]
                        );
                        
                        $jobsRemoved += count($deleteIds);
                        $this->logger->info("Removed " . count($deleteIds) . " duplicate entries for job {$jobCode} scheduled at {$scheduledAt}");
                    }
                }
            }
            
            if ($jobsRemoved > 0) {
                $this->logger->info("Total duplicate jobs removed: {$jobsRemoved}");
            }
        } catch (\Exception $e) {
            $this->logger->error('Error cleaning up duplicate jobs: ' . $e->getMessage());
        }
    }

    /**
     * Schedule a cron job
     *
     * @param string $jobCode
     * @param array $jobConfig
     * @return void
     */
    protected function scheduleJob(string $jobCode, array $jobConfig): void
    {
        $cronExpr = $this->getCronExpression($jobCode, $jobConfig);

        // Calculate next run time based on cron expression
        $now = $this->dateTime->gmtTimestamp();
        $scheduledAt = $this->getScheduledAtTime($cronExpr, $now);

        if (!$scheduledAt) {
            $this->logger->warning("Could not determine next run time for job {$jobCode}. Skipping.");
            return;
        }
        
        $scheduledAtFormatted = date('Y-m-d H:i:s', $scheduledAt);
        
        // Check if job already exists at this time
        if ($this->jobExistsAtTime($jobCode, $scheduledAtFormatted)) {
            $this->logger->info("Job {$jobCode} already scheduled for {$scheduledAtFormatted}. Skipping.");
            return;
        }

        /** @var Schedule $schedule */
        $schedule = $this->scheduleFactory->create();
        $schedule->setJobCode($jobCode)
            ->setStatus(StatusEnum::PENDING->value)
            ->setCreatedAt(date('Y-m-d H:i:s', $now))
            ->setScheduledAt($scheduledAtFormatted);

        try {
            $schedule->save();
            $this->logger->info("Job {$jobCode} scheduled for {$scheduledAtFormatted}");
        } catch (\Exception $e) {
            $this->logger->error("Error saving schedule for job {$jobCode}: " . $e->getMessage());
            throw $e;
        }
    }

    protected function getCronExpression(string $jobCode, array $jobConfig): string
    {
        if (!empty($jobConfig['schedule'])) {
            return $jobConfig['schedule'];
        }

        if (!empty($jobConfig['config_path'])) {
            return $this->scopeConfig->getValue($jobConfig['config_path']);
        }

        $this->logger->info("Job {$jobCode} has no schedule. Using default schedule.");
        return ScheduleEnum::DEFAULT_CRON_EXPRESSION->value;
    }


    /**
     * Get the next scheduled time based on cron expression
     *
     * @param string $cronExpr
     * @param int $now
     * @return int|null
     */
    protected function getScheduledAtTime(string $cronExpr, int $now): ?int
    {
        try {
            // Parse the cron expression
            $cronParts = preg_split('#\s+#', $cronExpr, -1, PREG_SPLIT_NO_EMPTY);
            if (count($cronParts) < 5 || count($cronParts) > 6) {
                throw new \Exception('Invalid cron expression: ' . $cronExpr);
            }

            // Get the current date/time
            $date = new \DateTime();
            $date->setTimestamp($now);
            
            // Start from the next minute
            $date->modify('+1 minute');
            $date->setTime((int)$date->format('H'), (int)$date->format('i'), 0);
            
            // Try to find a matching time within the next 24 hours
            $maxIterations = 1440; // 24 hours * 60 minutes
            $iteration = 0;
            
            while ($iteration < $maxIterations) {
                if ($this->doesTimeMatchCronExpression($date, $cronParts)) {
                    return $date->getTimestamp();
                }
                
                // Move to the next minute
                $date->modify('+1 minute');
                $iteration++;
            }
            
            $this->logger->warning("Could not find a matching time for cron expression {$cronExpr} within 24 hours.");
            return $now + 86400; // Default to 24 hours later if no match found
        } catch (\Exception $e) {
            $this->logger->error('Error calculating next run time: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if a given time matches a cron expression
     *
     * @param \DateTime $date
     * @param array $cronParts
     * @return bool
     */
    protected function doesTimeMatchCronExpression(\DateTime $date, array $cronParts): bool
    {
        $minute = (int)$date->format('i');
        $hour = (int)$date->format('H');
        $dayOfMonth = (int)$date->format('d');
        $month = (int)$date->format('m');
        $dayOfWeek = (int)$date->format('w'); // 0 (Sunday) to 6 (Saturday)
        
        return $this->matchCronPart($cronParts[0], $minute, 0, 59) &&
               $this->matchCronPart($cronParts[1], $hour, 0, 23) &&
               $this->matchCronPart($cronParts[2], $dayOfMonth, 1, 31) &&
               $this->matchCronPart($cronParts[3], $month, 1, 12) &&
               $this->matchCronPart($cronParts[4], $dayOfWeek, 0, 6);
    }
    
    /**
     * Check if a value matches a cron expression part
     *
     * @param string $part
     * @param int $value
     * @param int $min
     * @param int $max
     * @return bool
     */
    protected function matchCronPart(string $part, int $value, int $min, int $max): bool
    {
        // Handle all values (*)
        if ($part === '*') {
            return true;
        }
        
        // Handle lists (e.g., 1,3,5)
        if (strpos($part, ',') !== false) {
            $items = explode(',', $part);
            foreach ($items as $item) {
                if ($this->matchCronPart($item, $value, $min, $max)) {
                    return true;
                }
            }
            return false;
        }
        
        // Handle ranges with steps (e.g., 1-5/2)
        if (strpos($part, '/') !== false) {
            [$range, $step] = explode('/', $part);
            $step = (int)$step;
            
            if ($range === '*') {
                return ($value - $min) % $step === 0;
            }
            
            if (strpos($range, '-') !== false) {
                [$rangeStart, $rangeEnd] = explode('-', $range);
                $rangeStart = $this->convertCronValue($rangeStart, $min);
                $rangeEnd = $this->convertCronValue($rangeEnd, $max);
                
                return $value >= $rangeStart && $value <= $rangeEnd && ($value - $rangeStart) % $step === 0;
            }
            
            return false;
        }
        
        // Handle ranges (e.g., 1-5)
        if (strpos($part, '-') !== false) {
            [$rangeStart, $rangeEnd] = explode('-', $part);
            $rangeStart = $this->convertCronValue($rangeStart, $min);
            $rangeEnd = $this->convertCronValue($rangeEnd, $max);
            
            return $value >= $rangeStart && $value <= $rangeEnd;
        }
        
        // Handle single values
        return $value === $this->convertCronValue($part, $min);
    }
    
    /**
     * Convert a cron value to an integer, handling month and day names
     *
     * @param string $value
     * @param int $default
     * @return int
     */
    protected function convertCronValue(string $value, int $default): int
    {
        if (is_numeric($value)) {
            return (int)$value;
        }
        
        $value = strtolower($value);
        
        // Month names
        $months = [
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
            'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8,
            'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12
        ];
        
        // Day names
        $days = [
            'sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3,
            'thu' => 4, 'fri' => 5, 'sat' => 6
        ];
        
        foreach ($months as $name => $num) {
            if (strpos($value, $name) === 0) {
                return $num;
            }
        }
        
        foreach ($days as $name => $num) {
            if (strpos($value, $name) === 0) {
                return $num;
            }
        }
        
        return $default;
    }
}