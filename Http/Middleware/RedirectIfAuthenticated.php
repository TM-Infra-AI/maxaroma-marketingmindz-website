<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  ...$guards
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? null : $guards;
		$RedirectModules = ['login.html','register.html','forgot-password.html','wholesaleregister.html'];
        
		if(Auth::check() && in_array($request->route()->uri,$RedirectModules)) {
			//return redirect(RouteServiceProvider::HOME);
			return redirect('/myaccount.html');
		}
		/*if($guards != null)
        {
            foreach ($guards as $guard) {
                if(Auth::guard($guard)->check() && in_array($request->route()->uri,$RedirectModules)) {
                    //return redirect(RouteServiceProvider::HOME);
                    return redirect('/myaccount.html');
                }
            }
        }*/
        return $next($request);
    }
}
