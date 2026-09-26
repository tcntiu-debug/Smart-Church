@php
$member_role = session('member_role', 'Guest');
@endphp
@extends('layouts.app')

@section('content')
<style>
  #department-chart {
    max-height: 300px;
  }
  #table-results table {
    width: 100% !important;
  }
  #table-results .dataTables_wrapper .dataTables_filter input {
    margin-left: 0.5em;
    display: inline-block;
    width: auto;
  }
  #table-results .dataTables_wrapper .dataTables_length select {
    width: auto;
    display: inline-block;
  }
</style>
<div class="row">
  <div class="col-md-12">
    <!-- Filter Panel -->
    <div class="ms-panel">
      <div class="ms-panel-header"><h6>Filter Members by Department</h6></div>
      <div class="ms-panel-body">
        <form id="member-filter-form" method="GET">
          <div class="form-row">
            <div class="col-md-5 mb-3">
              <label for="dept-type-filter">Department Type</label>
              <select class="form-control" id="dept-type-filter" name="dept_type" required>
                <option value="" disabled selected>-- Select a Type --</option>
                @foreach($deptTypes as $type)
                  <option value="{{ $type }}" {{ $selectedDeptType == $type ? 'selected' : '' }}>{{ $type }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-5 mb-3">
              <label for="dept-name-filter">Department Name</label>
              <select class="form-control" id="dept-name-filter" name="dept_id" required>
                <option value="">-- First Select a Type --</option>
              </select>
            </div>
            <div class="col-md-2 mb-3 d-flex align-items-end">
              <button type="submit" class="btn btn-primary btn-block">Filter</button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Results Area -->
    <div id="results-area">
      <!-- Chart -->
      <div id="chart-container-wrapper" class="ms-panel" style="display: none;">
        <div class="ms-panel-header">
          <h6 id="chart-title">Department Member Distribution</h6>
        </div>
        <div class="ms-panel-body">
          <canvas id="department-chart"></canvas>
        </div>
      </div>

      <!-- Table Results -->
      <div id="table-results">
        <div class="ms-panel">
          <div class="ms-panel-body">
            <p class="text-center">Please use the filters above to search for members.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
  let myDeptChart;

  // CASCADING DROPDOWN LOGIC
  $('#dept-type-filter').on('change', function() {
    var selectedType = $(this).val();
    var nameDropdown = $('#dept-name-filter');

    nameDropdown.prop('disabled', true).html('<option value="">Loading...</option>');
    $('#chart-container-wrapper').hide();
    if (myDeptChart) { myDeptChart.destroy(); }

    if (!selectedType) {
      nameDropdown.html('<option value="">-- First Select a Type --</option>');
      return;
    }

    // AJAX to get department names
    $.ajax({
      url: '{{ url("/department-view/get-departments") }}',
      type: 'GET',
      data: { dept_type: selectedType },
      dataType: 'json',
      success: function(response) {
        nameDropdown.empty().append('<option value="All">All</option>');
        if (response.length > 0) {
          $.each(response, function(index, dept) {
            nameDropdown.append($('<option>', { value: dept.dept_id, text: dept.dept_name }));
          });
          nameDropdown.prop('disabled', false);
        } else {
          nameDropdown.html('<option value="">No names found</option>');
        }
      },
      complete: function() {
        nameDropdown.trigger('optionsLoaded');
      }
    });

    // AJAX to get chart data
    $.ajax({
      url: '{{ url("/department-view/chart-data") }}',
      type: 'GET',
      data: { dept_type: selectedType },
      dataType: 'json',
      success: function(chartData) {
        if (chartData && chartData.labels && chartData.labels.length > 0) {
          $('#chart-container-wrapper').show();
          $('#chart-title').text(selectedType + ' Member Distribution');
          const ctx = document.getElementById('department-chart').getContext('2d');

          const baseColors = [
            { bg: 'rgba(2, 88, 201, 0.8)', border: 'rgba(2, 88, 201, 1)' },
            { bg: 'rgba(192, 57, 43, 0.8)', border: 'rgba(192, 57, 43, 1)' },
            { bg: 'rgba(27, 122, 53, 0.8)', border: 'rgba(27, 122, 53, 1)' },
            { bg: 'rgba(142, 68, 173, 0.8)', border: 'rgba(142, 68, 173, 1)' },
            { bg: 'rgba(230, 126, 34, 0.8)', border: 'rgba(230, 126, 34, 1)' },
            { bg: 'rgba(22, 160, 133, 0.8)', border: 'rgba(22, 160, 133, 1)' },
            { bg: 'rgba(44, 62, 80, 0.8)', border: 'rgba(44, 62, 80, 1)' },
            { bg: 'rgba(195, 18, 126, 0.8)', border: 'rgba(195, 18, 126, 1)' },
            { bg: 'rgba(125, 87, 34, 0.8)', border: 'rgba(125, 87, 34, 1)' }
          ];

          var backgroundColors = chartData.labels.map(function(_, i) { return baseColors[i % baseColors.length].bg; });
          var borderColors = chartData.labels.map(function(_, i) { return baseColors[i % baseColors.length].border; });

          myDeptChart = new Chart(ctx, {
            type: 'bar',
            data: {
              labels: chartData.labels,
              datasets: [{
                label: 'Member Count',
                data: chartData.data,
                backgroundColor: backgroundColors,
                borderColor: borderColors,
                borderWidth: 1
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
              plugins: { legend: { display: false }, title: { display: false } }
            }
          });
        } else {
          $('#chart-container-wrapper').hide();
        }
      },
      error: function() {
        $('#chart-container-wrapper').hide();
      }
    });
  });

  // FORM SUBMISSION (AJAX)
  $('#member-filter-form').on('submit', function(e) {
    e.preventDefault();
    var resultsArea = $('#table-results');
    var formData = $(this).serialize();
    resultsArea.html('<div class="ms-panel"><div class="ms-panel-body text-center">Loading members...</div></div>');

    $.ajax({
      url: '{{ url("/department-view/fetch-members") }}',
      type: 'GET',
      data: formData,
      success: function(response) {
        resultsArea.html(response);
        // Re-initialize DataTable on the returned table
        if ($.fn.DataTable) {
          $('.data-table').DataTable({
            destroy: true,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
            order: [[0, 'asc']]
          });
        }
      },
      error: function() {
        resultsArea.html('<div class="ms-panel"><div class="ms-panel-body text-danger">Failed to load member data.</div></div>');
      }
    });
  });

  // STICKY FILTERS ON PAGE LOAD
  var selectedDeptType = "{{ $selectedDeptType }}";
  var selectedDeptId = "{{ $selectedDeptId }}";

  if (selectedDeptType) {
    var nameDropdown = $('#dept-name-filter');

    nameDropdown.on('optionsLoaded', function() {
      $(this).val(selectedDeptId);
      if (selectedDeptId) {
        $('#member-filter-form').trigger('submit');
      }
    });

    $('#dept-type-filter').trigger('change');
  }
});
</script>
@endpush
