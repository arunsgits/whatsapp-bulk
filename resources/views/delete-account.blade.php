<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Account & Data Deletion – ZapReach</title>
    <meta name="description" content="Request permanent account and data deletion for ZapReach WhatsApp Bulk Messaging platform.">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=IBM+Plex+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --primary-light: #eff6ff;
            --navy-dark: #0e2a47;
            --navy-deep: #091b30;
            --sidebar-bg: #102a5c;
            --accent-orange: #f97316;
            --accent-green: #10b981;
            --accent-red: #ef4444;
            --accent-red-hover: #dc2626;
            --bg-page: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 20px -2px rgba(15, 42, 71, 0.08);
            --radius-md: 12px;
            --radius-lg: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-page);
            color: var(--text-main);
            line-height: 1.65;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        /* TOP NAVIGATION */
        header.top-nav {
            background: #ffffff;
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }

        .nav-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .brand-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .brand-logo-icon {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800;
            font-size: 20px;
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
            flex-shrink: 0;
        }

        .brand-text {
            display: flex;
            flex-direction: column;
        }

        .brand-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 19px;
            font-weight: 800;
            color: var(--navy-dark);
            letter-spacing: -0.02em;
            line-height: 1.2;
        }

        .brand-title span.highlight {
            color: var(--primary);
        }

        .brand-subtitle {
            font-size: 11px;
            font-weight: 600;
            color: var(--accent-orange);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 8px;
            list-style: none;
        }

        .nav-item {
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-muted);
            padding: 8px 14px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .nav-item:hover {
            color: var(--primary);
            background: var(--primary-light);
        }

        .nav-item.active {
            color: var(--primary);
            background: var(--primary-light);
            font-weight: 700;
        }

        .nav-cta-btn {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff;
            font-size: 13.5px;
            font-weight: 700;
            text-decoration: none;
            padding: 9px 18px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
            transition: all 0.2s ease;
        }

        .nav-cta-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
        }

        /* HERO SECTION */
        .page-hero {
            background: linear-gradient(135deg, var(--navy-deep) 0%, var(--sidebar-bg) 50%, #1e40af 100%);
            color: #ffffff;
            padding: 56px 24px 64px;
            position: relative;
            overflow: hidden;
        }

        .hero-pattern {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: radial-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px);
            background-size: 24px 24px;
            opacity: 0.6;
            pointer-events: none;
        }

        .hero-container {
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.22);
            backdrop-filter: blur(8px);
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            color: #93c5fd;
            margin-bottom: 18px;
        }

        .hero-badge .dot {
            width: 8px;
            height: 8px;
            background: var(--accent-green);
            border-radius: 50%;
            box-shadow: 0 0 8px var(--accent-green);
        }

        .page-hero h1 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 38px;
            font-weight: 800;
            letter-spacing: -0.03em;
            margin-bottom: 12px;
            line-height: 1.2;
        }

        .page-hero p.subtitle {
            font-size: 17px;
            color: #cbd5e1;
            max-width: 680px;
            margin: 0 auto 24px;
        }

        .hero-meta {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 16px;
            font-size: 13.5px;
            color: #94a3b8;
        }

        .meta-pill {
            background: rgba(15, 23, 42, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 5px 12px;
            border-radius: 6px;
        }

        .meta-pill strong {
            color: #ffffff;
        }

        /* MAIN CONTENT LAYOUT */
        .main-wrapper {
            max-width: 1240px;
            margin: -28px auto 60px;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 28px;
            position: relative;
            z-index: 10;
        }

        /* SIDEBAR / TABLE OF CONTENTS */
        .sidebar-sticky {
            position: sticky;
            top: 86px;
            height: fit-content;
        }

        .toc-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: var(--shadow-md);
        }

        .toc-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .toc-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: var(--navy-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .toc-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .toc-link {
            display: flex;
            align-items: center;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-muted);
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.15s ease;
            line-height: 1.4;
        }

        .toc-link:hover {
            background: var(--primary-light);
            color: var(--primary);
            padding-left: 16px;
        }

        .quick-actions {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 9px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid var(--border-color);
            background: #f8fafc;
            color: var(--text-main);
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        .action-btn.api-btn {
            background: var(--primary-light);
            border-color: #bfdbfe;
            color: var(--primary);
        }

        /* CONTENT CARDS */
        .content-area {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .doc-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 32px 36px;
            box-shadow: var(--shadow-sm);
            scroll-margin-top: 96px;
            transition: border-color 0.2s;
        }

        .card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            gap: 12px;
        }

        .doc-card h2 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 20px;
            font-weight: 700;
            color: var(--navy-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-tag {
            background: #f1f5f9;
            color: #475569;
            font-family: 'IBM Plex Mono', monospace;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 6px;
        }

        .doc-card p {
            font-size: 15px;
            color: #334155;
            margin-bottom: 14px;
            line-height: 1.75;
        }

        .styled-list {
            list-style: none;
            padding-left: 0;
            margin: 12px 0 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .styled-list li {
            position: relative;
            padding-left: 26px;
            font-size: 14.5px;
            color: #334155;
            line-height: 1.6;
        }

        .styled-list li::before {
            content: "";
            position: absolute;
            left: 6px;
            top: 9px;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--primary);
        }

        /* ALERT BOXES */
        .callout {
            border-radius: var(--radius-md);
            padding: 16px 20px;
            margin: 18px 0;
            display: flex;
            gap: 14px;
            align-items: flex-start;
            font-size: 14px;
            line-height: 1.6;
        }

        .callout.danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .callout.info {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }

        .callout.success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .callout-icon {
            font-size: 18px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* GRID BOXES */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 14px;
            margin: 18px 0;
        }

        .info-grid-item {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 16px;
        }

        .info-grid-item h4 {
            font-size: 14px;
            font-weight: 700;
            color: var(--navy-dark);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .info-grid-item p {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.5;
            margin: 0;
        }

        /* DELETION FORM */
        .form-card {
            background: #ffffff;
            border: 2px solid #fecaca;
            border-radius: var(--radius-lg);
            padding: 28px 32px;
            box-shadow: 0 4px 20px rgba(239, 68, 68, 0.08);
            margin-top: 10px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            display: block;
            font-size: 13.5px;
            font-weight: 700;
            color: var(--navy-dark);
            margin-bottom: 6px;
        }

        .form-input, .form-textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            color: var(--text-main);
            background: #f8fafc;
            transition: all 0.2s;
        }

        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--accent-red);
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15);
        }

        .form-checkbox-label {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13.5px;
            color: #475569;
            cursor: pointer;
        }

        .form-checkbox-label input {
            margin-top: 3px;
            width: 16px;
            height: 16px;
            accent-color: var(--accent-red);
        }

        .submit-delete-btn {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14.5px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(239, 68, 68, 0.3);
            transition: all 0.2s;
        }

        .submit-delete-btn:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-1px);
        }

        .submit-delete-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .form-feedback {
            margin-top: 16px;
            padding: 14px;
            border-radius: 8px;
            display: none;
            font-size: 14px;
        }

        /* FOOTER */
        footer.page-footer {
            background: var(--navy-dark);
            color: #94a3b8;
            padding: 48px 24px 32px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            margin-top: 80px;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 24px;
        }

        .footer-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .footer-brand-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 20px;
            font-weight: 800;
            color: #ffffff;
        }

        .footer-links {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            list-style: none;
        }

        .footer-links a {
            color: #cbd5e1;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.2s ease;
        }

        .footer-links a:hover {
            color: #ffffff;
        }

        .footer-copy {
            font-size: 13px;
            color: #64748b;
            padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            width: 100%;
        }

        /* RESPONSIVE */
        @media (max-width: 960px) {
            .main-wrapper {
                grid-template-columns: 1fr;
            }
            .sidebar-sticky {
                position: static;
            }
            .page-hero h1 {
                font-size: 30px;
            }
            .doc-card {
                padding: 24px 20px;
            }
        }
    </style>
