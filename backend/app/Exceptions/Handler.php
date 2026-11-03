<?php
namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

class Handler extends ExceptionHandler
{
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        return redirect()->guest(route('login'));
    }

    public function register(): void
    {
        $this->renderable(function (NotFoundHttpException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Not Found'], 404);
            }
        });

        $this->renderable(function (ModelNotFoundException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Resource not found'], 404);
            }
        });

        $this->renderable(function (MethodNotAllowedHttpException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Method not allowed'], 405);
            }
        });

        $this->renderable(function (ValidationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $errors = $e->errors();
                $firstError = collect($errors)->flatten()->first() ?? 'Validation Error';
                return response()->json([
                    'message' => $firstError,
                    'errors' => $errors,
                ], 422);
            }
        });

        $this->renderable(function (HttpException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => $e->getMessage() ?: 'Server Error'], $e->getStatusCode());
            }
        });

        $this->renderable(function (ThrottleRequestsException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Too many requests. Please try again later.'], 429);
            }
        });

        $this->renderable(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Session expired. Please refresh the page.'], 419);
            }
        });
    }
}
