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
    .terms-box {
        border: 1px solid #ddd;
        padding: 15px;
        border-radius: 5px;
        background-color: #f9f9f9;
        max-height: 200px;
        overflow-y: auto;
        margin-bottom: 1rem;
    }
    .status-badge {
        font-size: 12px;
        padding: 5px 10px;
    }
</style>

<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb pl-0">
                <li class="breadcrumb-item"><a href="{{ url('/mytask') }}"><i class="material-icons">home</i> Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">My Businesses</li>
            </ol>
        </nav>

        <div class="marketplace-header">
            <h3><i class="fas fa-store mr-2"></i> TCN Ikorodu Marketplace</h3>
            <p class="mb-0 opacity-75">Manage your registered businesses</p>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        <!-- My Registered Businesses -->
        <div class="ms-panel">
            <div class="ms-panel-header d-flex justify-content-between align-items-center">
                <h6>My Registered Businesses</h6>
                <a href="{{ route('marketplace.my-businesses', ['register' => 'true']) }}#business-form-panel" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Add a Business
                </a>
            </div>
            <div class="ms-panel-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Business Name</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($businesses as $biz)
                                <tr>
                                    <td>{{ $biz->business_name }}</td>
                                    <td>{{ $biz->category_name }}</td>
                                    <td>
                                        @php
                                            $statusMap = [
                                                'pending_approval' => ['class' => 'badge-warning', 'text' => 'Pending Approval'],
                                                'active' => ['class' => 'badge-success', 'text' => 'Active'],
                                                'pending_deactivation' => ['class' => 'badge-danger', 'text' => 'Pending Deactivation'],
                                                'inactive' => ['class' => 'badge-secondary', 'text' => 'Inactive'],
                                            ];
                                            $statusInfo = $statusMap[$biz->status] ?? ['class' => 'badge-secondary', 'text' => ucfirst($biz->status)];
                                        @endphp
                                        <span class="badge status-badge {{ $statusInfo['class'] }}">{{ $statusInfo['text'] }}</span>
                                    </td>
                                    <td>
                                        <a href="{{ route('marketplace.my-businesses', ['edit' => $biz->business_id]) }}#business-form-panel" class="btn btn-sm btn-info">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        @if($biz->status === 'active')
                                            <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to request deactivation?');">
                                                @csrf
                                                <input type="hidden" name="business_id" value="{{ $biz->business_id }}">
                                                <button type="submit" name="deactivate_business" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-ban"></i> Deactivate
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        You have not registered any businesses yet. The registration form is below to help you get started.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Business Form -->
        @if($formMode === 'edit' || request()->has('register') || $businesses->isEmpty() || session('show_form'))
            <div id="business-form-panel" class="ms-panel mt-4">
                <div class="ms-panel-header">
                    <h6>{{ $formMode === 'create' ? 'Register a New Business' : 'Editing: ' . ($businessToEdit->business_name ?? '') }}</h6>
                </div>
                <div class="ms-panel-body">
                    <form id="business-form" method="post" action="{{ route('marketplace.store-business') }}">
                        @csrf
                        <input type="hidden" name="business_id" value="{{ $businessToEdit->business_id ?? '0' }}">

                        <h6 class="font-weight-bold">Business Details</h6>
                        <hr class="mt-1">
                        <div class="form-row">
                            <div class="col-md-6 mb-3">
                                <label>Business / Brand / Service Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('business_name') is-invalid @enderror" name="business_name" value="{{ old('business_name', $businessToEdit->business_name ?? '') }}" required>
                                @error('business_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Category <span class="text-danger">*</span></label>
                                <select class="form-control @error('category_id') is-invalid @enderror" name="category_id" required>
                                    <option value="">-- Select a Category --</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->category_id }}" {{ (old('category_id', $businessToEdit->category_id ?? '') == $cat->category_id) ? 'selected' : '' }}>
                                            {{ $cat->category_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Detailed Description <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('business_details') is-invalid @enderror" name="business_details" rows="5" placeholder="Describe what you do..." required>{{ old('business_details', $businessToEdit->business_details ?? '') }}</textarea>
                            @error('business_details') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <h6 class="font-weight-bold mt-4">Contact Information (Optional)</h6>
                        <hr class="mt-1">
                        <div class="form-row">
                            <div class="col-md-6 mb-3">
                                <label>Contact Email</label>
                                <input type="email" class="form-control" name="contact_email" value="{{ old('contact_email', $businessToEdit->contact_email ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Contact Phone</label>
                                <input type="tel" class="form-control" name="contact_phone" value="{{ old('contact_phone', $businessToEdit->contact_phone ?? '') }}">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Office / Business Address</label>
                            <textarea class="form-control" name="office_address" rows="3">{{ old('office_address', $businessToEdit->office_address ?? '') }}</textarea>
                        </div>

                        @if($formMode === 'create')
                            <h6 class="font-weight-bold mt-4">Terms of Business</h6>
                            <hr class="mt-1">
                            <div class="terms-box">
                                <p><strong>Disclaimer and Terms of Use</strong></p>
                                <p>By registering your business, service, or professional profile on the TCN Ikorodu Marketplace, you acknowledge and agree to the following terms:</p>
                                <ol>
                                    <li><strong>Connector Platform:</strong> The TCN Ikorodu app serves solely as a platform to connect members. We do not vet, endorse, or guarantee the quality, integrity, or legality of any business, service, or individual listed.</li>
                                    <li><strong>User Responsibility:</strong> Any interaction, transaction, or agreement made between users of this platform is done at your own risk. You are solely responsible for any due diligence, negotiation, and execution of services.</li>
                                    <li><strong>No Liability:</strong> TCN Ikorodu, its leadership, and its app developers shall not be held liable for any direct, indirect, incidental, or consequential gains, losses, damages, disputes, or any other issues that may arise from using this marketplace. All outcomes are the sole responsibility of the users involved.</li>
                                </ol>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input @error('agreed_to_terms') is-invalid @enderror" type="checkbox" id="agreed_to_terms" name="agreed_to_terms" value="1" {{ old('agreed_to_terms') ? 'checked' : '' }}>
                                <label class="form-check-label" for="agreed_to_terms">I have read, understood, and agree to the Terms of Business.</label>
                                @error('agreed_to_terms') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        @endif

                        <button type="submit" id="submit_button" name="submit_business" class="btn btn-primary btn-lg btn-block mt-4">
                            <i class="fa fa-check-circle mr-2"></i> {{ $formMode === 'create' ? 'Submit for Approval' : 'Update My Profile' }}
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    @if(session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '{{ session('success') }}',
            timer: 3000,
            showConfirmButton: true
        });
    @endif
});
</script>
@endpush
