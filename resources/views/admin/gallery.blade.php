@extends('layouts.app')

@section('content')
<div class="row">
    @if($isAdmin)
    <div class="col-xl-4">
        <div class="ms-panel">
            <div class="ms-panel-header">
                <h6 id="header-text">Upload to Gallery</h6>
            </div>
            <div class="ms-panel-body">
                <form method="POST" action="{{ route('admin.gallery.store') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="photo_id" id="p_id">
                    <div class="form-group">
                        <label>Select Photo</label>
                        <input type="file" name="img_file" class="form-control" accept="image/*">
                    </div>
                    <div class="form-group">
                        <label>Title <span class="text-danger">*</span></label>
                        <input type="text" name="photo_title" id="p_title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="photo_desc" id="p_desc" class="form-control" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block" id="btn-sub">Upload Now</button>
                </form>
            </div>
        </div>
    </div>
    @endif

    <div class="{{ $isAdmin ? 'col-xl-8' : 'col-xl-12' }}">
        <div class="row">
            @forelse($photos as $photo)
            <div class="col-md-6 col-lg-4">
                <div class="g-card">
                    <img src="{{ asset($photo->image_path) }}" alt="Gallery Image" style="width:100%;height:280px;object-fit:cover;object-position:center top;">
                    <div class="g-card-body" style="padding:15px;text-align:center;">
                        <h6>{{ $photo->title }}</h6>
                        @if($isAdmin)
                        <div class="mt-3">
                            <a href="{{ asset($photo->image_path) }}" download="{{ $photo->title }}" class="btn btn-download btn-block mb-2" style="background-color:#F97316;color:white;border:none;">
                                <i class="fa fa-download"></i> Download Photo
                            </a>
                            <button class="btn btn-sm btn-info btn-block mb-2" onclick="prepareEdit({{ $photo->id }}, '{{ addslashes($photo->title) }}', '{{ addslashes($photo->description ?? '') }}')">Edit Record</button>
                            <a href="{{ route('admin.gallery.delete', $photo->id) }}" class="btn btn-sm btn-danger btn-block" onclick="return confirm('Delete permanently?')">Delete Photo</a>
                        </div>
                        @else
                        <div class="mt-3">
                            <a href="{{ asset($photo->image_path) }}" download="{{ $photo->title }}" class="btn btn-download btn-block" style="background-color:#F97316;color:white;border:none;">
                                <i class="fa fa-download"></i> Download Photo
                            </a>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12 text-center py-5">
                <i class="fas fa-images fa-4x text-muted mb-3"></i>
                <p class="text-muted">No photos in the gallery yet.</p>
            </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function prepareEdit(id, title, desc) {
        document.getElementById('p_id').value = id;
        document.getElementById('p_title').value = title;
        document.getElementById('p_desc').value = desc;
        document.getElementById('header-text').innerText = "Edit Photo";
        document.getElementById('btn-sub').innerText = "Save Changes";
        document.getElementById('btn-sub').className = "btn btn-warning btn-block";
        window.scrollTo(0, 0);
    }
</script>
<style>
    .g-card { background: #fff; border-radius: 12px; margin-bottom: 25px; border: 1px solid #eee; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); transition: 0.3s; }
    .g-card:hover { transform: translateY(-5px); box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
    .btn-download:hover { background-color: #EA580C !important; color: white !important; }
</style>
@endpush
