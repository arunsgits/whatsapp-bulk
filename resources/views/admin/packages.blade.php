@extends('admin.layouts.app')

@section('title', 'Subscription Packages')

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

    .btn-create-plan {
        background: var(--primary-gradient);
        color: #ffffff;
        border: none;
        padding: 10px 20px;
        border-radius: var(--radius-md);
        font-size: 0.88rem;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 15px var(--primary-glow);
        transition: all 0.2s ease;
    }

    .btn-create-plan:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 20px var(--primary-glow);
    }

    .packages-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 24px;
    }

    .package-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-xl);
        padding: 30px;
        position: relative;
        display: flex;
        flex-direction: column;
        transition: all 0.2s ease;
    }

    .package-card:hover {
        border-color: var(--border-hover);
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
    }

    .package-card.popular {
        border-color: rgba(59, 130, 246, 0.4);
        background: linear-gradient(180deg, rgba(59, 130, 246, 0.05) 0%, var(--bg-card) 40%);
    }

    .popular-ribbon {
        position: absolute;
        top: 20px;
        right: 20px;
        background: linear-gradient(135deg, #3b82f6, #8b5cf6);
        color: #ffffff;
        font-size: 0.72rem;
        font-weight: 700;
        padding: 4px 12px;
        border-radius: 20px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .pkg-title {
        font-family: var(--font-display);
        font-size: 1.4rem;
        font-weight: 800;
        margin-bottom: 6px;
    }

    .pkg-desc {
        font-size: 0.84rem;
        color: var(--text-secondary);
        margin-bottom: 20px;
        min-height: 40px;
    }

    .pkg-price-row {
        display: flex;
        align-items: baseline;
        gap: 6px;
        margin-bottom: 20px;
    }

    .pkg-currency {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .pkg-amount {
        font-family: var(--font-display);
        font-size: 2.2rem;
        font-weight: 800;
        color: var(--primary);
    }

    .pkg-cycle {
        font-size: 0.85rem;
        color: var(--text-muted);
    }

    .feature-list {
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-bottom: 24px;
        flex: 1;
    }

    .feature-item {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.86rem;
        color: #cbd5e1;
    }

    .feature-item i {
        color: var(--primary);
        font-size: 0.85rem;
        width: 16px;
    }

    .card-footer-stats {
        padding-top: 16px;
        border-top: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 0.82rem;
        color: var(--text-secondary);
        margin-bottom: 16px;
    }

    .card-actions {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .btn-edit-plan {
        flex: 1;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--border);
        color: var(--text-primary);
        padding: 9px;
        border-radius: var(--radius-md);
        font-size: 0.84rem;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        transition: all 0.15s ease;
    }

    .btn-edit-plan:hover {
        background: rgba(59, 130, 246, 0.15);
        border-color: rgba(59, 130, 246, 0.35);
        color: #60a5fa;
    }

    .btn-delete-plan {
        background: rgba(239, 68, 68, 0.08);
        border: 1px solid rgba(239, 68, 68, 0.25);
        color: #f87171;
        padding: 9px 14px;
        border-radius: var(--radius-md);
        font-size: 0.84rem;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s ease;
    }

    .btn-delete-plan:hover {
        background: rgba(239, 68, 68, 0.18);
        border-color: #ef4444;
    }

    /* Modal Styles */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.75);
        backdrop-filter: blur(8px);
        z-index: 100;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal-card {
        width: 100%;
        max-width: 600px;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-xl);
        padding: 28px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.7);
    }

    .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 22px;
        padding-bottom: 14px;
        border-bottom: 1px solid var(--border);
    }

    .modal-title {
        font-family: var(--font-display);
        font-size: 1.25rem;
        font-weight: 700;
    }

    .btn-close-modal {
        background: transparent;
        border: none;
        color: var(--text-muted);
        font-size: 1.1rem;
        cursor: pointer;
    }

    .btn-close-modal:hover {
        color: #fff;
    }

    .modal-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .form-group-full {
        grid-column: 1 / -1;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
        margin-bottom: 14px;
    }

    .form-label {
        font-size: 0.76rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
    }

    .form-input {
        width: 100%;
        height: 42px;
        background: rgba(0, 0, 0, 0.35);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        padding: 0 14px;
        font-size: 0.88rem;
        color: var(--text-primary);
        outline: none;
    }

    .form-input:focus {
        border-color: var(--primary);
    }

    .form-textarea {
        width: 100%;
        background: rgba(0, 0, 0, 0.35);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        padding: 10px 14px;
        font-size: 0.88rem;
        color: var(--text-primary);
        outline: none;
        resize: vertical;
        min-height: 80px;
    }
