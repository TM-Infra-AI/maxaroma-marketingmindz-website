<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Symfony\Component\HttpKernel\Exception\HttpException;

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


        $this->renderable(function (NotFoundHttpException $e, $request) {
            return response()->view('errors.404', [], 404);
        });

        /*$this->renderable(function (HttpException $e, $request) {
            if ($e->getStatusCode() == 419) {
                return redirect('/login.html')->with('error','Your session expired due to inactivity. Please login again.');
                // $csrf_error = "Oops! Seems you couldn't submit form for a long time. Please try again.";
                // return redirect($request->fullUrl())->with($csrf_error);
            }
        });*/
    }
}
