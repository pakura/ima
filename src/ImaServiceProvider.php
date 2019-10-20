<?php
/**
 * Created by PhpStorm.
 * User: pakura
 * Date: 10/20/19
 * Time: 13:22
 */
namespace Pakura\Ima;

use Illuminate\Support\ServiceProvider;

class ImaServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app['ima'] = $this->app->share(function ($app){
            return new Ima;
        });
    }
}
