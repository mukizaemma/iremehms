<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;
use Illuminate\Http\Request;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Prevent raw SQL/stack traces from leaking to users on unique constraint violations.
        $this->renderable(function (UniqueConstraintViolationException $e, Request $request) {
            $messageValue = null;
            if (preg_match("/Duplicate entry '([^']*)'/i", $e->getMessage(), $m)) {
                $messageValue = $m[1] ?? null;
            }

            $safeMessage = $messageValue && trim($messageValue) !== ''
                ? "That value \"{$messageValue}\" already exists. Please use a different value."
                : 'That value already exists. Please use a different value.';

            $isLivewire = str_contains((string) $request->path(), 'livewire')
                || $request->hasHeader('x-livewire')
                || $request->header('X-Livewire') === 'true';

            if ($isLivewire || $request->expectsJson()) {
                return response()->json(['message' => $safeMessage], 422);
            }

            return redirect()->back()->with('error', $safeMessage);
        });

        $this->renderable(function (QueryException $e, Request $request) {
            // Fallback for databases that don't throw UniqueConstraintViolationException directly.
            if ((string) $e->getCode() !== '23000' || ! str_contains(strtolower($e->getMessage()), 'duplicate entry')) {
                return null;
            }

            $safeMessage = 'That value already exists. Please use a different value.';

            $isLivewire = str_contains((string) $request->path(), 'livewire')
                || $request->hasHeader('x-livewire')
                || $request->header('X-Livewire') === 'true';

            if ($isLivewire || $request->expectsJson()) {
                return response()->json(['message' => $safeMessage], 422);
            }

            return redirect()->back()->with('error', $safeMessage);
        });
    }

    /**
     * When a GET request hits a Livewire update route (e.g. after refresh or back),
     * redirect to the welcome page instead of showing "Method Not Allowed".
     */
    public function render($request, Throwable $e)
    {
        if ($e instanceof MethodNotAllowedHttpException
            && $request->isMethod('GET')
            && str_contains($request->path(), 'livewire') && str_contains($request->path(), 'update')) {
            return redirect()->route('welcome');
        }

        return parent::render($request, $e);
    }
}
