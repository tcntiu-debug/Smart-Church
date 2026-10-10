@extends('layouts.app')

@section('content')
<div class="ms-panel">
    <div class="ms-panel-header">
        <h6>Community Locations & Demographics</h6>
    </div>
    <div class="ms-panel-body">
        <div class="map-view-container">
            <div id="community-panel">
                <h5>Community List</h5>
                <ul id="community-list-ul"></ul>
            </div>
            <div id="map-panel">
                <div id="communityMap"></div>
                <div id="community-toggle" title="Toggle Community List"><span></span><span></span><span></span></div>
            </div>
        </div>
    </div>
</div>

<div id="member-details-panel" class="ms-panel" style="display: none;">
    <div class="ms-panel-header"><h6 id="member-details-header">Community Members</h6></div>
    <div class="ms-panel-body">
        <div class="table-responsive">
            <table class="table table-hover table-bordered">
                <thead class="bg-primary text-white">
                    <tr>
                        <th>Full Name</th><th>Residential Address</th><th>Phone Number</th><th>Email</th>
                    </tr>
                </thead>
                <tbody id="member-table-body"></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
    .map-view-container { display: flex; height: 75vh; }
    #community-panel { width: 350px; height: 100%; display: flex; flex-direction: column; border: 1px solid #ddd; border-radius: .25rem; padding: 1rem; transition: width 0.3s ease, padding 0.3s ease, opacity 0.3s ease; flex-shrink: 0; }
    #community-panel.collapsed { width: 0 !important; padding: 0 !important; border: none !important; opacity: 0; overflow: hidden; }
    #map-panel { flex-grow: 1; height: 100%; position: relative; margin-left: 1rem; }
    #communityMap { height: 100%; width: 100%; border-radius: .25rem; }
    #community-toggle { position: absolute; top: 10px; left: 10px; z-index: 1000; background: white; padding: 10px; border-radius: 4px; cursor: pointer; box-shadow: 0 1px 5px rgba(0,0,0,0.65); border: 1px solid #ccc; }
    #community-toggle span { display: block; width: 22px; height: 2px; background-color: #333; margin: 4px 0; }
    #community-list-ul { list-style-type: none; padding: 0; margin: 0; flex-grow: 1; overflow-y: auto; }
    #community-list-ul li { padding: 10px 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    .community-info { cursor: pointer; flex-grow: 1; }
    #community-list-ul li:hover .community-info, #community-list-ul li.active .community-info { color: #007bff; }
    #community-list-ul li.active { background-color: #f0f5ff; }
    .count-badge { background-color: #6c5ffc; color: white; padding: 3px 8px; border-radius: 12px; font-size: 0.9em; }
    .department-icon { color: #28a745; margin-right: 8px; }

    /* ---- Dark mode twins (see docs/DISPLAY-MODE.md) ----------------------
       The community list, its active row and the map toggle are painted light
       by this page while style.css recolours text inside `.ms-panel` #fff.
       Palette: surface #252851, deeper #323a67, border #242750, accent #ff8306. */
    .ms-dark-theme #community-panel {
        border-color: #242750;
    }
    .ms-dark-theme #community-list-ul li {
        border-bottom-color: #242750;
    }
    .ms-dark-theme #community-list-ul li.active {
        background-color: #323a67;
    }
    .ms-dark-theme #community-list-ul li:hover .community-info,
    .ms-dark-theme #community-list-ul li.active .community-info {
        color: #ff8306;
    }
    .ms-dark-theme #community-toggle {
        background: #252851;
        border-color: #242750;
    }
    .ms-dark-theme #community-toggle span {
        background-color: #e7e8f5;
    }
    /* `.ms-dark-theme .bg-primary` turns white, which would hide the header
       text (`.text-white` is `!important`), so keep the table head solid. */
    .ms-dark-theme thead.bg-primary {
        background-color: #ff8306;
    }
    /* Bootstrap utilities carry `!important`, so their twins need it too. */
    .ms-dark-theme .text-muted {
        color: #b9bcd8 !important;
    }
