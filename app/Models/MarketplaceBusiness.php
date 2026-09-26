<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceBusiness extends Model
{
    use HasFactory;

    protected $table = 'marketplace_businesses';
    protected $primaryKey = 'business_id';
    public $timestamps = true;

    protected $fillable = [
        'business_id',
        'tiu_member_id',
        'business_name',
        'category_id',
        'business_details',
        'contact_email',
        'contact_phone',
        'office_address',
        'status',
        'agreed_to_terms',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * A marketplace business belongs to a member (the business owner).
     * Reverse: TiuMember has many marketplace businesses.
     * Foreign Key: marketplace_businesses.tiu_member_id references tiu_member.tiu_member_id
     */
    public function owner()
    {
        return $this->belongsTo(TiuMember::class, 'tiu_member_id', 'tiu_member_id');
    }

    /**
     * A marketplace business belongs to a category.
     * Reverse: MarketplaceCategory has many businesses.
     * Foreign Key: marketplace_businesses.category_id references marketplace_categories.category_id
     */
    public function category()
    {
        return $this->belongsTo(MarketplaceCategory::class, 'category_id', 'category_id');
    }
}
