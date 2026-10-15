<?php

namespace App\Scopes;

use App\Models\Campus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CampusScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        // Single campus: there is nothing to scope by, so the scope is inert.
        if (Campus::isSingle()) {
            return;
        }

        // Only apply if we have an authenticated user
        if (auth()->check()) {
            $user = auth()->user();

            // Super Admin (Super User) can see everything across all campuses
            if (isset($user->member_role) && $user->member_role === 'Super User') {
                return;
            }

            // Campus of the logged-in user (falls back for 0/NULL campuses)
            $campusId = Campus::currentId();

            // Only apply if campus_id is set
            if ($campusId) {
                $table = $model->getTable();
                $builder->where($table . '.campus_id', $campusId);
            }
        }
    }
}
