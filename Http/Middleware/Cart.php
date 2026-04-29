<?php

namespace App\Http\Middleware;
use App\Http\Controllers\ShoppingcartController;
use Illuminate\Support\Facades\View;
use Closure;
use Session;
class Cart
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $PaidItemInCart = 0; 
        if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
        {
            foreach(Session::get('ShoppingCart.Cart') as $ShopCart)
            {
                if($ShopCart['ItemPrice'] > 0)
                    $PaidItemInCart = 1;
            }
        }
        config(['PaidItemInCart' => $PaidItemInCart]);
		if(Session::get('eusertype')=="Wholesaler")
		{
			config(['Settings.FREESHIPPING_VALUE' => '']);
		}
		$ObjCart = new ShoppingcartController();
		if($ObjCart->ShowCart($request) == 0)
			return redirect(config('app.url').'/cart');
		View::share('CartDetails', $ObjCart->ShowCart($request));
        return $next($request);
    }
}