</style>
@endsection

@section('content')

    <div class="page-header">
        <div>
            <h1 class="page-title">Platform Subscription Tiers</h1>
            <p style="color: var(--text-secondary); font-size: 0.88rem;">Create, edit, and organize customer broadcast packages, limits, and pricing.</p>
        </div>
        <div>
            <button type="button" class="btn-create-plan" onclick="openCreateModal()">
                <i class="fa-solid fa-plus"></i>
                <span>Create Subscription Tier</span>
            </button>
        </div>
    </div>

    <!-- Packages Grid -->
    <div class="packages-container">
        @forelse($packages as $pkg)
            <div class="package-card {{ $pkg->popular ? 'popular' : '' }}">
                @if($pkg->popular)
                    <div class="popular-ribbon">Most Popular</div>
                @endif

                <div class="pkg-title">{{ $pkg->packageName }}</div>
                <div class="pkg-desc">{{ $pkg->packageDesc }}</div>

                <div class="pkg-price-row">
                    <span class="pkg-currency">₹</span>
                    <span class="pkg-amount">{{ $pkg->price }}</span>
                    <span class="pkg-cycle">{{ $pkg->period ?: '/month' }}</span>
                </div>

                <ul class="feature-list">
                    <li class="feature-item">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span><strong>{{ number_format($pkg->msgCount) }}</strong> WhatsApp Broadcast Messages</span>
                    </li>
                    <li class="feature-item">
                        <i class="fa-solid fa-file-invoice"></i>
                        <span><strong>{{ $pkg->templateCount }}</strong> Pre-approved Meta Templates</span>
                    </li>
                    <li class="feature-item">
                        <i class="fa-solid fa-users-gear"></i>
                        <span><strong>{{ number_format($pkg->contactCount ?? 500) }}</strong> Contact Storage Quota</span>
                    </li>
                    <li class="feature-item">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <span><strong>{{ $pkg->day }} Days</strong> Plan Validity Cycle</span>
                    </li>
                    @if(is_array($pkg->features))
                        @foreach($pkg->features as $f)
                            <li class="feature-item">
                                <i class="fa-solid fa-check"></i>
                                <span>{{ $f }}</span>
                            </li>
                        @endforeach
                    @endif
                </ul>

                <div class="card-footer-stats">
                    <span>Active Subscribers</span>
                    <span style="font-family: var(--font-mono); font-weight: 700; color: #34d399; font-size: 0.95rem;">
                        {{ $pkg->subscribers_count ?? 0 }} Tenants
                    </span>
                </div>

                <div class="card-actions">
                    <button type="button" class="btn-edit-plan" onclick="openEditModal({{ json_encode($pkg) }})">
                        <i class="fa-solid fa-pen-to-square"></i>
                        <span>Edit Plan</span>
                    </button>
                    <form action="{{ route('arun-admin.packages.delete', $pkg->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete {{ $pkg->packageName }}? This cannot be undone.');" style="margin: 0;">
                        @csrf
                        <button type="submit" class="btn-delete-plan" title="Delete Plan">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div style="grid-column: 1 / -1; padding: 40px; text-align: center; color: var(--text-muted); background: var(--bg-card); border-radius: var(--radius-xl);">
                No subscription packages found. Click "Create Subscription Tier" above to build your first plan.
            </div>
        @endforelse
    </div>

    <!-- Create Plan Modal -->
    <div class="modal-overlay" id="createModal">
        <div class="modal-card">
            <div class="modal-header">
                <div class="modal-title">Create Subscription Tier</div>
                <button type="button" class="btn-close-modal" onclick="closeCreateModal()">&times;</button>
            </div>

            <form action="{{ route('arun-admin.packages.create') }}" method="POST">
                @csrf
                <div class="modal-form-grid">
                    <div class="form-group form-group-full">
                        <label class="form-label">Package Name *</label>
                        <input type="text" name="packageName" class="form-input" placeholder="e.g. Pro Growth Plan" required>
                    </div>

                    <div class="form-group form-group-full">
                        <label class="form-label">Package Description</label>
                        <input type="text" name="packageDesc" class="form-input" placeholder="Brief summary of target business scale">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Price (₹) *</label>
                        <input type="text" name="price" class="form-input" placeholder="2499" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Renewal Price (₹)</label>
                        <input type="text" name="renewal_price" class="form-input" placeholder="2499">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Billing Period</label>
                        <select name="period" class="form-input">
                            <option value="/month">Monthly (/month)</option>
                            <option value="/year">Yearly (/year)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Validity (Days) *</label>
                        <input type="number" name="day" class="form-input" value="30" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Messages Quota *</label>
                        <input type="number" name="msgCount" class="form-input" placeholder="5000" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Template Quota *</label>
                        <input type="number" name="templateCount" class="form-input" placeholder="25" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contact Limit</label>
                        <input type="number" name="contactCount" class="form-input" placeholder="2500" value="1000">
                    </div>

                    <div class="form-group" style="justify-content: center;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-top: 18px;">
                            <input type="checkbox" name="popular" value="1">
                            <span style="font-size: 0.85rem; font-weight: 600;">Mark as Most Popular</span>
                        </label>
                    </div>

                    <div class="form-group form-group-full">
                        <label class="form-label">Features List (One feature per line)</label>
                        <textarea name="features" class="form-textarea" placeholder="5,000 Messages per Month&#10;25 Approved Templates&#10;Priority 24/7 Support"></textarea>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
                    <button type="button" class="btn-edit-plan" onclick="closeCreateModal()" style="width: auto; padding: 10px 20px;">Cancel</button>
                    <button type="submit" class="btn-create-plan">Save New Plan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Plan Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-card">
            <div class="modal-header">
                <div class="modal-title">Edit Subscription Tier</div>
                <button type="button" class="btn-close-modal" onclick="closeEditModal()">&times;</button>
            </div>

            <form id="editPlanForm" method="POST">
                @csrf
                <div class="modal-form-grid">
                    <div class="form-group form-group-full">
                        <label class="form-label">Package Name *</label>
                        <input type="text" id="edit_packageName" name="packageName" class="form-input" required>
                    </div>

                    <div class="form-group form-group-full">
                        <label class="form-label">Package Description</label>
                        <input type="text" id="edit_packageDesc" name="packageDesc" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Price (₹) *</label>
                        <input type="text" id="edit_price" name="price" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Renewal Price (₹)</label>
                        <input type="text" id="edit_renewal_price" name="renewal_price" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Billing Period</label>
                        <select id="edit_period" name="period" class="form-input">
                            <option value="/month">Monthly (/month)</option>
                            <option value="/year">Yearly (/year)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Validity (Days) *</label>
                        <input type="number" id="edit_day" name="day" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Messages Quota *</label>
                        <input type="number" id="edit_msgCount" name="msgCount" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Template Quota *</label>
                        <input type="number" id="edit_templateCount" name="templateCount" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contact Limit</label>
                        <input type="number" id="edit_contactCount" name="contactCount" class="form-input">
                    </div>

                    <div class="form-group" style="justify-content: center;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-top: 18px;">
                            <input type="checkbox" id="edit_popular" name="popular" value="1">
                            <span style="font-size: 0.85rem; font-weight: 600;">Mark as Most Popular</span>
                        </label>
                    </div>

                    <div class="form-group form-group-full">
                        <label class="form-label">Features List (One feature per line)</label>
                        <textarea id="edit_features" name="features" class="form-textarea"></textarea>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
                    <button type="button" class="btn-edit-plan" onclick="closeEditModal()" style="width: auto; padding: 10px 20px;">Cancel</button>
                    <button type="submit" class="btn-create-plan">Update Package</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@section('scripts')
