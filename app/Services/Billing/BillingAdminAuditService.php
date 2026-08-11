<?php

namespace App\Services\Billing;

use App\Models\BillingAdminAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class BillingAdminAuditService
{
    /** @param array<string, mixed>|null $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(Request $request, string $action, Model|string $resource, ?array $before = null, ?array $after = null): BillingAdminAuditLog
    {
        $type = $resource instanceof Model ? $resource::class : $resource;
        $id = $resource instanceof Model ? (string) $resource->getKey() : null;

        return BillingAdminAuditLog::query()->create([
            'admin_id' => $request->user()?->id,
            'admin_identifier' => $request->user()->email ?? $request->session()->get('automind_admin_username'),
            'action' => $action,
            'resource_type' => $type,
            'resource_id' => $id,
            'before_json' => $before,
            'after_json' => $after,
            'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
        ]);
    }
}
