<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResourceShare extends Model
{
    use HasFactory;

    protected $table = 'resource_shares';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'id',
        'resource_id',
        'member_id',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * A resource share record belongs to a shared resource.
     * Reverse: SharedResource has many resource shares.
     * Foreign Key: resource_shares.resource_id references shared_resources.id
     */
    public function resource()
    {
        return $this->belongsTo(SharedResource::class, 'resource_id', 'id');
    }

    /**
     * A resource share record belongs to a member (the recipient).
     * Reverse: TiuMember has many resource shares received.
     * Foreign Key: resource_shares.member_id references tiu_member.tiu_member_id
     */
    public function recipient()
    {
        return $this->belongsTo(TiuMember::class, 'member_id', 'tiu_member_id');
    }
}
