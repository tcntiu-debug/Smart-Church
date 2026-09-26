@extends('layouts.app')

@section('content')
<div class="ms-panel">
    <div class="ms-panel-header">
        <h6>{{ $title ?? 'Page' }}</h6>
    </div>
    <div class="ms-panel-body text-center py-5">
        <i class="fas fa-tools fa-4x text-muted mb-3"></i>
        <h4>{{ $title ?? 'Page' }}</h4>
        <p class="text-muted">{{ $message ?? 'This page is under construction.' }}</p>
    </div>
</div>
@endsection
