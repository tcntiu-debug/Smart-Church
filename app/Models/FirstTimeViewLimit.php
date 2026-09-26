<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCampusScope;

class FirstTimeViewLimit extends Model
{
    use HasFactory;
    use HasCampusScope;

    protected $table = 'first_time_view_limit';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'id',
        'data_limit',
        'campus_id',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * A view limit setting belongs to one campus.
     * Reverse: Campus has many view limit settings.
     * Foreign Key: first_time_view_limit.campus_id references campus.cid
     */
    public function campus()
    {
        return $this->belongsTo(Campus::class, 'campus_id', 'cid');
    }
}
