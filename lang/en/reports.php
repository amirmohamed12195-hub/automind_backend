<?php

return [
    'missing_evidence' => ['description' => 'Description of the issue', 'symptoms' => 'Selected symptoms', 'engineSound' => 'Engine sound recording', 'photos' => 'Photos', 'obd' => 'OBD readings', 'mileage' => 'Mileage', 'vin' => 'Vehicle identification number', 'serviceHistory' => 'Service history'],
    'category' => ['part' => 'Replacement part', 'labor' => 'Labor', 'diagnostic' => 'Diagnostic inspection', 'consumables' => 'Consumables', 'tax' => 'Tax', 'fees' => 'Fees', 'service' => 'Service'],
    'unit' => ['each' => 'each', 'job' => 'job', 'hour' => 'hour', 'liter' => 'liter', 'set' => 'set', 'piece' => 'piece', 'unit' => 'unit'],
    'condition' => ['new' => 'new', 'used' => 'used', 'refurbished' => 'refurbished', 'remanufactured' => 'remanufactured', 'unknown' => 'unspecified'],
    'condition_assumption' => ':part uses :condition-condition prices from :count independent source domain(s).',
];
