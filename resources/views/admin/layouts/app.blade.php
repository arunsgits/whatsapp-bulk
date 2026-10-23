<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Control Plane') — ZapReach Super Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <style>
        :root {
            --bg-base: #090e17;
            --bg-surface: #0f172a;
            --bg-surface-elevated: #162036;
            --bg-card: #121c32;
            --bg-card-hover: #192745;
            --border: rgba(148, 163, 184, 0.12);
            --border-hover: rgba(148, 163, 184, 0.24);
            --border-accent: rgba(99, 102, 241, 0.35);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.28);
            --primary-dark: #4f46e5;
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
            --accent: #3b82f6;
            --accent-cyan: #06b6d4;
            --accent-emerald: #10b981;
            --accent-purple: #8b5cf6;
            --accent-amber: #f59e0b;
            --accent-red: #ef4444;
            --sidebar-width: 270px;
            --topbar-height: 70px;
            --font-display: 'Plus Jakarta Sans', sans-serif;
            --font-body: 'Inter', sans-serif;
            --font-mono: 'Fira Code', monospace;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 22px;
            --shadow-subtle: 0 4px 20px rgba(0, 0, 0, 0.4);
            --shadow-glow: 0 0 30px var(--primary-glow);
        }

        /* Dynamic Theme Variations */
        body.theme-indigo {
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.28);
            --primary-dark: #4f46e5;
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
            --border-accent: rgba(99, 102, 241, 0.35);
        }
        body.theme-emerald {
            --primary: #10b981;
            --primary-glow: rgba(16, 185, 129, 0.28);
            --primary-dark: #059669;
            --primary-gradient: linear-gradient(135deg, #10b981 0%, #047857 100%);
            --border-accent: rgba(16, 185, 129, 0.35);
        }
        body.theme-cyan {
            --primary: #06b6d4;
            --primary-glow: rgba(6, 182, 212, 0.28);
            --primary-dark: #0891b2;
            --primary-gradient: linear-gradient(135deg, #06b6d4 0%, #0e7490 100%);
            --border-accent: rgba(6, 182, 212, 0.35);
        }
        body.theme-amber {
            --primary: #f59e0b;
            --primary-glow: rgba(245, 158, 11, 0.28);
            --primary-dark: #d97706;
            --primary-gradient: linear-gradient(135deg, #f59e0b 0%, #b45309 100%);
            --border-accent: rgba(245, 158, 11, 0.35);
        }
        body.theme-violet {
            --primary: #8b5cf6;
            --primary-glow: rgba(139, 92, 246, 0.28);
            --primary-dark: #7c3aed;
            --primary-gradient: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);
            --border-accent: rgba(139, 92, 246, 0.35);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-body);
            background-color: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: var(--bg-base);
        }
        ::-webkit-scrollbar-thumb {
            background: #1e293b;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #334155;
        }

        /* Layout Structure */
        .admin-sidebar {
            width: var(--sidebar-width);
            min-width: var(--sidebar-width);
            height: 100vh;
            background: var(--bg-surface);
            border-right: 1px solid var(--border);
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 50;
            display: flex;
            flex-direction: column;
            transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .admin-sidebar-header {
            height: var(--topbar-height);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px 0 24px;
            border-bottom: 1px solid var(--border);
            gap: 12px;
        }

        .admin-sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text-primary);
        }

        .brand-logo-icon {
            width: 38px;
            height: 38px;
            background: var(--primary-gradient);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 1.1rem;
            box-shadow: 0 0 20px var(--primary-glow);
        }

        .brand-title-wrap {
            display: flex;
            flex-direction: column;
        }

        .brand-title {
            font-family: var(--font-display);
            font-weight: 800;
            font-size: 1.15rem;
            letter-spacing: -0.02em;
            background: linear-gradient(135deg, #ffffff 40%, var(--text-secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .brand-badge {
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .brand-badge::before {
            content: '';
            width: 6px;
            height: 6px;
            background: var(--primary);
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 8px var(--primary);
        }

        .btn-sidebar-close {
            display: none;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            color: var(--text-secondary);
            font-size: 1.1rem;
            width: 34px;
            height: 34px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease;
        }

        .btn-sidebar-close:hover {
            color: var(--accent-red);
            background: rgba(239, 68, 68, 0.15);
            border-color: rgba(239, 68, 68, 0.3);
        }

        /* Sidebar Nav */
        .sidebar-menu {
            flex: 1;
            overflow-y: auto;
            padding: 20px 14px;
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        .menu-group-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            font-weight: 700;
            padding: 0 10px;
            margin-bottom: 6px;
        }

        .nav-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            border-radius: var(--radius-md);
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 500;
            transition: all 0.18s ease;
            position: relative;
        }

        .nav-link i {
            font-size: 1rem;
            width: 22px;
            text-align: center;
            color: var(--text-muted);
            transition: color 0.18s ease;
        }

        .nav-link:hover {
            color: var(--text-primary);
            background: rgba(255, 255, 255, 0.05);
        }

        .nav-link:hover i {
            color: var(--primary);
        }

        .nav-link.active {
            color: #ffffff;
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.18) 0%, rgba(99, 102, 241, 0.04) 100%);
            border-left: 3px solid var(--primary);
            font-weight: 600;
        }

        body.theme-emerald .nav-link.active {
            background: linear-gradient(90deg, rgba(16, 185, 129, 0.18) 0%, rgba(16, 185, 129, 0.04) 100%);
        }
        body.theme-cyan .nav-link.active {
            background: linear-gradient(90deg, rgba(6, 182, 212, 0.18) 0%, rgba(6, 182, 212, 0.04) 100%);
        }
        body.theme-amber .nav-link.active {
            background: linear-gradient(90deg, rgba(245, 158, 11, 0.18) 0%, rgba(245, 158, 11, 0.04) 100%);
        }
        body.theme-violet .nav-link.active {
            background: linear-gradient(90deg, rgba(139, 92, 246, 0.18) 0%, rgba(139, 92, 246, 0.04) 100%);
        }

        .nav-link.active i {
            color: var(--primary);
        }

        .nav-badge {
            margin-left: auto;
            font-size: 0.7rem;
            padding: 2px 7px;
            border-radius: 10px;
            background: rgba(99, 102, 241, 0.15);
            color: var(--primary);
            font-weight: 600;
        }

        /* Sidebar Footer */
        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid var(--border);
            background: rgba(0, 0, 0, 0.2);
        }

        .admin-mini-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border-radius: var(--radius-md);
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
        }

        .mini-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            color: #fff;
            box-shadow: 0 2px 8px var(--primary-glow);
        }

        .mini-info {
            flex: 1;
            min-width: 0;
        }

        .mini-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mini-role {
            font-size: 0.7rem;
            color: var(--primary);
            font-weight: 500;
        }

        .btn-mini-logout {
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 6px;
            border-radius: var(--radius-sm);
            transition: all 0.15s ease;
        }

        .btn-mini-logout:hover {
            color: var(--accent-red);
            background: rgba(239, 68, 68, 0.1);
        }

        /* Backdrop Overlay for Mobile */
        .sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(4, 7, 13, 0.75);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 45;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
        }

        .sidebar-backdrop.active {
            opacity: 1;
            pointer-events: auto;
        }

        /* Main Content Container */
        .admin-main {
            margin-left: var(--sidebar-width);
            flex: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            width: calc(100% - var(--sidebar-width));
            background: radial-gradient(circle at 10% 20%, rgba(99, 102, 241, 0.04) 0%, transparent 40%),
                        radial-gradient(circle at 90% 80%, rgba(16, 185, 129, 0.03) 0%, transparent 40%);
        }

        /* Top Navigation Bar */
        .admin-topbar {
            height: var(--topbar-height);
            background: rgba(15, 23, 42, 0.88);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            gap: 16px;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 16px;
            min-width: 0;
        }

        .btn-sidebar-toggle {
            display: none;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border);
            color: var(--text-primary);
            font-size: 1.1rem;
            width: 38px;
            height: 38px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease;
            flex-shrink: 0;
        }

        .btn-sidebar-toggle:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--border-hover);
        }

        /* Breadcrumb on topbar */
        .topbar-breadcrumb {
            display: flex;
            align-items: center;
            font-size: 0.86rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-shrink: 0;
        }

        /* Theme Palette Picker */
        .theme-palette-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 10px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
            border-radius: 20px;
        }

        .theme-palette-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .theme-dot-btn {
            width: 15px;
            height: 15px;
            border-radius: 50%;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.15s ease;
            padding: 0;
            display: block;
        }

        .theme-dot-btn:hover {
            transform: scale(1.3);
        }

        .theme-dot-btn.active {
            border-color: #ffffff;
            transform: scale(1.2);
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
        }

        .dot-indigo { background: #6366f1; }
        .dot-emerald { background: #10b981; }
        .dot-cyan { background: #06b6d4; }
        .dot-amber { background: #f59e0b; }
        .dot-violet { background: #8b5cf6; }

        /* Status Pills */
        .status-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 20px;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.25);
            font-size: 0.75rem;
            font-weight: 600;
            color: #34d399;
        }

        .status-dot-pulse {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            position: relative;
        }

        .status-dot-pulse::after {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            border-radius: 50%;
            border: 2px solid #10b981;
            animation: pulse-ring 1.8s infinite;
        }

        @keyframes pulse-ring {
            0% { transform: scale(0.9); opacity: 1; }
            100% { transform: scale(2.2); opacity: 0; }
        }

        /* Profile Dropdown Toggle */
        .admin-profile-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 4px 10px 4px 4px;
            border-radius: 30px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border);
            cursor: pointer;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.15s ease;
        }

        .admin-profile-toggle:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--border-hover);
        }

        .topbar-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
            color: #ffffff;
            box-shadow: 0 2px 8px var(--primary-glow);
        }

        .topbar-admin-name {
            font-size: 0.82rem;
            font-weight: 600;
        }

        /* Page Content Wrapper */
        .admin-content {
            flex: 1;
            padding: 32px;
            max-width: 1540px;
            width: 100%;
            margin: 0 auto;
        }

        /* Alerts & Toasts */
        .alert-box {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
            font-size: 0.9rem;
            animation: slide-in 0.2s ease;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
        }

        @keyframes slide-in {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.14);
            border: 1px solid rgba(16, 185, 129, 0.35);
            color: #34d399;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.14);
            border: 1px solid rgba(239, 68, 68, 0.35);
            color: #f87171;
        }

        .btn-alert-close {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            padding: 0 4px;
            font-size: 1.15rem;
            opacity: 0.7;
            transition: opacity 0.15s ease;
        }
        .btn-alert-close:hover {
            opacity: 1;
        }

        /* Responsive Breakpoints */
        @media (max-width: 992px) {
            .admin-sidebar {
                transform: translateX(-100%);
                box-shadow: 0 0 50px rgba(0, 0, 0, 0.85);
            }
            .admin-sidebar.open {
                transform: translateX(0);
            }
            .sidebar-backdrop {
                display: block;
            }
            .btn-sidebar-close {
                display: flex;
            }
            .admin-main {
                margin-left: 0;
                width: 100%;
            }
            .btn-sidebar-toggle {
                display: flex;
            }
            .admin-content {
                padding: 20px 16px;
            }
            .admin-topbar {
                padding: 0 16px;
            }
        }

        @media (max-width: 768px) {
            .theme-palette-wrap .theme-palette-label {
                display: none;
            }
        }

        @media (max-width: 640px) {
            .topbar-breadcrumb span:first-child,
            .topbar-breadcrumb span:nth-child(2) {
                display: none;
            }
            .status-pill span:last-child {
                display: none;
            }
            .status-pill {
                padding: 6px 8px;
            }
            .theme-palette-wrap {
                display: none;
            }
            .topbar-admin-name {
                display: none;
            }
            .admin-profile-toggle {
                padding: 2px;
                border-radius: 50%;
            }
        }
    </style>
    <script>
        // Pre-apply saved theme immediately to prevent flashing
        (function() {
            var savedTheme = localStorage.getItem('zapreach_admin_theme') || 'indigo';
            document.documentElement.className = 'theme-' + savedTheme;
        })();
    </script>
    @yield('styles')
