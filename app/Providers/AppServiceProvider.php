<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            \App\Adapters\Contracts\InputNormalizerInterface::class,
            \App\Adapters\Normalizers\InputNormalizer::class
        );

        $this->app->singleton(
            \App\Adapters\Contracts\OutputFormatterInterface::class,
            \App\Adapters\Formatters\ClientOutputFormatter::class
        );

        $this->app->singleton(\App\Adapters\HttpWebhookAdapter::class);
        $this->app->singleton(\App\Adapters\Conversational\MockAsteriskChatAdapter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $isLocalHost = in_array(request()->getHost(), ['localhost', '127.0.0.1'], true);
        if (
            !$isLocalHost && (
                str_starts_with(config('app.url', ''), 'https://') ||
                request()->header('X-Forwarded-Proto') === 'https'
            )
        ) {
            URL::forceScheme('https');
        }

        \Filament\Tables\Table::configureUsing(function (\Filament\Tables\Table $table): void {
            $table
                ->defaultPaginationPageOption(50)
                ->paginationPageOptions([10, 25, 50, 100]);
        });
    }
}
