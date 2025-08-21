<?php
declare(strict_types=1);

namespace Blackbird\SelectiveCron\Model\ResourceModel\Schedule;

use Blackbird\SelectiveCron\Model\ResourceModel\Schedule as ScheduleResource;
use Blackbird\SelectiveCron\Model\Schedule;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(Schedule::class, ScheduleResource::class);
    }
}
