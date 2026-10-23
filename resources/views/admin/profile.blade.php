@extends('admin.layouts.app')

@section('title', 'Admin Profile & Security')

@section('styles')
<style>
    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 16px;
    }

    .page-title {
        font-family: var(--font-display);
        font-size: 1.5rem;
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    .profile-grid {
        display: grid;
        grid-template-columns: 1.2fr 1fr;
        gap: 24px;
    }

    @media (max-width: 992px) {
        .profile-grid {
            grid-template-columns: 1fr;
        }
    }

    .profile-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-xl);
        overflow: hidden;
    }

    .card-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 12px;
        font-family: var(--font-display);
        font-size: 1.05rem;
        font-weight: 700;
    }

    .card-header i {
        color: var(--primary);
        font-size: 1.15rem;
    }

    .card-body {
        padding: 24px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-label {
        display: block;
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        margin-bottom: 8px;
    }

    .form-input {
        width: 100%;
        height: 44px;
        background: rgba(0, 0, 0, 0.25);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        padding: 0 16px;
        font-size: 0.9rem;
        color: var(--text-primary);
        outline: none;
        transition: all 0.18s ease;
    }

    .form-input:focus {
        border-color: var(--primary);
        background: rgba(0, 0, 0, 0.4);
    }

    .btn-submit {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #ffffff;
        border: none;
        padding: 12px 24px;
        border-radius: var(--radius-md);
        font-size: 0.88rem;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
    }

    .btn-submit:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35);
    }

    .avatar-upload-wrap {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 20px;
    }

    .avatar-preview {
        width: 68px;
        height: 68px;
        border-radius: 50%;
        background: linear-gradient(135deg, #10b981, #3b82f6);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        font-weight: 800;
        color: #fff;
        border: 2px solid var(--border-accent);
    }
</style>
@endsection

@section('content')

    <div class="page-header">
        <div>
            <h1 class="page-title">Super Admin Profile & Security</h1>
            <p style="color: var(--text-secondary); font-size: 0.88rem;">Manage your master administrative identity, contact numbers, and security credentials.</p>
        </div>
    </div>

    <div class="profile-grid">
        <!-- 1. Master Profile Details Form -->
        <div class="profile-card">
            <div class="card-header">
                <i class="fa-solid fa-id-badge"></i>
                <span>Administrative Identity</span>
            </div>
            <div class="card-body">
                <form action="{{ route('arun-admin.profile.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="avatar-upload-wrap">
                        <div class="avatar-preview">
                            @if($currentUser->profileImage)
                                <img src="{{ asset($currentUser->profileImage) }}" alt="Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                            @else
                                {{ strtoupper(substr($currentUser->name ?? 'A', 0, 1)) }}
                            @endif
                        </div>
                        <div>
                            <label class="form-label">Profile Avatar</label>
                            <input type="file" name="profile_image" accept="image/*" style="font-size: 0.8rem; color: var(--text-muted);">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" id="name" name="name" class="form-input" value="{{ old('name', $currentUser->name) }}" required>
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">Master Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" value="{{ old('email', $currentUser->email) }}" required>
                    </div>

                    <div class="form-group">
                        <label for="number" class="form-label">Contact / Mobile Number</label>
                        <input type="text" id="number" name="number" class="form-input" value="{{ old('number', $currentUser->number) }}" placeholder="+919876543210">
                    </div>

                    <div class="form-group">
                        <label for="companyName" class="form-label">Entity / Business Name</label>
                        <input type="text" id="companyName" name="companyName" class="form-input" value="{{ old('companyName', $currentUser->companyName) }}">
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Save Profile Changes</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- 2. Security & Password Update Form -->
        <div class="profile-card">
            <div class="card-header">
                <i class="fa-solid fa-lock"></i>
                <span>Authentication Credentials</span>
            </div>
            <div class="card-body">
                <form action="{{ route('arun-admin.profile.password') }}" method="POST">
                    @csrf

                    <div class="form-group">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" id="current_password" name="current_password" class="form-input" placeholder="••••••••••••" required>
                    </div>

                    <div class="form-group">
                        <label for="new_password" class="form-label">New Password (min 8 chars)</label>
                        <input type="password" id="new_password" name="new_password" class="form-input" placeholder="••••••••••••" required>
                    </div>

                    <div class="form-group">
                        <label for="new_password_confirmation" class="form-label">Confirm New Password</label>
                        <input type="password" id="new_password_confirmation" name="new_password_confirmation" class="form-input" placeholder="••••••••••••" required>
                    </div>

                    <button type="submit" class="btn-submit" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>Update Security Password</span>
                    </button>
                </form>

                <div style="margin-top: 32px; padding: 18px; border-radius: var(--radius-md); background: rgba(0, 0, 0, 0.2); border: 1px solid var(--border);">
                    <div style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 8px;">
                        Access Privileges
                    </div>
                    <div style="font-size: 0.85rem; color: #cbd5e1; display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                        <span style="background: rgba(16, 185, 129, 0.2); color: #34d399; font-weight: 700; padding: 2px 8px; border-radius: 4px; font-size: 0.74rem;">
                            {{ strtoupper($currentUser->role) }}
                        </span>
                        <span>Full Platform Master Authority</span>
                    </div>
                    <div style="font-size: 0.76rem; color: var(--text-muted);">
                        Root control over all tenant accounts, packages, API endpoints, and system settings.
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
