<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHotelUser
{
    /**
     * Ensure the user is a hotel user (has hotel_id) and hotel is active.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        if ($user->isIremeUser()) {
            return redirect()->route('ireme.dashboard');
        }

        if (! $user->hotel_id) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->with('error', 'Your account is not assigned to a hotel.');
        }

        $hotel = $user->hotel;
        if (! $hotel) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->with('error', 'Hotel not found.');
        }

        if (($hotel->subscription_status ?? 'active') !== 'active') {
            return redirect()->route('login')->with('error', 'Hotel access is suspended. Please contact Ireme.');
        }

        session(['current_hotel_id' => $user->hotel_id]);

        return $next($request);
    }
}
