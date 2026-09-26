<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCampusScope;

class TransportRoute extends Model
{
    use HasFactory;
    use HasCampusScope;

    protected $table = 'transport_routes';
    protected $primaryKey = 'route_id';
    public $timestamps = true;

    protected $fillable = [
        'route_id',
        'route_name',
        'driver_name',
        'driver_contact',
        'team_lead_name',
        'team_lead_contact',
        'status',
        'campus_id',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * A transport route belongs to one campus.
     * Reverse: Campus has many transport routes.
     * Foreign Key: transport_routes.campus_id references campus.cid
     */
    public function campus()
    {
        return $this->belongsTo(Campus::class, 'campus_id', 'cid');
    }

    /**
     * A transport route has many transport stops along its path.
     * Reverse: TransportStop belongs to TransportRoute.
     * Foreign Key: transport_stops.route_id references transport_routes.route_id
     */
    public function stops()
    {
        return $this->hasMany(TransportStop::class, 'route_id', 'route_id');
    }

    /**
     * A transport route has many bus attendance records.
     * Reverse: BusAttendance belongs to TransportRoute.
     * Foreign Key: bus_attendance.route_id references transport_routes.route_id
     */
    public function busAttendances()
    {
        return $this->hasMany(BusAttendance::class, 'route_id', 'route_id');
    }
}
