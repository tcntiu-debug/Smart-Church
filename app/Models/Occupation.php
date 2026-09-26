<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Occupation extends Model
{
    use HasFactory;
    
    protected $table = 'occupation';
    protected $primaryKey = 'occ_id';
    public $timestamps = true;
    
    protected $fillable = [
        'occ_id',
        'occ_name',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * An occupation has many members who have this occupation.
     * Reverse: TiuMember belongs to Occupation.
     * Foreign Key: tiu_member.occupation references occupation.occ_name
     * NOTE: This is a loose relationship since tiu_member.occupation stores the name, not the occ_id.
     */
    public function members()
    {
        return $this->hasMany(TiuMember::class, 'occupation', 'occ_name');
    }
}
