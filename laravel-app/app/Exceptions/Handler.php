<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Exception  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Exception
     */
    public function render($request, Exception $exception)
    {
        if (! $request->expectsJson() && ! $request->ajax() && $this->isForbidden($exception)) {
            $message = trim((string) $exception->getMessage());
            if ($message === '') {
                $message = 'You do not have access to that page.';
            }
            if (Auth::check()) {
                $home = \App\Support\InternCompliance::homeUrl(Auth::user());

                return redirect($home)->with('not_permitted', $message);
            }

            return redirect()->guest(url('/login?redirect='.rawurlencode($request->getRequestUri())))
                ->with('not_permitted', $message);
        }

        return parent::render($request, $exception);
    }

    protected function isForbidden(Exception $exception)
    {
        if ($exception instanceof AuthorizationException) {
            return true;
        }

        return $exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 403;
    }
}
