<?php

namespace Elrayes\Normalizer\Providers;

use Elrayes\Normalizer\Support\NormalizedData;
use Elrayes\Normalizer\Support\Normalizer;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\VarDumper;

class NormalizerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge package config
        $this->mergeConfigFrom(__DIR__ . '/../../config/normalizer.php', 'normalizer');

        // Bind the Normalizer service for the Normalizer facade
        $this->app->singleton('normalizer', fn($app) => new Normalizer());
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../../config/normalizer.php' => config_path('normalizer.php'),
        ], 'normalizer-config');
        $this->handleCasters();
    }

    /**
     * @return void
     */
    public function handleCasters(): void
    {
        $cloner = new VarCloner();
        $cloner->addCasters([
            NormalizedData::class => function (NormalizedData $obj, array $a) {
                unset($a[Caster::PREFIX_VIRTUAL . 'all']);
                return $a;
            },
        ]);

        $dumper = in_array(PHP_SAPI, ['cli', 'phpdbg'], true) ? new CliDumper() : new HtmlDumper();

        VarDumper::setHandler(function ($var) use ($cloner, $dumper) {
            $dumper->dump($cloner->cloneVar($var));
        });
    }
}
