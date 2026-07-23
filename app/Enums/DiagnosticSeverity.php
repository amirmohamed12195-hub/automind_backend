<?php

namespace App\Enums;

enum DiagnosticSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';
    case Unknown = 'unknown';
}
