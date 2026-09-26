@extends('layouts.app')

@section('content')
<style>
    .marketplace-header {
        background: linear-gradient(135deg, #ffa94d 0%, #ff8306 100%);
        color: white !important;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 25px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .marketplace-header h3,
    .marketplace-header p {
        color: white !important;
    }
    .marketplace-header h3 {
        font-weight: 600;
        margin-bottom: 8px;
        font-size: 24px;
    }
    .business-card {
        transition: transform 0.2s, box-shadow 0.2s;
        border-radius: 12px;
        overflow: hidden;
        height: 100%;
        border: none;
    }
    .business-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    .business-card .card-body {
        padding: 20px;
    }
    .business-category {
        display: inline-block;
        padding: 4px 12px;
        background: #e9ecef;
        border-radius: 20px;
        font-size: 11px;
        color: #495057;
        margin-bottom: 10px;
    }
    .business-name {
        font-size: 16px;
        font-weight: 700;
        margin-top: 8px;
        margin-bottom: 5px;
        color: #1a1a2e;
    }
    .business-owner {
        font-size: 12px;
        color: #667eea;
        margin-bottom: 10px;
    }
    .business-description {
        font-size: 13px;
        color: #555;
        line-height: 1.5;
        margin-bottom: 12px;
    }
    .disclaimer-box {
        background-color: #fff3cd;
        border-left: 4px solid #ffc107;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 25px;
        font-size: 13px;
    }
    .contact-info-section {
        background: #f8f9fa;
        padding: 10px 12px;
        border-radius: 8px;
        margin: 10px 0;
    }
    .contact-info-item {
        font-size: 12px;
        margin-bottom: 5px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .contact-info-item i {
        width: 20px;
        color: #667eea;
    }
    .departments-section {
        margin-top: 10px;
        padding-top: 8px;
        border-top: 1px dashed #e0e0e0;
    }
    .department-badge {
        display: inline-block;
        background: #e9ecef;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 10px;
        margin-right: 5px;
        margin-bottom: 5px;
        color: #495057;
    }
    .filter-section {
        background: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .category-title {
        font-size: 18px;
        font-weight: 600;
        color: #333;
        border-bottom: 2px solid #e9ecef;
        padding-bottom: 8px;
        margin-bottom: 20px;
    }
    .category-title i {
        color: #667eea;
        margin-right: 8px;
    }
    .whatsapp-btn {
        font-size: 13px;
        padding: 8px 12px;
        margin-top: 10px;
    }
    .label-text {
        font-weight: 600;
        color: #555;
        font-size: 11px;
        margin-bottom: 2px;
    }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="marketplace-header">
        <h3><i class="fas fa-store me-2"></i>TCN Ikorodu Marketplace</h3>
        <p class="mb-0 opacity-75">Find businesses and services offered by members of our community.</p>
    </div>

    <!-- Disclaimer -->
    <div class="disclaimer-box">
        <i class="fas fa-exclamation-triangle me-2" style="color: #856404;"></i>
        <strong>Disclaimer:</strong> TCN Ikorodu is not liable for any transactions, services, or damages that may result from connections made through this platform. Users are advised to follow strict business rules and conduct their own due diligence before engaging in any transaction.
    </div>

    <!-- Filters -->
    <div class="filter-section">
        <form method="GET" action="{{ route('marketplace.purchase') }}" class="row align-items-end">
            <div class="col-md-5 mb-3">
                <label class="form-label fw-bold">Search by name or keyword...</label>
                <input type="text" name="search" class="form-control" placeholder="Search businesses..." value="{{ request('search') }}">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label fw-bold">Category</label>
                <select name="category" class="form-control">
                    <option value="">All Categories</option>
                    @foreach($categories ?? [] as $cat)
                        <option value="{{ $cat->category_id }}" {{ request('category') == $cat->category_id ? 'selected' : '' }}>
                            {{ $cat->category_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 mb-3">
                <label class="form-label fw-bold">Campus</label>
                <select name="campus" class="form-control">
                    <option value="">All Campuses</option>
                    @foreach($campuses ?? [] as $camp)
                        <option value="{{ $camp->cid }}" {{ request('campus') == $camp->cid ? 'selected' : '' }}>
                            {{ $camp->cname }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i> Search
                </button>
                <a href="{{ route('marketplace.purchase') }}" class="btn btn-outline-secondary w-100 mt-2">
                    <i class="fas fa-sync-alt me-1"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Results -->
    @if(isset($grouped) && $grouped->count() > 0)
        @foreach($grouped as $catName => $items)
        <div class="mb-4">
            <h5 class="category-title">
                <i class="fas fa-tag"></i>{{ $catName }}
                <span class="badge bg-secondary ms-2">{{ $items->count() }}</span>
            </h5>
            <div class="row">
                @foreach($items as $b)
                <div class="col-md-4 mb-4">
                    <div class="card business-card shadow-sm">
                        <div class="card-body">
                            <span class="business-category">{{ $catName }}</span>
                            <h5 class="business-name">{{ $b->business_name }}</h5>
                            <div class="business-owner">
                                <i class="fas fa-user-circle"></i> Owner: {{ $b->owner_full_name ?? 'TCN Member' }}
                            </div>
                            <p class="business-description">{{ Str::limit($b->business_details ?? 'No description available.', 150) }}</p>
                            
                            <!-- Contact Information -->
                            <div class="contact-info-section">
                                <div class="label-text">Contact Info:</div>
                                @if($b->contact_phone)
                                    <div class="contact-info-item">
                                        <i class="fas fa-phone"></i>
                                        <span>{{ $b->contact_phone }}</span>
                                        <a href="https://wa.me/{{ preg_replace('/^0/', '234', $b->contact_phone) }}" target="_blank" class="text-success ms-2">
                                            <i class="fab fa-whatsapp"></i> WhatsApp
                                        </a>
                                    </div>
                                @endif
                                @if($b->contact_email)
                                    <div class="contact-info-item">
                                        <i class="fas fa-envelope"></i>
                                        <span>{{ $b->contact_email }}</span>
                                    </div>
                                @endif
                                @if($b->office_address)
                                    <div class="contact-info-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span>{{ Str::limit($b->office_address, 80) }}</span>
                                    </div>
                                @endif
                            </div>
                            
                            <!-- Departments -->
                            @if($b->department_names && $b->department_names != 'None')
                            <div class="departments-section">
                                <div class="label-text">Departments:</div>
                                @foreach(explode(', ', $b->department_names) as $dept)
                                    <span class="department-badge">{{ trim($dept) }}</span>
                                @endforeach
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    @else
        <div class="text-center py-5">
            <i class="fas fa-store fa-4x text-muted mb-3"></i>
            <p class="text-muted">No approved businesses available at the moment.</p>
        </div>
    @endif

    <!-- Pagination -->
    <div class="d-flex justify-content-center">
        {{ $businesses->appends(request()->query())->links() }}
    </div>
</div>
@endsection
