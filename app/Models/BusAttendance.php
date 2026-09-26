<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusAttendance extends Model
{
    use HasFactory;

    protected $table = 'bus_attendance';
    protected $primaryKey = 'attendance_id';
    public $timestamps = true;

    protected $fillable = [
        'attendance_id',
        'tiu_member_id',
        'route_id',
        'bus_stop_id',
        'attendance_date',
        'check_in_time',
        'marked_by',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * A bus attendance record belongs to a member (the person who rode the bus).
     * Reverse: TiuMember has many bus attendance records.
     * Foreign Key: bus_attendance.tiu_member_id references tiu_member.tiu_member_id
     */
    public function member()
    {
        return $this->belongsTo(TiuMember::class, 'tiu_member_id', 'tiu_member_id');
    }

    /**
     * A bus attendance record belongs to a transport route.
     * Reverse: TransportRoute has many bus attendance records.
     * Foreign Key: bus_attendance.route_id references transport_routes.route_id
     */
    public function route()
    {
        return $this->belongsTo(TransportRoute::class, 'route_id', 'route_id');
    }

    /**
     * A bus attendance record belongs to a specific bus stop (where check-in occurred).
     * Reverse: TransportStop has many bus attendance records.
     * Foreign Key: bus_attendance.bus_stop_id references transport_stops.stop_id
     */
    public function busStop()
    {
        return $this->belongsTo(TransportStop::class, 'bus_stop_id', 'stop_id');
    }

    /**
     * A bus attendance record was marked by a member (the person who recorded attendance).
     * Reverse: TiuMember has many bus attendance records they marked.
     * Foreign Key: bus_attendance.marked_by references tiu_member.tiu_member_id
     */
    public function markedBy()
    {
        return $this->belongsTo(TiuMember::class, 'marked_by', 'tiu_member_id');
    }
}
