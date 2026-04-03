<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIremeUser
{
    /**
     * Ensure the user is an Ireme (platform) user (hotel_id is null).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        if (! auth()->user()->isIremeUser()) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
