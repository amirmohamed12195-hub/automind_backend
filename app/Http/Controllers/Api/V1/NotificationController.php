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
        $query = UserNotification::query()->where('user_id', $request->user()->id);
        $unreadCount = (clone $query)->whereNull('read_at')->count();
        $page = $query->latest('id')->cursorPaginate($data['limit'] ?? 30, ['*'], 'cursor', $data['cursor'] ?? null);

        return ApiResponse::success(collect($page->items())->map(fn ($notification) => $this->resource($notification))->all(), 200, ['nextCursor' => $page->nextCursor()?->encode(), 'unreadCount' => $unreadCount]);
    }

    public function read(Request $request, UserNotification $notification)
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(404);
        } $notification->update(['read_at' => $notification->read_at ?? now()]);

        return ApiResponse::success($this->resource($notification));
    }

    public function readAll(Request $request)
    {
        UserNotification::query()->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);

        return response()->noContent();
    }

    private function resource(UserNotification $notification): array
    {
        $locale = app()->getLocale();

        return [
            'id' => (string) $notification->id,
            'type' => $notification->type,
            'title' => $notification->{"title_$locale"},
            'body' => $notification->{"body_$locale"},
            'data' => $notification->data_json,
            'readAt' => $notification->read_at?->utc()->toIso8601ZuluString(),
            'createdAt' => $notification->created_at?->utc()->toIso8601ZuluString(),
        ];
    }
}
