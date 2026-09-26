@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h6>Private Resources (Shared with Me)</h6>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if(isset($resources) && $resources->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Description</th>
                            <th>File Name</th>
                            <th style="width: 100px;">Action</th>
                            <th>Shared On</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($resources as $resource)
                        <tr>
                            <td class="align-middle">{{ $resource->title }}</td>
                            <td class="align-middle">{{ Str::limit($resource->description ?? 'No description', 50) }}</td>
                            <td class="align-middle">{{ $resource->file_name }}</td>
                            <td class="align-middle">
                                <a href="{{ asset($resource->file_path) }}" class="btn btn-sm btn-primary" target="_blank">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            </td>
                            <td class="align-middle">{{ \Carbon\Carbon::parse($resource->share_date)->format('F j, Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
                <p class="text-center text-muted">No private resources have been shared with you yet.</p>
            @endif
        </div>
    </div>
</div>
@endsection