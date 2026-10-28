<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Atalho do menu (layout): campanhas em que a pessoa está, como mestre ou jogador ativo
        \Illuminate\Support\Facades\View::composer('layout', function ($view) {
            $campanhas = collect();
            try {
                if (auth()->check() && \Illuminate\Support\Facades\Schema::hasTable('campanhas')) {
                    $uid = auth()->id();
                    $campanhas = \App\Models\Campanha::query()
                        ->where(function ($q) use ($uid) {
                            $q->where('mestre_id', $uid)
                              ->orWhereHas('participantes', fn ($p) => $p->where('user_id', $uid)->where('status', 'ativo'));
                        })
                        ->orderBy('nome')->limit(12)->get(['id', 'nome', 'mestre_id', 'sessao_ativa']);
                }
            } catch (\Throwable $e) {
                // sem banco ou tabela: o menu segue sem o atalho
            }
            $view->with('campanhasAtalho', $campanhas);
        });
    }
}