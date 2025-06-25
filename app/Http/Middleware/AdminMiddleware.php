<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return response()->json([
                'error' => 'Não autenticado'
            ], 401);
        }

        $user = auth()->user();
        
        if (!$user->hasAnyRole(['admin', 'super_admin'])) {
            return response()->json([
                'error' => 'Acesso negado. Apenas administradores podem acessar esta funcionalidade.'
            ], 403);
        }

        return $next($request);
    }
}