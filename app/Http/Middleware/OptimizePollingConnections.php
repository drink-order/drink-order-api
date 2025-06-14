<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OptimizePollingConnections
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // For polling endpoints, ensure connections are closed quickly
        if ($this->isPollingEndpoint($request)) {
            try {
                // Set a shorter timeout for polling queries
                DB::statement("SET statement_timeout = '5s'");
                
                // After response, disconnect to prevent connection buildup
                $response->headers->set('Connection', 'close');
                
                // Force close PostgreSQL connections for polling endpoints
                DB::disconnect('pgsql');
                
            } catch (\Exception $e) {
                Log::warning('Connection cleanup warning', ['error' => $e->getMessage()]);
            }
        }
        
        return $response;
    }
    
    private function isPollingEndpoint(Request $request): bool
    {
        $pollingPaths = [
            'notifications/unread-count',
            'notifications/latest'
        ];
        
        foreach ($pollingPaths as $path) {
            if (str_contains($request->path(), $path)) {
                return true;
            }
        }
        
        return false;
    }
}
