<?php

namespace Blackbird\SelectiveCron\Api\Enums;

enum StatusEnum: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case SUCCESS = 'success';
    case MISSED = 'missed';
    case ERROR = 'error';
}