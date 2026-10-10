<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BirthdayController extends Controller
{
    /**
     * Get all birthdays for the user's campus
     */
    public function index(Request $request)
    {
        $userId = $request->user_id;
        $user = DB::selectOne("SELECT campus_id FROM tiu_member WHERE tiu_member_id = ?", [$userId]);
        $campusId = $user->campus_id ?? 0;

        $birthdays = DB::table('birthday as b')
            ->join('tiu_member as m', 'b.tiu_member_id', '=', 'm.tiu_member_id')
            ->where('m.campus_id', $campusId)
            ->select('b.*', 'm.first_name', 'm.last_name', 'm.phone_number', 'm.email')
            ->orderByRaw("MONTH(b.birthday), DAY(b.birthday)")
            ->get();

        return response()->json([
            'success' => true,
            'data' => $birthdays
        ]);
    }

    /**
     * Get today's and upcoming birthdays (next 7 days)
     */
    public function upcoming(Request $request)
    {
        $userId = $request->user_id;
        $user = DB::selectOne("SELECT campus_id FROM tiu_member WHERE tiu_member_id = ?", [$userId]);
        $campusId = $user->campus_id ?? 0;

        $birthdays = DB::table('birthday as b')
            ->join('tiu_member as m', 'b.tiu_member_id', '=', 'm.tiu_member_id')
            ->where('m.campus_id', $campusId)
            ->select('b.*', 'm.first_name', 'm.last_name', 'm.phone_number', 'm.email')
            ->orderByRaw("MONTH(b.birthday), DAY(b.birthday)")
            ->get();

        $upcoming = [];
        $today = Carbon::today();

        foreach ($birthdays as $bday) {
            if ($bday->birthday) {
                $birthdayDate = Carbon::parse($bday->birthday);
                $birthdayThisYear = Carbon::create($today->year, $birthdayDate->month, $birthdayDate->day);
                
                // Compare days only: a birthday falling today must stay day 0
                if ($birthdayThisYear->lt($today)) {
                    $birthdayThisYear->addYear();
                }
                
                $daysLeft = (int) $today->diffInDays($birthdayThisYear, false);
                
                $bday->days_left = $daysLeft;
                $bday->formatted_date = $birthdayDate->format('d M');
                $bday->is_today = ($daysLeft === 0);

                if ($daysLeft >= 0 && $daysLeft <= 7) {
                    $upcoming[] = $bday;
                }
            }
        }

        usort($upcoming, function($a, $b) {
            return $a->days_left - $b->days_left;
        });

        return response()->json([
            'success' => true,
            'data' => $upcoming
        ]);
    }

    /**
     * Filter birthdays by month
     */
    public function filterByMonth(Request $request)
    {
        $request->validate(['month' => 'required|integer|min:1|max:12']);

        $userId = $request->user_id;
        $user = DB::selectOne("SELECT campus_id FROM tiu_member WHERE tiu_member_id = ?", [$userId]);
        $campusId = $user->campus_id ?? 0;
        $month = $request->month;

        $birthdays = DB::table('birthday as b')
            ->join('tiu_member as m', 'b.tiu_member_id', '=', 'm.tiu_member_id')
            ->where('m.campus_id', $campusId)
            ->whereMonth('b.birthday', $month)
            ->select('b.*', 'm.first_name', 'm.last_name', 'm.phone_number', 'm.email')
            ->orderByRaw("DAY(b.birthday)")
            ->get();

        return response()->json([
            'success' => true,
            'data' => $birthdays
        ]);
    }

    /**
     * Send birthday wish (returns WhatsApp URL)
     */
    public function sendWish(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'name' => 'required|string',
        ]);

        $phone = preg_replace('/[^0-9]/', '', $request->phone);
        if (strpos($phone, '0') === 0) {
            $phone = '234' . substr($phone, 1);
        } elseif (strpos($phone, '234') !== 0) {
            $phone = '234' . $phone;
        }

        $message = "🎂 *Happy Birthday {$request->name}!* 🎉\n\n";
        $message .= "May this new year bring you joy, peace, and God's abundant blessings.\n\n";
        $message .= "Warm regards,\n*TCN Ikorodu*";

        return response()->json([
            'success' => true,
            'whatsapp_url' => "https://wa.me/{$phone}?text=" . urlencode($message)
        ]);
    }
}
