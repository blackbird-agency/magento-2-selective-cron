<?php

namespace Blackbird\SelectiveCron\Api\Enums;

enum ScheduleEnum: string
{
    case DEFAULT_CRON_EXPRESSION = '* * * * *';
}