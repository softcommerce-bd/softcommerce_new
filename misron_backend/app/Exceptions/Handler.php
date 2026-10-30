<?php

namespace App\Exceptions;

use App\Utility\NgeniusUtility;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {
        if ($e instanceof Redirectingexception) {
            return redirect()->back();
        }

        // Force JSON response for ALL API routes
        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->handleApiException($request, $e);
        }

        if($this->isHttpException($e))
        {
            if ($request->is('customer-products/admin')) {
                return NgeniusUtility::initPayment();
            }
        }

        return parent::render($request, $e);
    }

    /**
     * Handle API exceptions and return JSON response
     */
    private function handleApiException($request, Throwable $e)
    {
        // Handle validation exceptions
        if ($e instanceof ValidationException) {
            return response()->json([
                'data' => [],
                'success' => false,
                'status' => 422,
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        }

        // Handle authentication exceptions
        if ($e instanceof AuthenticationException) {
            return response()->json([
                'data' => [],
                'success' => false,
                'status' => 401,
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Handle not found exceptions
        if ($e instanceof NotFoundHttpException) {
            return response()->json([
                'data' => [],
                'success' => false,
                'status' => 404,
                'message' => 'Route not found'
            ], 404);
        }

        // Handle method not allowed
        if ($e instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'data' => [],
                'success' => false,
                'status' => 405,
                'message' => 'Method not allowed'
            ], 405);
        }

        // Handle HTTP exceptions
        if ($this->isHttpException($e)) {
            $statusCode = $e->getStatusCode();
            return response()->json([
                'data' => [],
                'success' => false,
                'status' => $statusCode,
                'message' => $e->getMessage() ?: $this->getStatusMessage($statusCode)
            ], $statusCode);
        }

        // Handle all other exceptions
        $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
        
        return response()->json([
            'data' => [],
            'success' => false,
            'status' => $statusCode,
            'message' => config('app.debug') ? $e->getMessage() : 'Server Error',
            // Uncomment the next line for debugging only
            // 'trace' => config('app.debug') ? $e->getTrace() : []
        ], $statusCode);
    }

    /**
     * Get status message for common HTTP codes
     */
    private function getStatusMessage($statusCode)
    {
        $messages = [
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not found',
            405 => 'Method not allowed',
            408 => 'Request timeout',
            429 => 'Too many requests',
            500 => 'Internal server error',
            502 => 'Bad gateway',
            503 => 'Service unavailable',
        ];

        return $messages[$statusCode] ?? 'Error occurred';
    }
}