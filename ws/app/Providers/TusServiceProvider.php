<?php
/**
 * RNADetector Web Service
 *
 * @author S. Alaimo, Ph.D. <alaimos at gmail dot com>
 */

namespace App\Providers;

use TusPhp\Config as TusConfig;
use TusPhp\Tus\Server as TusServer;
use Illuminate\Support\ServiceProvider;

class TusServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        if (!file_exists(storage_path('app/tus_cache/'))) {
            /** @noinspection MkdirRaceConditionInspection */
            @mkdir(storage_path('app/tus_cache/'), 0777, true);
            @chmod(storage_path('app/tus_cache/'), 0777);
        }
        TusConfig::set(
            [
                'file' => [
                    'dir'  => storage_path('app/tus_cache/'),
                    'name' => 'tus_php.server.cache',
                ],
            ]
        );
        $this->app->singleton(
            'tus-server',
            static function ($app) {
                return new TusServer();
            }
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
