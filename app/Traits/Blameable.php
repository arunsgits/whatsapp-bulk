<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

trait Blameable
{
    public static function bootBlameable()
    {
        static::creating(function ($model) {
            if (Auth::check()) {
                $table = $model->getTable();
                if (Schema::hasColumn($table, 'created_by') && !$model->created_by) {
                    $model->created_by = Auth::id();
                }
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $table = $model->getTable();
                if (Schema::hasColumn($table, 'updated_by')) {
                    $model->updated_by = Auth::id();
                }
            }
        });
    }
}
