<?php

namespace App\Enums;

enum DrivingRecommendation: string
{
    case SafeToDrive = 'safeToDrive';
    case DriveWithCaution = 'driveWithCaution';
    case StopSoon = 'stopSoon';
    case StopImmediately = 'stopImmediately';
    case TowRequired = 'towRequired';
    case Unknown = 'unknown';
}