</head>
@php
    $prefix = request()->is('services*') ? '/services' : '';
@endphp
<body>

    <!-- TOP NAVIGATION -->
    <header class="top-nav">
        <div class="nav-container">
            <a href="{{ $prefix ?: '/' }}" class="brand-wrapper">
                <div class="brand-logo-icon">Z</div>
                <div class="brand-text">
                    <div class="brand-title">Zap<span class="highlight">Reach</span></div>
                    <div class="brand-subtitle">WhatsApp Platform</div>
                </div>
            </a>

            <ul class="nav-links">
                <li><a href="{{ url($prefix . '/privacy-policy') }}" class="nav-item">Privacy Policy</a></li>
                <li><a href="{{ url($prefix . '/terms-and-conditions') }}" class="nav-item">Terms & Conditions</a></li>
                <li><a href="{{ url($prefix . '/delete-account') }}" class="nav-item active">Delete Account</a></li>
            </ul>
        </div>
    </header>

    <!-- HERO HEADER -->
    <div class="page-hero">
        <div class="hero-pattern"></div>
        <div class="hero-container">
            <div class="hero-badge">
                <span class="dot"></span>
                User Data Freedom & Compliance
            </div>
            <h1>Delete Account & Data Deletion</h1>
            <p class="subtitle">Understand our data deletion policies and request the permanent removal of your account, contact lists, and WhatsApp broadcasting records.</p>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <main class="main-wrapper">
        
        <!-- SIDEBAR NAVIGATION -->
        <aside class="sidebar-sticky">
            <div class="toc-card">
                <div class="toc-header">
                    <div class="toc-title">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"></path></svg>
                        Table of Contents
                    </div>
                </div>
                <ul class="toc-list">
                    <li><a href="#overview" class="toc-link">1. Deletion Overview</a></li>
                    <li><a href="#request-form" class="toc-link">2. Submit Deletion Request</a></li>
                    <li><a href="#data-erased" class="toc-link">3. Data Permanently Erased</a></li>
                    <li><a href="#data-retained" class="toc-link">4. Data Retained by Law</a></li>
                    <li><a href="#timeline" class="toc-link">5. Timeline & Revocation</a></li>
                    <li><a href="#contact" class="toc-link">6. Contact Support</a></li>
                </ul>
            </div>
        </aside>

        <!-- CONTENT SECTION -->
        <div class="content-area">

            <!-- 1. Overview -->
            <section id="overview" class="doc-card">
                <div class="card-head">
                    <h2>1. Overview of Account & Data Deletion</h2>
                    <span class="section-tag">Section 01</span>
                </div>
                <p>At <strong>ZapReach</strong>, we believe you should have complete autonomy over your personal details, business credentials, and audience contact lists.</p>
                <p>You may request the permanent termination of your account and deletion of all associated data at any time. Once processed, all access to the platform and API services will be revoked permanently.</p>
                
                <div class="callout danger">
                    <div class="callout-icon">⚠️</div>
                    <div>
                        <strong>Irreversible Action:</strong> Account deletion cannot be undone. All active message campaigns will be cancelled, remaining wallet credits will be forfeited, and contact lists will be permanently purged.
                    </div>
                </div>
            </section>

            <!-- 2. Request Form -->
            <section id="request-form" class="doc-card">
                <div class="card-head">
                    <h2>2. Submit Account Deletion Request</h2>
                    <span class="section-tag">Interactive Portal</span>
                </div>
                <p>Fill out the form below using your registered account email address to initiate an official deletion request:</p>

                <div class="form-card">
                    <form id="deletionForm" onsubmit="handleDeletionSubmit(event)">
                        <div class="form-group">
                            <label class="form-label" for="userEmail">Registered Account Email <span style="color: var(--accent-red)">*</span></label>
                            <input type="email" id="userEmail" class="form-input" placeholder="e.g. user@yourcompany.com" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="userPhone">Registered Phone Number (Optional)</label>
                            <input type="tel" id="userPhone" class="form-input" placeholder="e.g. +91 9876543210">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="deleteReason">Reason for Deletion (Optional)</label>
                            <textarea id="deleteReason" class="form-textarea" rows="3" placeholder="Tell us why you are leaving (e.g. project completed, switching providers)"></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-checkbox-label">
                                <input type="checkbox" id="confirmCheckbox" required>
                                <span>I understand that deleting my account is permanent and will irreversibly delete all my contact lists, templates, and Meta API connections.</span>
                            </label>
                        </div>

                        <button type="submit" id="submitBtn" class="submit-delete-btn">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"></path></svg>
                            Submit Deletion Request
                        </button>

                        <div id="formFeedback" class="form-feedback"></div>
                    </form>
                </div>
            </section>

            <!-- 3. Data Erased -->
            <section id="data-erased" class="doc-card">
                <div class="card-head">
                    <h2>3. Data That Will Be Permanently Erased</h2>
                    <span class="section-tag">Section 03</span>
                </div>
                <div class="info-grid">
                    <div class="info-grid-item">
                        <h4>👤 Account & Credentials</h4>
                        <p>User profile, company records, password hashes, and active authentication tokens.</p>
                    </div>
                    <div class="info-grid-item">
                        <h4>🔑 Meta API Credentials</h4>
                        <p>Meta App ID, App Secret, WABA ID, Phone Number ID, and Webhook secret keys.</p>
                    </div>
                    <div class="info-grid-item">
                        <h4>👥 Audience & Contacts</h4>
                        <p>All contact rosters, phone numbers, custom parameters, tags, and groups.</p>
                    </div>
                    <div class="info-grid-item">
                        <h4>📊 Broadcasts & Media</h4>
                        <p>Campaign logs, message histories, templates, and uploaded images/PDFs.</p>
                    </div>
                </div>
            </section>

            <!-- 4. Data Retained -->
            <section id="data-retained" class="doc-card">
                <div class="card-head">
                    <h2>4. Data Retained for Legal Compliance</h2>
                    <span class="section-tag">Section 04</span>
                </div>
                <p>Under statutory accounting, tax, and GST audit guidelines in India, certain financial records must be archived for a legally prescribed period:</p>
                <ul class="styled-list">
                    <li>Completed Razorpay payment transaction IDs and GST invoice records.</li>
                    <li>Financial ledger credits and tax deduction logs.</li>
                </ul>
                <p>These archived records are strictly stored in restricted financial vaults and are never processed for marketing or analytical purposes.</p>
            </section>

            <!-- 5. Timeline & Revocation -->
            <section id="timeline" class="doc-card">
                <div class="card-head">
                    <h2>5. Deletion Timeline & Meta Token Revocation</h2>
                    <span class="section-tag">Section 05</span>
                </div>
                <p><strong>Timeline:</strong> Upon submitting your request, your account is queued for verification. Account login is immediately blocked, and permanent eradication across databases and file servers is finalized within <strong>14 to 30 calendar days</strong>.</p>
                
                <div class="callout info">
                    <div class="callout-icon">💡</div>
                    <div>
                        <strong>Revoking WhatsApp API Access:</strong> You can also manually revoke access to our application directly from your <a href="https://business.facebook.com" target="_blank" style="color: var(--primary); text-decoration: underline;">Meta Business Manager</a> console under <em>System Users &gt; WhatsApp Business Accounts &gt; Assigned Assets</em>.
                    </div>
                </div>
            </section>

            <!-- 6. Contact -->
            <section id="contact" class="doc-card">
                <div class="card-head">
                    <h2>6. Contact & Manual Assistance</h2>
                    <span class="section-tag">Section 06</span>
                </div>
                <p>If you prefer to submit your deletion request directly or need assistance with your data:</p>
                
                <div class="callout success">
                    <div class="callout-icon">📬</div>
                    <div>
                        <strong>ZapReach Technologies Support</strong><br>
                        Support Email: <a href="mailto:support@zapreach.com" style="color: inherit; font-weight: 600;">support@zapreach.com</a><br>
                        Subject: <em>Account Deletion Request - [Your Registered Email]</em><br>
                        Website: <a href="{{ url('/') }}" style="color: inherit; text-decoration: underline;">{{ url('/') }}</a>
                    </div>
                </div>
            </section>

        </div>
    </main>

    <!-- FOOTER -->
    <footer class="page-footer">
        <div class="footer-container">
            <div class="footer-brand">
                <div class="brand-logo-icon" style="width: 36px; height: 36px; font-size: 16px;">Z</div>
                <span class="footer-brand-title">ZapReach</span>
            </div>

            <ul class="footer-links">
                <li><a href="{{ url($prefix . '/privacy-policy') }}">Privacy Policy</a></li>
                <li><a href="{{ url($prefix . '/terms-and-conditions') }}">Terms & Conditions</a></li>
                <li><a href="{{ url($prefix . '/delete-account') }}">Delete Account</a></li>
            </ul>

            <div class="footer-copy">
                &copy; {{ date('Y') }} ZapReach. Developed & Operated by ZapReach Technologies. All Rights Reserved.
            </div>
        </div>
    </footer>

    <script>
        async function handleDeletionSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            const feedback = document.getElementById('formFeedback');
            const email = document.getElementById('userEmail').value;
            const mobile = document.getElementById('userPhone').value;
            const reason = document.getElementById('deleteReason').value;
            const confirmCheckbox = document.getElementById('confirmCheckbox').checked;

            if (!email || !confirmCheckbox) return;

            btn.disabled = true;
            btn.innerHTML = 'Submitting Request...';
            feedback.style.display = 'none';

            try {
                const response = await fetch('{{ url($prefix . "/api/delete-account-request") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        email: email,
                        mobile: mobile,
                        reason: reason,
                        confirm_deletion: true
                    })
                });

                const data = await response.json();

                if (response.ok && data.status) {
                    feedback.style.display = 'block';
                    feedback.style.background = '#ecfdf5';
                    feedback.style.border = '1px solid #a7f3d0';
                    feedback.style.color = '#065f46';
                    feedback.innerHTML = `<strong>Request Received!</strong> Reference ID: <code>${data.data.reference_id}</code>.<br>${data.message}`;
                    document.getElementById('deletionForm').reset();
                } else {
                    feedback.style.display = 'block';
                    feedback.style.background = '#fef2f2';
                    feedback.style.border = '1px solid #fecaca';
                    feedback.style.color = '#991b1b';
                    feedback.innerHTML = `<strong>Error:</strong> ${data.message || 'Failed to submit request. Please contact support@zapreach.com'}`;
                }
            } catch (err) {
                feedback.style.display = 'block';
                feedback.style.background = '#fef2f2';
                feedback.style.border = '1px solid #fecaca';
                feedback.style.color = '#991b1b';
                feedback.innerHTML = `<strong>Network Error:</strong> Request could not be sent. Please email support@zapreach.com.`;
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"></path></svg> Submit Deletion Request';
            }
        }
    </script>

</body>
</html>