</style>
@php $amp = '&'; $lt = '<'; $gt = '>'; $quot = '"'; $apos = '&#039;'; @endphp
<script>
$(document).ready(function() {
    const communityData = {!! $communities_json !!};
    const map = L.map('communityMap').setView([6.5244, 3.3792], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    const communityList = $('#community-list-ul');
    const markers = [];
    const defaultMarkerColor = "#ff7800", departmentMarkerColor = "#28a745", activeMarkerColor = "#3554dcff", hoverMarkerColor = "#ffffff";

    communityData.forEach(community => {
        const listItem = $('<li></li>').data('community-id', community.community_id);
        
        if (community.latitude && community.longitude) {
            const initialColor = community.has_department ? departmentMarkerColor : defaultMarkerColor;
            const marker = L.circleMarker([community.latitude, community.longitude], {
                radius: 8, fillColor: initialColor, color: "#000", weight: 1, opacity: 1, fillOpacity: 0.8
            }).addTo(map);
            
            marker.options.originalColor = initialColor;
            marker.bindTooltip('<b>' + community.community_name + '</b><br>Active Members: ' + community.member_count);
            listItem.data('marker', marker); 
            markers.push(marker);
        }
        
        var deptIcon = community.has_department ? '<i class="fas fa-home department-icon" title="Has House Fellowship/Dept"></i>' : '';
        
        listItem.html(
            '<div class="community-info">' + deptIcon + community.community_name + '</div>' +
            '<div class="community-actions d-flex align-items-center">' +
                '<span class="count-badge">' + community.member_count + '</span>' +
                '<button class="btn btn-sm btn-outline-primary view-members-btn ml-2" title="View Active Members" ' +
                        'data-community-id="' + community.community_id + '" data-community-name="' + community.community_name + '">' +
                    '<i class="fa fa-users"></i>' +
                '</button>' +
            '</div>'
        );
        communityList.append(listItem);
    });

    function selectCommunity(listItem) {
        communityList.find('li').removeClass('active');
        listItem.addClass('active');
        const targetMarker = listItem.data('marker');
        if (targetMarker) {
            markers.forEach(marker => marker.setStyle({ fillColor: marker.options.originalColor }));
            targetMarker.setStyle({ fillColor: activeMarkerColor });
            map.setView(targetMarker.getLatLng(), 14);
        }
    }

    communityList.on('click', '.community-info', function() {
        selectCommunity($(this).closest('li'));
    });

    communityList.on('click', '.view-members-btn', function() {
        const button = $(this);
        const communityId = button.data('community-id');
        const communityName = button.data('community-name');
        const memberPanel = $('#member-details-panel');
        const memberTableBody = $('#member-table-body');
        const memberHeader = $('#member-details-header');

        selectCommunity(button.closest('li')); 

        memberHeader.text('Loading Active Members for ' + communityName + '...');
        memberTableBody.html('<tr><td colspan="4" class="text-center"><i class="fa fa-spinner fa-spin fa-2x"></i></td></tr>');
        memberPanel.slideDown();
        
        $.ajax({
            url: '{{ route("maps.community.members") }}', 
            type: 'POST',
            dataType: 'json',
            data: { 
                community_id: communityId,
                _token: '{{ csrf_token() }}'
            },
            success: function(members) {
                memberHeader.text('Active Members in ' + communityName);
                memberTableBody.empty();
                if (members && members.length > 0) {
                    members.forEach(member => {
                        var row = '<tr>' +
                            '<td>' + escapeHtml(member.member_name) + '</td>' +
                            '<td>' + escapeHtml(member.residential_address) + '</td>' +
                            '<td>' + escapeHtml(member.phone_number) + '</td>' +
                            '<td>' + escapeHtml(member.email) + '</td>' +
                        '</tr>';
                        memberTableBody.append(row);
                    });
                } else {
                    memberTableBody.html('<tr><td colspan="4" class="text-center text-muted">No active members found in this community.</td></tr>');
                }
                $('html, body').animate({ scrollTop: memberPanel.offset().top - 70 }, 500);
            },
            error: function() {
                memberHeader.text('Members in ' + communityName);
                memberTableBody.html('<tr><td colspan="4" class="text-center text-danger">Failed to load member data. Please try again.</td></tr>');
            }
        });
    });
    
    function escapeHtml(text) {
        if (text === null || text === undefined) return "";
        return text.toString()
            .replace(/&/g, '{{ $amp }}')
            .replace(/</g, '{{ $lt }}')
            .replace(/>/g, '{{ $gt }}')
            .replace(/"/g, '{{ $quot }}')
            .replace(/'/g, '{{ $apos }}');
    }

    $('#community-toggle').on('click', function() {
        $('#community-panel').toggleClass('collapsed');
        setTimeout(() => map.invalidateSize(), 350);
    });
    $('.ms-toggler').on('click', function() {
        setTimeout(() => map.invalidateSize(), 350);
    });
    setTimeout(() => map.invalidateSize(), 400);
});
</script>
@endpush
