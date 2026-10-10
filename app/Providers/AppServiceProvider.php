<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Unread counter for the in-app notification bell (layouts.app navbar).
        View::composer('layouts.app', function ($view) {
            $unread = 0;

            if (Auth::check()) {
                try {
                    $unread = DB::table('app_notifications')
                        ->where('tiu_member_id', Auth::id())
                        ->whereNull('read_at')
                        ->count();
                } catch (\Exception $e) {
                    // Table not migrated yet - keep the layout rendering.
                    $unread = 0;
                }
            }

            $view->with('unreadNotifications', $unread);
        });
    }
}
