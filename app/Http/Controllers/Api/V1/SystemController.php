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
            } catch (Throwable) {
                $checks['redis'] = 'failed';
            }
        } else {
            $checks['redis'] = 'not-required';
        }
        $queue = $this->queueReadiness();
        $checks['queue'] = $queue['status'];
        $ready = ! in_array('failed', $checks, true);

        return ApiResponse::success([
            'status' => $ready ? 'ready' : 'not_ready',
            'checks' => $checks,
            'queue' => ['connection' => $queue['connection'], 'depth' => $queue['depth']],
        ], $ready ? 200 : 503);
    }

    public function version()
    {
        return ApiResponse::success(['apiVersion' => config('automind.api_version'), 'laravelVersion' => app()->version(), 'environment' => app()->environment()]);
    }

    /** @return array{status: string, connection: string, depth: ?int} */
    private function queueReadiness(): array
    {
        $connection = (string) config('queue.default');
        $driver = (string) config("queue.connections.$connection.driver");
        if ($driver === 'sync') {
            return ['status' => 'sync', 'connection' => $connection, 'depth' => 0];
        }

        try {
            $queues = config('automind.queue.critical', ['diagnostic-ai']);
            $queues = is_array($queues) ? $queues : ['diagnostic-ai'];
            $queueConnection = Queue::connection($connection);
            $depth = array_sum(array_map(
                fn (string $queue): int => $queueConnection->size($queue),
                $queues,
            ));

            if ($driver === 'database' && $this->databaseQueueHasStalledJob($connection, $queues)) {
                return ['status' => 'failed', 'connection' => $connection, 'depth' => $depth];
            }

            return ['status' => 'ok', 'connection' => $connection, 'depth' => $depth];
        } catch (Throwable) {
            return ['status' => 'failed', 'connection' => $connection, 'depth' => null];
        }
    }

    private function databaseQueueHasStalledJob(string $connection, array $queues): bool
    {
        $table = (string) config("queue.connections.$connection.table", 'jobs');
        $databaseConnection = config("queue.connections.$connection.connection");
        $staleAfter = max(15, (int) config('automind.queue.stale_after_seconds', 90));

        return DB::connection($databaseConnection)
            ->table($table)
            ->whereIn('queue', $queues)
            ->whereNull('reserved_at')
            ->where('available_at', '<=', time() - $staleAfter)
            ->exists();
    }
}
