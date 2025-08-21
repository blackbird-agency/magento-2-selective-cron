<?php
declare(strict_types=1);

namespace Blackbird\SelectiveCron\Model;

use Blackbird\SelectiveCron\Api\Enums\StatusEnum;
use Blackbird\SelectiveCron\Model\ResourceModel\Schedule as ScheduleResource;
use Magento\Framework\Model\AbstractModel;

class Schedule extends AbstractModel
{

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(ScheduleResource::class);
    }

    /**
     * Sets a job to STATUS_RUNNING only if it is currently in STATUS_PENDING.
     *
     * Returns true if status was changed and false otherwise.
     *
     * @return boolean
     */
    public function tryLockJob(): bool
    {
        /** @var ScheduleResource $scheduleResource */
        $scheduleResource = $this->getResource();

        // Change statuses from running to error for terminated jobs
        $scheduleResource->getConnection()->update(
            $scheduleResource->getTable('selective_cron_schedule'),
            ['status' => StatusEnum::ERROR->value],
            ['job_code = ?' => $this->getJobCode(), 'status = ?' => StatusEnum::RUNNING->value]
        );

        // Change status from pending to running for ran jobs
        $result = $scheduleResource->trySetJobStatusAtomic(
            $this->getId(),
            StatusEnum::RUNNING->value,
            StatusEnum::PENDING->value
        );

        if ($result) {
            $this->setStatus(StatusEnum::RUNNING->value);
            return true;
        }
        return false;
    }
}
