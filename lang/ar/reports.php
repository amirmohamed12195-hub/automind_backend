<?php

return [
    'missing_evidence' => [
        'description' => 'وصف المشكلة',
        'symptoms' => 'العلامات التي لاحظتها',
        'engineSound' => 'تسجيل صوت المحرك',
        'photos' => 'صور',
        'obd' => 'قراءات جهاز فحص السيارة',
        'mileage' => 'المسافة المقطوعة',
        'vin' => 'رقم الشاسيه',
        'serviceHistory' => 'سجل الصيانة',
    ],
    'category' => [
        'part' => 'قطعة غيار',
        'labor' => 'تكلفة العمل',
        'diagnostic' => 'فحص السيارة',
        'consumables' => 'مواد الصيانة',
        'tax' => 'ضريبة',
        'fees' => 'رسوم',
        'service' => 'خدمة',
    ],
    'unit' => [
        'each' => 'قطعة',
        'job' => 'خدمة',
        'hour' => 'ساعة',
        'liter' => 'لتر',
        'set' => 'طقم',
        'piece' => 'قطعة',
        'unit' => 'وحدة',
    ],
    'condition' => [
        'new' => 'جديدة',
        'used' => 'مستعملة',
        'refurbished' => 'مجددة',
        'remanufactured' => 'معاد تصنيعها',
        'unknown' => 'غير محددة',
    ],
    'condition_assumption' => 'حُسبت تكلفة :part لقطعة بحالة :condition، بالاعتماد على أسعار من :count مواقع مستقلة.',
];
