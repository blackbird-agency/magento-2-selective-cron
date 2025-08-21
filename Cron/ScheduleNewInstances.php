<?php
declare(strict_types=1);

namespace Blackbird\SelectiveCron\Cron;

use Blackbird\SelectiveCron\Service\ScheduleService;
use Psr\Log\LoggerInterface;

class ScheduleNewInstances
{
    public function __construct(
        protected readonly ScheduleService $scheduleService,
        protected readonly LoggerInterface $logger
    ) {
    }

    /**
     * Schedule new instances of selected cron jobs
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            $this->scheduleService->scheduleNewJobInstances();
        } catch (\Exception $e) {
            $this->logger->error('Error scheduling new cron job instances: ' . $e->getMessage());
        }
    }
}
