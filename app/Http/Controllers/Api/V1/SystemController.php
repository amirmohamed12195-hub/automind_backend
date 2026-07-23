<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SystemController
{
    public function health(Request $request)
    {
        $type = $request->validate([
            'type' => ['sometimes', 'in:liveness,readiness'],
        ])['type'] ?? 'readiness';

        if ($type === 'liveness') {
            return ApiResponse::success(['status' => 'alive']);
        }
        $checks = [];
        try {
            DB::select('select 1');
            $checks['database'] = 'ok';
        } catch (Throwable) {
            $checks['database'] = 'failed';
        }
        try {
            Storage::disk(config('automind.media.disk'))->exists('.');
            $checks['storage'] = 'ok';
        } catch (Throwable) {
            $checks['storage'] = 'failed';
        }
        if (config('cache.default') === 'redis') {
            try {
                app('redis')->connection()->ping();
                $checks['redis'] = 'ok';
                Queue::size('diagnostic-ai');
                $checks['queue'] = 'ok';
            } catch (Throwable) {
                $checks['redis'] = 'failed';
                $checks['queue'] = 'failed';
            }
        } else {
            $checks['redis'] = 'not-required';
            $checks['queue'] = config('queue.default') === 'sync' ? 'sync' : 'not-checked';
        }
        $ready = ! in_array('failed', $checks, true);

        return ApiResponse::success(['status' => $ready ? 'ready' : 'not_ready', 'checks' => $checks], $ready ? 200 : 503);
    }

    public function version()
    {
        return ApiResponse::success(['apiVersion' => config('automind.api_version'), 'laravelVersion' => app()->version(), 'environment' => app()->environment()]);
    }
}
