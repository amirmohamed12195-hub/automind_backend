<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends UlidModel
{
    protected $table = 'notifications';

    protected function casts(): array
    {
        return ['data_json' => 'array', 'read_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