<script>
    function openCreateModal() {
        document.getElementById('createModal').classList.add('active');
    }
    function closeCreateModal() {
        document.getElementById('createModal').classList.remove('active');
    }

    function openEditModal(pkg) {
        document.getElementById('editPlanForm').action = '/arun-admin/packages/' + pkg.id + '/update';
        document.getElementById('edit_packageName').value = pkg.packageName || '';
        document.getElementById('edit_packageDesc').value = pkg.packageDesc || '';
        document.getElementById('edit_price').value = pkg.price || '';
        document.getElementById('edit_renewal_price').value = pkg.renewal_price || pkg.price || '';
        document.getElementById('edit_period').value = pkg.period || '/month';
        document.getElementById('edit_day').value = pkg.day || 30;
        document.getElementById('edit_msgCount').value = pkg.msgCount || 0;
        document.getElementById('edit_templateCount').value = pkg.templateCount || 0;
        document.getElementById('edit_contactCount').value = pkg.contactCount || 500;
        document.getElementById('edit_popular').checked = Boolean(pkg.popular);

        if (Array.isArray(pkg.features)) {
            document.getElementById('edit_features').value = pkg.features.join('\n');
        } else {
            document.getElementById('edit_features').value = '';
        }

        document.getElementById('editModal').classList.add('active');
    }
    function closeEditModal() {
        document.getElementById('editModal').classList.remove('active');
    }

    // Close on overlay click
    window.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal-overlay')) {
            e.target.classList.remove('active');
        }
    });
</script>
@endsection
