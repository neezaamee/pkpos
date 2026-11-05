<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
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
        EloquentBuilder::macro('whereLike', function ($column, $value) {
            $operator = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
            return $this->where($column, $operator, $value);
        });

        EloquentBuilder::macro('orWhereLike', function ($column, $value) {
            $operator = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
            return $this->orWhere($column, $operator, $value);
        });

        QueryBuilder::macro('whereLike', function ($column, $value) {
            $operator = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
            return $this->where($column, $operator, $value);
        });

        QueryBuilder::macro('orWhereLike', function ($column, $value) {
            $operator = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
            return $this->orWhere($column, $operator, $value);
        });
    }
}
