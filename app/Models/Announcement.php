<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCampusScope;

class Announcement extends Model
{
    use HasFactory;
    use HasCampusScope;

    protected $table = 'announcements';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'id',
        'title',
        'content',
        'campus_id',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * An announcement belongs to one campus.
     * Reverse: Campus has many announcements.
     * Foreign Key: announcements.campus_id references campus.cid
     */
    public function campus()
    {
        return $this->belongsTo(Campus::class, 'campus_id', 'cid');
    }
}
