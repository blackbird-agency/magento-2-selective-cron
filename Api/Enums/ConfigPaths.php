<?php

namespace Blackbird\SelectiveCron\Api\Enums;

enum ConfigPaths: string
{
    case ENABLED = 'system/selective_cron/enabled';
    case SELECTED_JOBS = 'system/selective_cron/selected_jobs';
}
