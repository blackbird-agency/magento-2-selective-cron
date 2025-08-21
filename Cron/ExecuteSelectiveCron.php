<?php
declare(strict_types=1);

namespace Blackbird\SelectiveCron\Cron;

use Blackbird\SelectiveCron\Service\CronService;
use Psr\Log\LoggerInterface;

class ExecuteSelectiveCron
{
    public function __construct(
        protected readonly CronService $cronService,
        protected readonly LoggerInterface $logger
    ) {
    }

    /**
     * Execute selective cron jobs
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            $this->cronService->executeSelectedCronJobs();
        } catch (\Exception $e) {
            $this->logger->error('Error executing selective cron jobs: ' . $e->getMessage());
        }
    }
}
