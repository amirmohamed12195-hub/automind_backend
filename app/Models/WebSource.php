<?php

namespace App\Models;

class WebSource extends UlidModel
{
    protected function casts(): array
    {
        return ['citation_metadata_json' => 'array', 'retrieved_at' => 'datetime', 'source_date' => 'datetime'];
    }
}
