<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransportStop extends Model
{
    use HasFactory;

    protected $table = 'transport_stops';
    protected $primaryKey = 'stop_id';
    public $timestamps = true;

    protected $fillable = [
        'stop_id',
        'route_id',
        'stop_name',
        'take_off_time',
        'latitude',
        'longitude',
        'stop_order',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * A transport stop belongs to one transport route.
     * Reverse: TransportRoute has many transport stops.
     * Foreign Key: transport_stops.route_id references transport_routes.route_id
     */
    public function route()
    {
        return $this->belongsTo(TransportRoute::class, 'route_id', 'route_id');
    }

    /**
     * A transport stop has many bus attendance check-ins recorded at it.
     * Reverse: BusAttendance belongs to TransportStop.
     * Foreign Key: bus_attendance.bus_stop_id references transport_stops.stop_id
     */
    public function busAttendances()
    {
        return $this->hasMany(BusAttendance::class, 'bus_stop_id', 'stop_id');
    }

    /**
     * A transport stop can be referenced by many members as their preferred bus stop.
     * Reverse: TiuMember belongs to TransportStop (via bus_stop_id).
     * Foreign Key: tiu_member.bus_stop_id references transport_stops.stop_id
     */
    public function members()
    {
        return $this->hasMany(TiuMember::class, 'bus_stop_id', 'stop_id');
    }
}
