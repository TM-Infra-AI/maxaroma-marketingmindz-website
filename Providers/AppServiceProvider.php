<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use App\Http\Services\PopUpService;
use App\Http\Services\PopUpServiceContract;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(PopUpServiceContract::class, PopUpService::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
		Schema::defaultStringLength(191);
        //
		/*$Settings = \App\Models\SiteSettings::selectRaw('var_name,setting')->where('status','=','1')->get();
		foreach($Settings as $Setting)
		{
			config([$Setting->var_name => $Setting->setting]);
		}*/
    }
}
