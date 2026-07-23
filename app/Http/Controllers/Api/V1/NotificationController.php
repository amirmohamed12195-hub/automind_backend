<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\UserNotification;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class NotificationController
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'cursor' => ['sometimes', 'nullable', 'string', 'max:512'],
            'limit' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $page = UserNotification::query()->where('user_id', $request->user()->id)->latest('id')->cursorPaginate($data['limit'] ?? 30, ['*'], 'cursor', $data['cursor'] ?? null);
        $locale = app()->getLocale();

        return ApiResponse::success(collect($page->items())->map(fn ($n) => ['id' => (string) $n->id, 'type' => $n->type, 'title' => $n->{"title_$locale"}, 'body' => $n->{"body_$locale"}, 'data' => $n->data_json, 'readAt' => $n->read_at?->utc()->toIso8601ZuluString(), 'createdAt' => $n->created_at?->utc()->toIso8601ZuluString()])->all(), 200, ['nextCursor' => $page->nextCursor()?->encode()]);
    }

    public function read(Request $request, UserNotification $notification)
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(404);
        } $notification->update(['read_at' => $notification->read_at ?? now()]);

        return ApiResponse::success(['id' => (string) $notification->id, 'readAt' => $notification->read_at->utc()->toIso8601ZuluString()]);
    }

    public function readAll(Request $request)
    {
        UserNotification::query()->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);

        return response()->noContent();
    }
}
