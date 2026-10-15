<?php

namespace App\Providers;

use App\Models\Campus;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\Paginator;
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
        // Every campus filter in the app goes through this macro, so the single
        // switch in config/campus.php retires (or restores) all of them at once:
        //
        //   ->where('ft.campus_id', $userCampusId)   becomes
        //   ->forCurrentCampus('ft.campus_id')
        //
        // In single campus mode Campus::filterId() is null and the where() is
        // never applied, so no query is narrowed by a campus the church no
        // longer uses - including rows whose campus_id is 0/NULL, which the old
        // filters silently hid.
        foreach ([QueryBuilder::class, EloquentBuilder::class] as $builder) {
            $builder::macro('forCurrentCampus', function ($column = 'campus_id', $requested = null) {
                /** @var \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $this */
                $campusId = Campus::filterId($requested);

                if ($campusId) {
                    $this->where($column, $campusId);
                }

                return $this;
            });
        }
        // The whole app is served off Bootstrap 4.1 (layouts.app), so pages that
        // paginate must not use the framework's Tailwind-flavoured default.
        Paginator::useBootstrap();

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
