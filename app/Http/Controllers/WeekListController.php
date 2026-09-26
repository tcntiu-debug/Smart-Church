<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class WeekListController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display the week list of first timers
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $member_role = $user->member_role ?? 'Guest';
        $userCampusId = $user->campus_id ?? 0;
        
        // Only Workers, Admins, Super Users can access
        if (!in_array($member_role, ['Worker', 'Admin', 'Admins', 'Workers', 'Super User'])) {
            return redirect('/mytask')->with('error', 'Access Denied');
        }

        // Get filter parameters
        $start_date_filter = $request->get('start_date', '');
        $end_date_filter = $request->get('end_date', '');
        $guest_type_filter = $request->get('guest_type', '');
        $church_type_id_filter = $request->get('church_type_id', '');

        // Set default date range (current week: Sunday to Saturday)
        if (!$start_date_filter || !$end_date_filter) {
            $today = Carbon::today();
            $dayOfWeek = $today->dayOfWeek; // 0 = Sunday, 6 = Saturday
            $sunday = $today->copy()->subDays($dayOfWeek);
            $saturday = $sunday->copy()->addDays(6);
            
            $start_date_filter = $sunday->format('Y-m-d');
            $end_date_filter = $saturday->format('Y-m-d');
            $page_title = "Weekly Prayer List (" . $sunday->format('M d') . " - " . $saturday->format('M d, Y') . ")";
        } else {
            $page_title = "First Timers from " . date('M d, Y', strtotime($start_date_filter)) . " to " . date('M d, Y', strtotime($end_date_filter));
        }

        // Build the query
        $query = DB::table('first_timer as ft')
            ->leftJoin('church_type as ct', 'ft.church_type_id', '=', 'ct.id');

        // Campus filter - only show first timers from user's campus
        if ($member_role !== 'Super User' && !empty($userCampusId)) {
            $query->where('ft.campus_id', $userCampusId);
        }

        // Date range condition
        if ($start_date_filter && $end_date_filter) {
            $query->whereBetween('ft.register_date', [$start_date_filter . ' 00:00:00', $end_date_filter . ' 23:59:59']);
        }

        // Guest type filter
        if ($guest_type_filter) {
            $query->where('ft.attendant_type', $guest_type_filter);
        }

        // Church type filter
        if ($church_type_id_filter && $church_type_id_filter !== 'All') {
            $query->where('ft.church_type_id', $church_type_id_filter);
        }

        // Get the results
        $firstTimers = $query->select('ft.*', 'ct.church_type_name')
            ->orderBy('ft.register_date', 'desc')
            ->get();

        // Get guest types for filter dropdown (also filtered by campus)
        $guestTypesQuery = DB::table('first_timer')
            ->whereNotNull('attendant_type')
            ->where('attendant_type', '!=', '');
        if ($member_role !== 'Super User' && !empty($userCampusId)) {
            $guestTypesQuery->where('campus_id', $userCampusId);
        }
        $guestTypes = $guestTypesQuery->distinct()->pluck('attendant_type');

        // Get church types for filter dropdown
        $churchTypes = DB::table('church_type')
            ->orderBy('church_type_name', 'asc')
            ->get();


        // Process duplicate detection
        $rows = [];
        $duplicateMap = [
            'phone' => [],
            'email' => [],
            'name' => []
        ];

        foreach ($firstTimers as $row) {
            $rows[] = $row;
            
            $phone = trim($row->phone_number);
            $email = strtolower(trim($row->email ?? ''));
            $name = strtolower(trim($row->first_name . $row->last_name));
            
            if ($phone) {
                $duplicateMap['phone'][$phone] = ($duplicateMap['phone'][$phone] ?? 0) + 1;
            }
            if ($email) {
                $duplicateMap['email'][$email] = ($duplicateMap['email'][$email] ?? 0) + 1;
            }
            if ($name) {
                $duplicateMap['name'][$name] = ($duplicateMap['name'][$name] ?? 0) + 1;
            }
        }

        // Process guide assignments
        foreach ($rows as $row) {
            $trimmedPhone = trim($row->phone_number);
            $email = strtolower(trim($row->email ?? ''));
            
            $row->isDuplicate = (
                ($duplicateMap['phone'][$trimmedPhone] > 1) || 
                ($email && ($duplicateMap['email'][$email] ?? 0) > 1) ||
                ($duplicateMap['name'][strtolower(trim($row->first_name . $row->last_name))] > 1)
            );
            
            // Guide assignment
            $tracking = DB::table('member_tracking_followup')
                ->where('first_timer_id', $row->first_timer_id)
                ->first();
            
            $row->isGuideAssigned = false;
            $row->guide_name = '';
            $row->guide_phone = '';
            $row->guide_gender = '';
            
            if ($tracking && $tracking->tiu_member_id) {
                $guide = DB::table('tiu_member')
                    ->where('tiu_member_id', $tracking->tiu_member_id)
                    ->first();
                
                if ($guide) {
                    $row->isGuideAssigned = true;
                    $row->guide_name = $guide->first_name . ' ' . $guide->last_name;
                    $row->guide_phone = '234' . ltrim($guide->phone_number, '0');
                    $row->guide_gender = $guide->gender ?? '';
                }
            }
            
            // Format phone number for WhatsApp
            $row->whatsapp_phone = '234' . ltrim($row->phone_number, '0');
            
            // Generate WhatsApp message based on guest type
            $row->whatsapp_message = $this->generateWhatsAppMessage(
                $row->first_name . ' ' . $row->last_name,
                $row->attendant_type,
                $row->isGuideAssigned,
                $row->guide_name
            );
        }

        return view('weeklist.index', compact('rows', 'page_title', 'guestTypes', 'churchTypes', 
            'start_date_filter', 'end_date_filter', 'guest_type_filter', 'church_type_id_filter', 'member_role'));
    }

    /**
     * Generate WhatsApp message based on guest type
     */
    private function generateWhatsAppMessage($name, $attendantType, $isGuideAssigned, $guideName)
    {
        $guideLine = $isGuideAssigned ? "\n\nA team member ($guideName) will reach out to you soon." : "";
        
        $messages = [
            "New To TCN (May Join TCN)" => "Dear $name,\n\nIt was a blessing to have you with us at TCN Ikorodu today! We're grateful for the time you spent in worship with us and pray that the Lord continues to lead and guide you.\n\nNo matter where your journey takes you, know that you're always welcome here.$guideLine\n\nTCN Ikorodu",
            
            "New To TCN (Will Join TCN)" => "Dear $name,\n\nWe are excited to welcome you to TCN Ikorodu! We thank God for leading you here and look forward to growing in faith together.$guideLine\n\nTCN Ikorodu",
            
            "TCN Member (Relocating to Ikd)" => "Dear $name,\n\nIt was a joy to worship with you at TCN Ikorodu today! We love connecting with our TCN family.\n\nMay God bless and strengthen you.\n\nWelcome home!$guideLine\n\nTCN Ikorodu",
            
            "TCN Member (In Transit)" => "Dear $name,\n\nIt was a joy to worship with you at TCN Ikorodu today! We love connecting with our TCN family.\n\nMay God bless and strengthen you.\n\nWelcome home!$guideLine\n\nTCN Ikorodu"
        ];
        
        $defaultMessage = "Dear $name,\n\nThank you for visiting TCN Ikorodu! We pray God blesses you abundantly.\n\nTCN Ikorodu";
        
        return $messages[$attendantType] ?? $defaultMessage;
    }
}