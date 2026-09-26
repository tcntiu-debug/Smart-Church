@php
$member_role = session('member_role', 'Guest');
$user_departments = session('department_name', '[]');
$user_departments = json_decode($user_departments, true) ?: [];
$church_type_id = session('church_type_id', '');
$lead_of_dept = session('lead_of_dept', '');
$lead_church_type_ids = session('lead_of_church_type', '');
$userIsHODOrLead = session('userIsHODOrLead', false);
@endphp

<aside id="ms-side-nav" class="side-nav fixed ms-aside-scrollable ms-aside-left">
  <div class="logo-sn ms-d-block-lg">
    <a class="pl-0 ml-0 text-center" href="{{ url('/mytask') }}">
      <img src="{{ asset('assets/img/tiupix/tiupix-logo.png') }}" alt="logo">
    </a>
  </div>

  <ul class="accordion ms-main-aside fs-14" id="side-nav-accordion">

    <!-- HOME DASHBOARD (Visible to all logged-in users) -->
    <li class="menu-item">
      <a href="{{ route('home') }}"><span><i class="fas fa-home fs-16"></i>Home Dashboard</span></a>
    </li>

    <!-- MY BOX -->
    <li class="menu-item">
      <a href="#" class="has-chevron" data-toggle="collapse" data-target="#my_box">
        <span><i class="fas fa-inbox fs-16"></i>My Box</span>
      </a>
      <ul id="my_box" class="collapse" data-parent="#side-nav-accordion">
        <li><a href="{{ url('/profile') }}">Profile</a></li>
        <li><a href="{{ url('/mtouchpoint') }}">My SubGroup</a></li>
        <li><a href="{{ route('child-ceremony.index') }}">Child Ceremony</a></li>
        <li><a href="{{ url('/bus-route') }}">Bus Route Registration</a></li>
        <li><a href="{{ url('/gallery') }}">Photos</a></li>
      </ul>
    </li>

    <!-- Week List -->
    @if(in_array($member_role, ['Worker', 'Admin', 'Admins', 'Workers']))
    <li class="menu-item">
      <a href="{{ url('/weeklist') }}"><span><i class="fas fa-users"></i>Week List</span></a>
    </li>
    @endif

    <!-- HOD-Lead View (New Version) -->
    @if($member_role == "Super User" || $member_role == "Admin" || $userIsHODOrLead)
      <li class="menu-item">
        <a href="#" class="has-chevron" data-toggle="collapse" data-target="#my_lead">
          <span><i class="fas fa-user-tie fs-16"></i>HOD-Lead View</span>
        </a>
        <ul id="my_lead" class="collapse" data-parent="#side-nav-accordion">
          <li><a href="{{ url('/lead/department') }}">Department View</a></li>
          <li><a href="{{ url('/lead/house-fellowship') }}">House Fellowship View</a></li>
          <li><a href="{{ url('/lead/cluster') }}">Cluster View</a></li>
          <li><a href="{{ url('/ftlead') }}">First Timers View</a></li>
        </ul>
      </li>
    @endif

    <!-- Welcome Center -->
    @if($member_role == "Super User" || $member_role == "Admin" || (in_array('28', $user_departments) && $member_role == "Worker"))
    <li class="menu-item">
      <a href="#" class="has-chevron" data-toggle="collapse" data-target="#welcome_center">
        <span><i class="fas fa-heart fs-16"></i>Welcome Center</span>
      </a>
      <ul id="welcome_center" class="collapse" data-parent="#side-nav-accordion">
        <li><a href="{{ url('/birthdays') }}">Birthdays</a></li>
        <li><a href="{{ url('/admin-announcements') }}">Announcements</a></li>
        <li><a href="{{ route('child-ceremony.admin') }}">Child Ceremony Requests</a></li>
      </ul>
    </li>
    @endif

    <!-- Marketplace -->
    <li class="menu-item">
      <a href="#" class="has-chevron" data-toggle="collapse" data-target="#marketplace_menu">
        <span><i class="fas fa-store fs-16"></i>Marketplace</span>
      </a>
      <ul id="marketplace_menu" class="collapse">
        <li><a href="{{ url('/my-business') }}">My Business</a></li>
        <li><a href="{{ url('/purchase') }}">Purchase</a></li>
      </ul>
    </li>

    <!-- Shared Resource -->
    <li class="menu-item">
      <a href="#" class="has-chevron" data-toggle="collapse" data-target="#resource_menu">
        <span><i class="fas fa-folder fs-16"></i>Shared Resource</span>
      </a>
      <ul id="resource_menu" class="collapse">
        <li><a href="{{ url('/public') }}">Public</a></li>
        <li><a href="{{ url('/private') }}">Private</a></li>
      </ul>
    </li>

    <!-- TRACKING & INTEGRATION -->
    @if($member_role == "Super User" || $member_role == "Admin" || (in_array('23', $user_departments) && ($member_role == "Worker") || $member_role == "Lead"))
    <li class="menu-item">
      <a href="#" class="has-chevron" data-toggle="collapse" data-target="#tracking_integration">
        <span><i class="fas fa-chart-line fs-16"></i>Tracking-Integration</span>
      </a>
      <ul id="tracking_integration" class="collapse">
        <li><a href="{{ route('my-tasks.index') }}">My Task</a></li>
        <li><a href="{{ url('/ft-register') }}">Register First Timer</a></li>
        <li class="menu-item">
          <a href="#" id="airtimeTopupBtn">
            <span>Airtime Topup</span>
          </a>
        </li>
        @if($member_role == "Lead" || $member_role == "Admin" || $member_role == "Super User")
        <li><a href="{{ url('/pending-tasks') }}">Task Reminder</a></li>
        <li><a href="{{ url('/fview') }}">First Timer View</a></li>
        <li><a href="{{ url('/fthangout') }}">Hangout Data</a></li>
        @endif
      </ul>
    </li>
    @endif

    <!-- CHILDREN ADMIN (Super User, Admin, or Children Church Teachers - dept 11) -->
    @if($member_role == "Super User" || $member_role == "Admin" || in_array('11', $user_departments))
    <li class="menu-item">
      <a href="#" class="has-chevron" data-toggle="collapse" data-target="#children_admin">
        <span><i class="fas fa-child fs-16"></i>Children Admin</span>
      </a>
      <ul id="children_admin" class="collapse">
        <li><a href="{{ url('/children-church') }}">Children Check-in</a></li>
        <li><a href="{{ route('children-church.manage') }}">Children Records</a></li>
      </ul>
    </li>
    @endif

    <!-- ADMIN ONLY MENUS -->
    @if($member_role == "Super User" || $member_role == "Admin")
    <hr>

    <!-- ADMIN CONSOLE -->
    <li class="menu-item">
      <a href="#" class="has-chevron" data-toggle="collapse" data-target="#admin_console_1">
        <span><i class="fas fa-user-shield fs-16"></i>Admin Console</span>
      </a>
      <ul id="admin_console_1" class="collapse">
        <li><a href="{{ url('/fviewupdate') }}">First Timers Update</a></li>
        <li><a href="{{ url('/mview') }}">Member View</a></li>
        <li><a href="{{ url('/mregister') }}">Member Register</a></li>
        <li><a href="{{ url('/prayer-suggestion-list') }}">Prayer/Suggestion</a></li>
        <li><a href="{{ route('admin.assignments.drag-drop') }}">Assign First Timer</a></li>
        <li><a href="{{ url('/aoverview') }}">Task Overview</a></li>
        <li><a href="{{ url('/airtime_admin') }}">Credit History</a></li>
      </ul>
    </li>

    <!-- ANALYTICS & MAPS -->
    <li class="menu-item">
      <a href="#" class="has-chevron" data-toggle="collapse" data-target="#analytics_maps">
        <span><i class="fas fa-map-marked-alt fs-16"></i>Analytics & Maps</span>
      </a>
      <ul id="analytics_maps" class="collapse">
        <li><a href="{{ url('/report-barcharts') }}">Bar Charts Report</a></li>
        <li><a href="{{ url('/report-piecharts') }}">Pie Charts Report</a></li>
        <li><a href="{{ url('/department-view') }}">Department List</a></li>
        <li><a href="{{ url('/attendance-analysis') }}">Attendance Analysis</a></li>
        <li><a href="{{ url('/map-view') }}">Community Map</a></li>
      </ul>
    </li>

    <!-- SETTINGS & CONFIG -->
    <li class="menu-item">
      <a href="#" class="has-chevron" data-toggle="collapse" data-target="#settings_config">
        <span><i class="fas fa-cogs fs-16"></i>Settings & Config</span>
      </a>
      <ul id="settings_config" class="collapse">
        <li><a href="{{ url('/setting') }}">General Settings</a></li>
        <li><a href="{{ url('/etask') }}">Set Weekly Task</a></li>
        <li><a href="{{ url('/group') }}">Subgroup Management</a></li>
        <li><a href="{{ url('/config-departments') }}">Department Config</a></li>
        <li><a href="{{ url('/config-community') }}">Community Config</a></li>
        <li><a href="{{ url('/config-bus-route') }}">Bus Route Config</a></li>
        <li><a href="{{ url('/admin-marketplace') }}">Marketplace Admin</a></li>
        <li><a href="{{ url('/manage-resources') }}">Manage Resources</a></li>
      </ul>
    </li>
    @endif

    <!-- FOF ADMIN -->
    @if($member_role == "Super User" || $member_role == "Admin" || (in_array('15', $user_departments) && $member_role == "Worker"))
    <li class="menu-item">
      <a href="#" class="has-chevron" data-toggle="collapse" data-target="#admin_fof">
        <span><i class="fas fa-church fs-16"></i>FOF Admin</span>
      </a>
      <ul id="admin_fof" class="collapse">
        <li><a href="{{ route('fof.register') }}">FOF Register</a></li>
        <li><a href="{{ route('fof.view-students') }}">Mark Attendance</a></li>
        <li><a href="{{ route('fof.view-attendance') }}">View Attendance</a></li>
        <li><a href="{{ route('fof.members') }}">FOF Members</a></li>
      </ul>
    </li>
    @endif

    <!-- TRANSPORT ADMIN -->
    @if($member_role == "Super User" || $member_role == "Admin" || (in_array('25', $user_departments) && $member_role == "Worker"))
    <li class="menu-item">
      <a href="#" class="has-chevron" data-toggle="collapse" data-target="#admin_transport">
        <span><i class="fas fa-church fs-16"></i>Transport Admin</span>
      </a>
      <ul id="admin_transport" class="collapse">
        <li><a href="{{ route('transport.admin-register') }}">Transport Register</a></li>
        <li><a href="{{ route('transport.mark-attendance') }}">Mark Attendance</a></li>
        <li><a href="{{ route('transport.view-attendance') }}">View Attendance</a></li>
      </ul>
    </li>
    @endif

    <!-- POLICY -->
    <li class="menu-item">
      <a href="#" class="has-chevron" data-toggle="collapse" data-target="#policy_menu">
        <span><i class="fas fa-user-secret fs-16"></i>Policy</span>
      </a>
      <ul id="policy_menu" class="collapse">
        <li><a href="{{ url('/privacy-policy') }}">Privacy Policy</a></li>
        <li><a href="{{ url('/data-policy') }}">Data Use Policy</a></li>
      </ul>
    </li>

    <!-- LOGOUT -->
    <li class="menu-item">
      <form method="POST" action="{{ route('logout') }}" id="logout-form" style="display: inline;">
        @csrf
        <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
          <span><i class="fas fa-sign-out-alt"></i>Log Out</span>
        </a>
      </form>
    </li>
  </ul>
