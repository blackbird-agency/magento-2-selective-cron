<?php
declare(strict_types=1);

namespace Blackbird\SelectiveCron\Service;

use Blackbird\SelectiveCron\Api\Enums\StatusEnum;
use Blackbird\SelectiveCron\Model\Config;
use Blackbird\SelectiveCron\Model\Schedule;
use Blackbird\SelectiveCron\Model\ResourceModel\Schedule\CollectionFactory as ScheduleCollectionFactory;
use Magento\Cron\Model\ConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

class CronService
{
    public function __construct(
        protected readonly Config $config,
        protected readonly ConfigInterface $cronConfig,
        protected readonly LoggerInterface $logger,
        protected readonly State $state,
        protected readonly ScheduleCollectionFactory $scheduleCollectionFactory,
        protected readonly DateTime $dateTime,
        protected readonly ScheduleService $scheduleService
    ) {
    }

    /**
     * Execute only selected cron jobs
     *
     * @return void
     * @throws LocalizedException
     */
    public function executeSelectedCronJobs(): void
    {
        if (!$this->config->isEnabled()) {
            $this->logger->info('Selective Cron is disabled. No jobs were executed.');
            return;
        }

        $this->logger->info('Starting selective cron execution.');

        $this->scheduleService->populateSchedule();

        $jobsExecuted = 0;
        $jobsSkipped = 0;
        $jobsFailed = 0;

        // Get pending jobs that are due to run
        $pendingJobs = $this->getPendingJobs();
        
        if (empty($pendingJobs)) {
            $this->logger->info('No pending jobs to execute at this time.');
            return;
        }

        $this->logger->info('Found ' . count($pendingJobs) . ' pending jobs to execute.');

        foreach ($pendingJobs as $job) {
            $jobCode = $job->getJobCode();
            
            // Try to lock the job (change status from pending to running)
            if (!$job->tryLockJob()) {
                $this->logger->info("Job {$jobCode} is already being processed by another execution. Skipping.");
                $jobsSkipped++;
                continue;
            }

            try {
                $this->logger->info("Executing job: {$jobCode}");
                
                // Find the job configuration
                $jobConfig = $this->getJobConfig($jobCode);
                if (!$jobConfig) {
                    throw new LocalizedException(__('Job configuration not found for %1.', $jobCode));
                }
                
                // Execute the job
                $this->executeJob($jobConfig);
                
                // Update job status to success
                $job->setStatus(StatusEnum::SUCCESS->value)
                    ->setExecutedAt(date('Y-m-d H:i:s'))
                    ->setFinishedAt(date('Y-m-d H:i:s'))
                    ->save();
                
                $jobsExecuted++;
                $this->logger->info("Job {$jobCode} executed successfully.");
            } catch (\Exception $e) {
                // Update job status to error
                $job->setStatus(StatusEnum::ERROR->value)
                    ->setMessages($e->getMessage())
                    ->setFinishedAt(date('Y-m-d H:i:s'))
                    ->save();
                
                $jobsFailed++;
                $this->logger->error("Error executing job {$jobCode}: " . $e->getMessage());
            }
        }

        $this->logger->info("Selective cron execution completed. Jobs executed: {$jobsExecuted}, skipped: {$jobsSkipped}, failed: {$jobsFailed}");
    }

    /**
     * Execute a single cron job
     *
     * @param array $jobConfig
     * @return void
     * @throws \Exception
     */
    protected function executeJob(array $jobConfig): void
    {
        if (!isset($jobConfig['instance']) || !isset($jobConfig['method'])) {
            throw new LocalizedException(__('Invalid job configuration.'));
        }

        $instance = ObjectManager::getInstance()->get($jobConfig['instance']);
        $method = $jobConfig['method'];

        if (!method_exists($instance, $method)) {
            throw new LocalizedException(__('Method %1::%2 does not exist.', $jobConfig['instance'], $method));
        }

        $this->state->emulateAreaCode('crontab', function () use ($instance, $method) {
            $instance->$method();
        });
    }
    
    /**
     * Check if the schedule table is empty
     *
     * @return bool
     */
    protected function isScheduleTableEmpty(): bool
    {
        $collection = $this->scheduleCollectionFactory->create();
        return $collection->getSize() === 0;
    }
    
    /**
     * Get pending jobs that are due to run
     *
     * @return array
     */
    protected function getPendingJobs(): array
    {
        $now = date('Y-m-d H:i:s');
        
        $collection = $this->scheduleCollectionFactory->create();
        $collection->addFieldToFilter('status', StatusEnum::PENDING->value)
            ->addFieldToFilter('scheduled_at', ['lteq' => $now])
            ->setOrder('scheduled_at', 'ASC');
            
        return $collection->getItems();
    }
    
    /**
     * Get job configuration by job code
     *
     * @param string $jobCode
     * @return array|null
     */
    protected function getJobConfig(string $jobCode): ?array
    {
        $jobGroups = $this->cronConfig->getJobs();
        foreach ($jobGroups as $groupId => $jobs) {
            if (isset($jobs[$jobCode])) {
                return $jobs[$jobCode];
            }
        }
        
        return null;
    }
}
