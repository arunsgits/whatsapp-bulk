<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy – ZapReach</title>
    <meta name="description" content="Privacy Policy for ZapReach WhatsApp Bulk Messaging Platform. Learn how we handle, store, and protect your data.">
    
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
                <li><a href="{{ url($prefix . '/privacy-policy') }}" class="nav-item active">Privacy Policy</a></li>
                <li><a href="{{ url($prefix . '/terms-and-conditions') }}" class="nav-item">Terms & Conditions</a></li>
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
                Official Legal Policy
            </div>
            <h1>Privacy Policy</h1>
            <p class="subtitle">Your privacy and data security are our top priorities. Learn how ZapReach collects, protects, and handles your information.</p>
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
                    <li><a href="#intro" class="toc-link">1. Introduction & Scope</a></li>
                    <li><a href="#info-collected" class="toc-link">2. Information We Collect</a></li>
                    <li><a href="#how-we-use" class="toc-link">3. How We Use Information</a></li>
                    <li><a href="#whatsapp-compliance" class="toc-link">4. WhatsApp Meta Compliance</a></li>
                    <li><a href="#third-parties" class="toc-link">5. Data Sharing & Processors</a></li>
                    <li><a href="#security" class="toc-link">6. Data Security & Storage</a></li>
                    <li><a href="#retention-rights" class="toc-link">7. User Rights & Retention</a></li>
                    <li><a href="#cookies" class="toc-link">8. Cookies & Sessions</a></li>
                    <li><a href="#policy-updates" class="toc-link">9. Policy Modifications</a></li>
                    <li><a href="#contact-us" class="toc-link">10. Contact & Support</a></li>
                </ul>
            </div>
        </aside>

        <!-- CONTENT SECTION -->
        <div class="content-area">

            <!-- 1. Introduction -->
            <section id="intro" class="doc-card">
                <div class="card-head">
                    <h2>1. Introduction & Scope</h2>
                    <span class="section-tag">Section 01</span>
                </div>
                <p>At <strong>ZapReach</strong> (operated by <strong>ZapReach Technologies</strong>, accessible online at <a href="{{ url('/') }}" style="color: var(--primary); text-decoration: underline;">{{ url('/') }}</a>), we are deeply committed to safeguarding the privacy and confidentiality of our users' personal data and corporate communication assets.</p>
                <p>This Privacy Policy governs your use of our WhatsApp Bulk Messaging platform, web dashboard, messaging APIs, Auto-AI automated chatbot, contact management tools, and wallet billing systems (collectively referred to as the <strong>"Platform"</strong> or <strong>"Services"</strong>).</p>
                
                <div class="callout info">
                    <div class="callout-icon">ℹ️</div>
                    <div>
                        <strong>Your Agreement:</strong> By accessing, signing up for, or using ZapReach, you acknowledge that you have read, understood, and agreed to this Privacy Policy. If you do not agree with any provision herein, please refrain from using our Platform.
                    </div>
                </div>
            </section>

            <!-- 2. Information We Collect -->
            <section id="info-collected" class="doc-card">
                <div class="card-head">
                    <h2>2. Information We Collect</h2>
                    <span class="section-tag">Section 02</span>
                </div>
                <p>We collect only the necessary data required to deliver high-performance WhatsApp broadcasting, template management, and automated messaging services:</p>

                <div class="info-grid">
                    <div class="info-grid-item">
                        <h4>👤 Account & Profile</h4>
                        <p>Full name, company name, corporate email address, contact phone number, and secured credentials.</p>
                    </div>
                    <div class="info-grid-item">
                        <h4>🔑 WhatsApp API Credentials</h4>
                        <p>Meta App ID, App Secret, WhatsApp Business Account (WABA) ID, Phone Number ID, and Access Tokens.</p>
                    </div>
                    <div class="info-grid-item">
                        <h4>👥 Contacts & Campaign Data</h4>
                        <p>Contact lists, phone numbers, recipient tags, custom variables, campaign schedules, and media uploads.</p>
                    </div>
                    <div class="info-grid-item">
                        <h4>💳 Wallet & Transactions</h4>
                        <p>Prepaid recharge records, Razorpay order IDs, message credits consumption logs (no raw card data stored).</p>
                    </div>
                </div>

                <h3 class="sub-heading">Technical & Diagnostic Information</h3>
                <ul class="styled-list">
                    <li><strong>Gateway Dispatch Logs:</strong> Real-time delivery callbacks from Meta Cloud API (Sent, Delivered, Read, Failed timestamps and error codes).</li>
                    <li><strong>Device & Network Data:</strong> IP address, browser type, operating system, and login session tokens for account protection.</li>
                </ul>
            </section>

            <!-- 3. How We Use Information -->
            <section id="how-we-use" class="doc-card">
                <div class="card-head">
                    <h2>3. How We Use Your Information</h2>
                    <span class="section-tag">Section 03</span>
                </div>
                <p>We utilize the collected information strictly for authorized platform operations, including:</p>
                <ul class="styled-list">
                    <li>Executing bulk WhatsApp broadcasts, transactional alerts, and automated follow-ups via official Meta Cloud APIs.</li>
                    <li>Powering Auto-AI chatbot responses and continuous conversational workflows according to your configured business profile.</li>
                    <li>Calculating real-time wallet deductions based on active message categories (Marketing, Utility, Authentication).</li>
                    <li>Delivering detailed delivery analytics, read receipts, and campaign performance dashboards.</li>
                    <li>Preventing malicious misuse, unsolicited spamming, unauthorized account takeover, and API rate-limit violations.</li>
                    <li>Providing proactive customer support, system health alerts, and transactional notifications.</li>
                </ul>
            </section>

            <!-- 4. WhatsApp Meta Compliance -->
            <section id="whatsapp-compliance" class="doc-card">
                <div class="card-head">
                    <h2>4. WhatsApp Business API & Meta Policy Compliance</h2>
                    <span class="section-tag">Section 04</span>
                </div>
                <p>ZapReach provides user-friendly software interfaces integrated with Meta Platforms, Inc.'s official WhatsApp Business Cloud API.</p>
                
                <div class="callout warning">
                    <div class="callout-icon">⚠️</div>
                    <div>
                        <strong>Opt-In Requirement:</strong> Users must obtain prior, verifiable opt-in consent from all message recipients before dispatching WhatsApp broadcasts. You must strictly adhere to WhatsApp's Business Messaging and Commerce Policies.
                    </div>
                </div>

                <p>We do not initiate broadcasts on our own behalf; all broadcasts are initiated exclusively by registered users using their designated Meta WABA credentials.</p>
            </section>

            <!-- 5. Third-Party Processors -->
            <section id="third-parties" class="doc-card">
                <div class="card-head">
                    <h2>5. Data Sharing & Third-Party Processors</h2>
                    <span class="section-tag">Section 05</span>
                </div>
                <p><strong>We never sell, rent, or trade your contact lists or message content to third parties.</strong> Data is shared only with verified service providers essential for platform execution:</p>
                
                <ul class="styled-list">
                    <li><strong>Meta Platforms, Inc. (WhatsApp Cloud API):</strong> Secure routing of outbound template messages, media files, and incoming webhook payloads.</li>
                    <li><strong>Payment Gateways (Razorpay):</strong> Secure processing of prepaid wallet recharges and financial ledger tracking.</li>
                    <li><strong>Cloud Infrastructure Providers:</strong> ISO-certified enterprise cloud servers for encrypted database storage and real-time processing.</li>
                    <li><strong>Legal & Law Enforcement:</strong> Disclosures made strictly in compliance with applicable judicial subpoenas or statutory mandates.</li>
                </ul>
            </section>

            <!-- 6. Data Security -->
            <section id="security" class="doc-card">
                <div class="card-head">
                    <h2>6. Data Security & Storage</h2>
                    <span class="section-tag">Section 06</span>
                </div>
                <p>We deploy robust defense-in-depth security standards to protect your data:</p>
                <ul class="styled-list">
                    <li><strong>Encryption in Transit & at Rest:</strong> All traffic is encrypted using modern TLS 1.3 / HTTPS encryption. Access tokens are stored with cryptographic encryption.</li>
                    <li><strong>Access Controls:</strong> Multi-layered role-based access control (RBAC) ensuring strict database boundary isolation.</li>
                    <li><strong>Automated Backups:</strong> Secure, redundant daily backups preventing data loss in disaster recovery scenarios.</li>
                </ul>
            </section>

            <!-- 7. Retention & Rights -->
            <section id="retention-rights" class="doc-card">
                <div class="card-head">
                    <h2>7. Data Retention & User Rights</h2>
                    <span class="section-tag">Section 07</span>
                </div>
                <p>You have full autonomy over your data stored on ZapReach:</p>
                <ul class="styled-list">
                    <li><strong>Access & Export:</strong> You can download and export your contact lists, message templates, and campaign delivery logs anytime.</li>
                    <li><strong>Rectification & Deletion:</strong> You can edit or permanently delete contacts, media files, and API settings from your dashboard.</li>
                    <li><strong>Account Purge:</strong> Upon account termination, all stored API tokens and recipient records are permanently deleted from active databases.</li>
                </ul>
            </section>

            <!-- 8. Cookies -->
            <section id="cookies" class="doc-card">
                <div class="card-head">
                    <h2>8. Cookies & Session Technologies</h2>
                    <span class="section-tag">Section 08</span>
                </div>
                <p>We use essential HTTP cookies and token-based session identifiers exclusively for authenticating user login sessions, maintaining CSRF security, and storing dashboard UI preferences. We do not deploy third-party advertising or behavioral tracking cookies.</p>
            </section>

            <!-- 9. Policy Updates -->
            <section id="policy-updates" class="doc-card">
                <div class="card-head">
                    <h2>9. Policy Updates & Notifications</h2>
                    <span class="section-tag">Section 09</span>
                </div>
                <p>We may periodically update this Privacy Policy to reflect enhancements in our software or regulatory modifications. Updates will be reflected with a revised "Last Updated" timestamp on this page. For significant updates, notifications will be displayed on the user dashboard.</p>
            </section>

            <!-- 10. Contact Us -->
            <section id="contact-us" class="doc-card">
                <div class="card-head">
                    <h2>10. Contact & Support Information</h2>
                    <span class="section-tag">Section 10</span>
                </div>
                <p>For any inquiries, data access requests, or privacy concerns, please contact our support team:</p>
                
                <div class="callout success">
                    <div class="callout-icon">📬</div>
                    <div>
                        <strong>ZapReach Technologies</strong><br>
                        Platform: <strong>ZapReach</strong><br>
                        Website: <a href="{{ url('/') }}" style="color: inherit; text-decoration: underline;">{{ url('/') }}</a><br>
                        Support Email: <a href="mailto:support@zapreach.com" style="color: inherit; font-weight: 600;">support@zapreach.com</a><br>
                        General Inquiries: <a href="mailto:info@zapreach.com" style="color: inherit; font-weight: 600;">info@zapreach.com</a>
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