</aside>

<!-- Airtime Topup Modal -->
<div id="airtimeModalContainer" style="display:none;">
    <div class="modal fade" id="airtimeModal" tabindex="-1" role="dialog" aria-labelledby="airtimeModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="airtimeModalLabel">Airtime Topup Available 1pm-5pm</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>You will be credited with <strong>N200</strong> airtime.</p>
                    <p>This may take a few minutes. Only one request per week is allowed.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmAirtimeBtn">Continue</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    function initAirtimeFeature() {
        if (typeof jQuery === 'undefined' || typeof jQuery.fn.modal === 'undefined') {
            setTimeout(initAirtimeFeature, 100);
            return;
        }
        
        var $ = jQuery;
        
        var modalContainer = $('#airtimeModalContainer');
        if (modalContainer.length && !$('#airtimeModal').parent().is('body')) {
            modalContainer.children().appendTo('body');
            modalContainer.remove();
        }
        
        $(document).on('click', '#airtimeTopupBtn', function(e) {
            e.preventDefault();
            $('#airtimeModal').modal('show');
        });
        
        $(document).on('click', '#confirmAirtimeBtn', function() {
            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');
            $.ajax({
                url: '{{ url("/airtime-request") }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        $('#airtimeModal').modal('hide');
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Server error. Please try again.');
                },
                complete: function() {
                    btn.prop('disabled', false).html('Continue');
                }
            });
        });
    }
    
    initAirtimeFeature();
})();
</script>