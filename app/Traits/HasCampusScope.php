<?php

namespace App\Traits;

use App\Scopes\CampusScope;

trait HasCampusScope
{
    /**
     * Boot the HasCampusScope trait for a model.
     *
     * @return void
     */
    public static function bootHasCampusScope()
    {
        static::addGlobalScope(new CampusScope);
    }

    /**
     * Get the name of the "campus_id" column.
     *
     * @return string
     */
    public function getCampusColumnName()
    {
        return defined('static::CAMPUS_COLUMN') ? static::CAMPUS_COLUMN : 'campus_id';
    }

    /**
     * Scope a query to only include records for a specific campus.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $campusId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForCampus($query, $campusId)
    {
        return $query->where($this->getTable() . '.' . $this->getCampusColumnName(), $campusId);
    }

    /**
     * Scope a query without the campus scope (for admins to see all).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithoutCampusScope($query)
    {
        return $query->withoutGlobalScope(CampusScope::class);
    }
}
