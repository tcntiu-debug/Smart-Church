<?php

namespace App\Scopes;

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
        // Only apply if we have an authenticated user
        if (auth()->check()) {
            $user = auth()->user();
            
            // Super Admin (Super User) can see everything across all campuses
            if (isset($user->member_role) && $user->member_role === 'Super User') {
                return;
            }
            
            // Get the user's campus_id
            $campusId = $user->campus_id ?? null;
            
            // Only apply if campus_id is set
            if ($campusId) {
                $table = $model->getTable();
                $builder->where($table . '.campus_id', $campusId);
            }
        }
    }
}
