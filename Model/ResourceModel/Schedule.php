<?php
declare(strict_types=1);

namespace Blackbird\SelectiveCron\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;

class Schedule extends AbstractDb
{
    public function __construct(
        Context $context,
        ?string $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
    }

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init('selective_cron_schedule', 'schedule_id');
    }

    /**
     * Try to set job status using atomic DB operation
     *
     * @param int|string $scheduleId
     * @param string $newStatus
     * @param string $currentStatus
     * @return bool
     */
    public function trySetJobStatusAtomic(int|string $scheduleId, string $newStatus, string $currentStatus): bool
    {
        $connection = $this->getConnection();
        $result = $connection->update(
            $this->getTable('selective_cron_schedule'),
            ['status' => $newStatus],
            ['schedule_id = ?' => (int)$scheduleId, 'status = ?' => $currentStatus]
        );
        return (bool)$result;
    }

    /**
     * Truncate the selective_cron_schedule table
     *
     * @return void
     */
    public function truncateSchedule(): void
    {
        $this->getConnection()->truncateTable($this->getTable('selective_cron_schedule'));
    }
}
