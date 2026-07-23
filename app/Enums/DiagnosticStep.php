<?php

namespace App\Enums;

enum DiagnosticStep: string
{
    case PreparingData = 'preparingData';
    case AnalyzingDescription = 'analyzingDescription';
    case AnalyzingSound = 'analyzingSound';
    case AnalyzingPhotos = 'analyzingPhotos';
    case ReadingObd = 'readingObd';
    case ResearchingPrices = 'researchingPrices';
    case BuildingReport = 'buildingReport';
    case Completed = 'completed';
    case Failed = 'failed';
}
