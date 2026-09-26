@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb pl-0">
                <li class="breadcrumb-item"><a href="{{ url('/mytask') }}"><i class="material-icons">home</i> Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Community Config</li>
            </ol>
        </nav>

        @if(session('success'))
            <div class="alert alert-success">{!! session('success') !!}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="row">
            <div class="col-lg-8 col-md-10 mx-auto">

                <!-- Edit Selector Panel -->
                <div class="ms-panel">
                    <div class="ms-panel-header">
                        <h6>Edit an Existing Community</h6>
                    </div>
                    <div class="ms-panel-body">
                        <div class="form-row align-items-end">
                            <div class="col-md-9">
                                <label for="community_selector">Select a Community to Edit</label>
                                <select id="community_selector" class="form-control">
                                    <option value="">-- Choose a community --</option>
                                    @foreach($allCommunities as $community)
                                        <option value="{{ $community->id }}">{{ $community->community_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button id="edit-button" class="btn btn-primary btn-block">Edit Selected</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add/Edit Form Panel -->
                <div class="ms-panel">
                    <div class="ms-panel-header">
                        <h6>{{ $isEditMode ? 'Editing: ' . e($communityData->community_name) : 'Add New Community Location' }}</h6>
                    </div>
                    <div class="ms-panel-body">
                        <form action="{{ url('/config-community/store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="community_id" value="{{ $isEditMode ? e($editId) : '' }}">

                            <div class="form-group">
                                <label for="community_name">Community Name</label>
                                <input type="text" class="form-control" id="community_name" name="community_name"
                                       value="{{ old('community_name', $communityData->community_name ?? '') }}" required>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="latitude">Latitude</label>
                                    <input type="text" class="form-control" id="latitude" name="latitude"
                                           value="{{ old('latitude', $communityData->latitude ?? '') }}" required>
                                    <small class="form-text text-muted">Range: -90 to 90</small>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="longitude">Longitude</label>
                                    <input type="text" class="form-control" id="longitude" name="longitude"
                                           value="{{ old('longitude', $communityData->longitude ?? '') }}" required>
                                    <small class="form-text text-muted">Range: -180 to 180</small>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-warning btn-block">
                                {{ $isEditMode ? 'Update Community' : 'Submit Community' }}
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Community List Panel -->
                <div class="ms-panel">
                    <div class="ms-panel-header">
                        <h6>All Communities</h6>
                    </div>
                    <div class="ms-panel-body">
                        <div class="table-responsive">
                            <table class="table table-hover thead-primary">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Latitude</th>
                                        <th>Longitude</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($allCommunities as $community)
                                        <tr>
                                            <td>{{ $community->id }}</td>
                                            <td>{{ $community->community_name }}</td>
                                            <td>{{ $community->latitude ?? '—' }}</td>
                                            <td>{{ $community->longitude ?? '—' }}</td>
                                            <td>
                                                <a href="{{ url('/config-community?edit_id=' . $community->id) }}"
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="{{ url('/config-community/delete/' . $community->id) }}"
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Delete {{ $community->community_name }}?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">No communities found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $('#edit-button').on('click', function() {
        var selectedId = $('#community_selector').val();
        if (selectedId) {
            window.location.href = '{{ url("/config-community") }}?edit_id=' + selectedId;
        } else {
            alert('Please select a community from the list first.');
        }
    });
});
</script>
@endpush
