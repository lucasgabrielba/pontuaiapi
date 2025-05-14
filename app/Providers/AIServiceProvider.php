<?php

namespace App\Providers;

use Domains\Finance\Contracts\InvoiceProcessorInterface;
use Domains\Finance\Services\AnalysisService;
use Domains\Finance\Services\MockAIProcessorService;
use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(InvoiceProcessorInterface::class, function ($app) {
            return new MockAIProcessorService();
        });
        
        $this->app->singleton(AnalysisService::class, function ($app) {
            return new AnalysisService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}