<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BirthdayController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display birthday list
     */
    public function index()
    {
        $user = Auth::user();
        $memberRole = $user->member_role ?? '';
        if (!in_array($memberRole, ['Super User', 'Admin', 'Admins', 'Workers'])) {
            return redirect('/aoverview')->with('error', 'Access Denied');
        }

        // Get all birthdays from birthday table (filtered by user's campus)
        $campusId = $user->campus_id ?? 0;
        $birthdays = DB::table('birthday as b')
            ->join('tiu_member as m', 'b.tiu_member_id', '=', 'm.tiu_member_id')
            ->where('m.campus_id', $campusId)
            ->select('b.*', 'm.first_name', 'm.last_name', 'm.phone_number', 'm.email')
            ->orderByRaw("MONTH(b.birthday), DAY(b.birthday)")
            ->get();

        // Get today's birthdays and upcoming birthdays (next 7 days)
        $todaysBirthdays = [];
        $upcomingBirthdays = [];
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
                $bday->birthday_date = $birthdayDate->format('d M');

                if ($daysLeft === 0) {
                    // Birthday is today
                    $todaysBirthdays[] = $bday;
                } elseif ($daysLeft > 0 && $daysLeft <= 7) {
                    // Birthday within the next 7 days
                    $upcomingBirthdays[] = $bday;
                }
            }
        }

        // Sort today's birthdays by name
        usort($todaysBirthdays, function($a, $b) {
            return strcasecmp($a->Name ?? '', $b->Name ?? '');
        });

        // Sort by days left
        usort($upcomingBirthdays, function($a, $b) {
            return $a->days_left - $b->days_left;
        });

        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[$i] = Carbon::create()->month($i)->format('F');
        }
        
        $currentMonth = now()->month;
        $currentMonthName = now()->format('F');

        return view('birthdays.index', compact('birthdays', 'todaysBirthdays', 'upcomingBirthdays', 'months', 'currentMonth', 'currentMonthName'));
    }

    /**
     * Filter birthdays by month
     */
    public function filter(Request $request)
    {
        $user = Auth::user();
        $campusId = $user->campus_id ?? 0;
        $month = $request->get('month', now()->month);
        $monthName = Carbon::create()->month($month)->format('F');

        $birthdays = DB::table('birthday as b')
            ->join('tiu_member as m', 'b.tiu_member_id', '=', 'm.tiu_member_id')
            ->where('m.campus_id', $campusId)
            ->whereMonth('b.birthday', $month)
            ->select('b.*', 'm.first_name', 'm.last_name', 'm.phone_number', 'm.email')
            ->orderByRaw("DAY(b.birthday)")
            ->get();

        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[$i] = Carbon::create()->month($i)->format('F');
        }

        return view('birthdays.index', compact('birthdays', 'monthName', 'months', 'month'));
    }

    /**
     * Send birthday wish via WhatsApp
     */
    public function sendWish(Request $request)
    {
        $phone = $request->phone;
        $name = $request->name;
        
        // Format phone number for WhatsApp
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strpos($phone, '0') === 0) {
            $phone = '234' . substr($phone, 1);
        } elseif (strpos($phone, '234') !== 0) {
            $phone = '234' . $phone;
        }
        
        $message = "🎂 *Happy Birthday {$name}!* 🎉\n\n";
        $message .= "May this new year bring you joy, peace, and God's abundant blessings.\n\n";
        $message .= "Warm regards,\n*TCN Ikorodu*";
        
        return response()->json([
            'success' => true,
            'message' => 'Opening WhatsApp...',
            'whatsapp_url' => "https://wa.me/{$phone}?text=" . urlencode($message)
        ]);
    }
}