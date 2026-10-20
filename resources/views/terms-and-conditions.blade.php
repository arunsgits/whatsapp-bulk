<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms & Conditions – ZapReach</title>
    <meta name="description" content="Terms and Conditions for ZapReach WhatsApp Bulk Messaging Platform. Acceptable use, anti-spam policy, wallet billing, and Meta compliance.">
    
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
            --sidebar-active: #2563eb;
            --accent-orange: #f97316;
            --accent-green: #10b981;
            --accent-purple: #8b5cf6;
            --accent-red: #ef4444;
            --bg-page: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --border-subtle: #edf2f7;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --text-light: #94a3b8;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 20px -2px rgba(15, 42, 71, 0.08);
            --shadow-lg: 0 10px 30px -4px rgba(15, 42, 71, 0.12);
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

        .action-btn.api-btn:hover {
            background: #dbeafe;
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

        .doc-card:hover {
            border-color: #cbd5e1;
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

        .doc-card p:last-child {
            margin-bottom: 0;
        }

        .sub-heading {
            font-size: 16px;
            font-weight: 700;
            color: var(--navy-dark);
            margin: 20px 0 10px;
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

        /* ALERT / CALLOUT BOXES */
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

        .callout.info {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }

        .callout.warning {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
        }

        .callout.danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
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

        /* GRID INFO BOXES */
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

        @media (max-width: 640px) {
            .nav-links {
                display: none;
            }
            .nav-container {
                padding: 10px 16px;
            }
            .page-hero {
                padding: 40px 16px 48px;
            }
            .page-hero h1 {
                font-size: 26px;
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
                <li><a href="{{ url($prefix . '/terms-and-conditions') }}" class="nav-item active">Terms & Conditions</a></li>
                <li><a href="{{ url($prefix . '/delete-account') }}" class="nav-item">Delete Account</a></li>
            </ul>
        </div>
    </header>

    <!-- HERO HEADER -->
    <div class="page-hero">
        <div class="hero-pattern"></div>
        <div class="hero-container">
            <div class="hero-badge">
                <span class="dot"></span>
                Legal Terms of Service
            </div>
            <h1>Terms & Conditions</h1>
            <p class="subtitle">Please read these Terms and Conditions carefully before utilizing the ZapReach bulk messaging services, dashboard, and Cloud APIs.</p>
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
                    <li><a href="#acceptance" class="toc-link">1. Acceptance of Terms</a></li>
                    <li><a href="#account-security" class="toc-link">2. Account & Security</a></li>
                    <li><a href="#anti-spam" class="toc-link">3. Strict Anti-Spam Policy</a></li>
                    <li><a href="#meta-compliance" class="toc-link">4. Meta Cloud API Policy</a></li>
                    <li><a href="#wallet-billing" class="toc-link">5. Wallet & Billing Terms</a></li>
                    <li><a href="#service-disclaimer" class="toc-link">6. Delivery Disclaimer</a></li>
                    <li><a href="#auto-ai" class="toc-link">7. Auto-AI & Automation</a></li>
                    <li><a href="#ip-rights" class="toc-link">8. Intellectual Property</a></li>
                    <li><a href="#liability" class="toc-link">9. Limitation of Liability</a></li>
                    <li><a href="#termination" class="toc-link">10. Suspension & Termination</a></li>
                    <li><a href="#governing-law" class="toc-link">11. Governing Law</a></li>
                    <li><a href="#contact" class="toc-link">12. Contact Information</a></li>
                </ul>
            </div>
        </aside>

        <!-- CONTENT SECTION -->
        <div class="content-area">

            <!-- 1. Acceptance -->
            <section id="acceptance" class="doc-card">
                <div class="card-head">
                    <h2>1. Acceptance of Terms</h2>
                    <span class="section-tag">Section 01</span>
                </div>
                <p>These Terms and Conditions constitute a legally binding agreement between you (whether acting personally or on behalf of an enterprise entity, <strong>"User"</strong>, <strong>"Client"</strong>, or <strong>"You"</strong>) and <strong>ZapReach Technologies</strong> (<strong>"Company"</strong>, <strong>"We"</strong>, <strong>"Us"</strong>, or <strong>"Our"</strong>), concerning your access to and use of <strong>ZapReach</strong> accessible at <a href="{{ url('/') }}" style="color: var(--primary); text-decoration: underline;">{{ url('/') }}</a> (the <strong>"Platform"</strong> or <strong>"Services"</strong>).</p>
                
                <div class="callout info">
                    <div class="callout-icon">📋</div>
                    <div>
                        By accessing or using the Platform, you acknowledge that you have read, understood, and agreed to be legally bound by these Terms and our Privacy Policy. If you do not agree with all of these Terms, you are prohibited from using the Platform.
                    </div>
                </div>
            </section>

            <!-- 2. Account & Security -->
            <section id="account-security" class="doc-card">
                <div class="card-head">
                    <h2>2. Account Registration & Security</h2>
                    <span class="section-tag">Section 02</span>
                </div>
                <p>To use our bulk broadcast, template management, and automation features, you must maintain an authenticated account:</p>
                <ul class="styled-list">
                    <li>You represent and warrant that you are at least 18 years of age and possess full corporate legal authority to enter into binding agreements.</li>
                    <li>You agree to provide true, accurate, current, and complete information during registration and keep your profile updated.</li>
                    <li>You are solely responsible for maintaining the confidentiality of your credentials, Meta API access tokens, and all activities conducted under your login.</li>
                    <li>You agree to immediately notify ZapReach Technologies upon discovering any unauthorized account usage or security compromise.</li>
                </ul>
            </section>

            <!-- 3. Strict Anti-Spam Policy -->
            <section id="anti-spam" class="doc-card">
                <div class="card-head">
                    <h2>3. Acceptable Use & Strict Anti-Spam Policy</h2>
                    <span class="section-tag">Section 03</span>
                </div>
                
                <div class="callout danger">
                    <div class="callout-icon">🚫</div>
                    <div>
                        <strong>Zero-Tolerance Anti-Spam Mandate:</strong> ZapReach strictly prohibits unsolicited bulk spam messaging. Messages may only be sent to individuals who have expressly provided prior, verifiable opt-in consent to receive communications from your business.
                    </div>
                </div>

                <p>You expressly agree NOT to utilize the Platform to:</p>
                <ul class="styled-list">
                    <li>Send unsolicited promotional blasts, cold spam, fraudulent financial schemes, or misleading sweepstakes.</li>
                    <li>Transmit abusive, defamatory, obscene, pornographic, harassing, discriminatory, or unlawful content.</li>
                    <li>Disseminate phishing links, viruses, malware, trojans, or unauthorized intellectual property.</li>
                    <li>Scrape, crawl, or harvest telephone numbers from unauthorized databases or public directories without recipient consent.</li>
                    <li>Violate WhatsApp's Phone Number Quality Rating standards or trigger mass recipient block/report actions.</li>
                </ul>
            </section>

            <!-- 4. Meta Cloud API Policy -->
            <section id="meta-compliance" class="doc-card">
                <div class="card-head">
                    <h2>4. WhatsApp Cloud API & Meta Policy Compliance</h2>
                    <span class="section-tag">Section 04</span>
                </div>
                <p>You acknowledge that ZapReach operates as an application layer over Meta Platforms, Inc.'s official WhatsApp Business Cloud API. Consequently:</p>
                <ul class="styled-list">
                    <li>You must maintain full compliance with the <strong>Meta WhatsApp Business Policy</strong>, <strong>WhatsApp Commerce Policy</strong>, and <strong>Meta Developer Terms</strong>.</li>
                    <li>All message templates submitted for broadcasting must adhere to Meta's categorization requirements (Marketing, Utility, Authentication) and pass Meta's automated template review.</li>
                    <li>ZapReach Technologies is not responsible for template rejections, account rate-limiting, phone number tier downgrades, or WABA bans imposed by Meta due to policy violations.</li>
                </ul>
            </section>

            <!-- 5. Wallet & Billing Terms -->
            <section id="wallet-billing" class="doc-card">
                <div class="card-head">
                    <h2>5. Prepaid Wallet, Credits & Billing Terms</h2>
                    <span class="section-tag">Section 05</span>
                </div>
                <p>Our messaging infrastructure utilizes a prepaid wallet model:</p>

                <div class="info-grid">
                    <div class="info-grid-item">
                        <h4>💳 Wallet Recharge</h4>
                        <p>Funds are deposited via authorized payment gateways (e.g., Razorpay) in INR or accepted currencies.</p>
                    </div>
                    <div class="info-grid-item">
                        <h4>📊 Per-Message Deduction</h4>
                        <p>Credits are deducted on campaign dispatch according to category rates (Marketing, Utility, Auth).</p>
                    </div>
                    <div class="info-grid-item">
                        <h4>⚠️ Non-Refundable Policy</h4>
                        <p>Recharged wallet balances and consumed messaging credits are strictly non-refundable.</p>
                    </div>
                    <div class="info-grid-item">
                        <h4>⚖️ Taxes & Invoicing</h4>
                        <p>All pricing rates are exclusive of applicable taxes (GST), which will be levied as required by law.</p>
                    </div>
                </div>
            </section>

            <!-- 6. Service Disclaimer -->
            <section id="service-disclaimer" class="doc-card">
                <div class="card-head">
                    <h2>6. Campaign Dispatch & Service Delivery Disclaimer</h2>
                    <span class="section-tag">Section 06</span>
                </div>
                <p>While ZapReach maintains high-availability server infrastructure:</p>
                <ul class="styled-list">
                    <li>Final message receipt depends on recipient handset connectivity, WhatsApp server status, and telecom gateway availability.</li>
                    <li>We cannot guarantee message delivery to phone numbers that are invalid, disconnected, unregistered on WhatsApp, or blocked.</li>
                    <li>Scheduled maintenance or upstream Meta Cloud API outages may temporarily affect message queuing and status callbacks.</li>
                </ul>
            </section>

            <!-- 7. Auto-AI -->
            <section id="auto-ai" class="doc-card">
                <div class="card-head">
                    <h2>7. Auto-AI & Chatbot Automation Guidelines</h2>
                    <span class="section-tag">Section 07</span>
                </div>
                <p>Our Auto-AI capabilities enable intelligent conversational responses for your customers:</p>
                <ul class="styled-list">
                    <li>You are responsible for the business knowledge base, business hours, and AI response tone configured in your settings.</li>
                    <li>You must periodically audit AI chat outputs to ensure accuracy and brand compliance.</li>
                    <li>ZapReach Technologies is not liable for business consequences arising from erroneous automated conversational responses generated by your configured AI prompts.</li>
                </ul>
            </section>

            <!-- 8. IP Rights -->
            <section id="ip-rights" class="doc-card">
                <div class="card-head">
                    <h2>8. Intellectual Property Rights</h2>
                    <span class="section-tag">Section 08</span>
                </div>
                <p>The ZapReach platform, including its source code, database architecture, algorithms, UI designs, icons, documentation, and trademarks, is the exclusive proprietary property of <strong>ZapReach Technologies</strong>. Users are granted a non-exclusive, non-transferable, revocable license to utilize the software for lawful business communications.</p>
            </section>

            <!-- 9. Limitation of Liability -->
            <section id="liability" class="doc-card">
                <div class="card-head">
                    <h2>9. Limitation of Liability & Indemnification</h2>
                    <span class="section-tag">Section 09</span>
                </div>
                <p>To the fullest extent permitted by applicable law, ZapReach Technologies and its affiliates shall not be liable for any indirect, incidental, special, consequential, or exemplary damages, including loss of profits, goodwill, data, or business interruption.</p>
                <p>You agree to indemnify, defend, and hold harmless ZapReach Technologies from and against any third-party claims, liabilities, damages, and legal costs arising from the content of broadcast messages transmitted via your account.</p>
            </section>

            <!-- 10. Termination -->
            <section id="termination" class="doc-card">
                <div class="card-head">
                    <h2>10. Suspension & Termination</h2>
                    <span class="section-tag">Section 10</span>
                </div>
                <p>We reserve the right to suspend or terminate your account with immediate effect, without notice or liability, if you violate these Terms, engage in spam operations, trigger critical Meta policy violations, or attempt unauthorized reverse-engineering of the Platform.</p>
            </section>

            <!-- 11. Governing Law -->
            <section id="governing-law" class="doc-card">
                <div class="card-head">
                    <h2>11. Governing Law & Dispute Resolution</h2>
                    <span class="section-tag">Section 11</span>
                </div>
                <p>These Terms and Conditions shall be governed by and construed in accordance with the laws of India. Any dispute, claim, or controversy arising out of or in connection with these Terms shall be subject to the exclusive jurisdiction of the competent courts in India.</p>
            </section>

            <!-- 12. Contact Information -->
            <section id="contact" class="doc-card">
                <div class="card-head">
                    <h2>12. Contact Information</h2>
                    <span class="section-tag">Section 12</span>
                </div>
                <p>For questions or notices regarding these Terms & Conditions, please contact us:</p>
                
                <div class="callout success">
                    <div class="callout-icon">📬</div>
                    <div>
                        <strong>ZapReach Technologies</strong><br>
                        Platform: <strong>ZapReach</strong><br>
                        Website: <a href="{{ url('/') }}" style="color: inherit; text-decoration: underline;">{{ url('/') }}</a><br>
                        Support Email: <a href="mailto:support@zapreach.com" style="color: inherit; font-weight: 600;">support@zapreach.com</a><br>
                        Business Inquiries: <a href="mailto:info@zapreach.com" style="color: inherit; font-weight: 600;">info@zapreach.com</a>
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

</body>
</html>
