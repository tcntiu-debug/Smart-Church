<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SharedResource extends Model
{
    use HasFactory;

    protected $table = 'shared_resources';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'id',
        'title',
        'description',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'uploader_id',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * A shared resource was uploaded by a member.
     * Reverse: TiuMember has many shared resources uploaded.
     * Foreign Key: shared_resources.uploader_id references tiu_member.tiu_member_id
     */
    public function uploader()
    {
        return $this->belongsTo(TiuMember::class, 'uploader_id', 'tiu_member_id');
    }

    /**
     * A shared resource can be shared with many members (tracked in resource_shares).
     * Reverse: ResourceShare belongs to SharedResource.
     * Foreign Key: resource_shares.resource_id references shared_resources.id
     */
    public function shares()
    {
        return $this->hasMany(ResourceShare::class, 'resource_id', 'id');
    }
}
