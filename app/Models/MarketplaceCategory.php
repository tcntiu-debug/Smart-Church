<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceCategory extends Model
{
    use HasFactory;

    protected $table = 'marketplace_categories';
    protected $primaryKey = 'category_id';
    public $timestamps = true;

    protected $fillable = [
        'category_id',
        'category_name',
        'is_active',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * A marketplace category has many businesses listed under it.
     * Reverse: MarketplaceBusiness belongs to MarketplaceCategory.
     * Foreign Key: marketplace_businesses.category_id references marketplace_categories.category_id
     */
    public function businesses()
    {
        return $this->hasMany(MarketplaceBusiness::class, 'category_id', 'category_id');
    }
}