</head>
<body class="theme-indigo">

    <!-- Mobile Sidebar Backdrop Overlay -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Left Sidebar -->
    <aside class="admin-sidebar" id="adminSidebar">
        <!-- Sidebar Header / Brand -->
        <div class="admin-sidebar-header">
            <a href="{{ route('arun-admin.dashboard') }}" class="admin-sidebar-brand">
                <img src="{{ asset('images/zapreach-icon.svg') }}" alt="ZapReach" style="width: 38px; height: 38px; filter: drop-shadow(0 0 14px var(--primary-glow));">
                <div class="brand-title-wrap">
                    <span class="brand-title">ZapReach</span>
                    <span class="brand-badge">Super Admin</span>
                </div>
            </a>
            <button type="button" class="btn-sidebar-close" id="btnSidebarClose" title="Close menu">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Sidebar Navigation Groups -->
        <nav class="sidebar-menu">
            <!-- Group: Overview -->
            <div>
                <div class="menu-group-label">Core Command</div>
                <ul class="nav-list">
                    <li>
                        <a href="{{ route('arun-admin.dashboard') }}" class="nav-link {{ request()->routeIs('arun-admin.dashboard') ? 'active' : '' }}">
                            <i class="fa-solid fa-gauge-high"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('arun-admin.health') }}" class="nav-link {{ request()->routeIs('arun-admin.health') ? 'active' : '' }}">
                            <i class="fa-solid fa-heart-pulse"></i>
                            <span>System Diagnostics</span>
                            <span class="nav-badge">Live</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Group: Tenants & Accounts -->
            <div>
                <div class="menu-group-label">Tenants & Finance</div>
                <ul class="nav-list">
                    <li>
                        <a href="{{ route('arun-admin.clients.index') }}" class="nav-link {{ request()->routeIs('arun-admin.clients.*') || request()->routeIs('arun-admin.users') ? 'active' : '' }}">
                            <i class="fa-solid fa-building-user"></i>
                            <span>All Clients / Tenants</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('arun-admin.transactions.index') }}" class="nav-link {{ request()->routeIs('arun-admin.transactions.*') ? 'active' : '' }}">
                            <i class="fa-solid fa-receipt"></i>
                            <span>Financial Transactions</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('arun-admin.packages') }}" class="nav-link {{ request()->routeIs('arun-admin.packages*') ? 'active' : '' }}">
                            <i class="fa-solid fa-layer-group"></i>
                            <span>Subscription Plans</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Group: Engine & Settings -->
            <div>
                <div class="menu-group-label">Engine & Platform</div>
                <ul class="nav-list">
                    <li>
                        <a href="{{ route('arun-admin.settings') }}" class="nav-link {{ request()->routeIs('arun-admin.settings') ? 'active' : '' }}">
                            <i class="fa-solid fa-sliders"></i>
                            <span>Platform Settings</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('arun-admin.profile') }}" class="nav-link {{ request()->routeIs('arun-admin.profile') ? 'active' : '' }}">
                            <i class="fa-solid fa-user-shield"></i>
                            <span>Admin Profile</span>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Sidebar Mini User Card & Logout -->
        <div class="sidebar-footer">
            <div class="admin-mini-profile">
                <a href="{{ route('arun-admin.profile') }}" style="display: flex; align-items: center; gap: 10px; text-decoration: none; flex: 1; min-width: 0;">
                    <div class="mini-avatar">
                        {{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}
                    </div>
                    <div class="mini-info">
                        <div class="mini-name">{{ Auth::user()->name ?? 'Super Admin' }}</div>
                        <div class="mini-role">Super Administrator</div>
                    </div>
                </a>
                <form action="{{ route('arun-admin.logout') }}" method="POST" style="margin: 0;">
                    @csrf
                    <button type="submit" class="btn-mini-logout" title="Sign out safely">
                        <i class="fa-solid fa-power-off"></i>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <!-- Main Container -->
    <div class="admin-main">
        <!-- Top Navigation Bar -->
        <header class="admin-topbar">
            <div class="topbar-left">
                <button class="btn-sidebar-toggle" id="btnToggleSidebar" title="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="topbar-breadcrumb">
                    <span style="font-size: 0.82rem; color: var(--text-muted); font-weight: 500;">Super Admin</span>
                    <span style="color: var(--border-hover); margin: 0 8px;">/</span>
                    <span style="font-size: 0.88rem; color: #ffffff; font-weight: 700;">@yield('title', 'Control Plane')</span>
                </div>
            </div>

            <!-- Right Side of Topbar -->
            <div class="topbar-right">
                <!-- Theme Accent Picker -->
                <div class="theme-palette-wrap" title="Change Color Accent Theme">
                    <span class="theme-palette-label"><i class="fa-solid fa-palette" style="margin-right: 4px;"></i>Color:</span>
                    <button type="button" class="theme-dot-btn dot-indigo active" onclick="setAdminTheme('indigo')" title="Electric Indigo"></button>
                    <button type="button" class="theme-dot-btn dot-emerald" onclick="setAdminTheme('emerald')" title="WhatsApp Emerald"></button>
                    <button type="button" class="theme-dot-btn dot-cyan" onclick="setAdminTheme('cyan')" title="Cyber Cyan"></button>
                    <button type="button" class="theme-dot-btn dot-amber" onclick="setAdminTheme('amber')" title="Sunset Amber"></button>
                    <button type="button" class="theme-dot-btn dot-violet" onclick="setAdminTheme('violet')" title="Cosmic Violet"></button>
                </div>

                <!-- Status Pill -->
                <div class="status-pill">
                    <span class="status-dot-pulse"></span>
                    <span>WABA Cloud: Ready</span>
                </div>

                <!-- Super Admin Profile Card & Logout -->
                <div class="admin-profile-toggle">
                    <a href="{{ route('arun-admin.profile') }}" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: inherit;">
                        <div class="topbar-avatar">
                            {{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}
                        </div>
                        <span class="topbar-admin-name">{{ Auth::user()->name ?? 'Super Admin' }}</span>
                    </a>
                    <form action="{{ route('arun-admin.logout') }}" method="POST" style="margin: 0; display: inline-flex;">
                        @csrf
                        <button type="submit" style="background: none; border: none; color: #ef4444; cursor: pointer; padding: 2px 6px; font-size: 0.85rem;" title="Logout">
                            <i class="fa-solid fa-right-from-bracket"></i>
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <!-- Main Page View Content -->
        <main class="admin-content">
            <!-- Flash Success Alert -->
            @if(session('success'))
                <div class="alert-box alert-success" id="flashSuccessAlert">
                    <i class="fa-solid fa-circle-check"></i>
                    <span style="flex: 1;">{{ session('success') }}</span>
                    <button type="button" class="btn-alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            @endif

            <!-- Flash Error Alert -->
            @if(session('error'))
                <div class="alert-box alert-error" id="flashErrorAlert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span style="flex: 1;">{{ session('error') }}</span>
                    <button type="button" class="btn-alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <!-- Theme Switcher & Mobile Drawer Scripts -->
    <script>
        function setAdminTheme(themeName) {
            document.body.className = 'theme-' + themeName;
            document.documentElement.className = 'theme-' + themeName;
            localStorage.setItem('zapreach_admin_theme', themeName);

            document.querySelectorAll('.theme-dot-btn').forEach(btn => {
                btn.classList.toggle('active', btn.classList.contains('dot-' + themeName));
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Restore saved theme
            const savedTheme = localStorage.getItem('zapreach_admin_theme') || 'indigo';
            setAdminTheme(savedTheme);

            // Mobile sidebar drawer logic
            const toggleBtn = document.getElementById('btnToggleSidebar');
            const closeBtn = document.getElementById('btnSidebarClose');
            const sidebar = document.getElementById('adminSidebar');
            const backdrop = document.getElementById('sidebarBackdrop');

            function openSidebar() {
                if (sidebar) sidebar.classList.add('open');
                if (backdrop) backdrop.classList.add('active');
            }

            function closeSidebar() {
                if (sidebar) sidebar.classList.remove('open');
                if (backdrop) backdrop.classList.remove('active');
            }

            if (toggleBtn) {
                toggleBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (sidebar && sidebar.classList.contains('open')) {
                        closeSidebar();
                    } else {
                        openSidebar();
                    }
                });
            }

            if (closeBtn) {
                closeBtn.addEventListener('click', closeSidebar);
            }

            if (backdrop) {
                backdrop.addEventListener('click', closeSidebar);
            }

            // Auto-fade flash alerts after 6 seconds
            setTimeout(() => {
                const sAlert = document.getElementById('flashSuccessAlert');
                if (sAlert) {
                    sAlert.style.transition = 'opacity 0.4s ease';
                    sAlert.style.opacity = '0';
                    setTimeout(() => sAlert.remove(), 400);
                }
            }, 6000);
        });
    </script>
    @yield('scripts')
</body>
</html>
