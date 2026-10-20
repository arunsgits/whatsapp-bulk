<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp CRM & Bulk Broadcast API Reference Documentation</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Fira+Code:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-dark: #090d16;
            --bg-panel: #0f172a;
            --bg-card: #1e293b;
            --border: #334155;
            --border-light: rgba(255, 255, 255, 0.08);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --text-sub: #cbd5e1;
            --primary: #10b981;
            --primary-glow: rgba(16, 185, 129, 0.18);
            --accent: #3b82f6;
            --accent-glow: rgba(59, 130, 246, 0.18);
            --badge-get: #10b981;
            --badge-post: #3b82f6;
            --badge-put: #f59e0b;
            --badge-delete: #ef4444;
            --badge-patch: #8b5cf6;
            --font-sans: 'Plus Jakarta Sans', sans-serif;
            --font-mono: 'Fira Code', monospace;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; scroll-behavior: smooth; }

        body {
            font-family: var(--font-sans);
            background-color: var(--bg-dark);
            color: var(--text-main);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* Sidebar */
        .sidebar {
            width: 340px;
            min-width: 340px;
            background-color: var(--bg-panel);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            height: 100%;
            z-index: 10;
        }

        .sidebar-header {
            padding: 18px 20px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(180deg, rgba(30, 41, 59, 0.5), rgba(15, 23, 42, 0.8));
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }

        .brand-logo {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 20px;
            box-shadow: 0 0 20px var(--primary-glow);
        }

        .brand-title {
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #ffffff;
        }

        .brand-subtitle {
            font-size: 0.72rem;
            color: var(--primary);
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .search-container {
            position: relative;
        }

        .search-input {
            width: 100%;
            background-color: rgba(9, 13, 22, 0.7);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 14px 10px 36px;
            color: var(--text-main);
            font-family: var(--font-sans);
            font-size: 0.85rem;
            transition: all 0.2s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .sidebar-menu {
            flex: 1;
            overflow-y: auto;
            padding: 14px 12px;
        }

        .sidebar-menu::-webkit-scrollbar {
            width: 5px;
        }
        .sidebar-menu::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 4px;
        }

        .folder-item {
            margin-bottom: 8px;
        }

        .folder-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 9px 12px;
            border-radius: 8px;
            cursor: pointer;
            user-select: none;
            transition: background 0.2s;
            background: rgba(30, 41, 59, 0.25);
        }

        .folder-header:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }

        .folder-name-container {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.82rem;
            font-weight: 700;
            color: #cbd5e1;
            letter-spacing: 0.3px;
        }

        .folder-icon {
            color: var(--accent);
            font-size: 0.9rem;
            width: 16px;
            text-align: center;
        }

        .folder-caret {
            color: var(--text-muted);
            font-size: 0.75rem;
            transition: transform 0.2s;
        }

        .folder-item.collapsed .folder-caret {
            transform: rotate(-90deg);
        }

        .folder-content {
            list-style: none;
            padding-left: 10px;
            margin-top: 4px;
            border-left: 1px solid var(--border);
            margin-left: 16px;
        }

        .folder-item.collapsed .folder-content {
            display: none;
        }

        .endpoint-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            margin: 2px 0;
            border-radius: 6px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .endpoint-link:hover {
            color: var(--text-main);
            background-color: rgba(255, 255, 255, 0.05);
        }

        .endpoint-link.active {
            color: #ffffff;
            background-color: var(--primary-glow);
            border-left: 3px solid var(--primary);
            font-weight: 600;
        }

        .method-badge {
            font-size: 0.65rem;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
            width: 48px;
            text-align: center;
            flex-shrink: 0;
            font-family: var(--font-mono);
        }

        .method-GET { background-color: rgba(16, 185, 129, 0.15); color: var(--badge-get); border: 1px solid rgba(16, 185, 129, 0.3); }
        .method-POST { background-color: rgba(59, 130, 246, 0.15); color: var(--badge-post); border: 1px solid rgba(59, 130, 246, 0.3); }
        .method-PUT { background-color: rgba(245, 158, 11, 0.15); color: var(--badge-put); border: 1px solid rgba(245, 158, 11, 0.3); }
        .method-DELETE { background-color: rgba(239, 68, 68, 0.15); color: var(--badge-delete); border: 1px solid rgba(239, 68, 68, 0.3); }
        .method-MATCH { background-color: rgba(139, 92, 246, 0.15); color: var(--badge-patch); border: 1px solid rgba(139, 92, 246, 0.3); }

        /* Main Content Container */
        .main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow: hidden;
        }

        /* Top Settings Toolbar */
        .top-toolbar {
            background-color: var(--bg-panel);
            border-bottom: 1px solid var(--border);
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            z-index: 5;
        }

        .env-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
        }

        .env-label {
            color: var(--text-muted);
            font-weight: 600;
        }

        .env-select {
            background-color: var(--bg-card);
            border: 1px solid var(--border);
            color: var(--text-main);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            outline: none;
            cursor: pointer;
        }

        .token-container {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            max-width: 450px;
        }

        .token-input {
            width: 100%;
            background-color: var(--bg-dark);
            border: 1px solid var(--border);
            color: #38bdf8;
            font-family: var(--font-mono);
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 0.8rem;
            outline: none;
        }

        .token-input:focus {
            border-color: var(--accent);
        }

        /* Main Scrollable Content */
        .main-content {
            flex: 1;
            overflow-y: auto;
            background-color: var(--bg-dark);
            padding: 30px 40px 60px;
        }

        .main-content::-webkit-scrollbar {
            width: 8px;
        }
        .main-content::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 4px;
        }

        /* Welcome hero */
        .welcome-screen {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
            text-align: center;
            max-width: 760px;
            margin: auto;
        }

        .welcome-logo {
            font-size: 3.5rem;
            margin-bottom: 20px;
            color: var(--primary);
        }

        .welcome-screen h1 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 12px;
            background: linear-gradient(135deg, #ffffff 40%, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .welcome-screen p {
            color: var(--text-muted);
            margin-bottom: 28px;
            font-size: 1rem;
            line-height: 1.6;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            width: 100%;
            margin-top: 20px;
        }

        .stat-card {
            background: var(--bg-panel);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            text-align: left;
        }

        .stat-card i {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .stat-card h3 {
            font-size: 1.1rem;
            color: #fff;
            margin-bottom: 6px;
        }

        .stat-card p {
            font-size: 0.82rem;
            color: var(--text-muted);
            margin: 0;
        }

        /* API Detail Section */
        .api-section {
            background-color: var(--bg-panel);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            margin-bottom: 40px;
        }

        .api-section-header {
            padding: 22px 28px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(to right, rgba(15, 23, 42, 0.95), rgba(30, 41, 59, 0.6));
        }

        .api-breadcrumbs {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .api-title-row {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        .api-title {
            font-size: 1.45rem;
            font-weight: 700;
            color: #ffffff;
        }

        .auth-badge {
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .auth-required {
            background: rgba(239, 68, 68, 0.12);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .auth-public {
            background: rgba(16, 185, 129, 0.12);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .api-url-bar {
            display: flex;
            align-items: center;
            background-color: var(--bg-dark);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 14px;
            font-family: var(--font-mono);
            font-size: 0.88rem;
            gap: 12px;
        }

        .api-url {
            flex: 1;
            overflow-x: auto;
            white-space: nowrap;
            color: #38bdf8;
        }

        .btn-copy {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            color: var(--text-muted);
            cursor: pointer;
            font-size: 0.82rem;
            padding: 4px 10px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-copy:hover {
            color: #fff;
            background-color: var(--primary-glow);
            border-color: var(--primary);
        }

        /* 2-Column API Body */
        .api-body {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
        }

        @media (max-width: 1280px) {
            .api-body {
                grid-template-columns: 1fr;
            }
        }

        .api-left {
            padding: 28px;
            border-right: 1px solid var(--border);
        }

        .api-right {
            padding: 28px;
            background-color: rgba(9, 13, 22, 0.6);
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .endpoint-desc {
            margin-bottom: 24px;
            color: var(--text-sub);
            font-size: 0.92rem;
            line-height: 1.6;
        }

        .section-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 14px;
            margin-top: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid var(--border-light);
            padding-bottom: 8px;
        }

        .section-title:first-child {
            margin-top: 0;
        }

        .section-title i {
            color: var(--accent);
            font-size: 0.9rem;
        }

        /* Parameters Table */
        .params-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            margin-bottom: 16px;
        }

        .params-table th {
            text-align: left;
            padding: 10px 12px;
            background-color: rgba(15, 23, 42, 0.8);
            border-bottom: 1px solid var(--border);
            color: var(--text-muted);
            font-weight: 700;
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .params-table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-light);
            vertical-align: top;
        }

        .params-table tr:hover td {
            background-color: rgba(255, 255, 255, 0.02);
        }

        .param-name {
            font-family: var(--font-mono);
            font-weight: 600;
            color: #f1f5f9;
        }

        .param-type-badge {
            font-family: var(--font-mono);
            font-size: 0.72rem;
            padding: 2px 6px;
            border-radius: 4px;
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.25);
            display: inline-block;
        }

        .param-req {
            font-size: 0.68rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
            display: inline-block;
            margin-left: 6px;
        }

        .req-true {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .req-false {
            background: rgba(148, 163, 184, 0.1);
            color: #94a3b8;
            border: 1px solid rgba(148, 163, 184, 0.2);
        }

        .param-example {
            font-family: var(--font-mono);
            font-size: 0.8rem;
            color: #38bdf8;
            word-break: break-all;
        }

        .param-desc {
            color: var(--text-sub);
            font-size: 0.82rem;
            line-height: 1.5;
        }

        .no-params-msg {
            color: var(--text-muted);
            font-size: 0.82rem;
            font-style: italic;
            padding: 10px 0;
        }

        /* Code Blocks */
        .code-container {
            background-color: #060911;
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 16px;
        }

        .code-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 14px;
            background-color: #0b101c;
            border-bottom: 1px solid var(--border);
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-muted);
        }

        .code-block {
            padding: 14px;
            overflow-x: auto;
            font-family: var(--font-mono);
            font-size: 0.82rem;
            line-height: 1.5;
            color: #e2e8f0;
            white-space: pre-wrap;
            word-break: break-word;
        }

        /* Response Tabs */
        .response-tabs {
            display: flex;
            gap: 6px;
            margin-bottom: 10px;
        }

        .res-tab-btn {
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid var(--border);
            color: var(--text-muted);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }

        .res-tab-btn.active {
            background: rgba(16, 185, 129, 0.15);
            color: var(--primary);
            border-color: var(--primary);
        }

        .res-tab-btn.err-tab.active {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border-color: #f87171;
        }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="brand">
                <div class="brand-logo">
                    <i class="fab fa-whatsapp"></i>
                </div>
                <div>
                    <div class="brand-title">WhatsApp CRM API</div>
                    <div class="brand-subtitle">REST API Reference 2026</div>
                </div>
            </div>
            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="search" class="search-input" placeholder="Search all endpoints...">
            </div>
        </div>

        <div class="sidebar-menu" id="sidebar-menu-list">
            <!-- Populated dynamically via api-docs-data.js -->
        </div>
    </div>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        
        <!-- Top Toolbar -->
        <div class="top-toolbar">
            <div class="env-selector">
                <span class="env-label"><i class="fas fa-server"></i> Base URL:</span>
                <select id="baseUrlSelect" class="env-select" onchange="updateBaseUrl(this.value)">
                    <option value="https://zapreach.com">Production Server (https://zapreach.com)</option>
                    <option value="http://localhost:8000">Local Development (http://localhost:8000)</option>
                </select>
            </div>

            <div class="token-container">
                <span class="env-label"><i class="fas fa-key"></i> Token:</span>
                <input type="text" id="bearerTokenInput" class="token-input" placeholder="Paste Bearer Token to update all cURLs..." oninput="updateBearerToken(this.value)">
            </div>
        </div>

        <!-- Main Content Scroll Area -->
        <div class="main-content" id="main-content">
            
            <!-- Welcome Screen -->
            <div class="welcome-screen" id="welcome-screen">
                <div class="welcome-logo">
                    <i class="fab fa-whatsapp"></i>
                </div>
                <h1>WhatsApp CRM & Bulk Broadcast API</h1>
                <p>Welcome to the official REST API reference documentation. Every endpoint is thoroughly documented with complete parameter specifications, data types, required constraints, example values, request bodies, response models, and instant copyable cURL commands.</p>

                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fas fa-shield-alt"></i>
                        <h3>Bearer Auth</h3>
                        <p>Standard OAuth 2.0 Bearer token authentication on all protected routes.</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-bullhorn"></i>
                        <h3>Bulk Broadcasts</h3>
                        <p>High-speed automated broadcasts with dynamic variable tags and delivery receipts.</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-comments"></i>
                        <h3>CRM Live Chat</h3>
                        <p>Real-time 2-way WhatsApp chats, multimedia attachments, and Auto AI responses.</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-wallet"></i>
                        <h3>Wallet & Razorpay</h3>
                        <p>Instant online wallet recharges with automated cryptographic payment verification.</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-store"></i>
                        <h3>Catalog & Product Sets</h3>
                        <p>Meta Commerce Catalogs, Product Sets CRUD, category taxonomy, cart management, and SPM/MPM messaging.</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <h3>GST Invoices & Templates</h3>
                        <p>Dynamic custom templates, logo upload, live HTML/PDF preview, and automated GST billing.</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-user-shield"></i>
                        <h3>Admin Wallet Management</h3>
                        <p>Super Admin manual credit/debit adjustments, full audit history ledger, and toggle controls.</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-brain"></i>
                        <h3>Knowledge Base & FAQs</h3>
                        <p>Dynamic AI knowledge management, URL scraping import, and automated WhatsApp replies.</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-bell"></i>
                        <h3>Payment & Renewal Reminders</h3>
                        <p>Super Admin one-click branded email alerts for plan renewals, low wallet recharges, and invoice payments.</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-users-cog"></i>
                        <h3>WhatsApp Group Grabber</h3>
                        <p>Connect multiple WhatsApp accounts via QR code, fetch group member lists, and export contacts into CRM groups.</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-layer-group"></i>
                        <h3>Plans & Monthly Quotas</h3>
                        <p>Monthly recurring resets for yearly & custom plans, automatic day extension, and unused message rollover on upgrade/renewal.</p>
                    </div>
                </div>
            </div>

            <!-- Endpoints Container -->
            <div id="endpoints-container">
                <!-- Dynamically rendered from comprehensive dataset -->
            </div>

        </div>
    </div>

    <!-- Data and Interactive Script -->
    <script>
/**
 * Complete WhatsApp CRM & Bulk Broadcast API Reference Dataset
 * Fully populated with all endpoints, headers, path params, query params, request body fields, types, and descriptions.
 */

window.API_MODULES = [
    {
        name: "Authentication & Profile",
        icon: "fas fa-shield-alt",
        description: "User authentication, registration, access tokens, profile management, and password updates.",
        endpoints: [
            {
                id: "endpoint-auth-register",
                name: "User Registration",
                method: "POST",
                path: "/api/auth/register",
                auth: false,
                description: "Register a new client account with name, email, phone number, and password. Returns an OAuth Bearer token upon successful creation.",
                contentType: "multipart/form-data",
                bodyMode: "formdata",
                headers: [
                    { key: "Accept", value: "application/json", description: "Expects JSON response format." }
                ],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "name", type: "string", required: true, value: "Rahul Sharma", description: "Full name of the user (max: 255 characters)." },
                    { key: "email", type: "string", required: true, value: "rahul.sharma@example.com", description: "Valid and unique email address for account login and notifications." },
                    { key: "number", type: "string", required: true, value: "919173129119", description: "User WhatsApp / Mobile number with country code (must be unique)." },
                    { key: "password", type: "string", required: true, value: "Secret@12345", description: "Account password (minimum 8 characters)." },
                    { key: "role", type: "string", required: false, value: "user", description: "Account role: \"user\" (default) or \"admin\"." },
                    { key: "profileImage", type: "file", required: false, value: "avatar.png", description: "Optional profile picture image file (jpg, jpeg, png)." }
                ],
                responses: {
                    "201": {
                        status: true,
                        message: "Registration successful",
                        user: {
                            id: 10,
                            name: "Rahul Sharma",
                            email: "rahul.sharma@example.com",
                            number: "919173129119",
                            role: "user",
                            profileImage: "https://zapreach.com/uploads/profiles/avatar.png"
                        },
                        token: "10|AbCdEf1234567890apiTokenString..."
                    },
                    "400": {
                        status: false,
                        message: "Validation failed",
                        errors: { email: ["The email has already been taken."], number: ["The number has already been taken."] }
                    }
                }
            },
            {
                id: "endpoint-auth-login",
                name: "User / Staff Login",
                method: "POST",
                path: "/api/auth/login",
                auth: false,
                description: "Authenticate user or staff credentials to receive a Bearer API Token and account profile summary.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Content-Type", value: "application/json", description: "Request body format." },
                    { key: "Accept", value: "application/json", description: "Response format." }
                ],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "email", type: "string", required: true, value: "rahul.sharma@example.com", description: "Registered user or staff account email address." },
                    { key: "password", type: "string", required: true, value: "Secret@12345", description: "User account password." }
                ],
                responses: {
                    "200": {
                        status: true,
                        message: "Login successful",
                        token: "10|AbCdEf1234567890apiTokenString...",
                        user: {
                            id: 10,
                            name: "Rahul Sharma",
                            email: "rahul.sharma@example.com",
                            role: "user",
                            number: "919173129119",
                            permissions: ["contacts", "campaigns", "conversations", "templates", "reports", "settings", "billing"]
                        },
                        wallet: { balance: 250.00, currency: "INR", unlimited: false }
                    },
                    "401": { status: false, message: "Invalid email or password" }
                }
            },
            {
                id: "endpoint-auth-profile",
                name: "Get Current Profile",
                method: "GET",
                path: "/api/auth/profile",
                auth: true,
                description: "Retrieve authenticated user profile, company details, active subscription limits, wallet balance, and assigned staff permissions.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        user: {
                            id: 10,
                            name: "Rahul Sharma",
                            email: "rahul.sharma@example.com",
                            number: "919173129119",
                            companyName: "Sharma Logistics Solutions",
                            companyMobile: "919173129119",
                            companyAddress: "101 Cyber Hub, Tech City",
                            companyEmail: "contact@sharmalogistics.com",
                            companyWebsite: "https://sharmalogistics.com",
                            status: "active"
                        }
                    }
                }
            },
            {
                id: "endpoint-auth-edit-profile",
                name: "Edit Profile & Company Info",
                method: "POST",
                path: "/api/auth/edit-profile",
                auth: true,
                description: "Update user personal details, company profile, address, website, avatar image, and company logo.",
                contentType: "multipart/form-data",
                bodyMode: "formdata",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "name", type: "string", required: false, value: "Rahul Sharma", description: "Updated full name." },
                    { key: "email", type: "string", required: false, value: "rahul.sharma@example.com", description: "Updated email address." },
                    { key: "number", type: "string", required: false, value: "919173129119", description: "Updated contact phone number." },
                    { key: "companyName", type: "string", required: false, value: "Sharma Logistics Solutions", description: "Registered business/company name." },
                    { key: "companyMobile", type: "string", required: false, value: "919173129119", description: "Official company contact number." },
                    { key: "companyAddress", type: "string", required: false, value: "101 Cyber Hub, Tech City", description: "Physical business address." },
                    { key: "companyEmail", type: "string", required: false, value: "support@sharmalogistics.com", description: "Company support email." },
                    { key: "companyWebsite", type: "string", required: false, value: "https://sharmalogistics.com", description: "Company website URL." },
                    { key: "profileImage", type: "file", required: false, value: "avatar.jpg", description: "New profile avatar image file." },
                    { key: "companyLogo", type: "file", required: false, value: "logo.png", description: "New business logo image file." },
                    { key: "remove_logo", type: "boolean", required: false, value: "false", description: "Set to true (or 1) to remove existing logo." }
                ],
                responses: {
                    "200": { status: true, message: "Profile updated successfully" }
                }
            },
            {
                id: "endpoint-auth-change-password",
                name: "Change Password",
                method: "PUT",
                path: "/api/auth/change-password",
                auth: true,
                description: "Update account password by validating the current password against stored credentials.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "current_password", type: "string", required: true, value: "Secret@12345", description: "Current active account password." },
                    { key: "new_password", type: "string", required: true, value: "NewSecret@2026", description: "New password (min: 8 characters)." },
                    { key: "new_password_confirmation", type: "string", required: true, value: "NewSecret@2026", description: "Confirmation matching new_password." }
                ],
                responses: {
                    "200": { status: true, message: "Password changed successfully" },
                    "400": { status: false, message: "Current password does not match" }
                }
            },
            {
                id: "endpoint-auth-logout",
                name: "Logout User",
                method: "POST",
                path: "/api/auth/logout",
                auth: true,
                description: "Revoke current OAuth Bearer access token and end the user session.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": { status: true, message: "Successfully logged out" }
                }
            }
        ]
    },
    {
        name: "Contacts Management",
        icon: "fas fa-address-book",
        description: "Manage audience contacts, phone numbers, custom tags, group associations, CSV/Excel bulk imports, and quota limits.",
        endpoints: [
            {
                id: "endpoint-contact-get-contacts",
                name: "List Contacts",
                method: "GET",
                path: "/api/contacts",
                auth: true,
                description: "Get paginated list of all contacts belonging to the authenticated account. Supports real-time text searching across name, phone, and email.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [
                    { key: "page", type: "integer", required: false, value: "1", description: "Page number for pagination (default: 1)." },
                    { key: "per_page", type: "integer", required: false, value: "20", description: "Number of contacts per page (default: 20)." },
                    { key: "search", type: "string", required: false, value: "Rahul", description: "Filter contacts by name, phone number, or email." }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        data: [
                            { id: 101, name: "Rahul Sharma", phone: "+919173129119", email: "rahul@example.com", tags: ["Lead", "VIP"], groups: [{ id: 1, name: "Retail Customers" }] }
                        ],
                        limits: { allowed: 5000, current: 1420, remaining: 3580 },
                        pagination: { current_page: 1, last_page: 71, per_page: 20, total: 1420 }
                    }
                }
            },
            {
                id: "endpoint-contact-add-contacts",
                name: "Add Single Contact",
                method: "POST",
                path: "/api/contacts",
                auth: true,
                description: "Add a new contact to the address book with phone validation, duplicate number checking, and plan quota verification.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "name", type: "string", required: true, value: "Rahul Sharma", description: "Contact full name." },
                    { key: "phone", type: "string", required: true, value: "+919173129119", description: "Valid WhatsApp phone number in international format (+CountryCode)." },
                    { key: "email", type: "string", required: false, value: "rahul@example.com", description: "Optional contact email address." },
                    { key: "tags", type: "array", required: false, value: "[\"Lead\", \"VIP\"]", description: "Array of string labels/tags for segmenting contacts." },
                    { key: "groupIds", type: "array", required: false, value: "[1, 3]", description: "Array of Group IDs to assign this contact to." }
                ],
                responses: {
                    "201": {
                        status: true,
                        message: "Contact added successfully",
                        data: { id: 101, name: "Rahul Sharma", phone: "+919173129119", email: "rahul@example.com", tags: ["Lead", "VIP"] }
                    },
                    "400": { status: false, message: "Phone number already exists" },
                    "403": { status: false, message: "Contact limit reached. Your plan allows 5000 contacts." }
                }
            },
            {
                id: "endpoint-contact-get-id-contacts",
                name: "Get Contact by ID",
                method: "GET",
                path: "/api/contacts/{id}",
                auth: true,
                description: "Retrieve detailed information for a specific contact including tag list and associated group memberships.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "101", description: "Unique numeric ID of the contact." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        data: { id: 101, name: "Rahul Sharma", phone: "+919173129119", email: "rahul@example.com", tags: ["Lead", "VIP"] }
                    }
                }
            },
            {
                id: "endpoint-contact-updeat-contacts",
                name: "Update Contact",
                method: "PUT",
                path: "/api/contacts/{id}",
                auth: true,
                description: "Update existing contact name, phone, email, tags, and group associations.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "101", description: "Contact ID." }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "name", type: "string", required: true, value: "Rahul Sharma Updated", description: "Updated contact full name." },
                    { key: "phone", type: "string", required: true, value: "+919173129119", description: "Updated phone number in international format." },
                    { key: "email", type: "string", required: false, value: "rahul.new@example.com", description: "Updated email address." },
                    { key: "tags", type: "array", required: false, value: "[\"Customer\", \"High Value\"]", description: "Updated array of tags." },
                    { key: "groupIds", type: "array", required: false, value: "[1, 2]", description: "Updated array of Group IDs." }
                ],
                responses: {
                    "200": { status: true, message: "Contact updated successfully" }
                }
            },
            {
                id: "endpoint-contact-delete-contacts",
                name: "Delete Single Contact",
                method: "DELETE",
                path: "/api/contacts/{id}",
                auth: true,
                description: "Permanently delete a contact and remove them from all assigned groups.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "101", description: "Contact ID to remove." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": { status: true, message: "Contact deleted successfully" }
                }
            },
            {
                id: "endpoint-contact-csv-add-contacts",
                name: "Import Contacts (CSV / Excel)",
                method: "POST",
                path: "/api/contacts/import",
                auth: true,
                description: "Upload a CSV or Excel spreadsheet (.xlsx, .xls) containing columns for Name, Phone, and optional Email/Tags.",
                contentType: "multipart/form-data",
                bodyMode: "formdata",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "file", type: "file", required: true, value: "contacts_list.csv", description: "CSV or Excel spreadsheet file (max size: 10MB)." },
                    { key: "groupIds", type: "array", required: false, value: "[1, 2]", description: "Optional array of group IDs to immediately assign imported contacts to." }
                ],
                responses: {
                    "200": {
                        status: true,
                        message: "Contacts imported successfully",
                        summary: { total_rows: 500, imported: 482, duplicates_skipped: 15, invalid_numbers: 3 }
                    }
                }
            },
            {
                id: "endpoint-contact-limits",
                name: "Get Contact Limits & Quota",
                method: "GET",
                path: "/api/contacts/limits",
                auth: true,
                description: "Returns active plan contact limits, current contact count, and remaining available contact storage.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        limits: { allowed: 5000, current: 1420, remaining: 3580, is_unlimited: false }
                    }
                }
            },
            {
                id: "endpoint-contact-bulk-delete",
                name: "Bulk Delete Contacts",
                method: "POST",
                path: "/api/contacts/delete",
                auth: true,
                description: "Delete multiple contacts at once by passing an array of contact IDs.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "contactIds", type: "array", required: true, value: "[101, 102, 105]", description: "Array of contact IDs to permanently remove." }
                ],
                responses: {
                    "200": { status: true, message: "3 contacts deleted successfully" }
                }
            }
        ]
    },
    {
        name: "Groups Management",
        icon: "fas fa-layer-group",
        description: "Organize contacts into custom audience groups for segmented bulk broadcast messaging.",
        endpoints: [
            {
                id: "endpoint-groups-get-groups",
                name: "List Groups",
                method: "GET",
                path: "/api/groups",
                auth: true,
                description: "Get all contact groups with total member counts and member previews.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [
                    { key: "page", type: "integer", required: false, value: "1", description: "Pagination page number." },
                    { key: "per_page", type: "integer", required: false, value: "15", description: "Items per page (default: 15)." }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        count: 5,
                        data: [{ id: 1, name: "VIP Customers 2026", description: "High lifetime value customers", memberCount: 240 }]
                    }
                }
            },
            {
                id: "endpoint-groups-add-group",
                name: "Create Group",
                method: "POST",
                path: "/api/groups",
                auth: true,
                description: "Create a new contact group with unique group name and optional description.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "name", type: "string", required: true, value: "Festive Campaign Audience", description: "Unique name for the group (max: 255 characters)." },
                    { key: "description", type: "string", required: false, value: "Target audience for Diwali and New Year promotions", description: "Detailed description / notes." }
                ],
                responses: {
                    "201": {
                        status: true,
                        message: "Group created successfully",
                        data: { id: 5, name: "Festive Campaign Audience" }
                    }
                }
            },
            {
                id: "endpoint-groups-get-id-group",
                name: "Get Group by ID",
                method: "GET",
                path: "/api/groups/{id}",
                auth: true,
                description: "Retrieve single group details and the full list of enrolled member contacts.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "1", description: "Group ID." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        data: { id: 1, name: "VIP Customers 2026", members: [{ id: 101, name: "Rahul Sharma", phone: "+919173129119" }] }
                    }
                }
            },
            {
                id: "endpoint-groups-edit-group",
                name: "Update Group",
                method: "PUT",
                path: "/api/groups/{id}",
                auth: true,
                description: "Modify group name and description.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "1", description: "Group ID." }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "name", type: "string", required: true, value: "VIP Platinum Members", description: "Updated group name." },
                    { key: "description", type: "string", required: false, value: "Top tier customers eligible for exclusive discounts", description: "Updated description." }
                ],
                responses: {
                    "200": { status: true, message: "Group updated successfully" }
                }
            },
            {
                id: "endpoint-groups-delete-singel-group",
                name: "Delete Group",
                method: "DELETE",
                path: "/api/groups/{id}",
                auth: true,
                description: "Delete group record (contacts in the group will NOT be deleted).",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "1", description: "Group ID to delete." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": { status: true, message: "Group deleted successfully" }
                }
            },
            {
                id: "endpoint-groups-add-existing-group-to-multiple-contacts",
                name: "Assign Contacts to Group",
                method: "POST",
                path: "/api/groups/assign",
                auth: true,
                description: "Add multiple existing contacts into a specific group simultaneously.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "groupId", type: "integer", required: true, value: "1", description: "Target Group ID." },
                    { key: "contactIds", type: "array", required: true, value: "[101, 102, 105]", description: "Array of contact IDs to enroll." }
                ],
                responses: {
                    "200": { status: true, message: "Contacts assigned to group successfully" }
                }
            },
            {
                id: "endpoint-groups-remove-group-from-multiple-contacts",
                name: "Remove Contacts from Group",
                method: "POST",
                path: "/api/groups/remove",
                auth: true,
                description: "Remove multiple contacts from a group without deleting the contacts themselves.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "groupId", type: "integer", required: true, value: "1", description: "Target Group ID." },
                    { key: "contactIds", type: "array", required: true, value: "[101, 102]", description: "Array of contact IDs to detach." }
                ],
                responses: {
                    "200": { status: true, message: "Contacts removed from group successfully" }
                }
            }
        ]
    },
    {
        name: "WhatsApp Multi-Account & Group Grabber",
        icon: "fas fa-users-cog",
        description: "Connect multiple personal or business WhatsApp accounts via multi-device QR code, monitor live connection status, list groups, and export group participants directly into CRM Groups and Contacts.",
        endpoints: [
            {
                id: "endpoint-wa-accounts-list",
                name: "List Connected WhatsApp Accounts",
                method: "GET",
                path: "/api/whatsapp-accounts",
                auth: true,
                description: "Retrieve all connected WhatsApp accounts and multi-device sessions belonging to the authenticated user company, including phone numbers, profile names, and connection status.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    { key: "Accept", value: "application/json", description: "Expects JSON response." }
                ],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        count: 2,
                        bridge_online: true,
                        data: [
                            {
                                id: 1,
                                account_name: "Sales Account - Mumbai",
                                phone_number: "919876543210",
                                profile_name: "Raj Singh",
                                profile_picture: null,
                                status: "connected",
                                is_connected: true,
                                last_connected_at: "2026-10-19T06:05:00.000000Z",
                                created_at: "2026-10-19T05:50:00.000000Z"
                            }
                        ]
                    }
                }
            },
            {
                id: "endpoint-wa-accounts-connect",
                name: "Connect New WhatsApp Account (Generate QR)",
                method: "POST",
                path: "/api/whatsapp-accounts/connect",
                auth: true,
                description: "Initialize a new multi-device session for linking a WhatsApp account. Generates an instant base64 QR code image that the user can scan using WhatsApp on their mobile device (Linked Devices > Link a Device).",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Content-Type", value: "application/json", description: "JSON payload." },
                    { key: "Accept", value: "application/json", description: "Expects JSON response." }
                ],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "account_name", type: "string", required: true, value: "Sales Team SIM 1", description: "Descriptive label for this WhatsApp account." }
                ],
                responses: {
                    "201": {
                        status: true,
                        message: "WhatsApp account session initialized. Scan QR code to connect.",
                        data: {
                            id: 1,
                            account_name: "Sales Team SIM 1",
                            session_id: "wa_sess_usr31_a1b2c3d4e5f6",
                            status: "qr_ready",
                            qr_code: "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...",
                            is_connected: false
                        }
                    },
                    "422": {
                        status: false,
                        message: "Validation failed",
                        errors: { account_name: ["The account name field is required."] }
                    }
                }
            },
            {
                id: "endpoint-wa-accounts-status",
                name: "Check Account Connection Status & QR",
                method: "GET",
                path: "/api/whatsapp-accounts/{id}/status",
                auth: true,
                description: "Poll this endpoint to monitor pairing progress. While awaiting scan, returns status: 'qr_ready' and refreshed QR base64 code. Once paired on mobile, automatically transitions to status: 'connected' with phone number and profile name.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    { key: "Accept", value: "application/json", description: "Expects JSON response." }
                ],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "1", description: "Numeric ID of the connected WhatsApp account." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200 (Connected)": {
                        status: true,
                        data: {
                            id: 1,
                            account_name: "Sales Team SIM 1",
                            phone_number: "919876543210",
                            profile_name: "Raj Singh",
                            status: "connected",
                            is_connected: true,
                            qr_code: null,
                            last_connected_at: "2026-10-19T06:12:00.000000Z"
                        }
                    },
                    "200 (Pending Scan)": {
                        status: true,
                        data: {
                            id: 1,
                            account_name: "Sales Team SIM 1",
                            phone_number: null,
                            profile_name: null,
                            status: "qr_ready",
                            is_connected: false,
                            qr_code: "data:image/png;base64,iVBORw0KGgoAAA...",
                            last_connected_at: null
                        }
                    }
                }
            },
            {
                id: "endpoint-wa-accounts-disconnect",
                name: "Disconnect WhatsApp Account",
                method: "POST",
                path: "/api/whatsapp-accounts/{id}/disconnect",
                auth: true,
                description: "Gracefully log out the multi-device WhatsApp session and mark account status as disconnected. Retains account record in CRM history.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    { key: "Accept", value: "application/json", description: "Expects JSON response." }
                ],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "1", description: "Connected WhatsApp Account ID." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        message: "WhatsApp account 'Sales Team SIM 1' disconnected successfully."
                    }
                }
            },
            {
                id: "endpoint-wa-accounts-delete",
                name: "Delete WhatsApp Account",
                method: "DELETE",
                path: "/api/whatsapp-accounts/{id}",
                auth: true,
                description: "Terminates the WhatsApp session, deletes auth files, and permanently removes the account record.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    { key: "Accept", value: "application/json", description: "Expects JSON response." }
                ],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "1", description: "Connected WhatsApp Account ID." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        message: "WhatsApp account removed successfully."
                    }
                }
            },
            {
                id: "endpoint-wa-accounts-get-groups",
                name: "Fetch Groups from Connected Account",
                method: "GET",
                path: "/api/whatsapp-accounts/{id}/groups",
                auth: true,
                description: "Retrieves the real-time list of all WhatsApp groups this connected account belongs to, along with group JIDs, subjects, member counts, and creation dates.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    { key: "Accept", value: "application/json", description: "Expects JSON response." }
                ],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "1", description: "Connected WhatsApp Account ID." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        account: {
                            id: 1,
                            account_name: "Sales Team SIM 1",
                            phone_number: "919876543210"
                        },
                        total_groups: 2,
                        groups: [
                            {
                                jid: "12036302838382101@g.us",
                                name: "Real Estate Investors Club",
                                participants_count: 512,
                                creation: "2026-03-15T10:00:00.000Z",
                                owner: "919811122233@s.whatsapp.net",
                                desc: "Discussion forum for property investors"
                            },
                            {
                                jid: "12036304928172910@g.us",
                                name: "B2B Wholesalers Delhi NCR",
                                participants_count: 340,
                                creation: "2026-06-20T14:30:00.000Z",
                                owner: null,
                                desc: ""
                            }
                        ]
                    },
                    "400": {
                        status: false,
                        message: "This WhatsApp account is not connected. Please scan QR code first."
                    }
                }
            },
            {
                id: "endpoint-wa-accounts-export-group",
                name: "Export Group Contacts into CRM Group & Contacts",
                method: "POST",
                path: "/api/whatsapp-accounts/{id}/groups/export",
                auth: true,
                description: "Extracts all participant phone numbers from the specified WhatsApp group, automatically finds or creates the group in Models\\Group, sanitizes international numbers, and bulk-inserts new contacts into Models\\Contact with active plan quota validation.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Content-Type", value: "application/json", description: "JSON format." },
                    { key: "Accept", value: "application/json", description: "Expects JSON response." }
                ],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "1", description: "Connected WhatsApp Account ID." }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "group_jid", type: "string", required: true, value: "12036302838382101@g.us", description: "WhatsApp group JID (obtained from GET /api/whatsapp-accounts/{id}/groups)." },
                    { key: "target_group_name", type: "string", required: false, value: "Real Estate Investors Club", description: "Optional name for CRM group in Models\\Group (defaults to WhatsApp group subject)." },
                    { key: "target_group_id", type: "integer", required: false, value: "", description: "Optional existing Group ID to append members into." },
                    { key: "tags", type: "array", required: false, value: "[\"group_export\", \"real_estate\"]", description: "Array of tags to attach to newly created contacts." },
                    { key: "skip_existing", type: "boolean", required: false, value: "true", description: "If true, appends group association to existing contacts instead of duplicating." }
                ],
                responses: {
                    "200": {
                        status: true,
                        message: "Group contacts exported successfully into 'Real Estate Investors Club'.",
                        data: {
                            group: {
                                id: 14,
                                name: "Real Estate Investors Club",
                                description: "Exported from WhatsApp Group via Sales Team SIM 1 (919876543210)",
                                total_contacts_in_group: 512
                            },
                            summary: {
                                total_extracted: 512,
                                new_contacts_created: 480,
                                already_existing_updated: 32,
                                skipped_due_to_limit: 0
                            },
                            limits: {
                                current: 1480,
                                allowed: 10000,
                                remaining: 8520
                            }
                        }
                    },
                    "400": {
                        status: false,
                        message: "No active package found. Please purchase a package first."
                    }
                }
            }
        ]
    },
    {
        name: "WhatsApp Templates",
        icon: "fas fa-file-alt",
        description: "Manage official WhatsApp Cloud API templates, sync approved Meta templates, create custom local templates, and verify template limits.",
        endpoints: [
            {
                id: "endpoint-templates-sync",
                name: "Sync Meta Approved Templates",
                method: "GET",
                path: "/api/templates/sync-templates",
                auth: true,
                description: "Fetch all approved message templates directly from Meta Graph API using your registered WhatsApp Business Account (WABA) credentials.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": { status: true, message: "Templates synced successfully", synced_count: 12 }
                }
            },
            {
                id: "endpoint-templates-user",
                name: "Get User Templates List",
                method: "GET",
                path: "/api/templates/user",
                auth: true,
                description: "Get all message templates available for sending broadcasts (Meta approved + custom local templates).",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [
                    { key: "type", type: "string", required: false, value: "custom", description: "Filter: \"custom\" or \"system\"." },
                    { key: "status", type: "string", required: false, value: "APPROVED", description: "Filter: \"APPROVED\", \"PENDING\", \"REJECTED\"." }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        count: 8,
                        data: [
                            { id: 20, name: "order_confirmation_v2", category: "UTILITY", language: "en_US", status: "APPROVED", type: "system", content: "Hello @{{1}}, your order #@{{2}} of @{{3}} is confirmed!" }
                        ]
                    }
                }
            },
            {
                id: "endpoint-templates-show",
                name: "Get Template by ID",
                method: "GET",
                path: "/api/templates/user/{templateId}",
                auth: true,
                description: "Retrieve single template details, body components, buttons, header media, and variable placeholders.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "templateId", type: "integer", required: true, value: "20", description: "Template ID." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": { status: true, data: { id: 20, name: "order_confirmation_v2", status: "APPROVED" } }
                }
            },
            {
                id: "endpoint-templates-limits",
                name: "Get Template Limits",
                method: "GET",
                path: "/api/templates/limits",
                auth: true,
                description: "Check subscription package template limit quota against currently created templates.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": { status: true, limits: { allowed: 50, current: 8, remaining: 42 } }
                }
            },
            {
                id: "endpoint-templates-create-custom",
                name: "Add Custom Template",
                method: "POST",
                path: "/api/templates/custom",
                auth: true,
                description: "Create a custom local template for quick broadcast messaging.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "name", type: "string", required: true, value: "flash_sale_promo", description: "Template internal name." },
                    { key: "content", type: "string", required: true, value: "Hi @{{1}}, huge 40% OFF sale is live now! Use code @{{2}}.", description: "Body text with dynamic @{{1}}, @{{2}} placeholders." },
                    { key: "type", type: "string", required: false, value: "custom", description: "Template type (default: \"custom\")." }
                ],
                responses: {
                    "201": { status: true, message: "Custom template created successfully", data: { id: 25, name: "flash_sale_promo", status: "APPROVED" } }
                }
            },
            {
                id: "endpoint-templates-edit-custom",
                name: "Edit Custom Template",
                method: "PUT",
                path: "/api/templates/custom/{templateId}",
                auth: true,
                description: "Edit text content and variables of a custom template.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [
                    { key: "templateId", type: "integer", required: true, value: "25", description: "Custom Template ID." }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "name", type: "string", required: true, value: "flash_sale_promo_updated", description: "Updated template name." },
                    { key: "content", type: "string", required: true, value: "Hi @{{1}}, exclusive 50% OFF discount! Use coupon @{{2}}.", description: "Updated message content." }
                ],
                responses: {
                    "200": { status: true, message: "Custom template updated successfully" }
                }
            },
            {
                id: "endpoint-templates-delete-custom",
                name: "Delete Custom Template",
                method: "DELETE",
                path: "/api/templates/custom/{templateId}",
                auth: true,
                description: "Delete custom template from database.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "templateId", type: "integer", required: true, value: "25", description: "Custom Template ID to delete." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": { status: true, message: "Custom template deleted successfully" }
                }
            },
            {
                id: "endpoint-templates-meta-create",
                name: "Submit Template to Meta Graph API",
                method: "POST",
                path: "/api/template/create",
                auth: true,
                description: "Submit an official WhatsApp message template directly to Meta Business Manager for review and approval.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "name", type: "string", required: true, value: "festive_discount_alert", description: "Meta template identifier (lowercase snake_case)." },
                    { key: "category", type: "string", required: true, value: "MARKETING", description: "Category: \"MARKETING\", \"UTILITY\", \"AUTHENTICATION\"." },
                    { key: "language", type: "string", required: true, value: "en_US", description: "Language code (e.g. en_US, hi_IN)." },
                    { key: "components", type: "array", required: true, value: "[{\"type\":\"BODY\",\"text\":\"Hello @{{1}}, enjoy our special festive offer!\"}]", description: "Meta component objects (HEADER, BODY, FOOTER, BUTTONS)." }
                ],
                responses: {
                    "201": { status: true, message: "Template submitted to Meta successfully", data: { id: "987654321012345", status: "PENDING" } }
                }
            }
        ]
    },
    {
        name: "Bulk Broadcast Campaigns",
        icon: "fas fa-bullhorn",
        description: "Schedule and launch large-scale WhatsApp bulk message campaigns with contact personalizations, live delivery logs, stats, and rate limit settings.",
        endpoints: [
            {
                id: "endpoint-bulk-list",
                name: "List Broadcast Campaigns",
                method: "GET",
                path: "/api/bulkmessage",
                auth: true,
                description: "Retrieve paginated history of all bulk broadcast campaigns launched by the user.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [
                    { key: "page", type: "integer", required: false, value: "1", description: "Pagination page number." },
                    { key: "status", type: "string", required: false, value: "completed", description: "Filter: \"completed\", \"processing\", \"scheduled\", \"failed\"." }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        data: {
                            current_page: 1,
                            data: [
                                { id: 45, name: "Diwali Mega Sale 2026", status: "completed", total_contacts: 1250, sent_count: 1250, delivered_count: 1210, read_count: 980, failed_count: 40 }
                            ]
                        }
                    }
                }
            },
            {
                id: "endpoint-bulk-send",
                name: "Launch / Schedule Bulk Campaign",
                method: "POST",
                path: "/api/bulkmessage",
                auth: true,
                description: "Launch an instant bulk broadcast or schedule it for a future timestamp with dynamic template variable replacements.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "name", type: "string", required: true, value: "Diwali Mega Sale 2026", description: "Campaign name for tracking and analytics." },
                    { key: "templateId", type: "integer", required: true, value: "20", description: "ID of the approved WhatsApp template to dispatch." },
                    { key: "contacts", type: "array", required: true, value: "[101, 102, 103, 105]", description: "Array of contact IDs to broadcast to." },
                    { key: "variables", type: "object", required: false, value: "{\"1\":\"@{{name}}\",\"2\":\"20% OFF\"}", description: "Dynamic variable mappings (e.g. @{{name}} automatically pulls recipient contact name)." },
                    { key: "headervariables", type: "array", required: false, value: "[\"https://example.com/banner.jpg\"]", description: "Header media or text variable replacements if required by template." },
                    { key: "scheduleAt", type: "datetime string", required: false, value: "2026-09-05 10:00:00", description: "Optional future datetime to schedule automated campaign launch." }
                ],
                responses: {
                    "201": { status: true, message: "Campaign created and queued for dispatch", campaignId: 46, recipients_count: 4 },
                    "400": { status: false, message: "Template is not approved. Cannot send campaign." }
                }
            },
            {
                id: "endpoint-bulk-stats",
                name: "Get Campaign Real-time Stats",
                method: "GET",
                path: "/api/bulk-messages/{id}/stats",
                auth: true,
                description: "Get live metrics including total messages dispatched, delivered, read receipts, and failed numbers.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "45", description: "Campaign ID." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        stats: { campaign_id: 45, name: "Diwali Mega Sale 2026", total: 1250, sent: 1250, delivered: 1210, read: 980, failed: 40, delivery_rate: "96.8%" }
                    }
                }
            },
            {
                id: "endpoint-bulk-delivery-status",
                name: "Get Detailed Delivery Status Log",
                method: "GET",
                path: "/api/bulk-messages/{id}/delivery-status",
                auth: true,
                description: "Inspect recipient-by-recipient delivery status with WhatsApp Message ID (WAMID), timestamp, and error reasons if failed.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "45", description: "Campaign ID." }
                ],
                queryParams: [
                    { key: "page", type: "integer", required: false, value: "1", description: "Page number." },
                    { key: "status", type: "string", required: false, value: "failed", description: "Filter: \"sent\", \"delivered\", \"read\", \"failed\"." },
                    { key: "search", type: "string", required: false, value: "919173", description: "Search by contact name or phone." }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        data: [
                            { contact_id: 101, contact_name: "Rahul Sharma", phone: "+919173129119", status: "read", wamid: "wamid.HBgLMOTE5MTczMTI5MTE5FQIAERgSQjE4Mk...", delivered_at: "2026-08-31 15:02:10" }
                        ]
                    }
                }
            },
            {
                id: "endpoint-bulk-resend",
                name: "Resend Failed / Pending Messages",
                method: "POST",
                path: "/api/bulk-messages/resend/{id}",
                auth: true,
                description: "Automatically re-attempt message delivery to all contacts who failed or were left pending in a previous campaign run.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "45", description: "Campaign ID." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": { status: true, message: "Resend initiated for 40 failed contacts" }
                }
            },
            {
                id: "endpoint-bulk-batch-settings-get",
                name: "Get Batch Sending Speed",
                method: "GET",
                path: "/api/batch-settings",
                auth: true,
                description: "Check current batch chunk size and dispatch delay intervals.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": { status: true, settings: { batchSize: 50, batchDelaySeconds: 10 } }
                }
            },
            {
                id: "endpoint-bulk-batch-settings-post",
                name: "Update Batch Sending Speed",
                method: "POST",
                path: "/api/batch-settings",
                auth: true,
                description: "Configure custom throttling for bulk messaging to comply with Meta messaging tier rate limits.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "batchSize", type: "integer", required: true, value: "50", description: "Number of messages to send in one batch chunk (e.g. 20 - 100)." },
                    { key: "batchDelaySeconds", type: "integer", required: true, value: "10", description: "Delay in seconds between successive batch chunks." }
                ],
                responses: {
                    "200": { status: true, message: "Batch settings updated successfully" }
                }
            }
        ]
    },
    {
        name: "WhatsApp CRM Live Chat & Inbox",
        icon: "fas fa-comments",
        description: "Full-featured live chat inbox: real-time 2-way conversation threads, inbound/outbound multimedia, agent assignment, emoji reactions, message pinning, and AI auto-replies.",
        endpoints: [
            {
                id: "endpoint-conversations-list",
                name: "List Conversations",
                method: "GET",
                path: "/api/conversations",
                auth: true,
                description: "Fetch paginated conversation threads for the authenticated business account with status filtering, search by name/phone, contact details, latest message preview, and 24h messaging window status.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [
                    { key: "status", type: "string", required: false, value: "all", description: "Filter by status: \"all\" (default, returns active + archived + closed), \"active\", \"archived\", \"closed\", \"pending\", \"unread\", \"starred\"." },
                    { key: "include_archived", type: "boolean", required: false, value: "true", description: "Set true to include archived conversations when status=active (default: true for all)." },
                    { key: "archived", type: "boolean", required: false, value: "false", description: "Convenience flag: set true/1 to retrieve only archived conversations." },
                    { key: "search", type: "string", required: false, value: "Arun", description: "Search query across contact name or phone number." },
                    { key: "limit", type: "integer", required: false, value: "200", description: "Items per page (e.g. 200, default: 20)." },
                    { key: "page", type: "integer", required: false, value: "1", description: "Current page number (default: 1)." },
                    { key: "_t", type: "string", required: false, value: "1788249300442", description: "Cache busting timestamp." }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        data: {
                            current_page: 1,
                            data: [
                                {
                                    id: 14,
                                    user_id: 1,
                                    phone_number: "919876543210",
                                    contact_name: "Arun Prajapati",
                                    last_message_at: "2026-09-01T12:51:00.000000Z",
                                    last_message_preview: "Good afternoon! Is this available?",
                                    is_unread: true,
                                    unread_count: 2,
                                    unread_messages_count: 2,
                                    status: "active",
                                    status_label: "Active",
                                    is_closed: false,
                                    is_archived: false,
                                    tags: [],
                                    active_tags: [],
                                    is_important: false,
                                    important: false,
                                    metadata: { ai_reply_disabled: false },
                                    contact_details: {
                                        id: 88,
                                        name: "Arun Prajapati",
                                        phone: "919876543210",
                                        email: "arun@example.com",
                                        tags: ["VIP", "Lead"],
                                        groups: ["Hot Leads 2026"],
                                        status: "active"
                                    },
                                    messaging_window_active: true,
                                    inbox_conversation_id: 14,
                                    ai_reply_enabled: true,
                                    capabilities: {
                                        can_reply: true,
                                        can_send_free_form: true,
                                        can_send_media: true,
                                        can_send_template: true,
                                        messaging_window_active: true,
                                        auto_ai_enabled: true
                                    },
                                    latest_message: {
                                        id: 204,
                                        conversation_id: 14,
                                        whatsapp_message_id: "wamid.HBgLM...",
                                        direction: "inbound",
                                        type: "text",
                                        content: "Good afternoon! Is this available?",
                                        status: "delivered",
                                        whatsapp_timestamp: "2026-09-01T12:51:00.000000Z",
                                        is_read: false
                                    }
                                }
                            ],
                            first_page_url: "https://zapreach.com/api/conversations?page=1",
                            from: 1,
                            last_page: 1,
                            per_page: 20,
                            total: 1
                        }
                    }
                }
            },
            {
                id: "endpoint-conversations-show",
                name: "Get Conversation by ID",
                method: "GET",
                path: "/api/conversations/{id}",
                auth: true,
                description: "Get full conversation thread, contact details, 24-hour window countdown, Auto AI status, and chronologically ordered messages with signed media URLs.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "14", description: "Conversation ID." }
                ],
                queryParams: [
                    { key: "limit", type: "integer", required: false, value: "50", description: "Max messages to fetch (default: 50)." },
                    { key: "page", type: "integer", required: false, value: "1", description: "Page number (1-based)." }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        data: {
                            conversation: {
                                id: 14,
                                user_id: 1,
                                phone_number: "919876543210",
                                contact_name: "Arun Prajapati",
                                status: "active",
                                status_label: "Active",
                                is_closed: false,
                                is_archived: false,
                                tags: [],
                                active_tags: [],
                                is_important: false,
                                important: false,
                                contact_details: { id: 88, name: "Arun Prajapati", phone: "919876543210", email: "arun@example.com" },
                                messaging_window_status: "active",
                                messaging_window_active: true,
                                messaging_window_hours_remaining: 23,
                                last_inbound_at: "2026-09-01T12:51:00.000000Z",
                                ai_reply_enabled: true,
                                auto_ai_enabled: true
                            },
                            messages: [
                                { id: 201, conversation_id: 14, direction: "inbound", type: "text", content: "Hi, can you send details?", whatsapp_timestamp: "2026-09-01T12:45:00.000000Z", is_read: true },
                                { id: 202, conversation_id: 14, direction: "outbound", type: "text", content: "Sure, here it is!", whatsapp_timestamp: "2026-09-01T12:48:00.000000Z", is_read: true }
                            ],
                            pagination: { total: 2, limit: 50, offset: 0, page: 1, total_pages: 1, has_more: false }
                        }
                    }
                }
            },
            {
                id: "endpoint-conversations-download-media",
                name: "Download / Stream Conversation Media",
                method: "GET",
                path: "/api/conversations/{conversationId}/media/{mediaId}",
                auth: true,
                description: "Download or stream WhatsApp media files securely for a conversation. Pass stream=1 for inline browser rendering (e.g. <img> tags) or download=1 for file attachment download.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "conversationId", type: "integer", required: true, value: "14", description: "Conversation ID." },
                    { key: "mediaId", type: "string", required: true, value: "987654321012345", description: "WhatsApp media ID or media filename." }
                ],
                queryParams: [
                    { key: "stream", type: "string", required: false, value: "1", description: "Set to \"1\" to return raw binary stream inline." },
                    { key: "download", type: "string", required: false, value: "1", description: "Set to \"1\" to trigger file download attachment." }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        data: {
                            media_id: "987654321012345",
                            filename: "987654321012345.jpg",
                            url: "https://zapreach.com/uploads/media/987654321012345.jpg",
                            full_url: "https://zapreach.com/api/conversations/14/media/987654321012345",
                            mime_type: "image/jpeg",
                            size: 245760
                        }
                    }
                }
            },
            {
                id: "endpoint-conversations-latest-contacts",
                name: "Get Latest Active Contacts",
                method: "GET",
                path: "/api/conversations/latest/contacts",
                auth: true,
                description: "Retrieve recent conversations that had incoming or outgoing activity within a specified number of hours (e.g., last 24 hours).",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [
                    { key: "limit", type: "integer", required: false, value: "10", description: "Max contacts to retrieve (default: 10)." },
                    { key: "hours", type: "integer", required: false, value: "24", description: "Activity lookback window in hours (default: 24)." }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        data: [
                            {
                                id: 14,
                                user_id: 1,
                                phone_number: "919876543210",
                                contact_name: "Arun Prajapati",
                                last_message_at: "2026-09-01T12:51:00.000000Z",
                                last_message_preview: "Good afternoon!",
                                is_unread: false,
                                unread_count: 0,
                                unread_messages_count: 0,
                                status: "active",
                                contact_details: { id: 88, name: "Arun Prajapati", phone: "919876543210", email: "arun@example.com" },
                                latest_message: { id: 204, type: "text", content: "Good afternoon!", direction: "inbound", whatsapp_timestamp: "2026-09-01T12:51:00.000000Z" }
                            }
                        ]
                    }
                }
            },
            {
                id: "endpoint-conversations-unread",
                name: "Get Unread Conversations",
                method: "GET",
                path: "/api/conversations/unread",
                auth: true,
                description: "Fetch all conversations that currently have unread incoming messages (is_unread = true).",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        data: [
                            {
                                id: 14,
                                user_id: 1,
                                phone_number: "919876543210",
                                contact_name: "Arun Prajapati",
                                last_message_at: "2026-09-01T12:51:00.000000Z",
                                last_message_preview: "Good afternoon!",
                                is_unread: true,
                                unread_count: 2,
                                unread_messages_count: 2,
                                status: "active",
                                contact_details: { id: 88, name: "Arun Prajapati", phone: "919876543210" }
                            }
                        ]
                    }
                }
            },
            {
                id: "endpoint-conversations-archived",
                name: "Get Archived Conversations",
                method: "GET",
                path: "/api/conversations/archived",
                auth: true,
                description: "Fetch paginated archived conversations (status = archived). Equivalent to GET /api/conversations?status=archived.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [
                    { key: "limit", type: "integer", required: false, value: "200", description: "Items per page (e.g. 200, default: 20)." },
                    { key: "page", type: "integer", required: false, value: "1", description: "Current page number (default: 1)." },
                    { key: "search", type: "string", required: false, value: "", description: "Search query across contact name or phone number." },
                    { key: "_t", type: "string", required: false, value: "1788249300442", description: "Cache busting timestamp." }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        data: {
                            current_page: 1,
                            data: [
                                {
                                    id: 15,
                                    user_id: 1,
                                    phone_number: "919876543211",
                                    contact_name: "Rahul Sharma",
                                    last_message_at: "2026-09-01T12:40:00.000000Z",
                                    last_message_preview: "Thank you, talk soon!",
                                    is_unread: false,
                                    unread_count: 0,
                                    unread_messages_count: 0,
                                    status: "archived",
                                    status_label: "Archived",
                                    is_closed: false,
                                    is_archived: true,
                                    tags: ["Archived"],
                                    active_tags: ["Archived"],
                                    is_important: false,
                                    important: false,
                                    metadata: { ai_reply_disabled: true },
                                    contact_details: {
                                        id: 89,
                                        name: "Rahul Sharma",
                                        phone: "919876543211",
                                        email: "rahul@example.com",
                                        tags: ["Archived"],
                                        groups: [],
                                        status: "active"
                                    },
                                    messaging_window_active: false,
                                    inbox_conversation_id: 15,
                                    ai_reply_enabled: false,
                                    capabilities: {
                                        can_reply: false,
                                        can_send_free_form: false,
                                        can_send_media: false,
                                        can_send_template: true,
                                        messaging_window_active: false,
                                        auto_ai_enabled: false
                                    }
                                }
                            ],
                            first_page_url: "https://zapreach.com/api/conversations/archived?page=1",
                            from: 1,
                            last_page: 1,
                            per_page: 200,
                            total: 1
                        }
                    }
                }
            },
            {
                id: "endpoint-conversations-update-status",
                name: "Update Conversation Status",
                method: "PUT",
                path: "/api/conversations/{id}/status",
                auth: true,
                description: "Update the operational status of a conversation (active, closed, archived, pending).",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "14", description: "Conversation ID." }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "status", type: "string", required: true, value: "closed", description: "Target status: \"active\", \"closed\", \"archived\", \"pending\"." }
                ],
                responses: {
                    "200": {
                        success: true,
                        message: "Conversation status updated successfully",
                        data: { id: 14, status: "closed", contact_name: "Arun Prajapati" }
                    }
                }
            },
            {
                id: "endpoint-conversations-set-important",
                name: "Mark / Toggle Important Conversation",
                method: "POST",
                path: "/api/conversations/{id}/important",
                auth: true,
                description: "Mark, unmark, or toggle the 'important' star flag on a conversation thread. Also accessible at /api/whatsapp/inbox/{id}/important.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "14", description: "Conversation ID (legacy conversation ID or inbox conversation ID)." }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "important", type: "boolean", required: false, value: "true", description: "Optional boolean (true = star/mark important, false = unstar). If omitted, automatically toggles state." }
                ],
                responses: {
                    "200": {
                        success: true,
                        message: "Conversation marked as important",
                        important: true,
                        is_important: true,
                        tag: "important",
                        tag_label: "Important",
                        tags: ["important"],
                        status: "active",
                        status_label: "Active"
                    }
                }
            },
            {
                id: "endpoint-conversations-close",
                name: "Close Conversation",
                method: "POST",
                path: "/api/conversations/{id}/close",
                auth: true,
                description: "Mark a conversation as resolved/closed. Also accessible at /api/whatsapp/inbox/{id}/close.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "14", description: "Conversation ID." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        message: "Conversation closed successfully",
                        status: "closed",
                        status_label: "Closed",
                        is_closed: true,
                        is_archived: false,
                        tags: []
                    }
                }
            },
            {
                id: "endpoint-conversations-archive",
                name: "Archive Conversation",
                method: "POST",
                path: "/api/conversations/{id}/archive",
                auth: true,
                description: "Move a conversation to archived status. Also accessible at /api/whatsapp/inbox/{id}/archive.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "14", description: "Conversation ID." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        message: "Conversation archived successfully",
                        status: "archived",
                        status_label: "Archived",
                        is_closed: false,
                        is_archived: true,
                        tags: []
                    }
                }
            },
            {
                id: "endpoint-conversations-reopen",
                name: "Reopen Conversation",
                method: "POST",
                path: "/api/conversations/{id}/reopen",
                auth: true,
                description: "Reopen a previously closed or archived conversation back to active/open status. Also accessible at /api/whatsapp/inbox/{id}/reopen.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "14", description: "Conversation ID." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        message: "Conversation reopened successfully",
                        status: "active",
                        status_label: "Active",
                        is_closed: false,
                        is_archived: false,
                        tags: []
                    }
                }
            },
            {
                id: "endpoint-conversations-toggle-tag",
                name: "Toggle Tag on Conversation",
                method: "POST",
                path: "/api/conversations/{id}/tag",
                auth: true,
                description: "Add, remove, or toggle custom tags (e.g. 'important', 'followup', 'lead', 'paid') on a conversation. Also accessible at /api/whatsapp/inbox/{id}/tag.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "14", description: "Conversation ID." }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "tag", type: "string", required: true, value: "important", description: "Name of the tag to add, remove, or toggle." },
                    { key: "enabled", type: "boolean", required: false, value: "true", description: "Optional boolean (true = add tag, false = remove tag). If omitted, automatically toggles tag state." }
                ],
                responses: {
                    "200": {
                        success: true,
                        message: "Tag updated successfully",
                        tag: "important",
                        enabled: true,
                        tags: ["important"]
                    }
                }
            },
            {
                id: "endpoint-conversations-ai-reply",
                name: "Toggle Auto AI for Conversation",
                method: "POST",
                path: "/api/conversations/{id}/ai-reply",
                auth: true,
                description: "Enable or disable automated AI responses for a specific customer conversation thread.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "14", description: "Conversation ID." }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "ai_reply_enabled", type: "boolean", required: true, value: "false", description: "true to enable AI Auto-Reply, false to disable." }
                ],
                responses: {
                    "200": {
                        success: true,
                        ai_reply_enabled: false,
                        auto_ai_enabled: true,
                        message: "Auto AI disabled for this chat"
                    }
                }
            },
            {
                id: "endpoint-conversations-stats",
                name: "Get Conversation Statistics",
                method: "GET",
                path: "/api/conversations/stats",
                auth: true,
                description: "Retrieve quick operational metrics for conversations and messaging activity (total, active, unread, recent, total messages today).",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        data: {
                            total_conversations: 142,
                            active_conversations: 85,
                            unread_conversations: 4,
                            recent_conversations: 18,
                            total_messages_today: 96
                        }
                    }
                }
            },
            {
                id: "endpoint-messages-send",
                name: "Send Free-Form WhatsApp Message",
                method: "POST",
                path: "/api/messages/send",
                auth: true,
                description: "Send a 1-on-1 WhatsApp message (Text, Image, Video, Audio, Document, Location) within the 24-hour customer care service window. Supports JSON or multipart/form-data for direct file uploads.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "conversation_id", type: "integer", required: true, value: "14", description: "Target conversation ID." },
                    { key: "message", type: "string", required: false, value: "Hello Arun, thanks for reaching out! How can we assist you?", description: "Text content or caption (required if no file)." },
                    { key: "file", type: "file", required: false, value: "brochure.pdf", description: "Optional binary file to upload & send directly (max 50MB)." },
                    { key: "type", type: "string", required: false, value: "text", description: "Message type: \"text\", \"image\", \"video\", \"audio\", \"document\", \"location\"." },
                    { key: "media_url", type: "string", required: false, value: "https://example.com/brochure.pdf", description: "Remote media URL if not uploading file." },
                    { key: "media_type", type: "string", required: false, value: "application/pdf", description: "MIME type of media." },
                    { key: "media_filename", type: "string", required: false, value: "brochure.pdf", description: "Filename for document type." }
                ],
                responses: {
                    "200": {
                        success: true,
                        message: "Message sent successfully",
                        data: {
                            id: 501,
                            conversation_id: 14,
                            whatsapp_message_id: "wamid.HBgLMzkxOTg3NjU0MzIxMBUCABEYEkFEMDU4MU...",
                            direction: "outbound",
                            type: "text",
                            content: "Hello Arun, thanks for reaching out! How can we assist you?",
                            status: "sent",
                            whatsapp_timestamp: "2026-09-01T13:30:00.000000Z",
                            is_read: true
                        }
                    },
                    "400": {
                        success: false,
                        message: "Cannot send free-form message. The 24-hour window has expired. Use a template message instead.",
                        can_send_free_form: false
                    }
                }
            },
            {
                id: "endpoint-messages-get-conversation",
                name: "Get Message History for Conversation",
                method: "GET",
                path: "/api/messages/conversation/{conversationId}",
                auth: true,
                description: "Retrieve message history for a specific conversation with pagination and pre-signed media preview URLs.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "conversationId", type: "integer", required: true, value: "14", description: "Conversation ID." }
                ],
                queryParams: [
                    { key: "limit", type: "integer", required: false, value: "50", description: "Number of messages to retrieve (default: 50)." },
                    { key: "offset", type: "integer", required: false, value: "0", description: "Message offset index (default: 0)." }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        data: [
                            {
                                id: 501,
                                conversation_id: 14,
                                whatsapp_message_id: "wamid.HBgLM...",
                                direction: "outbound",
                                type: "text",
                                content: "Hello Arun, thanks for reaching out!",
                                status: "sent",
                                whatsapp_timestamp: "2026-09-01T13:30:00.000000Z",
                                is_read: true
                            }
                        ]
                    }
                }
            },
            {
                id: "endpoint-messages-mark-read",
                name: "Mark Conversation Messages as Read",
                method: "POST",
                path: "/api/messages/{conversationId}/mark-read",
                auth: true,
                description: "Mark all incoming messages in a conversation as read and clear the unread indicator.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [
                    { key: "conversationId", type: "integer", required: true, value: "14", description: "Conversation ID." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        message: "Messages marked as read"
                    }
                }
            },
            {
                id: "endpoint-messages-eligible-conversations",
                name: "Get Eligible Conversations (24h Window)",
                method: "GET",
                path: "/api/messages/eligible-conversations",
                auth: true,
                description: "Fetch all conversations that have received an incoming message within the last 24 hours (eligible for free-form direct replies without Meta template charges).",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        data: [
                            {
                                id: 14,
                                user_id: 1,
                                phone_number: "919876543210",
                                contact_name: "Arun Prajapati",
                                last_message_at: "2026-09-01T12:51:00.000000Z",
                                last_message_preview: "Good afternoon!",
                                is_unread: false,
                                status: "active"
                            }
                        ]
                    }
                }
            },
            {
                id: "endpoint-messages-check-eligibility",
                name: "Check 24-Hour Window Eligibility",
                method: "GET",
                path: "/api/messages/{conversationId}/check-eligibility",
                auth: true,
                description: "Check whether a specific conversation is within the 24-hour WhatsApp customer care window and calculate hours remaining.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "conversationId", type: "integer", required: true, value: "14", description: "Conversation ID." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        data: {
                            conversation_id: "14",
                            is_eligible: true,
                            hours_remaining: 23,
                            last_message_time: "2026-09-01T12:51:00.000000Z"
                        }
                    }
                }
            },
            {
                id: "endpoint-whatsapp-inbox-list",
                name: "Get Live Chat Inbox",
                method: "GET",
                path: "/api/whatsapp/inbox",
                auth: true,
                description: "Fetch all WhatsApp chat conversations with real-time unread badges, lead details, assigned agent, and latest message snippet.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [
                    { key: "status", type: "string", required: false, value: "all", description: "Filter: \"all\", \"unread\", \"pending\", \"closed\", \"starred\", \"archived\"." },
                    { key: "assigned_to", type: "integer", required: false, value: "12", description: "Filter by assigned staff member ID." },
                    { key: "search", type: "string", required: false, value: "Rahul", description: "Search across contact name or phone number." },
                    { key: "page", type: "integer", required: false, value: "1", description: "Pagination page number." }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        totalUnread: 14,
                        conversations: {
                            current_page: 1,
                            data: [
                                { id: 301, contact_name: "Rahul Sharma", phone_number: "+919173129119", status: "open", unread_count: 2, ai_reply_enabled: true, tags: ["Hot Lead"], last_message: { text: "Can you send pricing?", direction: "inbound" } }
                            ]
                        }
                    }
                }
            },
            {
                id: "endpoint-whatsapp-inbox-show",
                name: "Get Single Conversation Details",
                method: "GET",
                path: "/api/whatsapp/inbox/{conversation}",
                auth: true,
                description: "Get complete conversation metadata, CRM lead associations, follow-up reminders, and assigned agent information.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "conversation", type: "integer", required: true, value: "301", description: "Conversation ID." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        conversation: { id: 301, contact_name: "Rahul Sharma", phone_number: "+919173129119", lead: { id: 88, company: "Sharma Logistics" } }
                    }
                }
            },
            {
                id: "endpoint-whatsapp-inbox-messages",
                name: "Get Conversation Message Thread",
                method: "GET",
                path: "/api/whatsapp/inbox/{conversation}/messages",
                auth: true,
                description: "Retrieve all chronological messages (inbound & outbound) for a specific conversation.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "conversation", type: "integer", required: true, value: "301", description: "Conversation ID." }
                ],
                queryParams: [
                    { key: "page", type: "integer", required: false, value: "1", description: "Pagination page number." }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        messages: [
                            { id: 1205, direction: "inbound", type: "text", body: "Hello, I need pricing information.", created_at: "2026-09-01T11:40:00.000000Z" },
                            { id: 1206, direction: "outbound", type: "text", body: "Hi Rahul! Glad to assist you today.", created_at: "2026-09-01T11:42:00.000000Z" }
                        ]
                    }
                }
            },
            {
                id: "endpoint-whatsapp-inbox-send",
                name: "Send Live Text Message",
                method: "POST",
                path: "/api/whatsapp/inbox/{conversation}/send",
                auth: true,
                description: "Send an outbound text message directly to the customer in the active WhatsApp conversation.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [
                    { key: "conversation", type: "integer", required: true, value: "301", description: "Conversation ID." }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "message", type: "string", required: true, value: "Hello! Here is our latest pricing brochure.", description: "Text message to dispatch to the customer." }
                ],
                responses: {
                    "200": { success: true, message: "Message sent successfully", data: { id: 1207, direction: "outbound", status: "sent" } }
                }
            },
            {
                id: "endpoint-whatsapp-inbox-send-media",
                name: "Send Media (Image / Doc / Audio / Video)",
                method: "POST",
                path: "/api/whatsapp/inbox/{conversation}/send-media",
                auth: true,
                description: "Upload and send attachments such as PDF brochures, images, voice notes, or videos directly into the chat.",
                contentType: "multipart/form-data",
                bodyMode: "formdata",
                headers: [],
                pathParams: [
                    { key: "conversation", type: "integer", required: true, value: "301", description: "Conversation ID." }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "file", type: "file", required: true, value: "catalogue.pdf", description: "File binary to upload and send." },
                    { key: "type", type: "string", required: true, value: "document", description: "Media type: \"image\", \"document\", \"audio\", \"video\"." },
                    { key: "caption", type: "string", required: false, value: "Here is our full 2026 catalogue", description: "Optional text caption for images or documents." }
                ],
                responses: {
                    "200": { success: true, message: "Media dispatched successfully", data: { id: 1208, type: "document" } }
                }
            },
            {
                id: "endpoint-whatsapp-inbox-assign",
                name: "Assign Conversation to Staff Agent",
                method: "POST",
                path: "/api/whatsapp/inbox/{conversation}/assign",
                auth: true,
                description: "Assign the conversation to a specific staff member for specialized support handling.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [
                    { key: "conversation", type: "integer", required: true, value: "301", description: "Conversation ID." }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "assigned_user_id", type: "integer", required: true, value: "12", description: "Staff user ID to assign to (or null to unassign)." }
                ],
                responses: {
                    "200": { success: true, message: "Conversation assigned successfully" }
                }
            },
            {
                id: "endpoint-whatsapp-inbox-status",
                name: "Update Conversation Status",
                method: "POST",
                path: "/api/whatsapp/inbox/{conversation}/status",
                auth: true,
                description: "Update chat ticket lifecycle status.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [
                    { key: "conversation", type: "integer", required: true, value: "301", description: "Conversation ID." }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "status", type: "string", required: true, value: "closed", description: "Target status: \"open\", \"pending\", \"closed\", \"archived\"." }
                ],
                responses: {
                    "200": { success: true, message: "Status updated successfully" }
                }
            },
            {
                id: "endpoint-whatsapp-inbox-ai-reply",
                name: "Toggle AI Auto-Reply for Conversation",
                method: "POST",
                path: "/api/whatsapp/inbox/{conversation}/ai-reply",
                auth: true,
                description: "Enable or disable automated AI responses for this specific customer conversation thread.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [
                    { key: "conversation", type: "integer", required: true, value: "301", description: "Conversation ID." }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "ai_reply_enabled", type: "boolean", required: true, value: "true", description: "true to enable AI Auto-Reply, false to disable." }
                ],
                responses: {
                    "200": { success: true, message: "AI reply preference updated", ai_reply_enabled: true }
                }
            },
            {
                id: "endpoint-whatsapp-inbox-create-lead",
                name: "Convert Chat to CRM Lead",
                method: "POST",
                path: "/api/whatsapp/inbox/{conversation}/create-lead",
                auth: true,
                description: "Instantly generate a structured CRM lead record directly from this WhatsApp conversation.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [
                    { key: "conversation", type: "integer", required: true, value: "301", description: "Conversation ID." }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "name", type: "string", required: true, value: "Rahul Sharma", description: "Lead contact name." },
                    { key: "phone", type: "string", required: true, value: "+919173129119", description: "Lead mobile number." },
                    { key: "email", type: "string", required: false, value: "rahul@example.com", description: "Lead email address." },
                    { key: "source", type: "string", required: false, value: "WhatsApp Broadcast", description: "Lead origin source." },
                    { key: "status", type: "string", required: false, value: "Qualified", description: "Lead pipeline status." }
                ],
                responses: {
                    "201": { success: true, message: "Lead created and linked to conversation", leadId: 88 }
                }
            }
        ]
    },
    {
        name: "CRM Automation & Chatbots",
        icon: "fas fa-robot",
        description: "Keyword triggers, welcome auto-responders, out-of-office automations, and intelligent rule routing.",
        endpoints: [
            {
                id: "endpoint-automation-list",
                name: "List Automation Rules",
                method: "GET",
                path: "/api/whatsapp/automation",
                auth: true,
                description: "Get all configured chatbot rules and auto-reply triggers.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        data: [
                            { id: 1, name: "Price Inquiries Auto-Response", trigger_type: "keyword", trigger_keywords: ["price", "pricing", "cost"], action_type: "send_message", is_active: true }
                        ]
                    }
                }
            },
            {
                id: "endpoint-automation-create",
                name: "Create Automation Rule",
                method: "POST",
                path: "/api/whatsapp/automation",
                auth: true,
                description: "Define a new automated trigger rule.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "name", type: "string", required: true, value: "Price Inquiries Auto-Response", description: "Rule name." },
                    { key: "trigger_type", type: "string", required: true, value: "keyword", description: "Trigger type: \"keyword\", \"welcome\", \"offline\"." },
                    { key: "trigger_keywords", type: "array", required: false, value: "[\"price\", \"cost\", \"catalogue\"]", description: "Array of match keywords (case-insensitive)." },
                    { key: "action_type", type: "string", required: true, value: "send_message", description: "Action type: \"send_message\", \"assign_agent\", \"add_tag\"." },
                    { key: "action_payload", type: "object", required: true, value: "{\"message\":\"Here is our pricing list: https://example.com/prices\"}", description: "Configuration payload for action." },
                    { key: "is_active", type: "boolean", required: false, value: "true", description: "Enable or disable rule immediately." }
                ],
                responses: {
                    "201": { status: true, message: "Automation rule created successfully" }
                }
            },
            {
                id: "endpoint-automation-toggle",
                name: "Toggle Automation Rule Status",
                method: "POST",
                path: "/api/whatsapp/automation/{rule}/toggle",
                auth: true,
                description: "Quickly toggle an automation rule between Active and Inactive.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "rule", type: "integer", required: true, value: "1", description: "Automation rule ID." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": { status: true, is_active: false, message: "Automation rule status updated" }
                }
            }
        ]
    },
    {
        name: "WhatsApp CRM Campaigns",
        icon: "fas fa-paper-plane",
        description: "Full lifecycle Campaign API Resource for broadcasting, scheduled drops, and drip campaigns with launch, cancel, status metrics, and template associations.",
        endpoints: [
            {
                id: "endpoint-crm-campaigns-list",
                name: "List CRM Campaigns & Stats",
                method: "GET",
                path: "/api/whatsapp/campaigns",
                auth: true,
                description: "Retrieve paginated list of WhatsApp CRM campaigns with live aggregated stats (total, draft, scheduled, sending, completed, cancelled, sent_count, delivered_count, read_count, failed_count).",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [
                    { key: "page", type: "integer", required: false, value: "1", description: "Page number for pagination." },
                    { key: "status", type: "string", required: false, value: "scheduled", description: "Filter by status: \"draft\", \"scheduled\", \"sending\", \"completed\", \"cancelled\"." },
                    { key: "all", type: "boolean", required: false, value: "false", description: "Admin only: set to 1 to view all campaigns across company accounts." },
                    { key: "user_id", type: "integer", required: false, value: "10", description: "Admin only: filter campaigns by target user ID." }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        campaigns: {
                            current_page: 1,
                            data: [
                                {
                                    id: 12,
                                    userId: 10,
                                    name: "September Festive Promo",
                                    description: "Exclusive discount blast for loyalty members",
                                    type: "broadcast",
                                    status: "scheduled",
                                    template_id: 20,
                                    message: null,
                                    scheduled_at: "2026-09-05 10:00:00",
                                    total_recipients: 1500,
                                    sent_count: 0,
                                    delivered_count: 0,
                                    read_count: 0,
                                    failed_count: 0,
                                    created_at: "2026-09-01T10:00:00.000000Z",
                                    template: { id: 20, name: "order_confirmation_v2", status: "APPROVED" }
                                }
                            ],
                            total: 1
                        },
                        stats: {
                            total: 12,
                            draft: 2,
                            scheduled: 4,
                            sending: 1,
                            completed: 5,
                            cancelled: 0,
                            total_recipients: 15400,
                            sent_count: 14200,
                            delivered_count: 13950,
                            read_count: 11200,
                            failed_count: 250
                        },
                        meta: { userId: 10 }
                    }
                }
            },
            {
                id: "endpoint-crm-campaigns-store",
                name: "Create CRM Campaign",
                method: "POST",
                path: "/api/whatsapp/campaigns",
                auth: true,
                description: "Create a new broadcast, drip, or scheduled campaign record.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "name", type: "string", required: true, value: "September Festive Promo", description: "Campaign display name (max: 255 chars)." },
                    { key: "type", type: "string", required: true, value: "broadcast", description: "Campaign dispatch mode: \"broadcast\", \"drip\", \"scheduled\"." },
                    { key: "template_id", type: "integer", required: false, value: "20", description: "ID of the approved WhatsApp template belonging to your account." },
                    { key: "message", type: "string", required: false, value: "Hi there, special autumn discount is now live!", description: "Custom message body if not using a template." },
                    { key: "description", type: "string", required: false, value: "Autumn campaign targeting all active contacts", description: "Internal notes / description for the campaign." },
                    { key: "scheduled_at", type: "datetime string", required: false, value: "2026-09-05 10:00:00", description: "Datetime to schedule campaign dispatch (YYYY-MM-DD HH:MM:SS)." },
                    { key: "filters", type: "array", required: false, value: "{\"tags\":[\"VIP\",\"Lead\"],\"groupIds\":[1,2]}", description: "Target audience filter rules (tags, groups, attributes)." }
                ],
                responses: {
                    "201": {
                        success: true,
                        campaign: {
                            id: 12,
                            userId: 10,
                            name: "September Festive Promo",
                            type: "broadcast",
                            status: "draft",
                            template_id: 20,
                            created_at: "2026-09-01T12:00:00.000000Z"
                        }
                    },
                    "422": {
                        success: false,
                        errors: {
                            name: ["The name field is required."],
                            type: ["The selected type is invalid."]
                        }
                    },
                    "404": {
                        success: false,
                        message: "Template not found for your account"
                    }
                }
            },
            {
                id: "endpoint-crm-campaigns-show",
                name: "Get Campaign by ID",
                method: "GET",
                path: "/api/whatsapp/campaigns/{campaign}",
                auth: true,
                description: "Retrieve specific campaign details, scheduled time, target counts, and associated template.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "campaign", type: "integer", required: true, value: "12", description: "Unique Campaign ID." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        campaign: {
                            id: 12,
                            userId: 10,
                            name: "September Festive Promo",
                            description: "Exclusive discount blast",
                            type: "broadcast",
                            status: "scheduled",
                            template_id: 20,
                            scheduled_at: "2026-09-05 10:00:00",
                            total_recipients: 1500,
                            template: { id: 20, name: "order_confirmation_v2", status: "APPROVED" }
                        }
                    },
                    "404": {
                        success: false,
                        message: "Campaign not found"
                    }
                }
            },
            {
                id: "endpoint-crm-campaigns-update",
                name: "Update Campaign",
                method: "PUT",
                path: "/api/whatsapp/campaigns/{campaign}",
                auth: true,
                description: "Modify campaign configuration (only campaigns in \"draft\" or \"scheduled\" status can be edited).",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [
                    { key: "campaign", type: "integer", required: true, value: "12", description: "Campaign ID to update." }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "name", type: "string", required: true, value: "September Festive Promo - Updated", description: "Updated campaign name (max: 255 chars)." },
                    { key: "type", type: "string", required: true, value: "broadcast", description: "Dispatch mode: \"broadcast\", \"drip\", \"scheduled\"." },
                    { key: "template_id", type: "integer", required: false, value: "20", description: "Template ID." },
                    { key: "message", type: "string", required: false, value: "Updated broadcast body message", description: "Custom body text." },
                    { key: "description", type: "string", required: false, value: "Updated internal description", description: "Optional description." },
                    { key: "scheduled_at", type: "datetime string", required: false, value: "2026-09-06 11:30:00", description: "Updated scheduled launch datetime." }
                ],
                responses: {
                    "200": {
                        success: true,
                        campaign: {
                            id: 12,
                            name: "September Festive Promo - Updated",
                            status: "scheduled",
                            scheduled_at: "2026-09-06 11:30:00"
                        }
                    },
                    "422": {
                        success: false,
                        message: "Cannot edit a running or completed campaign."
                    },
                    "404": {
                        success: false,
                        message: "Campaign not found"
                    }
                }
            },
            {
                id: "endpoint-crm-campaigns-delete",
                name: "Delete Campaign",
                method: "DELETE",
                path: "/api/whatsapp/campaigns/{campaign}",
                auth: true,
                description: "Permanently delete a campaign record.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "campaign", type: "integer", required: true, value: "12", description: "Campaign ID to delete." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": { success: true },
                    "404": { success: false, message: "Campaign not found" }
                }
            },
            {
                id: "endpoint-crm-campaigns-launch",
                name: "Launch Campaign",
                method: "POST",
                path: "/api/whatsapp/campaigns/{campaign}/launch",
                auth: true,
                description: "Trigger immediate execution and dispatch of a draft or scheduled campaign via background job queue.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "campaign", type: "integer", required: true, value: "12", description: "Campaign ID to launch." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        message: "Campaign launched successfully."
                    },
                    "422": {
                        success: false,
                        message: "Campaign cannot be launched."
                    },
                    "404": {
                        success: false,
                        message: "Campaign not found"
                    }
                }
            },
            {
                id: "endpoint-crm-campaigns-cancel",
                name: "Cancel Campaign",
                method: "POST",
                path: "/api/whatsapp/campaigns/{campaign}/cancel",
                auth: true,
                description: "Cancel a draft, scheduled, or sending campaign to stop further message dispatches.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "campaign", type: "integer", required: true, value: "12", description: "Campaign ID to cancel." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": { success: true },
                    "422": { success: false, message: "Campaign cannot be cancelled." },
                    "404": { success: false, message: "Campaign not found" }
                }
            }
        ]
    },
    {
        name: "CRM Follow-ups & Tasks",
        icon: "fas fa-calendar-check",
        description: "Schedule customer follow-up dates, set agent reminders, and track conversion milestones.",
        endpoints: [
            {
                id: "endpoint-followups-list",
                name: "List Followups",
                method: "GET",
                path: "/api/whatsapp/followups",
                auth: true,
                description: "Get upcoming, overdue, and completed follow-up reminders.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [
                    { key: "status", type: "string", required: false, value: "pending", description: "Filter: \"pending\", \"completed\", \"overdue\"." },
                    { key: "assigned_to", type: "integer", required: false, value: "12", description: "Filter by staff ID." }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        data: [{ id: 15, conversation_id: 301, contact_name: "Rahul Sharma", due_at: "2026-09-03 14:00:00", notes: "Call for bulk purchase", is_completed: false }]
                    }
                }
            },
            {
                id: "endpoint-followups-create",
                name: "Schedule Followup Task",
                method: "POST",
                path: "/api/whatsapp/followups",
                auth: true,
                description: "Create a scheduled follow-up reminder for a conversation.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "conversation_id", type: "integer", required: true, value: "301", description: "Target conversation ID." },
                    { key: "assigned_user_id", type: "integer", required: false, value: "12", description: "Staff ID assigned to complete the followup." },
                    { key: "due_at", type: "datetime string", required: true, value: "2026-09-03 14:00:00", description: "Followup reminder date and time." },
                    { key: "notes", type: "string", required: false, value: "Call to close 50,000 unit bulk purchase contract", description: "Detailed notes." }
                ],
                responses: {
                    "201": { status: true, message: "Followup scheduled successfully" }
                }
            },
            {
                id: "endpoint-followups-complete",
                name: "Mark Followup as Completed",
                method: "POST",
                path: "/api/whatsapp/followups/{followup}/complete",
                auth: true,
                description: "Mark a pending follow-up task as resolved.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "followup", type: "integer", required: true, value: "15", description: "Followup ID." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": { status: true, message: "Followup marked as completed" }
                }
            }
        ]
    },
    {
        name: "Staff & Sub-account Management",
        icon: "fas fa-users-cog",
        description: "Create and control staff agent credentials, assign permission modules (contacts, campaigns, inbox, billing), and toggle status.",
        endpoints: [
            {
                id: "endpoint-staff-list",
                name: "List Staff Members",
                method: "GET",
                path: "/api/whatsapp/admin/staff",
                auth: true,
                description: "Retrieve all staff sub-accounts registered under the company profile with assigned module permissions.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        staff: [
                            { id: 12, name: "Priya Support Agent", email: "priya@zapreach.com", phone: "+919876543211", role: "staff", status: "active", permissions: ["contacts", "conversations"] }
                        ]
                    }
                }
            },
            {
                id: "endpoint-staff-create",
                name: "Create Staff Member",
                method: "POST",
                path: "/api/whatsapp/admin/staff",
                auth: true,
                description: "Add a new staff team member with custom module permissions.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "name", type: "string", required: true, value: "Priya Support Agent", description: "Full name of staff member." },
                    { key: "email", type: "string", required: true, value: "priya@zapreach.com", description: "Staff login email address (must be unique)." },
                    { key: "phone", type: "string", required: true, value: "+919876543211", description: "Staff mobile number." },
                    { key: "password", type: "string", required: true, value: "StaffSecret@2026", description: "Initial login password (min: 8 characters)." },
                    { key: "permissions", type: "array", required: true, value: "[\"contacts\", \"campaigns\", \"conversations\", \"templates\", \"reports\", \"settings\", \"billing\"]", description: "Array of allowed module permissions." },
                    { key: "role_title", type: "string", required: false, value: "Customer Success Specialist", description: "Internal job designation." }
                ],
                responses: {
                    "201": { success: true, message: "Staff member created successfully", staff: { id: 12, name: "Priya Support Agent" } }
                }
            },
            {
                id: "endpoint-staff-status",
                name: "Update Staff Status",
                method: "PUT",
                path: "/api/whatsapp/admin/staff/{id}/status",
                auth: true,
                description: "Activate or suspend staff member access.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "12", description: "Staff user ID." }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "status", type: "string", required: true, value: "inactive", description: "Status: \"active\" or \"inactive\"." }
                ],
                responses: {
                    "200": { success: true, message: "Staff status updated" }
                }
            }
        ]
    },
    {
        name: "Settings & Auto AI Configuration",
        icon: "fas fa-cogs",
        description: "Meta WhatsApp Business API credentials, Facebook Embedded Signup OAuth, access token refresh, and Auto AI persona settings.",
        endpoints: [
            {
                id: "endpoint-settings-get",
                name: "Get WhatsApp & Business Settings",
                method: "GET",
                path: "/api/settings",
                auth: true,
                description: "Retrieve active WhatsApp Cloud API credentials, webhook URL, token expiry status, and Auto AI business parameters.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        apiConfiguration: { credentials_status: "live", is_configured: true, isActive: true, fb_app_id: "123456789012345", phoneNumberId: "109876543210987", waba_id: "1322751702857894", webhook_url: "https://zapreach.com/api/whatsapp/webhook/10" },
                        businessDetails: { auto_ai_enabled: true, business_name: "ZapReach Solutions", auto_ai_tone: "friendly", first_time_message: "Hello! Welcome to our service. How can we assist you today?" }
                    }
                }
            },
            {
                id: "endpoint-settings-save",
                name: "Save Meta WhatsApp API Config",
                method: "POST",
                path: "/api/settings/save-config",
                auth: true,
                description: "Save or update Meta Graph API credentials: WABA ID, Phone Number ID, App Secret, and Permanent Access Token.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "app_id", type: "string", required: true, value: "123456789012345", description: "Meta Developer App ID." },
                    { key: "app_secret", type: "string", required: true, value: "a1b2c3d4e5f6g7h8i9j0", description: "Meta Developer App Secret." },
                    { key: "phoneNumberId", type: "string", required: true, value: "109876543210987", description: "WhatsApp Phone Number ID generated by Meta." },
                    { key: "waba_id", type: "string", required: true, value: "1322751702857894", description: "WhatsApp Business Account (WABA) ID." },
                    { key: "access_token", type: "string", required: true, value: "EAAGm0PXqOkB...", description: "Permanent System User Access Token with messaging permissions." }
                ],
                responses: {
                    "200": { status: true, message: "Settings saved successfully" }
                }
            },
            {
                id: "endpoint-settings-auto-ai",
                name: "Configure Auto AI Assistant",
                method: "POST",
                path: "/api/settings/auto-ai",
                auth: true,
                description: "Configure intelligent AI auto-responder for customer queries with business knowledge, operating hours, tone of voice, and first-time user greeting.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "business_name", type: "string", required: false, value: "ZapReach Solutions", description: "Company or brand name." },
                    { key: "business_type", type: "string", required: false, value: "B2B SaaS / Marketing Agency", description: "Business category or model." },
                    { key: "industry", type: "string", required: false, value: "Software & Information Technology", description: "Industry domain." },
                    { key: "business_details", type: "string", required: false, value: "We provide official WhatsApp Business API broadcast software, conversational AI chatbots, and lead automation tools for businesses to scale customer engagement and order handling.", description: "Business background and knowledge summary." },
                    { key: "services", type: "string", required: false, value: "WhatsApp Bulk Messaging, Official Meta API Setup, Automated Chatbot Flows, Customer Support Routing", description: "List of services offered." },
                    { key: "products", type: "string", required: false, value: "Starter Messaging Plan, Growth Plan, Enterprise Unlimited Broadcast, Custom API Integration", description: "Main products or packages." },
                    { key: "phone", type: "string", required: false, value: "+91 98765 43210", description: "Helpline or customer support phone number." },
                    { key: "email", type: "string", required: false, value: "support@zapreach.com", description: "Official support email address." },
                    { key: "website", type: "string", required: false, value: "https://zapreach.com", description: "Official website URL." },
                    { key: "location", type: "string", required: false, value: "Pan-India & Global Digital Services", description: "Service coverage or operational location." },
                    { key: "address", type: "string", required: false, value: "Suite 402, Business Hub, Tech Park, Bangalore, Karnataka - 560001", description: "Physical office or store address." },
                    { key: "business_hours", type: "string", required: false, value: "09:00 AM - 08:00 PM", description: "Operating hours." },
                    { key: "working_days", type: "array", required: false, value: "[\"Monday\",\"Tuesday\",\"Wednesday\",\"Thursday\",\"Friday\",\"Saturday\"]", description: "Array of operational weekdays." },
                    { key: "language", type: "string", required: false, value: "English, Hindi, Hinglish", description: "Supported conversation languages." },
                    { key: "auto_ai_enabled", type: "boolean", required: true, value: "true", description: "Set to true to activate AI auto-replies on incoming chats." },
                    { key: "auto_ai_tone", type: "string", required: false, value: "friendly", description: "AI personality tone: \"friendly\", \"professional\", \"concise\"." },
                    { key: "first_time_message", type: "string", required: false, value: "Hello! Welcome to ZapReach Solutions. How can we assist you today with your WhatsApp broadcast or software setup?", description: "Welcome greeting sent to first-time customer messages." }
                ],
                responses: {
                    "200": { status: true, message: "Auto AI settings updated successfully" }
                }
            }
        ]
    },
    {
        name: "Wallet & Razorpay Recharge",
        icon: "fas fa-wallet",
        description: "Prepaid wallet balance, transaction ledger, Razorpay order generation, and signature verification.",
        endpoints: [
            {
                id: "endpoint-wallet-summary",
                name: "Get Wallet Balance & Summary",
                method: "GET",
                path: "/api/wallet",
                auth: true,
                description: "Retrieve live prepaid wallet balance and currency settings.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": { status: true, success: true, data: { balance: 1500.50, currency: "INR", total_recharged: 5000.00, total_spent: 3499.50 } }
                }
            },
            {
                id: "endpoint-wallet-history",
                name: "Wallet Transaction History",
                method: "GET",
                path: "/api/wallet/history",
                auth: true,
                description: "Get chronological transaction log of all recharges and campaign deductions.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [
                    { key: "category", type: "string", required: false, value: "MARKETING", description: "Filter by category: MARKETING, UTILITY, AUTHENTICATION (case-insensitive, supports comma-separated list)." },
                    { key: "search", type: "string", required: false, value: "", description: "Search query across description, reference ID, amount, or source." },
                    { key: "date_from", type: "string", required: false, value: "", description: "Start date filter (YYYY-MM-DD)." },
                    { key: "date_to", type: "string", required: false, value: "", description: "End date filter (YYYY-MM-DD)." },
                    { key: "date", type: "string", required: false, value: "", description: "Single date filter (YYYY-MM-DD)." },
                    { key: "page", type: "integer", required: false, value: "1", description: "Page number (default 1)." },
                    { key: "limit", type: "integer", required: false, value: "50", description: "Number of transaction records (default 50, max 100)." }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        data: [{ id: 84, amount: 1000.00, type: "credit", description: "Razorpay Wallet Recharge - Order #order_Nx8Y1...", balance_after: 1500.50 }]
                    }
                }
            },
            {
                id: "endpoint-wallet-create-order",
                name: "Create Razorpay Recharge Order",
                method: "POST",
                path: "/api/wallet/create-order",
                auth: true,
                description: "Generate an official Razorpay Order ID for initiating payment gateway checkout on frontend.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "amount", type: "numeric", required: true, value: "1000", description: "Recharge amount in INR (minimum ₹1.00)." },
                    { key: "currency", type: "string", required: false, value: "INR", description: "Payment currency (default: \"INR\")." }
                ],
                responses: {
                    "201": { status: true, success: true, message: "Order created successfully", data: { order_id: "order_Nx8Y12345Abcde", amount: 100000, currency: "INR", key: "rzp_live_abcdef123456" } }
                }
            },
            {
                id: "endpoint-wallet-verify-payment",
                name: "Verify Razorpay Payment",
                method: "POST",
                path: "/api/wallet/verify",
                auth: true,
                description: "Verify cryptographic signature returned by Razorpay checkout and credit user wallet balance.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "razorpay_order_id", type: "string", required: true, value: "order_Nx8Y12345Abcde", description: "Razorpay Order ID." },
                    { key: "razorpay_payment_id", type: "string", required: true, value: "pay_Nx8Z98765Qwert", description: "Razorpay Payment ID generated upon success." },
                    { key: "razorpay_signature", type: "string", required: true, value: "9ef8a1768c35b80a7864...", description: "HMAC-SHA256 signature calculated by Razorpay." }
                ],
                responses: {
                    "200": { status: true, success: true, message: "Payment verified successfully and ₹1000 added to wallet", wallet: { balance: 1500.50, currency: "INR" } }
                }
            }
        ]
    },
    {
        name: "Packages & Subscriptions",
        icon: "fas fa-box-open",
        description: "Public and authenticated subscription plans: browse plans with GST calculation, buy/subscribe via Razorpay or Wallet balance, upgrade plans, and track active plan status.",
        endpoints: [
            {
                id: "endpoint-plans-list",
                name: "List Available Subscription Plans & Packages",
                method: "GET",
                path: "/api/plans",
                auth: false,
                description: "Retrieve all active subscription plans with full pricing, period breakdown (Monthly / Yearly), message & template quotas, dynamic 18% GST calculation, and current plan status if authenticated with Bearer token. Also accessible via GET /api/packages.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    { key: "Accept", value: "application/json", description: "Expects JSON response format." },
                    { key: "Authorization", value: "Bearer {token}", description: "Optional: If provided, marks is_current_plan, can_upgrade flags and returns user's current_plan payload." }
                ],
                pathParams: [],
                queryParams: [
                    { key: "period", type: "string", required: false, value: "monthly", description: "Filter by billing cycle: 'monthly' or 'yearly'." },
                    { key: "search", type: "string", required: false, value: "starter", description: "Search plans by keyword in plan name or description." },
                    { key: "popular", type: "boolean", required: false, value: "1", description: "Filter for popular/featured plans." }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        success: true,
                        data: [
                            {
                                id: 1,
                                packageName: "BASIC",
                                packageDesc: "Standard Starter Plan with 1000 messages",
                                price: "10.00",
                                renewal_price: "8.00",
                                effective_renewal_price: 8,
                                day: 30,
                                period_label: "Monthly",
                                msgCount: 1000,
                                templateCount: 50,
                                contactCount: 1000,
                                gst_percentage: 18,
                                gst_amount: 1.8,
                                total_price: 11.8,
                                renewal_total_price: 9.44,
                                formatted_price: "₹11.80",
                                is_current_plan: false,
                                can_upgrade: true
                            },
                            {
                                id: 2,
                                packageName: "Enterprise Pro Plan",
                                packageDesc: "Complete enterprise communication package",
                                price: "1499.00",
                                renewal_price: "1199.00",
                                effective_renewal_price: 1199,
                                day: 30,
                                period_label: "Monthly",
                                msgCount: 25000,
                                templateCount: 200,
                                contactCount: 25000,
                                gst_percentage: 18,
                                gst_amount: 269.82,
                                total_price: 1768.82,
                                renewal_total_price: 1414.82,
                                formatted_price: "₹1,768.82",
                                is_current_plan: true,
                                can_upgrade: false
                            }
                        ],
                        current_plan: {
                            id: 2,
                            packageName: "Enterprise Pro Plan",
                            daysRemaining: 18,
                            status: "active"
                        }
                    }
                }
            },
            {
                id: "endpoint-plan-buy",
                name: "Buy / Subscribe to Plan",
                method: "POST",
                path: "/api/plan/buy",
                auth: true,
                description: "Purchase / subscribe to a standard subscription plan. Supports online Razorpay payment links or instant one-click activation via available Wallet Balance. Also accessible via /api/plan/subscribe and /api/v1/plan/buy.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "package_id", type: "integer", required: true, value: "2", description: "Target plan / package ID to subscribe. (Alias: packageId)." },
                    { key: "payment_method", type: "string", required: false, value: "razorpay", description: "Payment mode: 'razorpay' (creates payment link) or 'wallet' (instant activation from wallet credits)." },
                    { key: "callback_url", type: "string", required: false, value: "https://zapreach.com/payment/callback", description: "Success callback URL. (Alias: callbackUrl)." },
                    { key: "back_url", type: "string", required: false, value: "https://zapreach.com/billing", description: "Cancel or return URL. (Aliases: backUrl, return_url, cancel_url)." },
                    { key: "billing_state", type: "string", required: false, value: "Gujarat", description: "Client billing state for GST invoice calculation (CGST+SGST vs IGST). (Aliases: billingState, state, customer_state)." }
                ],
                responses: {
                    "200 (Razorpay Mode)": {
                        status: true,
                        success: true,
                        message: "Payment link for plan 'Professional' generated successfully",
                        data: {
                            order_id: "order_RzpSub12345",
                            payment_link_id: "plink_RzpSub12345",
                            short_url: "https://rzp.io/i/Sub123",
                            amount: 2999,
                            total_amount: 3538.82,
                            gst_amount: 539.82
                        }
                    },
                    "200 (Wallet Mode)": {
                        status: true,
                        success: true,
                        message: "Successfully subscribed to plan 'Professional' using wallet balance!",
                        data: {
                            payment_type: "subscription",
                            amount_debited: 3538.82,
                            new_wallet_balance: 1461.18,
                            active_package: {
                                id: 14,
                                packageId: 2,
                                status: 1,
                                day: 30,
                                startDate: "2026-10-08 18:00:00",
                                endDate: "2026-11-07 18:00:00"
                            }
                        }
                    },
                    "400": {
                        status: false,
                        success: false,
                        message: "Insufficient wallet balance. Please recharge wallet or choose Razorpay."
                    }
                }
            },
            {
                id: "endpoint-plan-upgrade-customer",
                name: "Upgrade Subscription Plan",
                method: "POST",
                path: "/api/plan/upgrade",
                auth: true,
                description: "Upgrade logged-in user's active subscription to a higher tier plan. Supports Razorpay payment link generation or instant payment via user's Wallet balance. All remaining unused message credits from old plan are preserved and rolled over! Also accessible via /api/v1/plan/upgrade.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "package_id", type: "integer", required: true, value: "3", description: "Target higher plan / package ID to upgrade to. (Alias: packageId)." },
                    { key: "payment_method", type: "string", required: false, value: "razorpay", description: "Payment mode: 'razorpay' or 'wallet'." },
                    { key: "callback_url", type: "string", required: false, value: "https://zapreach.com/payment/callback", description: "Success callback URL. (Alias: callbackUrl)." },
                    { key: "back_url", type: "string", required: false, value: "https://zapreach.com/billing", description: "Cancel or return URL. (Aliases: backUrl, return_url, cancel_url)." },
                    { key: "billing_state", type: "string", required: false, value: "Gujarat", description: "Client billing state for GST tax calculation. (Aliases: billingState, state, customer_state)." }
                ],
                responses: {
                    "200 (Razorpay Mode)": {
                        status: true,
                        success: true,
                        message: "Payment link for plan 'Enterprise' generated successfully",
                        data: {
                            order_id: "order_Upg12345",
                            payment_link_id: "plink_Upg12345",
                            short_url: "https://rzp.io/i/Upg123",
                            amount: 5999,
                            total_amount: 7078.82,
                            gst_amount: 1079.82
                        }
                    },
                    "200 (Wallet Mode)": {
                        status: true,
                        success: true,
                        message: "Successfully upgraded to plan 'Enterprise' using wallet balance!",
                        data: {
                            payment_type: "upgrade",
                            amount_debited: 7078.82,
                            new_wallet_balance: 1231.18,
                            active_package: {
                                id: 14,
                                packageId: 3,
                                status: 1,
                                day: 30
                            }
                        }
                    },
                    "422": {
                        status: false,
                        success: false,
                        message: "You are already subscribed to the 'Enterprise' plan. Use renewal if you wish to extend it."
                    }
                }
            },
            {
                id: "endpoint-plan-verify-unified",
                name: "Verify Plan Payment (Unified: Buy, Upgrade, Renewal)",
                method: "POST",
                path: "/api/plan/verify",
                auth: true,
                description: "Unified endpoint to verify Razorpay payments and complete activation for any plan transaction (Buy, Upgrade, or Renewal). Validates payment signature, activates the package, and logs the transaction. Also accessible via POST /api/plan/verify-renewal.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Accept", value: "application/json", description: "Expects JSON response format." },
                    { key: "Authorization", value: "Bearer {token}", description: "Required user Bearer token." }
                ],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "payment_id", type: "string", required: true, value: "pay_Nx8Z98765Qwert", description: "Razorpay Payment ID (also accepts razorpay_payment_id)." },
                    { key: "order_id", type: "string", required: false, value: "plink_AbCdEfGhIjKlMn", description: "Payment Link ID or Order ID (also accepts razorpay_order_id, razorpay_payment_link_id, or receipt)." },
                    { key: "signature", type: "string", required: false, value: "9ef8a1768c35b80a7864...", description: "Optional HMAC-SHA256 signature calculated by Razorpay." }
                ],
                responses: {
                    "200": {
                        status: true,
                        success: true,
                        message: "Subscription plan activated successfully!",
                        data: {
                            success: true,
                            already_credited: false,
                            package_activated: true,
                            amount: 1499,
                            total_amount: 1768.82,
                            payment_id: "pay_Nx8Z98765Qwert",
                            package_name: "Enterprise Pro Plan"
                        }
                    },
                    "400": {
                        status: false,
                        success: false,
                        message: "Payment verification failed: Payment signature verification failed."
                    }
                }
            },
            {
                id: "endpoint-plan-renewal-status",
                name: "Get Active Plan & Renewal Status (Core React Endpoint)",
                method: "GET",
                path: "/api/plan/renewal-status",
                auth: true,
                description: "Primary endpoint for React frontends to fetch current active plan limits, monthly message quota breakdown, remaining days, renewal status, and price breakdown. React developers should use this to power the Plan Dashboard, quota progress bars, and renewal banners. Also accessible via GET /api/v1/plan/renewal-status.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    { key: "Accept", value: "application/json", description: "Expects JSON response format." },
                    { key: "Authorization", value: "Bearer {token}", description: "Authenticated client Bearer token." }
                ],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200 (Yearly Plan with Monthly Quotas)": {
                        status: true,
                        success: true,
                        data: {
                            needs_renewal: false,
                            needsRenewal: false,
                            show_renewal_popup: false,
                            is_expired: false,
                            days_remaining: 369,
                            daysUntilExpiry: 369,
                            renewal_price: "2400.00",
                            effective_renewal_price: 2400,
                            renewal_price_breakdown: {
                                base_price: 2400,
                                gst_percentage: 18,
                                gst_amount: 432,
                                total_amount: 2832,
                                formatted_total: "₹2,832.00"
                            },
                            package: {
                                id: 14,
                                packageId: 4,
                                packageName: "Gold Yearly Plan",
                                is_yearly: true,
                                is_monthly: false,
                                is_custom: false,
                                billing_cycle: "yearly",
                                billing_period: "yearly",
                                period: "/year",
                                period_label: "Yearly",
                                duration_text: "1 Year",
                                messageLimit: 200,
                                monthly_quota: 200,
                                monthly_used_messages: 45,
                                monthly_remaining_messages: 165,
                                all_time_used_messages: 245,
                                remainingMessages: 165,
                                contactLimit: 120,
                                templateLimit: 15,
                                startDate: "2026-10-19T14:00:00+05:30",
                                endDate: "2027-10-23T14:00:00+05:30",
                                next_monthly_reset: "2026-11-18T14:00:00+05:30",
                                formatted_renewal_date: "23 Oct, 2027"
                            },
                            usage: {
                                monthlyUsedMessages: 45,
                                monthlyUsedTemplates: 3,
                                monthlyUsedContacts: 12,
                                usedContacts: 78,
                                remainingContacts: 42,
                                totalUsedMessages: 245,
                                yearlyUsedMessages: 245
                            },
                            limits: {
                                messageLimit: 200,
                                templateLimit: 15,
                                contactLimit: 120
                            }
                        }
                    },
                    "200 (Custom Monthly Plan)": {
                        status: true,
                        success: true,
                        data: {
                            needs_renewal: false,
                            days_remaining: 26,
                            package: {
                                packageName: "Custom Growth Plan",
                                is_yearly: false,
                                is_monthly: true,
                                is_custom: true,
                                billing_cycle: "monthly",
                                messageLimit: 200,
                                remainingMessages: 125,
                                contactLimit: 200,
                                templateLimit: 10,
                                custom_price: 80
                            },
                            usage: {
                                monthlyUsedMessages: 75,
                                totalUsedMessages: 75
                            }
                        }
                    }
                }
            },
            {
                id: "endpoint-plan-renew-customer",
                name: "Renew Subscription Plan (Early or Post-Expiry)",
                method: "POST",
                path: "/api/plan/renew",
                auth: true,
                description: "Renew the user's active subscription plan. Seamlessly supports both Razorpay online checkout and instant Wallet debit. Crucial Features: (1) Remaining days are NEVER lost - when renewing 4 days early, validity extends by 30 or 365 days from current expiry (30+4=34 days or 365+4=369 days). (2) Remaining unused messages roll over into Month 1. (3) Custom plan limits (custom_msg_count, custom_price) are 100% locked and preserved. Also accessible via POST /api/plan/buy with payment_type: 'renewal'.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Accept", value: "application/json", description: "Expects JSON response format." },
                    { key: "Authorization", value: "Bearer {token}", description: "Client Bearer token." },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "package_id", type: "integer", required: false, value: "1", description: "Package ID to renew (defaults to current package if omitted). (Alias: packageId)." },
                    { key: "payment_method", type: "string", required: false, value: "wallet", description: "Payment mode: 'wallet' (instant activation) or 'razorpay' (generates payment link)." },
                    { key: "callback_url", type: "string", required: false, value: "https://zapreach.com/payment/callback", description: "Razorpay success redirect URL." },
                    { key: "billing_state", type: "string", required: false, value: "Gujarat", description: "State for GST tax invoice generation (CGST+SGST vs IGST)." }
                ],
                responses: {
                    "200 (Instant Wallet Renewal)": {
                        status: true,
                        success: true,
                        message: "Plan renewed successfully using wallet balance!",
                        data: {
                            payment_type: "renewal",
                            amount_debited: 944,
                            new_wallet_balance: 1056,
                            active_package: {
                                id: 14,
                                status: 1,
                                day: 34,
                                startDate: "2026-10-19T14:00:00+05:30",
                                endDate: "2026-11-22T14:00:00+05:30",
                                notes: "Plan renewed with +10 unused messages, +4 remaining days carried forward"
                            }
                        }
                    },
                    "200 (Razorpay Payment Link)": {
                        status: true,
                        success: true,
                        message: "Payment link for plan renewal generated successfully",
                        data: {
                            order_id: "order_Ren12345",
                            short_url: "https://rzp.io/i/Ren123",
                            amount: 800,
                            total_amount: 944,
                            gst_amount: 144
                        }
                    }
                }
            },
            {
                id: "endpoint-plan-history",
                name: "Get Plan History & Completed Monthly Cycles",
                method: "GET",
                path: "/api/plan/history/{userId?}",
                auth: true,
                description: "Retrieve comprehensive audit history logs for the user's subscription plans. Includes records for initial subscriptions, plan upgrades (with rollover notes), renewals, and automated completed monthly cycles ('monthly_cycle_reset') showing exact messages consumed per month. Also accessible via GET /api/active-package/history.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    { key: "Accept", value: "application/json" },
                    { key: "Authorization", value: "Bearer {token}" }
                ],
                pathParams: [
                    { key: "userId", type: "integer", required: false, value: "10", description: "Optional user ID for Super Admins. Defaults to authenticated client." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        success: true,
                        data: [
                            {
                                id: 52,
                                action: "monthly_cycle_reset",
                                packageName: "Basic Yearly Plan",
                                msgCount: 100,
                                startDate: "2026-09-19T14:00:00+05:30",
                                endDate: "2026-10-19T14:00:00+05:30",
                                day: 30,
                                notes: "Monthly cycle completed: 92 of 100 messages sent. Monthly quota refreshed for the new 30-day cycle.",
                                createdAt: "2026-10-19T14:00:00+05:30"
                            },
                            {
                                id: 48,
                                action: "upgraded",
                                packageName: "Basic Yearly Plan",
                                price: 1200,
                                msgCount: 110,
                                startDate: "2026-09-19T14:00:00+05:30",
                                endDate: "2027-09-23T14:00:00+05:30",
                                day: 369,
                                notes: "Plan upgraded with +10 unused messages, +4 remaining days carried forward from previous plan",
                                createdAt: "2026-09-19T14:00:00+05:30"
                            }
                        ]
                    }
                }
            },
            {
                id: "endpoint-plan-user",
                name: "Get User Active Plan Details",
                method: "GET",
                path: "/api/plan/user/{userId?}",
                auth: true,
                description: "Get detailed active plan information, date validity, remaining quotas, and monthly usage counters. Supports optional userId parameter for Super Admins.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    { key: "Accept", value: "application/json" },
                    { key: "Authorization", value: "Bearer {token}" }
                ],
                pathParams: [
                    { key: "userId", type: "integer", required: false, value: "10", description: "Optional user ID (Admin only). Defaults to current user." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        plan: {
                            packageName: "Gold Yearly Plan",
                            startDate: "2026-10-19T14:00:00+05:30",
                            endDate: "2027-10-23T14:00:00+05:30",
                            is_yearly: true,
                            is_monthly: false,
                            billing_cycle: "yearly",
                            messageLimit: 200,
                            monthly_quota: 200,
                            usedMsgCount: 45,
                            monthlyUsedMsgCount: 45,
                            remainingMsgCount: 165,
                            monthly_remaining_messages: 165,
                            allTimeUsedMsgCount: 45,
                            contactLimit: 120,
                            templateLimit: 15,
                            daysRemaining: 369,
                            next_monthly_reset: "2026-11-18T14:00:00+05:30"
                        }
                    }
                }
            },
            {
                id: "endpoint-plan-assign",
                name: "Assign Plan to User (Admin Only)",
                method: "POST",
                path: "/api/plan/assign",
                auth: true,
                description: "Super Admin endpoint to directly assign a standard or custom package subscription to any user without payment. Allows custom overrides for msgCount, contactCount, templateCount, and price.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Accept", value: "application/json" },
                    { key: "Authorization", value: "Bearer {admin_token}" }
                ],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "userId", type: "integer", required: true, value: "10", description: "Target User ID." },
                    { key: "packageId", type: "integer", required: true, value: "1", description: "Package ID to assign." },
                    { key: "days", type: "integer", required: false, value: "365", description: "Custom validity duration in days." },
                    { key: "custom_msg_count", type: "integer", required: false, value: "200", description: "Override message limit (e.g. 200 for Custom Plan)." },
                    { key: "custom_contact_count", type: "integer", required: false, value: "200", description: "Override contact limit." },
                    { key: "custom_template_count", type: "integer", required: false, value: "10", description: "Override template limit." },
                    { key: "custom_price", type: "number", required: false, value: "960", description: "Override price in INR." }
                ],
                responses: {
                    "200": { status: true, message: "Package assigned successfully" }
                }
            },
            {
                id: "endpoint-react-plan-guide",
                name: "React Developer Integration Guide & TypeScript Specs",
                method: "GET",
                path: "/api/docs/react-plan-guide",
                auth: false,
                description: "Complete front-end implementation guide for React developers. Explains how to handle Monthly vs Yearly plans, how to render quota progress bars, and copy-pasteable TypeScript interfaces.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "TypeScript Interfaces": {
                        "// 1. Core Plan Payload Interface": {
                            "interface ActivePlanPayload": {
                                "id": "number",
                                "packageName": "string",
                                "is_yearly": "boolean // TRUE for Yearly plans, FALSE for Monthly",
                                "is_monthly": "boolean",
                                "is_custom": "boolean // TRUE if custom limits/prices applied",
                                "billing_cycle": "'monthly' | 'yearly'",
                                "duration_text": "string // e.g. '1 Year' or 'Per Month'",
                                "monthly_quota": "number // e.g. 100 or 200 msgs/month",
                                "monthly_used_messages": "number // Messages sent in CURRENT 30-day cycle",
                                "monthly_remaining_messages": "number // Fresh quota left this month",
                                "all_time_used_messages": "number // Cumulative messages across entire year",
                                "contactLimit": "number",
                                "templateLimit": "number",
                                "startDate": "string (ISO)",
                                "endDate": "string (ISO)",
                                "next_monthly_reset": "string | null (ISO timestamp of next 30-day reset)",
                                "days_remaining": "number",
                                "needs_renewal": "boolean"
                            }
                        },
                        "// 2. React UI Rendering Rule": {
                            "Monthly Plan": "Display: {monthly_remaining_messages} of {monthly_quota} messages remaining. Plan expires in {days_remaining} days.",
                            "Yearly Plan": "Display: {monthly_remaining_messages} of {monthly_quota} monthly messages left (Resets on {next_monthly_reset}). Total year sent: {all_time_used_messages}. Subscription valid for {days_remaining} days.",
                            "Custom Plan": "Display a 'Custom Plan' badge. All custom quotas (custom_msg_count, etc.) are already resolved in monthly_quota and messageLimit."
                        },
                        "// 3. React Upgrade / Renewal Action Handler": {
                            "Upgrade Handler": "await axios.post('/api/plan/upgrade', { package_id: targetId, payment_method: 'wallet' }) -> Unused days (+4) and unused msgs (+10) automatically roll over!",
                            "Renew Handler": "await axios.post('/api/plan/renew', { payment_method: 'wallet' }) -> Validity extends by 30 or 365 days from current expiry. Unused msgs carry forward."
                        }
                    }
                }
            }
        ]
    },
    {
        name: "Media Library",
        icon: "fas fa-photo-video",
        description: "Upload, store, and manage promotional media assets, PDF flyers, videos, and images used in broadcasts.",
        endpoints: [
            {
                id: "endpoint-media-list",
                name: "List Media Files",
                method: "GET",
                path: "/api/media",
                auth: true,
                description: "Get all media assets uploaded by the user with full public URLs and file size info.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        data: [{ id: 14, title: "Festive Banner 2026", url: "https://zapreach.com/uploads/media/banner_diwali.png", file_type: "image/png", file_size: "245 KB" }]
                    }
                }
            },
            {
                id: "endpoint-media-upload",
                name: "Upload Media File",
                method: "POST",
                path: "/api/media",
                auth: true,
                description: "Upload image, video, or document asset to the cloud media repository. Specifically validated for WhatsApp Cloud API templates: Images (JPG, JPEG, PNG max 5MB), Videos (MP4, 3GP max 16MB), Documents (PDF max 50MB). WebP, GIF, SVG, AVI, MOV are not supported for WhatsApp templates and will be rejected with a clear message.",
                contentType: "multipart/form-data",
                bodyMode: "formdata",
                headers: [
                    { key: "Accept", value: "application/json", description: "Expects JSON response format." },
                    { key: "Authorization", value: "Bearer {token}", description: "Client/User Bearer Token." }
                ],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "file", type: "file", required: true, value: "banner.jpg", description: "Media file binary. Supported for WhatsApp templates: JPG, JPEG, PNG (max 5MB), MP4, 3GP (max 16MB), PDF (max 50MB)." },
                    { key: "description", type: "string", required: false, value: "Campaign header image for festive sale", description: "Optional notes/description (max: 1000 characters)." },
                    { key: "alt_text", type: "string", required: false, value: "Festival Sale 20% OFF", description: "Optional alt text (max: 255 characters)." }
                ],
                responses: {
                    "201": { status: true, message: "Media uploaded successfully", data: { id: 15, name: "banner.jpg", url: "https://zapreach.com/uploads/media/1744877777_uuid.jpg", mime_type: "image/jpeg", size: 245000 } },
                    "422": { status: false, message: "Validation failed", errors: { file: ["WebP images (.webp) are not supported by WhatsApp Cloud API templates. Please upload JPG or PNG images."] } }
                }
            },
            {
                id: "endpoint-media-stats",
                name: "Get Media Storage Stats",
                method: "GET",
                path: "/api/media/stats",
                auth: true,
                description: "Get user media repository statistics including total files uploaded and total storage size consumed.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    { key: "Accept", value: "application/json", description: "Expects JSON response format." },
                    { key: "Authorization", value: "Bearer {token}", description: "Client/User Bearer Token." }
                ],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        data: { total_files: 24, total_size_mb: 48.5, image_count: 18, video_count: 2, document_count: 4 }
                    }
                }
            },
            {
                id: "endpoint-media-delete",
                name: "Delete Media File",
                method: "DELETE",
                path: "/api/media/{id}",
                auth: true,
                description: "Permanently delete media file from storage.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "14", description: "Media ID." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": { status: true, message: "Media deleted successfully" }
                }
            }
        ]
    },
    {
        name: "Reports & Dashboard Analytics",
        icon: "fas fa-chart-line",
        description: "Comprehensive analytics, message delivery metrics, real-time campaign delivery checking, credit consumption, and executive dashboard KPIs.",
        endpoints: [
            {
                id: "endpoint-dashboard-overview",
                name: "Get Dashboard KPI Summary",
                method: "GET",
                path: "/api/dashboard",
                auth: true,
                description: "Get executive high-level counts: total contacts, total campaigns sent, messages delivered today, and unread chat threads.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        data: { total_contacts: 1420, total_campaigns: 45, total_messages_sent: 28500, active_chats: 18, wallet_balance: 1500.50 }
                    }
                }
            },
            {
                id: "endpoint-reports-messages",
                name: "Get Detailed Message Reports",
                method: "GET",
                path: "/api/reports",
                auth: true,
                description: "Filter and download historical message delivery logs by date range, status, or specific bulk campaign ID. When bulk_id is provided, returns per-contact delivery records with detailed delivery reasons.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    { key: "Accept", value: "application/json", description: "Expects JSON response format." },
                    { key: "Authorization", value: "Bearer {token}", description: "Client/User Bearer Token." }
                ],
                pathParams: [],
                queryParams: [
                    { key: "bulk_id", type: "integer", required: false, value: "149", description: "Filter by specific Bulk Campaign ID to get per-recipient delivery statuses and reasons." },
                    { key: "date_from", type: "date string", required: false, value: "2026-08-01", description: "Start date (YYYY-MM-DD)." },
                    { key: "date_to", type: "date string", required: false, value: "2026-08-31", description: "End date (YYYY-MM-DD)." },
                    { key: "status", type: "string", required: false, value: "all", description: "Filter: \"all\", \"completed\", \"processing\", \"failed\"." },
                    { key: "search", type: "string", required: false, value: "", description: "Search by campaign or template name." },
                    { key: "page", type: "integer", required: false, value: "1", description: "Pagination page number." },
                    { key: "per_page", type: "integer", required: false, value: "15", description: "Number of records per page." }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        summary: { totalMessages: 1, sent: 1, delivered: 0, read: 0, failed: 0, completed: 1 },
                        messages: [
                            {
                                bulk_id: 149,
                                campaignName: "Festival Offer 2026",
                                status: "completed",
                                contactDetails: [
                                    {
                                        id: 56915,
                                        name: "Arun Kumar",
                                        phone: "918866761752",
                                        delivery_status: {
                                            status: "sent",
                                            status_label: "Sent",
                                            delivery_reason: "Accepted by Meta WhatsApp Cloud and queued for handset delivery. Waiting for recipient device to connect to WhatsApp.",
                                            reason: "Accepted by Meta WhatsApp Cloud and queued for handset delivery. Waiting for recipient device to connect to WhatsApp.",
                                            status_description: "Accepted by Meta WhatsApp Cloud and queued for handset delivery. Waiting for recipient device to connect to WhatsApp.",
                                            whatsapp_message_id: "wamid.HBgMOTE4...",
                                            sent_at: "2026-10-16T09:38:03.000000Z",
                                            delivered_at: null
                                        }
                                    }
                                ]
                            }
                        ]
                    }
                }
            },
            {
                id: "endpoint-reports-campaigns-list",
                name: "List Reporting Campaigns",
                method: "GET",
                path: "/api/campaigns",
                auth: true,
                description: "List campaigns with delivery summaries for reporting and analytics visualization.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    { key: "Accept", value: "application/json", description: "Expects JSON response format." },
                    { key: "Authorization", value: "Bearer {token}", description: "Client/User Bearer Token." }
                ],
                pathParams: [],
                queryParams: [
                    { key: "date_from", type: "date string", required: false, value: "2026-01-01", description: "Start date (YYYY-MM-DD)." },
                    { key: "date_to", type: "date string", required: false, value: "2026-12-31", description: "End date (YYYY-MM-DD)." }
                ],
                bodyParams: [],
                responses: {
                    "200": { success: true, data: [{ id: 149, name: "Festival Offer 2026", status: "completed", total: 10, sent: 10, delivered: 8, read: 4 }] }
                }
            },
            {
                id: "endpoint-reports-campaign-delivery-status",
                name: "Get Campaign Delivery Status & Webhook Health",
                method: "GET",
                path: "/api/reports/campaign/{id}/delivery-status",
                auth: true,
                description: "Retrieve complete delivery logs, recipient counts, and webhook connectivity health for a specific campaign.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    { key: "Accept", value: "application/json", description: "Expects JSON response format." },
                    { key: "Authorization", value: "Bearer {token}", description: "Client/User Bearer Token." }
                ],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "149", description: "Bulk Campaign ID." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        data: {
                            campaign: { id: 149, name: "Festival Offer 2026", status: "completed" },
                            delivery_statuses: [{ id: 263, status: "sent", whatsapp_message_id: "wamid.HBgM...", delivery_reason: "Accepted by Meta WhatsApp Cloud..." }],
                            summary: { pending: 0, processing: 0, sent: 1, delivered: 0, read: 0, failed: 0 },
                            webhook_info: { has_webhook_updates: false, user_webhook_url: "https://zapreach.com/api/whatsapp/webhook/31", status_note: "Messages sent to WhatsApp Cloud..." }
                        }
                    }
                }
            },
            {
                id: "endpoint-reports-check-delivery",
                name: "Real-Time Campaign Delivery Check & Webhook Sync",
                method: "GET",
                path: "/api/reports/campaign/{id}/check-delivery",
                auth: true,
                description: "Live check and sync of message delivery status for a specific bulk campaign ID. Automatically verifies/re-subscribes Meta WABA webhook subscriptions and returns per-recipient delivery statuses with detailed reasons (explaining why handset delivery is pending, completed, or failed).",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    { key: "Accept", value: "application/json", description: "Expects JSON response format." },
                    { key: "Authorization", value: "Bearer {token}", description: "Client/User Bearer Token." }
                ],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "149", description: "Bulk Campaign ID to check delivery for." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        campaign: {
                            bulk_id: 149,
                            name: "Festival Offer 2026",
                            status: "completed",
                            created_at: "2026-10-16T09:37:09.000000Z",
                            template: { id: 100, name: "join_us_with_poster" }
                        },
                        summary: {
                            total: 10,
                            sent: 2,
                            delivered: 6,
                            read: 2,
                            failed: 0,
                            delivered_percentage: 80.0,
                            read_percentage: 20.0
                        },
                        recipients: [
                            {
                                contact_id: 56915,
                                name: "Arun Kumar",
                                phone: "918866761752",
                                whatsapp_message_id: "wamid.HBgMOTE4ODY2NzYxNzUyFQIAERgSRjdFQzM3MUNENTk2Q0NBQjREAA==",
                                status: "sent",
                                status_label: "Sent",
                                status_badge_color: "success",
                                reason: "Accepted by Meta WhatsApp Cloud, but handset delivery is pending. Possible reasons: recipient device is switched off/offline, recipient has blocked this business number, or Meta marketing pacing is delaying delivery.",
                                sent_at: "2026-10-16T09:38:03.000000Z",
                                delivered_at: null,
                                read_at: null,
                                minutes_since_sent: 25,
                                error: null
                            }
                        ],
                        meta_sync_info: {
                            waba_subscribed: true,
                            waba_message: "WhatsApp app subscribed to WABA successfully",
                            webhook_url: "https://zapreach.com/api/whatsapp/webhook/31",
                            meta_api_note: "Meta WhatsApp Cloud API delivers status updates (delivered, read, failed) strictly via real-time Webhooks."
                        }
                    },
                    "404": {
                        success: false,
                        message: "Campaign with ID 149 not found or you do not have permission to view it."
                    }
                }
            },
            {
                id: "endpoint-campaign-delivery-check-alias",
                name: "Campaign Delivery Check (Short Route Alias)",
                method: "GET",
                path: "/api/campaign/{id}/delivery-check",
                auth: true,
                description: "Convenience short alias for /api/reports/campaign/{id}/check-delivery. Live delivery status and webhook verification for a campaign.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    { key: "Accept", value: "application/json", description: "Expects JSON response format." },
                    { key: "Authorization", value: "Bearer {token}", description: "Client/User Bearer Token." }
                ],
                pathParams: [
                    { key: "id", type: "integer", required: true, value: "149", description: "Bulk Campaign ID." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": { success: true, summary: { total: 1, sent: 1, delivered: 0, read: 0, failed: 0 } }
                }
            }
        ]
    },
    {
        name: "Notifications",
        icon: "fas fa-bell",
        description: "System alerts, plan expiry warnings, low balance notifications, and bulk campaign completion events.",
        endpoints: [
            {
                id: "endpoint-notifications-list",
                name: "List Notifications",
                method: "GET",
                path: "/api/notification",
                auth: true,
                description: "Get all user in-app notifications with read/unread status.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        data: [{ id: 1, title: "Campaign Completed", message: "Campaign \"Diwali Mega Sale\" completed with 96.8% delivery.", type: "success", read: false }]
                    }
                }
            },
            {
                id: "endpoint-notifications-mark-read",
                name: "Mark Notification as Read",
                method: "POST",
                path: "/api/notification/mark-read",
                auth: true,
                description: "Mark specific notification as read.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "notificationId", type: "integer", required: true, value: "1", description: "Notification ID." }
                ],
                responses: {
                    "200": { status: true, message: "Notification marked as read" }
                }
            },
            {
                id: "endpoint-notifications-mark-all-read",
                name: "Mark All Notifications as Read",
                method: "POST",
                path: "/api/notification/mark-all-read",
                auth: true,
                description: "Mark all unread notifications as read at once.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": { status: true, message: "All notifications marked as read" }
                }
            }
        ]
    },
    {
        name: "WhatsApp Webhooks",
        icon: "fas fa-satellite-dish",
        description: "Meta webhook receiver for inbound customer messages, delivery receipts (sent/delivered/read/failed), and status webhooks.",
        endpoints: [
            {
                id: "endpoint-webhook-global",
                name: "Global WhatsApp Webhook Endpoint",
                method: "POST",
                path: "/api/whatsapp/webhook",
                auth: false,
                description: "Receives live webhook events directly from Meta Graph API servers (supports GET for token verification & POST for inbound event payloads).",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [
                    { key: "hub.mode", type: "string", required: false, value: "subscribe", description: "Verification mode (used on Meta GET challenge)." },
                    { key: "hub.challenge", type: "string", required: false, value: "1158201444", description: "Meta challenge string to return." },
                    { key: "hub.verify_token", type: "string", required: false, value: "zapreach_secret_webhook_verify", description: "Verify token configured in Meta App." }
                ],
                bodyParams: [
                    { key: "entry", type: "array", required: true, value: "[{\"id\":\"1322751702857894\",\"changes\":[{\"value\":{\"messages\":[{\"from\":\"919173129119\",\"text\":{\"body\":\"Hello\"}}]}}]}]", description: "Standard Meta Webhook payload object." }
                ],
                responses: {
                    "200": { status: "EVENT_RECEIVED" }
                }
            },
            {
                id: "endpoint-webhook-user-wise",
                name: "User-wise Dedicated Webhook",
                method: "POST",
                path: "/api/whatsapp/webhook/{user_id}",
                auth: false,
                description: "Dedicated per-user webhook endpoint isolating incoming chats directly to the owner account.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [
                    { key: "user_id", type: "integer", required: true, value: "10", description: "Unique User / Account ID." }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "entry", type: "array", required: true, value: "[{\"changes\":[{\"value\":{\"messages\":[{\"from\":\"919173129119\",\"text\":{\"body\":\"Hi\"}}]}}]}]", description: "Meta Webhook payload." }
                ],
                responses: {
                    "200": { status: "EVENT_RECEIVED" }
                }
            }
        ]
    },
    {
        name: "App Live Status & Maintenance",
        icon: "fas fa-power-off",
        description: "Application live status check and toggle controls (is_app_live boolean flag for maintenance/active mode).",
        endpoints: [
            {
                id: "endpoint-app-live-get",
                name: "Get App Live Status",
                method: "GET",
                path: "/api/is-app-live",
                auth: false,
                description: "Retrieve the current boolean live status of the application (is_app_live). Returns true if the app is online/active, or false if in maintenance mode.",
                contentType: "application/json",
                bodyMode: "none",
                headers: [
                    { key: "Accept", value: "application/json", description: "Expects JSON response format." }
                ],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        message: "App live status retrieved successfully",
                        is_app_live: true,
                        data: {
                            is_app_live: true
                        }
                    }
                }
            },
            {
                id: "endpoint-app-live-toggle",
                name: "Toggle / Set App Live Status (On/Off)",
                method: "POST",
                path: "/api/is-app-live/toggle",
                auth: false,
                description: "Toggle or explicitly update the application's is_app_live boolean state. Omit request body to auto-toggle current status (true ⇄ false), or pass an explicit boolean in the request body.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Content-Type", value: "application/json", description: "JSON payload format." },
                    { key: "Accept", value: "application/json", description: "Expects JSON response format." }
                ],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "is_app_live", type: "boolean", required: false, value: "false", description: "Optional boolean (true/false) to set status explicitly. If omitted, inverts the current state automatically." }
                ],
                responses: {
                    "200": {
                        status: true,
                        message: "App live status updated successfully",
                        is_app_live: false,
                        data: {
                            is_app_live: false
                        }
                    }
                }
            }
        ]
    },
    {
        name: "Legal & Compliance",
        icon: "fas fa-shield-alt",
        description: "Official public legal endpoints: Privacy Policy, Terms & Conditions, and Legal Overview.",
        endpoints: [
            {
                id: "endpoint-legal-privacy",
                name: "Get Privacy Policy",
                method: "GET",
                path: "/api/privacy-policy",
                auth: false,
                description: "Retrieve complete structured Privacy Policy including data collection policies, WhatsApp Cloud API compliance, contact list protection, Razorpay billing details, and data retention rights.",
                contentType: "application/json",
                bodyMode: "none",
                headers: [
                    { key: "Accept", value: "application/json", description: "Expects JSON response format." }
                ],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        message: "Privacy policy retrieved successfully",
                        data: {
                            title: "Privacy Policy – ZapReach",
                            config: {
                                app_name: "ZapReach",
                                company_name: "ZapReach Technologies",
                                website_url: "https://zapreach.com",
                                support_email: "support@zapreach.com",
                                last_updated: "September 02, 2026",
                                version: "1.0.0"
                            },
                            sections: [
                                { id: "intro", title: "1. Introduction & Scope", content: "..." }
                            ],
                            html_url: "https://zapreach.com/privacy-policy"
                        }
                    }
                }
            },
            {
                id: "endpoint-legal-terms",
                name: "Get Terms and Conditions",
                method: "GET",
                path: "/api/terms-and-conditions",
                auth: false,
                description: "Retrieve complete structured Terms & Conditions covering acceptable use, strict anti-spam policy, WhatsApp Meta guidelines, wallet credits & non-refundable billing rules, Auto-AI usage, and liability disclaimers.",
                contentType: "application/json",
                bodyMode: "none",
                headers: [
                    { key: "Accept", value: "application/json", description: "Expects JSON response format." }
                ],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        message: "Terms and conditions retrieved successfully",
                        data: {
                            title: "Terms & Conditions – ZapReach",
                            config: {
                                app_name: "ZapReach",
                                company_name: "ZapReach Technologies",
                                website_url: "https://zapreach.com",
                                support_email: "support@zapreach.com",
                                last_updated: "September 02, 2026",
                                version: "1.0.0"
                            },
                            sections: [
                                { id: "acceptance", title: "1. Acceptance of Terms", content: "..." }
                            ],
                            html_url: "https://zapreach.com/terms-and-conditions"
                        }
                    }
                }
            },
            {
                id: "endpoint-legal-delete-account-info",
                name: "Get Delete Account & Data Deletion Info",
                method: "GET",
                path: "/api/delete-account",
                auth: false,
                description: "Retrieve comprehensive instructions and policy guidelines regarding permanent account deactivation and data purging.",
                contentType: "application/json",
                bodyMode: "none",
                headers: [
                    { key: "Accept", value: "application/json", description: "Expects JSON response format." }
                ],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        message: "Account deletion instructions retrieved successfully",
                        data: {
                            title: "Delete Account & Data Deletion – ZapReach",
                            config: {
                                app_name: "ZapReach",
                                company_name: "ZapReach Technologies",
                                website_url: "https://zapreach.com",
                                support_email: "support@zapreach.com"
                            },
                            html_url: "https://zapreach.com/delete-account"
                        }
                    }
                }
            },
            {
                id: "endpoint-legal-delete-account-submit",
                name: "Submit Account Deletion Request",
                method: "POST",
                path: "/api/delete-account-request",
                auth: false,
                description: "Submit a formal request for account termination and permanent data eradication.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Content-Type", value: "application/json", description: "JSON payload format." },
                    { key: "Accept", value: "application/json", description: "Expects JSON response format." }
                ],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "email", type: "string", required: true, value: "user@example.com", description: "Registered account email address." },
                    { key: "mobile", type: "string", required: false, value: "+919876543210", description: "Optional contact phone number." },
                    { key: "reason", type: "string", required: false, value: "Closing account", description: "Reason for account deletion." },
                    { key: "confirm_deletion", type: "boolean", required: true, value: "true", description: "Explicit confirmation of permanent deletion." }
                ],
                responses: {
                    "200": {
                        status: true,
                        message: "Account deletion request submitted successfully. Our team will verify and process your request within 7-14 business days.",
                        data: {
                            reference_id: "DEL-8A7B9C1D2E",
                            email: "user@example.com",
                            status: "Pending Verification",
                            support_contact: "support@zapreach.com",
                            timeline: "Account will be deactivated and data permanently purged within 14 to 30 days."
                        }
                    }
                }
            }
        ]
    },
    {
        name: "WhatsApp Catalog & Commerce",
        icon: "fas fa-store",
        description: "Meta Commerce Catalogs, product inventory management, storefront & cart visibility toggle, and interactive WhatsApp Single/Multi Product messaging.",
        endpoints: [
            {
                id: "endpoint-catalog-diagnostics",
                name: "Token & Permissions Diagnostics",
                method: "GET",
                path: "/api/catalog/diagnostics",
                auth: true,
                description: "Inspect active Meta Access Token, verified permissions (scopes), and API readiness. Validates catalog_management and whatsapp_business_messaging permissions.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        token_diagnostics: {
                            is_valid: true,
                            app_id: "1705027483899172",
                            system_user_id: "61581505132671",
                            application_name: "Ranaji showroom",
                            granted_scopes: [
                                "whatsapp_business_messaging",
                                "whatsapp_business_management",
                                "catalog_management"
                            ]
                        },
                        status: {
                            can_send_products_now: true,
                            can_fetch_catalog_products_api: true
                        }
                    }
                }
            },
            {
                id: "endpoint-catalog-settings-get",
                name: "Get WhatsApp Commerce Settings",
                method: "GET",
                path: "/api/catalog/settings",
                auth: true,
                description: "Fetch the WhatsApp Commerce settings for the authenticated business phone number from Meta Graph API (checks if storefront catalog visibility and shopping cart are enabled).",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        data: {
                            phone_number_id: "104192305987654",
                            is_catalog_visible: true,
                            is_cart_enabled: true,
                            default_catalog_id: "987654321012345"
                        }
                    }
                }
            },
            {
                id: "endpoint-catalog-settings-update",
                name: "Update WhatsApp Commerce Settings",
                method: "POST",
                path: "/api/catalog/settings",
                auth: true,
                description: "Update WhatsApp Commerce settings on Meta to toggle the shopping cart and catalog storefront visibility on/off, or set the default active catalog ID.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "is_catalog_visible", type: "boolean", required: false, value: "true", description: "Enable or disable storefront catalog icon on the WhatsApp profile." },
                    { key: "is_cart_enabled", type: "boolean", required: false, value: "true", description: "Enable or disable the shopping cart feature for customers." },
                    { key: "default_catalog_id", type: "string", required: false, value: "987654321012345", description: "Set the primary Meta Catalog ID to use for this account." }
                ],
                responses: {
                    "200": {
                        success: true,
                        message: "WhatsApp commerce settings updated successfully.",
                        data: {
                            phone_number_id: "104192305987654",
                            is_catalog_visible: true,
                            is_cart_enabled: true,
                            default_catalog_id: "987654321012345"
                        }
                    }
                }
            },
            {
                id: "endpoint-catalog-list",
                name: "List Connected Catalogs",
                method: "GET",
                path: "/api/catalog/list",
                auth: true,
                description: "Discover and list all product catalogs linked to the business WhatsApp Business Account (WABA) from Meta.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        default_catalog_id: "987654321012345",
                        count: 1,
                        data: [
                            {
                                id: "987654321012345",
                                name: "Ranaji Showroom Catalog",
                                vertical: "commerce",
                                product_count: 48,
                                is_default: true
                            }
                        ]
                    }
                }
            },
            {
                id: "endpoint-catalog-create",
                name: "Create New Meta Catalog",
                method: "POST",
                path: "/api/catalog/create",
                auth: true,
                description: "Create a new Meta Commerce Catalog directly attached to your business manager account. If business_id is omitted, it is automatically resolved from your connected WhatsApp Business Account (WABA).",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "name", type: "string", required: true, value: "Diwali Festive Collection 2026", description: "Display name for the new catalog." },
                    { key: "business_id", type: "string", required: false, value: "61581505132671", description: "Meta Business Manager ID (auto-discovered from WABA if omitted)." }
                ],
                responses: {
                    "201": {
                        success: true,
                        message: "Catalog created successfully.",
                        data: {
                            id: "182938475610293",
                            name: "Diwali Festive Collection 2026",
                            vertical: "commerce",
                            business_id: "61581505132671"
                        }
                    }
                }
            },
            {
                id: "endpoint-catalog-select",
                name: "Select Active Default Catalog",
                method: "POST",
                path: "/api/catalog/select",
                auth: true,
                description: "Select and set the active default Meta Catalog ID for this account.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "catalog_id", type: "string", required: true, value: "987654321012345", description: "The Meta Catalog ID to designate as default." }
                ],
                responses: {
                    "200": {
                        success: true,
                        message: "Default catalog selected successfully.",
                        data: { catalog_id: "987654321012345" }
                    }
                }
            },
            {
                id: "endpoint-catalog-details",
                name: "Get Catalog Details",
                method: "GET",
                path: "/api/catalog/details/{catalog_id?}",
                auth: true,
                description: "Get metadata, product counts, and configuration details for a specific catalog.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "catalog_id", type: "string", required: false, value: "987654321012345", description: "Meta Catalog ID (auto-resolved to default if omitted)." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        data: {
                            id: "987654321012345",
                            name: "Ranaji Showroom Catalog",
                            vertical: "commerce",
                            product_count: 48
                        }
                    }
                }
            },
            {
                id: "endpoint-catalog-products-list",
                name: "List & Search Products in Catalog",
                method: "GET",
                path: "/api/catalog/products",
                auth: true,
                description: "Fetch product items from the Meta Catalog with real-time currency formatting, SKU identifiers, stock availability, and search filters. Supports pagination via 'after' cursor, 'limit' up to 100, or 'all=true' to auto-fetch all products across pages in a single call.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [
                    { key: "catalog_id", type: "string", required: false, value: "1818014785872279", description: "Specific catalog ID (auto-resolved if omitted)." },
                    { key: "search", type: "string", required: false, value: "Loafers", description: "Search keyword matching title, description, or SKU." },
                    { key: "retailer_id", type: "string", required: false, value: "SKU-LOAFERS-01", description: "Filter by exact SKU / retailer ID." },
                    { key: "limit", type: "integer | string", required: false, value: "100", description: "Items per page (default: 25, max: 100). Set to 100 to fetch up to 100 products in one call, or pass 'all' to fetch everything." },
                    { key: "all", type: "boolean", required: false, value: "true", description: "Set true to automatically retrieve all products across all pages in a single response." },
                    { key: "after", type: "string", required: false, value: "QVFIUk...", description: "Cursor token for next page (found in pagination.after)." },
                    { key: "before", type: "string", required: false, value: "", description: "Cursor token for previous page (found in pagination.before)." }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        catalog_id: "987654321012345",
                        count: 1,
                        data: [
                            {
                                id: "10192837465",
                                retailer_id: "SKU-LOAFERS-01",
                                name: "लेदर लोफर्स",
                                description: "Handcrafted premium pure leather ethnic loafers.",
                                price: 1200.0,
                                currency: "INR",
                                formatted_price: "₹1,200.00",
                                availability: "in stock",
                                image_url: "https://zapreach.com/uploads/media/loafers.jpg",
                                url: "https://ranaji.com/products/leather-loafers",
                                category: "Apparel & Accessories > Shoes",
                                condition: "new",
                                visibility: "published",
                                review_status: "approved"
                            }
                        ],
                        pagination: { has_next: false, has_previous: false, after: null, before: null }
                    }
                }
            },
            {
                id: "endpoint-catalog-product-show",
                name: "Get Single Product Details",
                method: "GET",
                path: "/api/catalog/products/{identifier}",
                auth: true,
                description: "Retrieve single product details by passing either the merchant SKU (e.g. SKU-LOAFERS-01) or the Meta internal Product ID.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "identifier", type: "string", required: true, value: "SKU-LOAFERS-01", description: "Product merchant SKU or Meta internal product ID." }
                ],
                queryParams: [
                    { key: "catalog_id", type: "string", required: false, value: "987654321012345", description: "Catalog ID." }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        data: {
                            id: "10192837465",
                            retailer_id: "SKU-LOAFERS-01",
                            name: "लेदर लोफर्स",
                            description: "Handcrafted premium pure leather ethnic loafers.",
                            price: 1200.0,
                            currency: "INR",
                            formatted_price: "₹1,200.00",
                            availability: "in stock",
                            image_url: "https://zapreach.com/uploads/media/loafers.jpg",
                            url: "https://ranaji.com/products/leather-loafers",
                            condition: "new",
                            review_status: "approved"
                        }
                    }
                }
            },
            {
                id: "endpoint-catalog-product-store",
                name: "Create Product in Meta Catalog",
                method: "POST",
                path: "/api/catalog/products",
                auth: true,
                description: "Add a new item to the Meta Commerce Catalog. Supports both direct image file uploads ('images' or 'image' via multipart/form-data) and image URL links ('image_url'). When a file is uploaded, the server automatically saves it and generates the required public URL for Meta.",
                contentType: "multipart/form-data",
                bodyMode: "form-data",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "retailer_id", type: "string", required: true, value: "SKU-SHERWANI-02", description: "Unique SKU / Retailer ID (or pass 'content_id')." },
                    { key: "content_id", type: "string", required: false, value: "SKU-SHERWANI-02", description: "Alternative alias for retailer_id / SKU." },
                    { key: "name", type: "string", required: true, value: "Royal Silk Sherwani", description: "Product title." },
                    { key: "price", type: "numeric", required: true, value: "8500.00", description: "Original selling price." },
                    { key: "sale_price", type: "numeric", required: false, value: "7499.00", description: "Discounted promotional sale price (must be less than price)." },
                    { key: "currency", type: "string", required: false, value: "INR", description: "3-letter ISO currency code (default: INR)." },
                    { key: "images", type: "file | string | array", required: false, value: "sherwani.jpg", description: "Product image file upload (multipart/form-data), base64 data string, array of URLs, or single image link. EITHER 'images' OR 'image_url' must be provided." },
                    { key: "image_url", type: "string (url)", required: false, value: "https://zapreach.com/uploads/media/sherwani.jpg", description: "Public direct image link for the product. EITHER 'images' OR 'image_url' must be provided." },
                    { key: "brand", type: "string", required: false, value: "Ranaji Heritage", description: "Brand name." },
                    { key: "fb_product_category", type: "string", required: false, value: "Clothing & Accessories > Clothing > Traditional & Cultural Clothing", description: "Meta Facebook product category taxonomy string." },
                    { key: "description", type: "string", required: false, value: "Designer wedding sherwani with intricate zari embroidery.", description: "Product description." },
                    { key: "availability", type: "string", required: false, value: "in stock", description: "\"in stock\" or \"out of stock\"." },
                    { key: "condition", type: "string", required: false, value: "new", description: "\"new\", \"refurbished\", \"used\", \"used_like_new\", \"used_good\", \"used_fair\"." },
                    { key: "status", type: "string", required: false, value: "show", description: "Visibility status: 'show'/'published' (default) or 'hide'/'staging'." },
                    { key: "product_set_id", type: "string", required: false, value: "182938475610293", description: "Optional product set ID to immediately link item to." },
                    { key: "url", type: "string (url)", required: false, value: "https://ranaji.com/products/royal-sherwani", description: "Website product URL." },
                    { key: "catalog_id", type: "string", required: false, value: "987654321012345", description: "Target catalog ID (defaults to active catalog)." }
                ],
                responses: {
                    "201": {
                        success: true,
                        message: "Product added to Meta Catalog successfully.",
                        product_id: "209384756102938",
                        catalog_id: "987654321012345",
                        retailer_id: "SKU-SHERWANI-02",
                        image_url: "https://zapreach.com/uploads/catalog/prod_1791200000.jpg"
                    }
                }
            },
            {
                id: "endpoint-catalog-products-bulk-template",
                name: "Download Bulk Feed Template",
                method: "GET",
                path: "/api/catalog/products/bulk/template",
                auth: true,
                description: "Download the official Meta Commerce Manager 31-column product feed spreadsheet template (CSV/TSV) or inspect the JSON schema.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [
                    { key: "format", type: "string", required: false, value: "csv", description: "Export format: \"csv\" or \"tsv\" (tab-delimited for Excel/Google Sheets)." },
                    { key: "json", type: "integer", required: false, value: "0", description: "Pass 1 to return column specification in JSON format." }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        message: "Commerce Manager feed template specification.",
                        total_columns: 31,
                        sample_feed_rows: {
                            row_1_annotations: "# Required / # Optional tags",
                            row_2_headers: ["id", "title", "description", "availability", "condition", "link", "image_link", "brand", "price", "..."],
                            row_3_sample_data: ["0", "Blue Facebook T-Shirt (Unisex)", "...", "in stock", "new", "...", "10.00 USD", "..."]
                        }
                    }
                }
            },
            {
                id: "endpoint-catalog-products-bulk",
                name: "Bulk Create Products in Meta Catalog",
                method: "POST",
                path: "/api/catalog/products/bulk",
                auth: true,
                description: "Bulk upload products to Meta Commerce Catalog via CSV/TSV/XLSX file upload, JSON products array, or raw spreadsheet text. Uses Meta items batch API with sequential fallback.",
                contentType: "multipart/form-data",
                bodyMode: "form-data",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "file", type: "file", required: false, value: "catalog_products.csv", description: "Commerce Manager CSV, TSV, or XLSX file (up to 50MB)." },
                    { key: "catalog_id", type: "string", required: false, value: "1818014785872279", description: "Target Meta Catalog ID (defaults to active catalog)." },
                    { key: "products", type: "array", required: false, value: "[{\"id\":\"SKU-001\",\"title\":\"Blue Facebook T-Shirt\",\"price\":\"10.00 USD\"}]", description: "Optional JSON array of products (if not uploading a file)." }
                ],
                responses: {
                    "200": {
                        success: true,
                        message: "Batch product upload submitted successfully to Meta Commerce Catalog.",
                        catalog_id: "1818014785872279",
                        total: 1,
                        created_count: 1,
                        failed_count: 0,
                        method: "meta_items_batch",
                        created_products: [
                            {
                                retailer_id: "SKU-001",
                                name: "Blue Facebook T-Shirt (Unisex)",
                                price: 10,
                                currency: "USD",
                                status: "submitted"
                            }
                        ],
                        errors: []
                    }
                }
            },
            {
                id: "endpoint-catalog-product-update",
                name: "Update Product in Meta Catalog",
                method: "PUT",
                path: "/api/catalog/products/{productId}",
                auth: true,
                description: "Update product attributes (price, stock availability, description, title, or image) on Meta. {productId} can be either the numeric Meta Product ID OR the merchant SKU / retailer_id (e.g. SKU-SHERWANI-02). Images can be updated via file upload ('images') or URL ('image_url').",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [
                    { key: "productId", type: "string", required: true, value: "SKU-SHERWANI-02", description: "Product merchant SKU / retailer_id OR Meta numerical Product ID." }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "name", type: "string", required: false, value: "Royal Silk Sherwani (Festive Edition)", description: "Updated product name." },
                    { key: "price", type: "numeric", required: false, value: "7999.00", description: "Updated regular price." },
                    { key: "sale_price", type: "numeric", required: false, value: "6999.00", description: "Updated discounted sale price." },
                    { key: "brand", type: "string", required: false, value: "Ranaji Heritage", description: "Updated brand name." },
                    { key: "condition", type: "string", required: false, value: "new", description: "\"new\", \"refurbished\", \"used\", \"used_like_new\", \"used_good\", \"used_fair\"." },
                    { key: "fb_product_category", type: "string", required: false, value: "Clothing & Accessories > Clothing > Traditional & Cultural Clothing", description: "Updated Meta product category taxonomy." },
                    { key: "status", type: "string", required: false, value: "show", description: "Visibility status: 'show'/'published' or 'hide'/'staging'." },
                    { key: "availability", type: "string", required: false, value: "in stock", description: "\"in stock\" or \"out of stock\"." },
                    { key: "description", type: "string", required: false, value: "Special Festive Offer: Designer wedding sherwani with zari embroidery.", description: "Updated description." },
                    { key: "images", type: "file | string", required: false, value: "updated_sherwani.jpg", description: "Optional updated image file upload or image URL." },
                    { key: "image_url", type: "string (url)", required: false, value: "https://zapreach.com/uploads/media/sherwani.jpg", description: "Optional updated direct image link." }
                ],
                responses: {
                    "200": {
                        success: true,
                        message: "Product updated successfully in Meta Catalog.",
                        product_id: "209384756102938",
                        retailer_id: "SKU-SHERWANI-02"
                    }
                }
            },
            {
                id: "endpoint-catalog-product-delete",
                name: "Delete Product from Meta Catalog",
                method: "DELETE",
                path: "/api/catalog/products/{productId}",
                auth: true,
                description: "Permanently remove a product item from the Meta Commerce Catalog.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "productId", type: "string", required: true, value: "209384756102938", description: "Meta Product ID to delete." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        message: "Product deleted successfully from Meta Catalog.",
                        product_id: "209384756102938"
                    }
                }
            },
            {
                id: "endpoint-catalog-categories",
                name: "Search Facebook Product Categories",
                method: "GET",
                path: "/api/catalog/categories",
                auth: true,
                description: "Search and retrieve official Meta Facebook Product Taxonomy categories to use in product creation or filtering.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Accept", value: "application/json" }
                ],
                pathParams: [],
                queryParams: [
                    { key: "search", type: "string", required: false, value: "clothing", description: "Search keyword matching taxonomy categories." },
                    { key: "limit", type: "integer", required: false, value: "20", description: "Number of categories to return (default 20, max 100)." }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        count: 2,
                        categories: [
                            "Clothing & Accessories > Clothing > Traditional & Cultural Clothing",
                            "Clothing & Accessories > Shoes > Loafers & Slip-Ons"
                        ]
                    }
                }
            },
            {
                id: "endpoint-catalog-sets-list",
                name: "List Product Sets",
                method: "GET",
                path: "/api/catalog/sets",
                auth: true,
                description: "Fetch all product sets created under the active Meta Commerce Catalog.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Accept", value: "application/json" }
                ],
                pathParams: [],
                queryParams: [
                    { key: "catalog_id", type: "string", required: false, value: "987654321012345", description: "Catalog ID (defaults to active catalog)." }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        catalog_id: "987654321012345",
                        count: 1,
                        data: [
                            {
                                id: "182938475610293",
                                name: "Festive Ethnic Wear",
                                retailer_id: "SET-ETHNIC-01",
                                product_count: 12,
                                filter: {
                                    retailer_id: {
                                        is_any: ["SKU-SHERWANI-02", "SKU-KURTA-05"]
                                    }
                                }
                            }
                        ]
                    }
                }
            },
            {
                id: "endpoint-catalog-sets-create",
                name: "Create Product Set",
                method: "POST",
                path: "/api/catalog/sets",
                auth: true,
                description: "Create a new product set inside the Meta Commerce Catalog. Initialize it with retailer SKU IDs or custom filter rules.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "name", type: "string", required: true, value: "Festive Ethnic Wear", description: "Name of the product set." },
                    { key: "retailer_ids", type: "array", required: false, value: "[\"SKU-SHERWANI-02\", \"SKU-KURTA-05\"]", description: "Array of retailer SKUs to include in the set." },
                    { key: "catalog_id", type: "string", required: false, value: "987654321012345", description: "Catalog ID (defaults to active catalog)." }
                ],
                responses: {
                    "201": {
                        success: true,
                        message: "Product set created successfully.",
                        data: {
                            id: "182938475610293",
                            name: "Festive Ethnic Wear"
                        }
                    }
                }
            },
            {
                id: "endpoint-catalog-sets-show",
                name: "Get Product Set Details",
                method: "GET",
                path: "/api/catalog/sets/{setId}",
                auth: true,
                description: "Get metadata and filter rules for a specific Meta product set.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Accept", value: "application/json" }
                ],
                pathParams: [
                    { key: "setId", type: "string", required: true, value: "182938475610293", description: "Product Set ID." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        data: {
                            id: "182938475610293",
                            name: "Festive Ethnic Wear",
                            retailer_id: "SET-ETHNIC-01",
                            product_count: 12,
                            filter: {
                                retailer_id: {
                                    is_any: ["SKU-SHERWANI-02", "SKU-KURTA-05"]
                                }
                            }
                        }
                    }
                }
            },
            {
                id: "endpoint-catalog-sets-products-list",
                name: "Get Products in Set",
                method: "GET",
                path: "/api/catalog/sets/{setId}/products",
                auth: true,
                description: "Fetch products belonging to a specific product set. Supports pagination via 'after' cursor, 'limit' up to 100, or 'all=true' to auto-fetch all products in the set in a single call.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Accept", value: "application/json" }
                ],
                pathParams: [
                    { key: "setId", type: "string", required: true, value: "182938475610293", description: "Product Set ID." }
                ],
                queryParams: [
                    { key: "limit", type: "integer | string", required: false, value: "100", description: "Products per page (default: 25, max: 100). Set to 100 to fetch up to 100 products, or pass 'all' to fetch everything." },
                    { key: "all", type: "boolean", required: false, value: "true", description: "Set true to automatically retrieve all products in the set across all pages in a single response." },
                    { key: "after", type: "string", required: false, value: "", description: "Pagination cursor token for next page (found in pagination.after)." },
                    { key: "before", type: "string", required: false, value: "", description: "Pagination cursor token for previous page (found in pagination.before)." }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        set_id: "182938475610293",
                        count: 1,
                        data: [
                            {
                                id: "10192837465",
                                retailer_id: "SKU-SHERWANI-02",
                                name: "Royal Silk Sherwani",
                                price: 8500,
                                sale_price: 7499,
                                currency: "INR",
                                formatted_price: "₹8,500.00",
                                formatted_sale_price: "₹7,499.00",
                                availability: "in stock",
                                image_url: "https://zapreach.com/uploads/sherwani.jpg",
                                condition: "new",
                                brand: "Ranaji Heritage",
                                fb_product_category: "Clothing & Accessories > Clothing > Traditional & Cultural Clothing"
                            }
                        ],
                        pagination: {
                            has_next: false,
                            has_previous: false,
                            after: null,
                            before: null
                        }
                    }
                }
            },
            {
                id: "endpoint-catalog-sets-products-add",
                name: "Add Products to Set",
                method: "POST",
                path: "/api/catalog/sets/{setId}/products",
                auth: true,
                description: "Add one or more products by retailer SKU ID to an existing product set.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [
                    { key: "setId", type: "string", required: true, value: "182938475610293", description: "Product Set ID." }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "retailer_ids", type: "array | string", required: true, value: "[\"SKU-LOAFERS-01\", \"SKU-JUTTI-02\"]", description: "Retailer SKU ID(s) to add to set." }
                ],
                responses: {
                    "200": {
                        success: true,
                        message: "Products added to product set successfully.",
                        data: {
                            set_id: "182938475610293",
                            retailer_ids: ["SKU-SHERWANI-02", "SKU-KURTA-05", "SKU-LOAFERS-01", "SKU-JUTTI-02"]
                        }
                    }
                }
            },
            {
                id: "endpoint-catalog-sets-products-remove",
                name: "Remove Products from Set",
                method: "POST",
                path: "/api/catalog/sets/{setId}/products/remove",
                auth: true,
                description: "Remove one or more products from a product set by retailer SKU ID.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [
                    { key: "setId", type: "string", required: true, value: "182938475610293", description: "Product Set ID." }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "retailer_ids", type: "array | string", required: true, value: "[\"SKU-JUTTI-02\"]", description: "Retailer SKU ID(s) to remove from set." }
                ],
                responses: {
                    "200": {
                        success: true,
                        message: "Products removed from product set successfully.",
                        data: {
                            set_id: "182938475610293",
                            retailer_ids: ["SKU-SHERWANI-02", "SKU-KURTA-05", "SKU-LOAFERS-01"]
                        }
                    }
                }
            },
            {
                id: "endpoint-catalog-sets-delete",
                name: "Delete Product Set",
                method: "DELETE",
                path: "/api/catalog/sets/{setId}",
                auth: true,
                description: "Permanently delete a product set from Meta Commerce Manager (does not delete products from catalog).",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" }
                ],
                pathParams: [
                    { key: "setId", type: "string", required: true, value: "182938475610293", description: "Product Set ID to delete." }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        message: "Product set deleted successfully.",
                        set_id: "182938475610293"
                    }
                }
            },            {
                id: "endpoint-catalog-send-storefront",
                name: "Send Storefront Catalog Message",
                method: "POST",
                path: "/api/catalog/send-catalog",
                auth: true,
                description: "Send an interactive WhatsApp message inviting the customer to open and view the business storefront catalog directly inside WhatsApp.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "to", type: "string", required: false, value: "919506658558", description: "Recipient WhatsApp phone number (optional if conversation_id is passed)." },
                    { key: "body_text", type: "string", required: true, value: "Hello Rohit! Check out our new wedding & festive collection.", description: "Greeting or promotional text message." },
                    { key: "footer_text", type: "string", required: false, value: "Ranaji Showroom", description: "Optional footer text (e.g. business name)." },
                    { key: "thumbnail_product_retailer_id", type: "string", required: false, value: "SKU-LOAFERS-01", description: "SKU of a featured product to display as thumbnail." },
                    { key: "conversation_id", type: "integer", required: false, value: "48", description: "Link to conversation or dispatch directly without passing 'to'." }
                ],
                responses: {
                    "200": {
                        success: true,
                        message: "Catalog message sent successfully",
                        message_id: "wamid.HBgMOTE5NTA2NjU4NTU4FQIAERgSRjQ1..."
                    }
                }
            },
            {
                id: "endpoint-catalog-send-product",
                name: "Send Single Product Message (SPM)",
                method: "POST",
                path: "/api/catalog/send-product",
                auth: true,
                description: "Dispatch a Single Product Message (SPM) displaying the product picture, title, price, description, and an interactive \"View\" / \"Add to Cart\" button directly in the WhatsApp chat. Can be sent directly to a conversation.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "to", type: "string", required: false, value: "919506658558", description: "Recipient WhatsApp phone number (optional if conversation_id is passed)." },
                    { key: "product_retailer_id", type: "string", required: true, value: "SKU-LOAFERS-01", description: "Merchant SKU / Retailer ID of the product." },
                    { key: "catalog_id", type: "string", required: false, value: "987654321012345", description: "Catalog ID (defaults to active catalog)." },
                    { key: "body_text", type: "string", required: false, value: "Here is the footwear you were inquiring about.", description: "Accompanying message text." },
                    { key: "footer_text", type: "string", required: false, value: "Ranaji Showroom", description: "Optional footer text." },
                    { key: "conversation_id", type: "integer", required: false, value: "48", description: "Link to conversation or dispatch directly without passing 'to'." }
                ],
                responses: {
                    "200": {
                        success: true,
                        message: "Single product message sent successfully",
                        message_id: "wamid.HBgMOTE5NTA2NjU4NTU4FQIAERgSRjQ2..."
                    }
                }
            },
            {
                id: "endpoint-catalog-send-product-list",
                name: "Send Multi-Product Message (MPM)",
                method: "POST",
                path: "/api/catalog/send-product-list",
                auth: true,
                description: "Dispatch a Multi-Product Message (MPM) grouping up to 30 products into distinct sections/categories. Customers can open the list, select items, and submit their cart to your business.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "to", type: "string", required: false, value: "919506658558", description: "Recipient WhatsApp phone number (optional if conversation_id is passed)." },
                    { key: "sections", type: "array", required: true, value: "[{\"title\":\"Ethnic Footwear\",\"product_items\":[{\"product_retailer_id\":\"SKU-LOAFERS-01\"},{\"product_retailer_id\":\"SKU-JUTTI-02\"}]},{\"title\":\"Wedding Sherwanis\",\"product_items\":[{\"product_retailer_id\":\"SKU-SHERWANI-02\"}]}]", description: "Array of section objects with title and product_items." },
                    { key: "header_text", type: "string", required: false, value: "Ranaji Festive Highlights", description: "Header text for the product list (max 60 chars)." },
                    { key: "body_text", type: "string", required: true, value: "Browse our curated selection and add items to your cart directly inside WhatsApp.", description: "Body text explaining the collection." },
                    { key: "footer_text", type: "string", required: false, value: "Ranaji Showroom", description: "Optional footer text." },
                    { key: "conversation_id", type: "integer", required: false, value: "48", description: "Link to conversation or dispatch directly without passing 'to'." }
                ],
                responses: {
                    "200": {
                        success: true,
                        message: "Multi-product message sent successfully",
                        message_id: "wamid.HBgMOTE5NTA2NjU4NTU4FQIAERgSRjQ3..."
                    }
                }
            }
        ]
    },
    {
        name: "Subscription & Plan Renewal",
        icon: "fas fa-rotate",
        description: "Subscription plan renewal management: check renewal status, generate Razorpay payment links for user self-renewal and admin-triggered renewal, verify payment, and manual admin renew without payment.",
        endpoints: [
            {
                id: "endpoint-renewal-status",
                name: "Get Renewal Status (User Popup)",
                method: "GET",
                path: "/api/plan/renewal-status",
                auth: true,
                description: "Returns the authenticated user's active plan status including whether a renewal is needed (for frontend popup), expiry date, plan details, and the effective renewal price. Frontend should show a renewal popup if needs_renewal is true.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        status: true,
                        needs_renewal: true,
                        is_expired: false,
                        plan: {
                            id: 3,
                            name: "Professional Plan",
                            price: 2999,
                            renewal_price: 1999,
                            effective_renewal_price: 1999,
                            billing_cycle: "monthly"
                        },
                        active_package: {
                            id: 12,
                            start_date: "2026-09-05",
                            end_date: "2026-10-05",
                            days_remaining: 0,
                            is_expired: false
                        },
                        message: "Your subscription expires soon. Please renew to continue."
                    },
                    "200 (no renewal needed)": {
                        status: true,
                        needs_renewal: false,
                        is_expired: false,
                        message: "Subscription is active and does not need renewal yet."
                    }
                }
            },
            {
                
                id: "endpoint-renewal-generate-link-user",
                name: "Generate Renewal Payment Link (User)",
                method: "POST",
                path: "/api/plan/renewal-payment-link",
                auth: true,
                description: "Generates a Razorpay payment link or wallet renewal for currently logged-in user. Automatically uses the locked-in renewal_price with GST calculation. Also accessible via /api/v1/plan/renewal-payment-link.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "package_id", type: "integer", required: false, value: "2", description: "Plan ID to renew (defaults to user's active plan). (Alias: packageId)." },
                    { key: "payment_method", type: "string", required: false, value: "razorpay", description: "Payment method: 'razorpay' (generates link) or 'wallet' (instant debit from wallet balance)." },
                    { key: "callback_url", type: "string", required: false, value: "https://zapreach.com/payment/callback", description: "Success callback URL. (Alias: callbackUrl)." },
                    { key: "back_url", type: "string", required: false, value: "https://zapreach.com/billing", description: "Return / cancel URL. (Aliases: backUrl, return_url, cancel_url)." },
                    { key: "billing_state", type: "string", required: false, value: "Gujarat", description: "Billing state name for GST invoicing (CGST+SGST vs IGST). (Aliases: billingState, state, customer_state)." }
                ],
                responses: {
                    "200 (Razorpay Mode)": {
                        success: true,
                        message: "Payment link generated successfully",
                        data: {
                            order_id: "order_Qwert12345",
                            payment_link_id: "plink_Qwert12345",
                            short_url: "https://rzp.io/i/XyZ123",
                            amount: 1999,
                            total_amount: 2358.82,
                            gst_amount: 359.82
                        }
                    },
                    "200 (Wallet Mode)": {
                        success: true,
                        message: "Subscription plan renewed successfully using wallet balance!",
                        data: {
                            payment_type: "renewal",
                            amount_debited: 2358.82,
                            new_wallet_balance: 1420.50
                        }
                    }
                }
            },
{
                id: "endpoint-renewal-cancel-link",
                name: "Cancel Plan Payment Link",
                method: "POST",
                path: "/api/plan/payment-link/cancel",
                auth: false,
                description: "Cancel an unpaid subscription plan payment link or pending order. Marks local order status as 'cancelled' and cancels the remote link on Razorpay so the user cannot pay for an abandoned order. Also accessible via public /payment/cancel.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "payment_link_id", type: "string", required: false, value: "plink_AbCdEfGhIjKlMn", description: "Razorpay Payment Link ID to cancel." },
                    { key: "razorpay_order_id", type: "string", required: false, value: "order_AbCdEfGh12345", description: "Razorpay Order ID to cancel." },
                    { key: "receipt", type: "string", required: false, value: "renw_10_3_1791368940", description: "Local order receipt identifier to cancel." }
                ],
                responses: {
                    "200": {
                        status: true,
                        success: true,
                        message: "Payment link has been successfully cancelled."
                    },
                    "400": {
                        status: false,
                        message: "Missing payment_link_id, razorpay_order_id, or receipt identifier."
                    },
                    "404": {
                        status: false,
                        message: "Payment order not found."
                    }
                }
            },
            {
                id: "endpoint-renewal-verify-payment",
                name: "Verify Renewal Payment",
                method: "POST",
                path: "/api/plan/verify-renewal",
                auth: true,
                description: "Verify a completed Razorpay payment for plan renewal and activate the subscription. Pass the Razorpay payment link ID or order ID. The system validates the payment signature and extends or renews the active plan accordingly.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "payment_link_id", type: "string", required: false, value: "plink_AbCdEfGhIjKlMn", description: "Razorpay Payment Link ID (from generate link response). Either this or razorpay_order_id required." },
                    { key: "razorpay_order_id", type: "string", required: false, value: "order_AbCdEfGh12345", description: "Razorpay Order ID (alternative to payment_link_id)." },
                    { key: "razorpay_payment_id", type: "string", required: false, value: "pay_AbCdEfGh12345678", description: "Razorpay Payment ID to confirm the transaction." },
                    { key: "razorpay_signature", type: "string", required: false, value: "a1b2c3d4e5f6...", description: "Razorpay signature for payment verification (HMAC-SHA256)." }
                ],
                responses: {
                    "200": {
                        status: true,
                        message: "Plan renewed successfully.",
                        new_end_date: "2026-11-05",
                        plan_name: "Professional Plan"
                    },
                    "400": { status: false, message: "Payment not completed yet or invalid signature." },
                    "404": { status: false, message: "Renewal order not found." }
                }
            },
            {
                
                id: "endpoint-renewal-admin-generate-link",
                name: "Admin: Generate Renewal Payment Link for User",
                method: "POST",
                path: "/api/v1/admin/users/{userId}/renewal-payment-link",
                auth: true,
                description: "Super Admin endpoint to generate a Razorpay Payment Link for renewing a user's subscription. Uses the plan's special renewal_price with GST calculation. Also accessible via /api/v1/users/{userId}/renewal-payment-link.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [
                    { key: "userId", required: true, value: "1", description: "Target user ID (numeric or 'usr_{id}')" }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "packageId", type: "integer", required: false, value: "2", description: "Package ID to renew (defaults to user's active plan). (Alias: package_id)." },
                    { key: "callbackUrl", type: "string", required: false, value: "https://zapreach.com/payment/callback", description: "Payment success redirect URL. (Alias: callback_url)." },
                    { key: "back_url", type: "string", required: false, value: "https://zapreach.com/billing", description: "Cancel / back URL. (Aliases: backUrl, return_url, cancel_url)." },
                    { key: "billing_state", type: "string", required: false, value: "Gujarat", description: "Client billing state for GST tax calculation (determines CGST+SGST vs IGST). (Aliases: billingState, state, customer_state)." }
                ],
                responses: {
                    "200": {
                        success: true,
                        message: "Renewal payment link generated successfully",
                        data: {
                            order_id: "order_Qwert12345",
                            payment_link_id: "plink_Qwert12345",
                            short_url: "https://rzp.io/i/XyZ123",
                            amount: 1999,
                            total_amount: 2358.82,
                            gst_amount: 359.82
                        }
                    }
                }
            },
            {
                id: "endpoint-renewal-admin-manual-renew",
                name: "Admin: Manual Plan Renewal (No Payment)",
                method: "POST",
                path: "/api/v1/admin/users/{userId}/renew",
                auth: true,
                description: "Super Admin instant manual renewal. Extends user subscription without requiring payment gateway transaction. Also allows generating a payment link by passing payment_link=true. Automatically resets monthly usage counters and reactivates user and staff accounts. Also accessible via /api/v1/users/{userId}/renew.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [
                    { key: "userId", required: true, value: "1", description: "Target user ID (numeric or 'usr_{id}')" }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "daysToAdd", type: "integer", required: false, value: "30", description: "Number of days to extend validity (e.g. 30, 365; defaults to package duration). (Aliases: days_to_add, days)." },
                    { key: "startDate", type: "string", required: false, value: "2026-10-08", description: "Custom start date override (format: YYYY-MM-DD or DD/MM/YYYY). (Alias: start_date)." },
                    { key: "endDate", type: "string", required: false, value: "2026-11-08", description: "Custom explicit end date override. If omitted, seamlessly extended from current endDate (or now if expired). (Alias: end_date)." },
                    { key: "payment_link", type: "boolean", required: false, value: "false", description: "Set to true to generate a Razorpay payment link instead of performing instant manual renewal. (Alias: generate_payment_link)." },
                    { key: "packageId", type: "integer", required: false, value: "2", description: "Target plan ID for renewal payment link (defaults to current active plan). (Alias: package_id)." },
                    { key: "callbackUrl", type: "string", required: false, value: "https://zapreach.com/payment/callback", description: "Success callback URL. (Alias: callback_url)." }
                ],
                responses: {
                    "200 (Instant Renewal)": {
                        success: true,
                        message: "Subscription renewed successfully for 30 days",
                        data: {
                            userId: "usr_10",
                            status: "active",
                            newEndDate: "2026-11-08T23:59:59+05:30"
                        }
                    },
                    "200 (Payment Link Generated)": {
                        success: true,
                        message: "Renewal payment link generated successfully",
                        payment_link_url: "https://rzp.io/l/abc123xyz",
                        amount: 1999
                    }
                }
            },
            {
                id: "endpoint-plan-admin-upgrade",
                name: "Admin: Upgrade User Subscription Plan",
                method: "POST",
                path: "/api/v1/admin/users/{userId}/upgrade-plan",
                auth: true,
                description: "Super Admin endpoint to upgrade any user's subscription to a higher standard package or a new custom plan. Switches active plan, grants quota limits (messages, contacts, templates), resets monthly usage, and automatically rolls over remaining unused messages from previous plan. Auto-generates GST Tax Invoice if requested.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [
                    { key: "userId", required: true, value: "1", description: "Target user ID (numeric or 'usr_{id}')" }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "packageId", type: "integer | string", required: false, value: "2", description: "Standard plan ID or package name. (Aliases: package_id, planId, plan_id, plan)." },
                    { key: "is_custom_plan", type: "boolean", required: false, value: "false", description: "Set true to dynamically upgrade to custom parameters. (Alias: custom_plan)." },
                    { key: "custom_plan_name", type: "string", required: false, value: "Enterprise High-Volume Tier", description: "Custom plan name when is_custom_plan is true. (Aliases: customPlanName, plan_name, packageName)." },
                    { key: "packageDesc", type: "string", required: false, value: "Enterprise upgrade plan with dedicated bandwidth", description: "Plan description. (Alias: description)." },
                    { key: "days", type: "integer", required: false, value: "30", description: "Validity duration in days. (Aliases: duration, duration_days, durationDays)." },
                    { key: "startDate", type: "string", required: false, value: "2026-10-08", description: "Custom start date (format: YYYY-MM-DD or DD/MM/YYYY or ISO). (Aliases: start_date, start)." },
                    { key: "endDate", type: "string", required: false, value: "2026-11-08", description: "Custom expiry date (format: YYYY-MM-DD or DD/MM/YYYY). (Aliases: end_date, end)." },
                    { key: "extend_duration", type: "boolean", required: false, value: "true", description: "Add days onto user's remaining future unexpired plan days." },
                    { key: "totalMessages", type: "integer", required: false, value: "100000", description: "Message credit quota limit with auto-rollover of unused credits. (Aliases: msgCount, custom_msg_count, messageLimit, total_messages, allocatedMessages)." },
                    { key: "totalContacts", type: "integer", required: false, value: "25000", description: "Maximum contact limit. (Aliases: contactCount, contactsLimit, custom_contact_count, total_contacts)." },
                    { key: "template_limit", type: "integer", required: false, value: "100", description: "WhatsApp custom template limit. (Aliases: templateCount, templatesLimit, custom_template_count, templateLimit)." },
                    { key: "price", type: "numeric", required: false, value: "5999.00", description: "Upgrade price in INR. (Aliases: custom_price, customPrice)." },
                    { key: "renewal_price", type: "numeric", required: false, value: "4999.00", description: "Renewal price for subsequent renewals. (Aliases: renewalPrice)." },
                    { key: "features", type: "array | string", required: false, value: "[\"Full Broadcast Access\", \"Live CRM Chat\", \"API Webhooks\"]", description: "Enabled features list." },
                    { key: "reset_used_messages", type: "boolean", required: false, value: "true", description: "Reset monthly usage counters to 0. (Alias: resetUsage)." },
                    { key: "notes", type: "string", required: false, value: "Upgraded by Super Admin", description: "Internal notes or audit remarks. (Alias: note)." },
                    { key: "allow_negative_balance", type: "boolean", required: false, value: "false", description: "Allow sending if balance drops to 0." },
                    { key: "generate_invoice", type: "boolean", required: false, value: "true", description: "Auto-create GST Tax Invoice. (Aliases: generate_tax_invoice, generateTaxInvoice, tax_invoice, taxInvoice, generateInvoice)." },
                    { key: "payment_method", type: "string", required: false, value: "Cash", description: "Payment mode: 'Cash', 'Bank Transfer', 'UPI', 'Razorpay', 'Card', 'Wallet', 'Admin Allocation'. (Alias: paymentMethod)." },
                    { key: "payment_status", type: "string", required: false, value: "paid", description: "Payment status: 'paid', 'pending', 'completed'. (Alias: paymentStatus)." },
                    { key: "tax_inclusive", type: "boolean", required: false, value: "false", description: "Whether price includes GST." },
                    { key: "gst_percentage", type: "numeric", required: false, value: "18.0", description: "GST rate percentage." },
                    { key: "invoice_notes", type: "string", required: false, value: "Payment Ref #987654", description: "Notes printed on invoice." }
                ],
                responses: {
                    "200": {
                        success: true,
                        message: "User plan upgraded successfully from Basic to Professional",
                        data: {
                            userId: "usr_10",
                            previousPlan: "Basic",
                            upgradedPlan: "Professional",
                            invoice: { invoice_number: "INV-2026-00008", total_amount: 7078.82 }
                        }
                    }
                }
            },
{
                id: "endpoint-plan-create-admin",
                name: "Admin: Create Plan (with Renewal Price)",
                method: "POST",
                path: "/api/v1/admin/plans",
                auth: true,
                description: "Super Admin endpoint to create a new subscription plan with separate standard price and discounted renewal_price, monthly or yearly billing cycles, quota limits, and custom GST percentage. Also supports automatic dual-variant creation via createYearlyPair=true. Also accessible via /api/v1/plans.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "packageName", type: "string", required: true, value: "Diamond Enterprise", description: "Plan name. (Alias: name)." },
                    { key: "packageDesc", type: "string", required: false, value: "High-volume broadcast plan with priority routing", description: "Detailed plan description." },
                    { key: "msgCount", type: "integer", required: true, value: "250000", description: "Monthly message quota limit. (Alias: max_messages_per_month)." },
                    { key: "contactCount", type: "integer", required: false, value: "50000", description: "Maximum contact limit. (Alias: max_contacts)." },
                    { key: "templateCount", type: "integer", required: false, value: "200", description: "Custom WhatsApp template limit. (Alias: max_templates)." },
                    { key: "price", type: "numeric", required: true, value: "12999.00", description: "Standard price in INR." },
                    { key: "renewalPrice", type: "numeric", required: false, value: "9999.00", description: "Discounted recurring renewal price. (Alias: renewal_price)." },
                    { key: "yearlyPrice", type: "numeric", required: false, value: "129990.00", description: "Optional yearly discounted price. (Alias: yearly_price)." },
                    { key: "yearlyRenewalPrice", type: "numeric", required: false, value: "99990.00", description: "Optional yearly renewal discount price. (Alias: yearly_renewal_price)." },
                    { key: "period", type: "string", required: false, value: "monthly", description: "Billing period: 'monthly' or 'yearly'. (Aliases: billingPeriod, billingCycle, planType, type)." },
                    { key: "days", type: "integer", required: false, value: "30", description: "Plan validity in days (e.g. 30 or 365). (Aliases: day, duration)." },
                    { key: "popular", type: "boolean", required: false, value: "true", description: "Whether to highlight as popular / recommended badge." },
                    { key: "color", type: "string", required: false, value: "#3b82f6", description: "Hex accent color for card UI." },
                    { key: "iconName", type: "string", required: false, value: "fa-gem", description: "FontAwesome icon name." },
                    { key: "features", type: "array | string", required: false, value: "[\"Priority Throughput\", \"Unlimited Campaigns\", \"Dedicated Support\"]", description: "Features list (JSON array or comma/newline separated string)." },
                    { key: "createYearlyPair", type: "boolean", required: false, value: "false", description: "If true, automatically creates both Monthly and Yearly package variants in one API call." },
                    { key: "gstPercentage", type: "numeric", required: false, value: "18.0", description: "Custom GST tax percentage override (0-100). (Alias: gst_percentage)." }
                ],
                responses: {
                    "201": {
                        success: true,
                        message: "Subscription plan created successfully",
                        data: {
                            id: 9,
                            packageName: "Diamond Enterprise",
                            price: "12999",
                            renewal_price: "9999",
                            period: "/month",
                            msgCount: 250000,
                            contactCount: 50000
                        }
                    }
                }
            },
            {
                id: "endpoint-plan-update-admin",
                name: "Admin: Update Plan (with Renewal Price)",
                method: "PUT",
                path: "/api/v1/admin/plans/{planId}",
                auth: true,
                description: "Super Admin endpoint to update any existing subscription plan details, pricing, renewal_price, quotas, GST rate, or billing period. Also accessible via POST and /api/v1/plans/{planId}.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [
                    { key: "planId", required: true, value: "1", description: "Numeric ID of the plan to update" }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "packageName", type: "string", required: false, value: "Diamond Enterprise (Updated)", description: "Updated plan name. (Alias: name)." },
                    { key: "packageDesc", type: "string", required: false, value: "Updated high-volume broadcast plan", description: "Updated plan description." },
                    { key: "msgCount", type: "integer", required: false, value: "300000", description: "Updated message quota. (Alias: max_messages_per_month)." },
                    { key: "contactCount", type: "integer", required: false, value: "60000", description: "Updated contact limit. (Alias: max_contacts)." },
                    { key: "templateCount", type: "integer", required: false, value: "250", description: "Updated template limit. (Alias: max_templates)." },
                    { key: "price", type: "numeric", required: false, value: "11999.00", description: "Updated standard price in INR." },
                    { key: "renewalPrice", type: "numeric", required: false, value: "8999.00", description: "Updated renewal price in INR. (Alias: renewal_price)." },
                    { key: "period", type: "string", required: false, value: "monthly", description: "Billing period: 'monthly' or 'yearly'. (Aliases: billingPeriod, billingCycle, planType, type)." },
                    { key: "days", type: "integer", required: false, value: "30", description: "Duration in days. (Aliases: day, duration)." },
                    { key: "popular", type: "boolean", required: false, value: "true", description: "Popular badge toggle." },
                    { key: "color", type: "string", required: false, value: "#2563eb", description: "Hex card color." },
                    { key: "iconName", type: "string", required: false, value: "fa-gem", description: "FontAwesome icon." },
                    { key: "features", type: "array | string", required: false, value: "[\"Updated Priority Throughput\", \"Unlimited Campaigns\"]", description: "Features list." },
                    { key: "gstPercentage", type: "numeric", required: false, value: "18.0", description: "GST percentage override. (Alias: gst_percentage)." }
                ],
                responses: {
                    "200": {
                        success: true,
                        message: "Subscription plan updated successfully",
                        data: {
                            id: 1,
                            packageName: "Diamond Enterprise (Updated)",
                            price: "11999",
                            renewal_price: "8999",
                            period: "/month"
                        }
                    }
                }
            }
        ]
    },
    {
        name: "Admin: GST Settings",
        icon: "fas fa-percent",
        description: "Manage global GST (Goods & Services Tax) configuration for subscription plan billing and wallet recharge invoicing. Includes get and update endpoints for both the Settings and Plans GST controllers.",
        endpoints: [
            {
                id: "endpoint-admin-gst-settings-get",
                name: "Admin: Get GST Settings",
                method: "GET",
                path: "/api/v1/admin/settings/gst",
                auth: true,
                description: "Retrieve the current global GST configuration including enabled status, tax rate percentage, GSTIN registration number, and registered state. Also accessible via GET /api/v1/admin/settings/credentials/gst.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        data: {
                            enabled: true,
                            percentage: 18,
                            gstin: "27AABCU9603R1ZX",
                            state: "Maharashtra"
                        }
                    },
                    "500": { success: false, message: "Error retrieving GST settings: ..." }
                }
            },
            {
                id: "endpoint-admin-gst-settings-update",
                name: "Admin: Update GST Settings",
                method: "PUT",
                path: "/api/v1/admin/settings/gst",
                auth: true,
                description: "Update the global GST tax configuration — enable/disable GST, set the tax rate percentage, update the GSTIN registration number, and set the registered state. Also accessible via POST /api/v1/admin/settings/gst or PUT/POST /api/v1/admin/settings/credentials/gst. All fields are optional; only the fields sent will be updated.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "enabled", type: "boolean", required: false, value: "true", description: "Enable or disable GST globally. Also accepted as 'gstEnabled' or 'gst_enabled'." },
                    { key: "percentage", type: "numeric", required: false, value: "18", description: "GST tax rate percentage (0–100). Also accepted as 'gstPercentage' or 'gst_percentage'." },
                    { key: "gstin", type: "string", required: false, value: "27AABCU9603R1ZX", description: "Business GSTIN (GST Identification Number). Max 30 characters." },
                    { key: "state", type: "string", required: false, value: "Maharashtra", description: "Registered GST state name. Also accepted as 'gst_state'. Max 100 characters." }
                ],
                responses: {
                    "200": {
                        success: true,
                        message: "GST tax settings updated successfully",
                        data: {
                            enabled: true,
                            percentage: 18,
                            gstin: "27AABCU9603R1ZX",
                            state: "Maharashtra"
                        }
                    },
                    "400": { success: false, message: "No GST configuration fields provided to update." },
                    "400 (validation)": {
                        success: false,
                        message: "Validation failed",
                        errors: { percentage: ["The percentage must not be greater than 100."] }
                    }
                }
            },
            {
                id: "endpoint-admin-plans-gst-get",
                name: "Admin: Get Plans GST Settings",
                method: "GET",
                path: "/api/v1/admin/plans/gst",
                auth: true,
                description: "Retrieve the GST configuration that applies specifically to subscription plan pricing and invoicing. Returns the current GST rate, enabled status, GSTIN, and registered state from the plans controller.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        data: {
                            enabled: true,
                            percentage: 18,
                            gstin: "27AABCU9603R1ZX",
                            state: "Maharashtra"
                        }
                    },
                    "500": { success: false, message: "Error retrieving GST settings: ..." }
                }
            },
            {
                id: "endpoint-admin-plans-gst-update",
                name: "Admin: Update Plans GST Settings",
                method: "PUT",
                path: "/api/v1/admin/plans/gst",
                auth: true,
                description: "Update the GST settings applied to subscription plan billing and invoices. Also accessible via POST /api/v1/admin/plans/gst. Controls whether GST is applied on plan invoices and the applicable rate. All fields are optional.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "enabled", type: "boolean", required: false, value: "true", description: "Enable or disable GST on subscription plan invoices." },
                    { key: "percentage", type: "numeric", required: false, value: "18", description: "GST percentage to apply on plan amounts (0–100)." },
                    { key: "gstin", type: "string", required: false, value: "27AABCU9603R1ZX", description: "Business GSTIN to display on plan invoices. Max 30 characters." },
                    { key: "state", type: "string", required: false, value: "Maharashtra", description: "Registered GST state for plan billing purposes. Max 100 characters." }
                ],
                responses: {
                    "200": {
                        success: true,
                        message: "GST tax settings updated successfully",
                        data: {
                            enabled: true,
                            percentage: 18,
                            gstin: "27AABCU9603R1ZX",
                            state: "Maharashtra"
                        }
                    },
                    "400": { success: false, message: "No GST configuration fields provided to update." }
                }
            }
        ]
    },
    {
        name: "Admin: SMTP Settings",
        icon: "fas fa-envelope-open-text",
        description: "Configure system-wide SMTP email gateway credentials, outgoing mail server parameters, and live email delivery testing for password resets, invoices, and alerts.",
        endpoints: [
            {
                id: "endpoint-admin-smtp-get",
                name: "Admin: Get SMTP Configuration",
                method: "GET",
                path: "/api/v1/admin/settings/smtp",
                auth: true,
                description: "Retrieve active system SMTP credentials and configuration. Sensitive password is masked for security. Also accessible via GET /api/v1/admin/settings/credentials/smtp.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        status: true,
                        data: {
                            smtpHost: "smtp.gmail.com",
                            smtpPort: 587,
                            smtpUser: "alerts@example.com",
                            smtpPassword: "********",
                            smtpCcEmail: "admin@zapreach.com",
                            smtpCcEnabled: true,
                            is_configured: true
                        }
                    },
                    "500": {
                        success: false,
                        status: false,
                        message: "Error retrieving SMTP settings: ..."
                    }
                }
            },
            {
                id: "endpoint-admin-smtp-update",
                name: "Admin: Update SMTP Configuration",
                method: "PUT",
                path: "/api/v1/admin/settings/smtp",
                auth: true,
                description: "Update system SMTP host, port, username, password, and admin CC email. When any user purchases, upgrades, or renews a plan, the system sends an email to the user and CCs admin if enabled.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "smtpHost", type: "string", required: true, value: "smtp.hostinger.com", description: "SMTP host address (e.g. smtp.hostinger.com, smtp.gmail.com)." },
                    { key: "smtpPort", type: "integer", required: true, value: "465", description: "SMTP server port (e.g. 465 for SSL, 587 for TLS)." },
                    { key: "smtpUser", type: "string", required: true, value: "smtp@zapreach.com", description: "SMTP authentication username or email." },
                    { key: "smtpPassword", type: "string", required: false, value: "mySecretAppPassword123", description: "SMTP password or App Password. Leave blank or '********' to preserve existing." },
                    { key: "smtpCcEmail", type: "string", required: false, value: "admin@zapreach.com,billing@zapreach.com", description: "Admin CC Email address(es) to receive copies of plan purchases, renewals, and invoice reminders. Supports multiple comma-separated emails (e.g. \"admin@zapreach.com,billing@zapreach.com\")." },
                    { key: "smtpCcEnabled", type: "boolean", required: false, value: "true", description: "Whether to enable or disable sending plan notification emails to the CC email address." }
                ],
                responses: {
                    "200": {
                        success: true,
                        status: true,
                        message: "SMTP settings updated successfully",
                        data: {
                            smtpHost: "smtp.hostinger.com",
                            smtpPort: 465,
                            smtpUser: "smtp@zapreach.com",
                            smtpPassword: "********",
                            smtpCcEmail: "admin@zapreach.com",
                            smtpCcEnabled: true,
                            is_configured: true
                        }
                    },
                    "400 (validation)": {
                        success: false,
                        status: false,
                        message: "Validation failed",
                        errors: {
                            smtpHost: ["The smtpHost field is required."],
                            smtpPort: ["The smtpPort field is required."]
                        }
                    }
                }
            },
            {
                id: "endpoint-admin-smtp-test",
                name: "Admin: Test SMTP Connection",
                method: "POST",
                path: "/api/v1/admin/settings/smtp/test",
                auth: true,
                description: "Test and validate SMTP email configuration by checking credentials and server connectivity. Also accessible via POST /api/v1/admin/settings/credentials/smtp/test.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        status: true,
                        message: "SMTP settings validated successfully (Host: smtp.gmail.com, Port: 587, User: alerts@example.com)",
                        data: {
                            smtpHost: "smtp.gmail.com",
                            smtpPort: 587,
                            smtpUser: "alerts@example.com",
                            configured: true
                        }
                    },
                    "400": {
                        success: false,
                        status: false,
                        message: "SMTP settings are not configured yet."
                    }
                }
            }
        ]
    },
    {
        name: "Admin: Custom Plans & Invoices",
        icon: "fas fa-file-invoice-dollar",
        description: "Endpoints for Super Admin to create & assign custom subscription plans with customizable limits, generate GST tax invoices, track plan ledger transactions, and enable client invoice downloads.",
        endpoints: [
            {
                
                id: "endpoint-admin-assign-custom-plan",
                name: "Admin: Assign Custom Subscription Plan",
                method: "POST",
                path: "/api/v1/admin/users/{userId}/custom-plan",
                auth: true,
                description: "Create and assign a fully custom subscription plan tailored for a specific user with custom message limits, contacts limits, template limits, duration (days), pricing, renewal pricing override, payment method, and optional automatic GST invoice generation.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [
                    { key: "userId", required: true, value: "1", description: "Target user ID (numeric or 'usr_{id}')" }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "plan_name", type: "string", required: true, value: "Enterprise (Advanced)", description: "Name of the custom plan to create/assign. (Aliases: custom_plan_name, customPlanName, planName, packageName)." },
                    { key: "packageDesc", type: "string", required: false, value: "Custom negotiated enterprise subscription", description: "Plan description / subtitle. (Alias: description)." },
                    { key: "duration", type: "integer", required: false, value: "30", description: "Duration in days. (Aliases: days, duration_days, durationDays)." },
                    { key: "start_date", type: "string", required: false, value: "2026-10-08", description: "Plan start date (format: YYYY-MM-DD or DD/MM/YYYY or ISO). (Aliases: startDate, start)." },
                    { key: "end_date", type: "string", required: false, value: "2026-11-08", description: "Plan end date (format: YYYY-MM-DD or DD/MM/YYYY or ISO). (Aliases: endDate, end)." },
                    { key: "price", type: "numeric", required: false, value: "5999.00", description: "Plan price in INR. (Aliases: custom_price, customPrice)." },
                    { key: "renewal_price", type: "numeric", required: false, value: "4999.00", description: "Locked-in renewal price for subsequent renewals. (Aliases: renewalPrice)." },
                    { key: "total_messages", type: "integer", required: false, value: "50000", description: "Total message credit quota. (Aliases: totalMessages, custom_msg_count, msgCount, messageLimit, allocatedMessages)." },
                    { key: "total_contacts", type: "integer", required: false, value: "20000", description: "Total contact capacity limit. (Aliases: totalContacts, custom_contact_count, contactCount, contactsLimit)." },
                    { key: "template_limit", type: "integer", required: false, value: "50", description: "WhatsApp template limit. (Aliases: templateLimit, custom_template_count, templateCount, templatesLimit)." },
                    { key: "features", type: "array | string", required: false, value: "[\"Full Platform Access\", \"Priority Speed\", \"Direct Webhooks\"]", description: "Array or comma-separated list of enabled plan features." },
                    { key: "reset_used_messages", type: "boolean", required: false, value: "true", description: "Reset monthly usage counters to 0 upon assignment. (Alias: resetUsage)." },
                    { key: "payment_method", type: "string", required: false, value: "Cash", description: "Payment method: 'Cash', 'Bank Transfer', 'UPI', 'Razorpay', 'Card', 'Wallet', 'Admin Allocation'. (Alias: paymentMethod)." },
                    { key: "payment_status", type: "string", required: false, value: "paid", description: "Payment status: 'paid', 'pending', 'completed'. (Alias: paymentStatus)." },
                    { key: "notes", type: "string", required: false, value: "Custom negotiated package for enterprise client", description: "Optional notes about this plan. (Alias: note)." },
                    { key: "allow_negative_balance", type: "boolean", required: false, value: "false", description: "Allow user to continue sending if credits reach 0." },
                    { key: "generate_tax_invoice", type: "boolean", required: false, value: "true", description: "Auto-create GST Tax Invoice for this plan (default: true). (Aliases: generate_invoice, generateTaxInvoice, generateInvoice, tax_invoice, taxInvoice)." },
                    { key: "tax_inclusive", type: "boolean", required: false, value: "false", description: "If true, price includes GST; if false, GST is calculated on top." },
                    { key: "gst_percentage", type: "numeric", required: false, value: "18.0", description: "Custom GST percentage rate (defaults to system setting)." },
                    { key: "invoice_notes", type: "string", required: false, value: "Payment received via NEFT", description: "Notes printed on generated GST invoice." }
                ],
                responses: {
                    "200": {
                        success: true,
                        message: "User active subscription plan updated successfully",
                        data: {
                            userId: "usr_10",
                            dbId: 10,
                            userName: "John Doe",
                            plan: "VIP Gold Custom Plan",
                            packageName: "VIP Gold Custom Plan",
                            invoice: {
                                id: 7,
                                invoice_number: "INV-2026-00007",
                                receipt: "cpln_12_10_1791286252",
                                status: "PAID",
                                base_amount: 5999,
                                gst_percentage: 18,
                                gst_amount: 1079.82,
                                total_amount: 7078.82,
                                view_url: "https://zapreach.com/invoices/cpln_12_10_1791286252/view"
                            }
                        }
                    }
                }
            },
            {
                id: "endpoint-admin-upgrade-custom-plan",
                name: "Admin: Upgrade User to Plan",
                method: "POST",
                path: "/api/v1/admin/users/{userId}/upgrade-plan",
                auth: true,
                description: "Upgrade an existing user's subscription to a higher standard package or a custom plan. Supports full parameter customization: duration, custom start/end dates, rollover of remaining messages, contacts and template quotas, renewal price locking, GST invoice generation, and payment method options. Also accessible via /api/v1/admin/users/{userId}/upgrade.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [
                    { key: "userId", required: true, value: "1", description: "Target user ID (numeric or 'usr_{id}')" }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "packageId", type: "integer | string", required: false, value: "2", description: "Target standard package ID or package name. (Aliases: package_id, planId, plan_id, plan)." },
                    { key: "is_custom_plan", type: "boolean", required: false, value: "false", description: "Set to true to dynamically upgrade user to custom limits/pricing. (Alias: custom_plan)." },
                    { key: "custom_plan_name", type: "string", required: false, value: "Titanium Elite Custom Plan", description: "Custom plan name when is_custom_plan is true. (Aliases: customPlanName, plan_name, packageName)." },
                    { key: "packageDesc", type: "string", required: false, value: "Enterprise tier with priority message throughput", description: "Description / summary of the upgraded package. (Alias: description)." },
                    { key: "days", type: "integer", required: false, value: "30", description: "Validity duration in days (e.g. 30, 90, 365). (Aliases: duration, duration_days, durationDays)." },
                    { key: "startDate", type: "string", required: false, value: "2026-10-08", description: "Custom start date (format: YYYY-MM-DD or DD/MM/YYYY or ISO). Defaults to current timestamp. (Aliases: start_date, start)." },
                    { key: "endDate", type: "string", required: false, value: "2026-11-08", description: "Custom explicit expiry date (format: YYYY-MM-DD or DD/MM/YYYY). If omitted, automatically computed from days + remaining days. (Aliases: end_date, end)." },
                    { key: "extend_duration", type: "boolean", required: false, value: "true", description: "If true, adds new days onto any remaining future days from user's previous active plan." },
                    { key: "totalMessages", type: "integer", required: false, value: "150000", description: "Total message credit quota for upgraded plan. Remaining unused messages from previous plan automatically rollover on top! (Aliases: msgCount, custom_msg_count, messageLimit, total_messages, allocatedMessages)." },
                    { key: "totalContacts", type: "integer", required: false, value: "25000", description: "Total contact list capacity limit. (Aliases: contactCount, contactsLimit, custom_contact_count, total_contacts)." },
                    { key: "template_limit", type: "integer", required: false, value: "100", description: "WhatsApp custom template creation limit. (Aliases: templateCount, templatesLimit, custom_template_count, templateLimit)." },
                    { key: "price", type: "numeric", required: false, value: "7999.00", description: "Base subscription / upgrade price in INR. (Aliases: custom_price, customPrice)." },
                    { key: "renewal_price", type: "numeric", required: false, value: "6999.00", description: "Locked-in renewal price for subsequent recurring renewals. (Aliases: renewalPrice)." },
                    { key: "features", type: "array | string", required: false, value: "[\"Priority Throughput\", \"Unlimited Contacts\", \"Dedicated API Access\"]", description: "Array or comma-separated list of enabled plan features." },
                    { key: "reset_used_messages", type: "boolean", required: false, value: "true", description: "Whether to reset monthly used message/contact counters to 0 on upgrade. (Alias: resetUsage)." },
                    { key: "notes", type: "string", required: false, value: "Upgraded from Pro to Titanium tier by Super Admin", description: "Internal admin remarks or audit log notes. (Alias: note)." },
                    { key: "allow_negative_balance", type: "boolean", required: false, value: "false", description: "Allow user to continue dispatching WhatsApp messages even if credits reach 0." },
                    { key: "generate_invoice", type: "boolean", required: false, value: "true", description: "Auto-create GST Tax Invoice for this plan upgrade. (Aliases: generate_tax_invoice, generateTaxInvoice, tax_invoice, taxInvoice, generateInvoice)." },
                    { key: "payment_method", type: "string", required: false, value: "Cash", description: "Payment mode: 'Cash', 'Bank Transfer', 'UPI', 'Razorpay', 'Card', 'Wallet', 'Admin Allocation'. (Alias: paymentMethod)." },
                    { key: "payment_status", type: "string", required: false, value: "paid", description: "Payment status: 'paid', 'pending', 'completed'. (Alias: paymentStatus)." },
                    { key: "tax_inclusive", type: "boolean", required: false, value: "false", description: "If true, price already includes GST. If false, GST is calculated on top." },
                    { key: "gst_percentage", type: "numeric", required: false, value: "18.0", description: "Custom GST tax percentage (defaults to system setting e.g. 18%)." },
                    { key: "invoice_notes", type: "string", required: false, value: "Paid via IMPS Ref #92837492", description: "Notes printed on generated GST Tax Invoice." }
                ],
                responses: {
                    "200": {
                        success: true,
                        message: "User plan upgraded successfully from Basic to Titanium Elite Custom Plan with +15000 unused msgs carried over by Super Admin",
                        data: {
                            userId: "usr_10",
                            previousPlan: "Basic",
                            upgradedPlan: "Titanium Elite Custom Plan",
                            upgradedPackageId: 8,
                            upgradedAt: "2026-10-08T14:30:00+05:30",
                            daysRemaining: 45,
                            startDate: "2026-10-08T14:30:00+05:30",
                            endDate: "2026-11-22T23:59:59+05:30",
                            totalMessages: 165000,
                            usedMsgCount: 0,
                            contactCount: 25000,
                            templateCount: 100,
                            invoice: {
                                id: 8,
                                invoice_number: "INV-2026-00008",
                                receipt: "upg_12_10_1791286300",
                                status: "PAID",
                                base_amount: 7999,
                                gst_percentage: 18,
                                gst_amount: 1439.82,
                                total_amount: 9438.82,
                                view_url: "https://zapreach.com/invoices/upg_12_10_1791286300/view"
                            }
                        }
                    }
                }
            },
            {
                id: "endpoint-admin-user-plan-get",
                name: "Admin: Get User Active Plan Details",
                method: "GET",
                path: "/api/v1/admin/users/{userId}/plan",
                auth: true,
                description: "Super Admin view of any user's currently active subscription plan, validity expiry date, days remaining, message credit usage, and contact capacity. Also accessible via /api/v1/users/{userId}/plan.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Accept", value: "application/json" }
                ],
                pathParams: [
                    { key: "userId", required: true, value: "1", description: "Target user ID (numeric or 'usr_{id}')" }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        status: true,
                        data: {
                            userId: "usr_1",
                            packageName: "Professional",
                            daysRemaining: 24,
                            startDate: "2026-10-01T00:00:00+05:30",
                            endDate: "2026-10-31T23:59:59+05:30",
                            totalMessages: 50000,
                            usedMessages: 12450,
                            remainingMessages: 37550,
                            contactLimit: 15000,
                            renewalPrice: "1999"
                        }
                    }
                }
            },
            {
                id: "endpoint-admin-user-plan-update",
                name: "Admin: Assign or Update User Plan",
                method: "POST",
                path: "/api/v1/admin/users/{userId}/plan",
                auth: true,
                description: "Assign or modify any user's subscription package with full granular control over start date, end date, message quota, contact limits, templates limit, pricing overrides, renewal price, payment method, and automated invoice creation. Also accessible via PUT and /api/v1/users/{userId}/plan.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [
                    { key: "userId", required: true, value: "1", description: "Target user ID (numeric or 'usr_{id}')" }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "packageId", type: "integer | string", required: false, value: "2", description: "Target package ID or plan name. (Aliases: package_id, planId, plan_id, plan)." },
                    { key: "custom_plan", type: "boolean", required: false, value: "false", description: "Set true to assign custom limits/pricing. (Alias: is_custom_plan)." },
                    { key: "custom_plan_name", type: "string", required: false, value: "Custom VIP Plan", description: "Custom plan name. (Aliases: customPlanName, plan_name, packageName)." },
                    { key: "packageDesc", type: "string", required: false, value: "Custom allocated plan", description: "Plan description. (Alias: description)." },
                    { key: "days", type: "integer", required: false, value: "30", description: "Duration in days. (Aliases: duration, duration_days, durationDays)." },
                    { key: "startDate", type: "string", required: false, value: "2026-10-08", description: "Start date (YYYY-MM-DD or DD/MM/YYYY or ISO). (Aliases: start_date, start)." },
                    { key: "endDate", type: "string", required: false, value: "2026-11-08", description: "End date (YYYY-MM-DD or DD/MM/YYYY or ISO). (Aliases: end_date, end)." },
                    { key: "msgCount", type: "integer", required: false, value: "75000", description: "Message credit quota limit. (Aliases: custom_msg_count, messageLimit, totalMessages, total_messages)." },
                    { key: "contactCount", type: "integer", required: false, value: "20000", description: "Contact capacity limit. (Aliases: contactsLimit, totalContacts, custom_contact_count, total_contacts)." },
                    { key: "templateCount", type: "integer", required: false, value: "100", description: "Template creation limit. (Aliases: templatesLimit, custom_template_count, template_limit, templateLimit)." },
                    { key: "price", type: "numeric", required: false, value: "4999.00", description: "Plan price in INR. (Aliases: custom_price, customPrice)." },
                    { key: "renewal_price", type: "numeric", required: false, value: "3999.00", description: "Renewal price for subsequent renewals. (Aliases: renewalPrice)." },
                    { key: "features", type: "array | string", required: false, value: "[\"Full Broadcast Access\", \"Live CRM Chat\"]", description: "Enabled features list." },
                    { key: "usedMsgCount", type: "integer", required: false, value: "0", description: "Explicit used messages counter override. (Alias: usedMessages)." },
                    { key: "reset_used_messages", type: "boolean", required: false, value: "false", description: "Reset monthly usage counters to 0. (Alias: resetUsage)." },
                    { key: "notes", type: "string", required: false, value: "Plan updated by Super Admin", description: "Audit remarks. (Aliases: note, description)." },
                    { key: "allow_negative_balance", type: "boolean", required: false, value: "false", description: "Allow sending if balance drops to 0." },
                    { key: "generate_tax_invoice", type: "boolean", required: false, value: "true", description: "Auto-create GST Tax Invoice. (Aliases: generate_invoice, generateTaxInvoice, generateInvoice, tax_invoice, taxInvoice)." },
                    { key: "payment_method", type: "string", required: false, value: "Cash", description: "Payment mode: 'Cash', 'Bank Transfer', 'UPI', 'Razorpay', 'Card', 'Wallet', 'Admin Allocation'. (Alias: paymentMethod)." },
                    { key: "payment_status", type: "string", required: false, value: "paid", description: "Payment status: 'paid', 'pending', 'completed'. (Alias: paymentStatus)." },
                    { key: "tax_inclusive", type: "boolean", required: false, value: "false", description: "Whether price includes GST." },
                    { key: "gst_percentage", type: "numeric", required: false, value: "18.0", description: "Custom GST percentage rate." },
                    { key: "invoice_notes", type: "string", required: false, value: "Payment Ref #12345", description: "Notes printed on generated GST invoice." }
                ],
                responses: {
                    "200": {
                        success: true,
                        message: "User active subscription plan updated successfully",
                        data: {
                            userId: "usr_1",
                            packageName: "Custom VIP Plan",
                            daysRemaining: 30
                        }
                    }
                }
            },
            {
                id: "endpoint-admin-user-plan-history",
                name: "Admin: Get User Plan Assignment History",
                method: "GET",
                path: "/api/v1/admin/users/{userId}/plan-history",
                auth: true,
                description: "Retrieve chronological audit log of all package assignments, renewals, upgrades, and custom plan changes for a specific user. Also accessible via /api/v1/users/{userId}/plan-history and /api/v1/admin/plan/history/{userId}.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Accept", value: "application/json" }
                ],
                pathParams: [
                    { key: "userId", required: true, value: "1", description: "Target user ID (numeric or 'usr_{id}')" }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        status: true,
                        count: 2,
                        data: [
                            {
                                id: 14,
                                action: "upgraded",
                                packageName: "Titanium Elite Custom Plan",
                                price: 7999,
                                msgCount: 150000,
                                contactCount: 25000,
                                startDate: "2026-10-08T14:30:00+05:30",
                                endDate: "2026-11-22T23:59:59+05:30",
                                notes: "Plan upgraded from Pro with rollover messages"
                            }
                        ]
                    }
                }
            },            {
                id: "endpoint-admin-invoices-list",
                name: "Admin: List All Invoices",
                method: "GET",
                path: "/api/v1/admin/invoices",
                auth: true,
                description: "List all GST tax invoices and orders across all users with pagination, search, status filter, and date filters.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [],
                queryParams: [
                    { key: "page", required: false, value: "1", description: "Page number" },
                    { key: "limit", required: false, value: "20", description: "Items per page" },
                    { key: "user_id", required: false, value: "", description: "Filter by specific user" },
                    { key: "status", required: false, value: "paid", description: "Filter: paid, pending, failed" },
                    { key: "search", required: false, value: "", description: "Search by receipt, user name, or invoice number" }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        status: true,
                        data: [
                            {
                                id: 7,
                                invoice_number: "INV-2026-00007",
                                receipt: "cpln_12_10_1791286252",
                                date: "06 Oct, 2026",
                                status: "PAID",
                                payment_method: "Admin",
                                user: { id: "usr_10", name: "John Doe", company: "Acme Corp" },
                                plan_name: "VIP Gold Custom Plan",
                                base_amount: 5999,
                                gst_percentage: 18,
                                gst_amount: 1079.82,
                                total_amount: 7078.82,
                                view_url: "https://zapreach.com/invoices/cpln_12_10_1791286252/view"
                            }
                        ]
                    }
                }
            },
            {
                id: "endpoint-admin-invoices-get",
                name: "Admin: Get Invoice Details",
                method: "GET",
                path: "/api/v1/admin/invoices/{invoiceId}",
                auth: true,
                description: "Retrieve complete tax invoice details including seller details with GSTIN, customer details, HSN/SAC code 998313, tax breakdown (CGST, SGST, IGST), and total amount in words.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "invoiceId", required: true, value: "7", description: "Invoice ID or receipt string" }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        data: {
                            id: 7,
                            invoice_number: "INV-2026-00007",
                            receipt: "cpln_12_10_1791286252",
                            date: "06 Oct, 2026",
                            status: "PAID",
                            seller: { name: "WhatsApp Bulk CRM", gstin: "27AAPFU0939L1ZV", state: "Maharashtra" },
                            customer: { name: "John Doe", company: "Acme Corp", phone: "9876543210" },
                            items: [
                                { name: "VIP Gold Custom Plan", sac_code: "998313", quantity: 1, unit_price: 5999, total_amount: 7078.82 }
                            ],
                            tax_breakdown: {
                                base_amount: 5999,
                                cgst_amount: 539.91,
                                sgst_amount: 539.91,
                                total_tax: 1079.82,
                                total_amount: 7078.82,
                                amount_in_words: "Rupees Seven Thousand Seventy Eight and Eighty Two Paise Only"
                            },
                            view_url: "https://zapreach.com/invoices/cpln_12_10_1791286252/view"
                        }
                    }
                }
            },
            {
                
                id: "endpoint-admin-invoices-generate",
                name: "Admin: Generate Plan Invoice On-Demand",
                method: "POST",
                path: "/api/v1/admin/users/{userId}/generate-invoice",
                auth: true,
                description: "Super Admin endpoint to generate a standalone GST Tax Invoice on-demand for any user's subscription or custom plan with customized amount, GST rate, payment mode, and direct PDF generation. Also accessible via /api/v1/admin/users/{userId}/invoice.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [
                    { key: "userId", required: true, value: "1", description: "Target user ID (numeric or 'usr_{id}')" }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "packageId", type: "integer | string", required: false, value: "2", description: "Subscription plan ID (defaults to active plan). (Alias: package_id)." },
                    { key: "base_amount", type: "numeric", required: false, value: "1999.00", description: "Base taxable invoice amount in INR before GST. (Aliases: amount, price)." },
                    { key: "plan_name", type: "string", required: false, value: "Professional Plan Annual", description: "Plan name override on invoice. (Alias: packageName)." },
                    { key: "payment_method", type: "string", required: false, value: "Cash", description: "Payment mode: 'Cash', 'UPI', 'Bank Transfer', 'Razorpay', 'Card'. (Alias: paymentMethod)." },
                    { key: "payment_status", type: "string", required: false, value: "PAID", description: "Payment status: 'PAID', 'PENDING', 'FAILED'. (Alias: paymentStatus)." },
                    { key: "gst_percentage", type: "numeric", required: false, value: "18.0", description: "GST rate percentage (e.g. 18.0)." },
                    { key: "tax_inclusive", type: "boolean", required: false, value: "false", description: "Whether base_amount is inclusive of GST." },
                    { key: "invoice_notes", type: "string", required: false, value: "Payment received via IMPS Ref #92837492", description: "Remarks printed on generated invoice. (Aliases: notes, note)." },
                    { key: "format", type: "string", required: false, value: "pdf", description: "Output format: 'pdf' (download binary PDF) or 'html' (standalone HTML view)." }
                ],
                responses: {
                    "201": {
                        success: true,
                        message: "Invoice generated successfully",
                        invoice: {
                            id: 9,
                            invoice_number: "INV-2026-00009",
                            receipt: "inv_12_10_1791286350",
                            status: "PAID",
                            base_amount: 1999,
                            gst_amount: 359.82,
                            total_amount: 2358.82,
                            pdf_url: "https://zapreach.com/invoices/inv_12_10_1791286350/download?format=pdf"
                        }
                    }
                }
            },
            {
                id: "endpoint-client-invoice-download",
                name: "Client: Download Tax Invoice (PDF or HTML)",
                method: "GET",
                path: "/api/v1/invoices/{receipt}/download",
                auth: true,
                description: "Client-authenticated instant download of their GST Tax Invoice. Generates professional PDF with company logo, terms, and unified GST or standalone HTML file. Also accessible via singular /api/v1/invoice/{receipt}/download.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" }
                ],
                pathParams: [
                    { key: "receipt", type: "string", required: true, value: "cpln_12_10_1791286252", description: "Invoice receipt identifier or order ID." }
                ],
                queryParams: [
                    { key: "format", type: "string", required: false, value: "pdf", description: "Download file format: 'pdf' (binary PDF) or 'html'." }
                ],
                bodyParams: [],
                responses: {
                    "200": { description: "Downloads Invoice-{number}.pdf (Content-Type: application/pdf) or HTML" }
                }
            },
            {
                id: "endpoint-public-invoice-view",
                name: "Public: Printable Tax Invoice HTML",
                method: "GET",
                path: "/invoices/{receipt}/view",
                auth: false,
                description: "Publicly accessible print-ready HTML view of the GST Tax Invoice. Opens in browser tab with one-click 'Print / Save as PDF' button. No authorization token required. Also accessible via singular /invoice/{receipt}/view.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "receipt", type: "string", required: true, value: "cpln_12_10_1791286252", description: "Invoice receipt identifier" }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": { description: "Returns styled HTML page with print-ready CSS" }
                }
            },
            {
                id: "endpoint-public-invoice-download",
                name: "Public: Download Tax Invoice (PDF or HTML)",
                method: "GET",
                path: "/invoices/{receipt}/download",
                auth: false,
                description: "Publicly download standalone GST tax invoice file without authorization token. Supports ?format=pdf to download the binary PDF. Also accessible via singular /invoice/{receipt}/download.",
                contentType: "none",
                bodyMode: "none",
                headers: [],
                pathParams: [
                    { key: "receipt", type: "string", required: true, value: "cpln_12_10_1791286252", description: "Invoice receipt identifier" }
                ],
                queryParams: [
                    { key: "format", type: "string", required: false, value: "pdf", description: "Download file format: 'pdf' (binary PDF) or 'html'." }
                ],
                bodyParams: [],
                responses: {
                    "200": { description: "Downloads Invoice-{number}.pdf (Content-Type: application/pdf) or HTML file" }
                }
            }
        ]
    },,
{
        name: "Admin: User & Payment Reminders",
        icon: "fas fa-bell",
        description: "Super Admin automated and manual email notifications for subscription plan renewals, low wallet balance recharge alerts, invoice payments, and direct outstanding balance reminders.",
        endpoints: [
            {
                id: "endpoint-admin-user-send-reminder",
                name: "Admin: Send User Reminder (Unified)",
                method: "POST",
                path: "/api/v1/admin/users/{userId}/send-reminder",
                auth: true,
                description: "Super Admin unified endpoint to trigger an email reminder to a user. Supports reminder types: 'renewal' (subscription plan expiring/expired), 'wallet' (low wallet balance recharge), and 'payment' (pending payment or invoice due). If payment link or due dates are omitted, the system dynamically calculates them. Also accessible via /api/v1/admin/users/{userId}/reminder.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [
                    { key: "userId", required: true, value: "1", description: "Target user ID (numeric or 'usr_{id}')" }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "type", type: "string", required: true, value: "renewal", description: "Reminder type: 'renewal', 'wallet', or 'payment'." },
                    { key: "amount", type: "numeric", required: false, value: "2999.00", description: "Amount for reminder (renewal price, recommended wallet recharge, or invoice due amount)." },
                    { key: "payment_link", type: "string", required: false, value: "https://zapreach.com/wallet", description: "Direct payment / recharge URL. If omitted, dynamically generated." },
                    { key: "due_date", type: "string", required: false, value: "2026-10-31", description: "Due date or expiry date (format: YYYY-MM-DD)." },
                    { key: "invoice_id", type: "integer | string", required: false, value: "INV-2026-0001", description: "Specific invoice ID/number (applicable when type='payment')." },
                    { key: "message", type: "string", required: false, value: "Please renew your subscription to maintain uninterrupted high-speed WhatsApp broadcast delivery.", description: "Custom admin message or note displayed inside the email body." },
                    { key: "custom_subject", type: "string", required: false, value: "", description: "Optional custom email subject line override." },
                    { key: "custom_content", type: "string", required: false, value: "", description: "Optional custom email body HTML override." }
                ],
                responses: {
                    "200": {
                        success: true,
                        type: "renewal",
                        message: "Renewal reminder sent successfully to rahul.sharma@example.com",
                        data: {
                            user_id: "usr_1",
                            email: "rahul.sharma@example.com",
                            plan_name: "Diamond VIP Plan",
                            expiry_date: "31 Oct 2026",
                            days_remaining: 17,
                            renewal_amount: 2999,
                            payment_link: "https://zapreach.com/dashboard/plans",
                            sent_at: "2026-10-14T17:15:00+05:30"
                        }
                    },
                    "404": {
                        success: false,
                        message: "User not found"
                    },
                    "422": {
                        success: false,
                        message: "User #1 does not have an active subscription package to renew."
                    },
                    "500": {
                        success: false,
                        message: "SMTP Gateway Error: Connection could not be established with host."
                    }
                }
            },
            {
                id: "endpoint-admin-user-reminder-renewal",
                name: "Admin: Send Subscription Renewal Reminder",
                method: "POST",
                path: "/api/v1/admin/users/{userId}/reminder/renewal",
                auth: true,
                description: "Dedicated endpoint for Super Admin to send a professional branded subscription plan renewal reminder email to a user. Pre-fills user's active plan, expiry date, days remaining, and payment link.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [
                    { key: "userId", required: true, value: "1", description: "Target user ID (numeric or 'usr_{id}')" }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "plan_name", type: "string", required: false, value: "Enterprise VIP Plan", description: "Override plan title shown in the reminder email." },
                    { key: "amount", type: "numeric", required: false, value: "4999.00", description: "Renewal price in INR. If omitted, uses plan renewal price or active package price." },
                    { key: "payment_link", type: "string", required: false, value: "https://zapreach.com/dashboard/plans", description: "Online renewal checkout URL. Auto-generated via Razorpay / payment gateway if omitted." },
                    { key: "due_date", type: "string", required: false, value: "2026-10-31", description: "Subscription expiration date (YYYY-MM-DD)." },
                    { key: "message", type: "string", required: false, value: "Your annual broadcast quota and WhatsApp cloud API routes will renew upon payment.", description: "Custom admin message included in the email notice." },
                    { key: "custom_subject", type: "string", required: false, value: "", description: "Custom email subject line override." }
                ],
                responses: {
                    "200": {
                        success: true,
                        type: "renewal",
                        message: "Renewal reminder sent successfully to rahul.sharma@example.com",
                        data: {
                            user_id: "usr_1",
                            email: "rahul.sharma@example.com",
                            plan_name: "Enterprise VIP Plan",
                            expiry_date: "31 Oct 2026",
                            days_remaining: 17,
                            renewal_amount: 4999,
                            payment_link: "https://zapreach.com/dashboard/plans",
                            sent_at: "2026-10-14T17:15:00+05:30"
                        }
                    },
                    "404": {
                        success: false,
                        message: "User not found"
                    }
                }
            },
            {
                id: "endpoint-admin-user-reminder-wallet",
                name: "Admin: Send Low Wallet / Recharge Reminder",
                method: "POST",
                path: "/api/v1/admin/users/{userId}/reminder/wallet",
                auth: true,
                description: "Send an email alert to a user reminding them that their messaging wallet balance is low and prompt them to recharge their wallet credits.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [
                    { key: "userId", required: true, value: "1", description: "Target user ID (numeric or 'usr_{id}')" }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "amount", type: "numeric", required: false, value: "1000.00", description: "Suggested wallet recharge amount in INR." },
                    { key: "payment_link", type: "string", required: false, value: "https://zapreach.com/wallet", description: "Direct wallet recharge payment URL." },
                    { key: "message", type: "string", required: false, value: "Your wallet balance is below ₹100. Recharge now to prevent scheduled campaigns from pausing.", description: "Custom admin message or alert note." },
                    { key: "custom_subject", type: "string", required: false, value: "", description: "Custom email subject line override." }
                ],
                responses: {
                    "200": {
                        success: true,
                        type: "wallet",
                        message: "Wallet recharge reminder sent successfully to rahul.sharma@example.com",
                        data: {
                            user_id: "usr_1",
                            email: "rahul.sharma@example.com",
                            current_balance: 45.50,
                            recommended_recharge: 1000,
                            payment_link: "https://zapreach.com/wallet",
                            sent_at: "2026-10-14T17:15:00+05:30"
                        }
                    },
                    "404": {
                        success: false,
                        message: "User not found"
                    }
                }
            },
            {
                id: "endpoint-admin-user-reminder-payment",
                name: "Admin: Send Payment Due Reminder",
                method: "POST",
                path: "/api/v1/admin/users/{userId}/reminder/payment",
                auth: true,
                description: "Send a formal payment reminder email to a user for an outstanding subscription invoice, customized service fee, or pending account balance.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [
                    { key: "userId", required: true, value: "1", description: "Target user ID (numeric or 'usr_{id}')" }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "amount", type: "numeric", required: false, value: "3500.00", description: "Payment amount due in INR." },
                    { key: "due_date", type: "string", required: false, value: "2026-10-25", description: "Payment deadline / due date (format: YYYY-MM-DD)." },
                    { key: "invoice_number", type: "string", required: false, value: "INV-2026-0891", description: "Associated invoice reference number." },
                    { key: "payment_link", type: "string", required: false, value: "https://zapreach.com/pay/inv_0891", description: "Direct link for user to pay online via Razorpay/Card/UPI." },
                    { key: "message", type: "string", required: false, value: "Kindly complete the pending payment for your custom onboarding invoice.", description: "Custom admin message." },
                    { key: "custom_subject", type: "string", required: false, value: "", description: "Custom email subject line override." }
                ],
                responses: {
                    "200": {
                        success: true,
                        type: "payment",
                        message: "Payment reminder sent successfully to rahul.sharma@example.com",
                        data: {
                            user_id: "usr_1",
                            email: "rahul.sharma@example.com",
                            invoice_number: "INV-2026-0891",
                            amount_due: 3500,
                            due_date: "2026-10-25",
                            payment_link: "https://zapreach.com/pay/inv_0891",
                            sent_at: "2026-10-14T17:15:00+05:30"
                        }
                    },
                    "404": {
                        success: false,
                        message: "User not found"
                    }
                }
            },
            {
                id: "endpoint-admin-invoice-send-reminder",
                name: "Admin: Send Invoice Payment Reminder",
                method: "POST",
                path: "/api/v1/admin/invoices/{invoiceId}/send-reminder",
                auth: true,
                description: "Super Admin one-click payment reminder for a specific GST tax invoice. Automatically pulls invoice amount, due date, customer email, item description, and payment link directly from the invoice record and delivers an email notification. Also accessible via /api/v1/admin/invoice/{invoiceId}/send-reminder.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [
                    { key: "invoiceId", required: true, value: "INV-2026-0001", description: "Invoice ID or reference number (e.g. 1, 'inv_1', 'INV-2026-0001')" }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "payment_link", type: "string", required: false, value: "https://zapreach.com/invoices/pay/INV-2026-0001", description: "Custom payment link. If omitted, uses link stored on the invoice." },
                    { key: "due_date", type: "string", required: false, value: "2026-10-28", description: "Override due date displayed in the reminder (format: YYYY-MM-DD)." },
                    { key: "message", type: "string", required: false, value: "Gentle reminder regarding your pending tax invoice.", description: "Custom message note included in the email notice." },
                    { key: "custom_subject", type: "string", required: false, value: "", description: "Custom email subject line override." }
                ],
                responses: {
                    "200": {
                        success: true,
                        type: "payment",
                        message: "Payment reminder sent successfully to rahul.sharma@example.com",
                        data: {
                            user_id: "usr_1",
                            email: "rahul.sharma@example.com",
                            invoice_number: "INV-2026-0001",
                            amount_due: 1180.00,
                            due_date: "2026-10-28",
                            payment_link: "https://zapreach.com/invoices/pay/INV-2026-0001",
                            sent_at: "2026-10-14T17:15:00+05:30"
                        }
                    },
                    "404": {
                        success: false,
                        status: false,
                        message: "Associated customer / user for this invoice could not be found."
                    }
                }
            }
        ]
    },
    {
        name: "Admin: Wallet & Manual Balance Management",
        icon: "fas fa-wallet",
        description: "Super Admin management of client prepaid wallets: manual balance adjustment (credit\/debit with audit notes), manual update history ledger, user wallet toggle, global wallet overview, and per-message pricing rates.",
        endpoints: [
            {
                id: "endpoint-admin-wallet-summary",
                name: "Admin: Get Wallet Summary & Overview",
                method: "GET",
                path: "\/api\/v1\/admin\/wallet\/summary",
                auth: true,
                description: "Retrieve platform-wide wallet statistics: count of wallet-enabled users, cumulative balance across users, total recharges via Razorpay, total usage deductions, total manual credits added by Super Admin, currency, and WhatsApp message pricing matrix.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    {
                        key: "Authorization",
                        value: "Bearer {token}",
                        description: "Super Admin Bearer token"
                    },
                    {
                        key: "Accept",
                        value: "application/json"
                    }
                ],
                pathParams: {},
                queryParams: {},
                bodyParams: {},
                responses: {
                    200: {
                        success: true,
                        data: {
                            walletEnabledUsers: 45,
                            totalWalletBalance: 12450.5,
                            totalCredits: 85000,
                            totalDebits: 72549.5,
                            totalRecharges: 75000,
                            totalUsage: 72549.5,
                            totalAdminCredits: 10000,
                            currency: "INR",
                            pricing: {
                                marketing: 0.85,
                                utility: 0.35,
                                authentication: 0.3,
                                service: 0
                            }
                        }
                    }
                }
            },
            {
                id: "endpoint-admin-wallet-users",
                name: "Admin: List Wallet Users",
                method: "GET",
                path: "\/api\/v1\/admin\/wallet\/users",
                auth: true,
                description: "List client company accounts with their wallet status, current wallet balance, and company details. Supports search and filtering by enabled status.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    {
                        key: "Authorization",
                        value: "Bearer {token}",
                        description: "Super Admin Bearer token"
                    },
                    {
                        key: "Accept",
                        value: "application/json"
                    }
                ],
                pathParams: {},
                queryParams: [
                    {
                        key: "wallet_enabled",
                        type: "boolean",
                        required: false,
                        value: "true",
                        description: "Filter users where wallet is enabled (true) or disabled (false)"
                    },
                    {
                        key: "search",
                        type: "string",
                        required: false,
                        value: "Rahul",
                        description: "Search by user name, email, phone number, or company name"
                    },
                    {
                        key: "page",
                        type: "integer",
                        required: false,
                        value: "1",
                        description: "Page number (default: 1)"
                    },
                    {
                        key: "limit",
                        type: "integer",
                        required: false,
                        value: "20",
                        description: "Records per page (default: 20, max: 100)"
                    }
                ],
                bodyParams: {},
                responses: {
                    200: {
                        success: true,
                        data: [
                            {
                                id: "usr_10",
                                dbId: 10,
                                name: "Rahul Sharma",
                                email: "rahul.sharma@example.com",
                                phone: "919173129119",
                                companyName: "Sharma Logistics Solutions",
                                walletEnabled: true,
                                walletBalance: 1500.5,
                                status: "active"
                            }
                        ],
                        meta: {
                            total: 45,
                            page: 1,
                            limit: 20,
                            totalPages: 3
                        }
                    }
                }
            },
            {
                id: "endpoint-admin-wallet-user-detail",
                name: "Admin: Get User Wallet & Adjustment History",
                method: "GET",
                path: "\/api\/v1\/admin\/wallet\/users\/{userId}",
                auth: true,
                description: "Inspect a specific user's wallet details, live balance, manual adjustment summary (total credited, total debited, net manual adjustment), and transaction ledger. Use manual_only=true to view Super Admin manual adjustments only.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    {
                        key: "Authorization",
                        value: "Bearer {token}",
                        description: "Super Admin Bearer token"
                    },
                    {
                        key: "Accept",
                        value: "application/json"
                    }
                ],
                pathParams: [
                    {
                        key: "userId",
                        type: "string",
                        required: true,
                        value: "10",
                        description: "Target user ID (e.g. 10 or usr_10)"
                    }
                ],
                queryParams: [
                    {
                        key: "manual_only",
                        type: "boolean",
                        required: false,
                        value: "true",
                        description: "Filter only Super Admin manual adjustments (source = 'admin')"
                    },
                    {
                        key: "page",
                        type: "integer",
                        required: false,
                        value: "1",
                        description: "Page number (default: 1)"
                    },
                    {
                        key: "limit",
                        type: "integer",
                        required: false,
                        value: "20",
                        description: "Records per page (default: 20, max: 100)"
                    }
                ],
                bodyParams: {},
                responses: {
                    200: {
                        success: true,
                        data: {
                            userId: "usr_10",
                            name: "Rahul Sharma",
                            email: "rahul.sharma@example.com",
                            phone: "919173129119",
                            walletEnabled: true,
                            walletBalance: 1500.5,
                            manualSummary: {
                                totalAdminCredit: 2000,
                                totalAdminDebit: 500,
                                netAdminAdjustment: 1500
                            },
                            transactions: [
                                {
                                    id: 142,
                                    transactionId: "TXN-142",
                                    type: "credit",
                                    source: "admin",
                                    is_manual_admin: true,
                                    amount: 500,
                                    balance_after: 1500.5,
                                    description: "Promotional bonus credit by Super Admin",
                                    created_at: "2026-10-13T12:00:00Z"
                                }
                            ]
                        },
                        meta: {
                            total: 1,
                            page: 1,
                            limit: 20,
                            totalPages: 1
                        }
                    }
                }
            },
            {
                id: "endpoint-admin-wallet-credit",
                name: "Admin: Credit \/ Debit User Wallet (Manual Adjustment)",
                method: "POST",
                path: "\/api\/v1\/admin\/wallet\/credit",
                auth: true,
                description: "Super Admin manual balance adjustment endpoint (aliased at \/api\/v1\/admin\/wallet\/adjust and \/api\/v1\/admin\/wallet\/users\/{userId}\/credit). Instantly credits or debits user prepaid wallet balance and creates an immutable audit transaction record with source = 'admin'.",
                contentType: "application\/json",
                bodyMode: "json",
                headers: [
                    {
                        key: "Authorization",
                        value: "Bearer {token}",
                        description: "Super Admin Bearer token"
                    },
                    {
                        key: "Content-Type",
                        value: "application/json"
                    },
                    {
                        key: "Accept",
                        value: "application/json"
                    }
                ],
                pathParams: {},
                queryParams: {},
                bodyParams: [
                    {
                        key: "userId",
                        type: "string",
                        required: true,
                        value: "10",
                        description: "Target user ID (supports numeric ID or usr_ prefix)"
                    },
                    {
                        key: "amount",
                        type: "numeric",
                        required: true,
                        value: "500",
                        description: "Amount in INR. Positive to credit, negative to debit (e.g. 500 or -200)"
                    },
                    {
                        key: "type",
                        type: "string",
                        required: false,
                        value: "credit",
                        description: "Explicit adjustment type: 'credit' or 'debit'"
                    },
                    {
                        key: "description",
                        type: "string",
                        required: false,
                        value: "Promotional bonus credit by Super Admin",
                        description: "Audit remarks or reason for manual adjustment"
                    }
                ],
                responses: {
                    200: {
                        success: true,
                        message: "Wallet credited successfully",
                        data: {
                            userId: "usr_10",
                            user: {
                                id: "usr_10",
                                name: "Rahul Sharma",
                                email: "rahul.sharma@example.com"
                            },
                            balance: 1500.5,
                            transaction: {
                                id: 142,
                                transactionId: "TXN-142",
                                type: "credit",
                                source: "admin",
                                amount: 500,
                                balance_after: 1500.5,
                                description: "Promotional bonus credit by Super Admin",
                                created_at: "2026-10-13T12:00:00Z"
                            }
                        }
                    }
                }
            },
            {
                id: "endpoint-admin-wallet-toggle",
                name: "Admin: Toggle User Wallet Feature",
                method: "POST",
                path: "\/api\/v1\/admin\/wallet\/users\/{userId}\/toggle",
                auth: true,
                description: "Enable or disable prepaid wallet feature for a specific company user. When enabled, broadcast campaigns deduct from prepaid wallet balance at per-message rates. When disabled, broadcast campaigns deduct from plan quota.",
                contentType: "application\/json",
                bodyMode: "json",
                headers: [
                    {
                        key: "Authorization",
                        value: "Bearer {token}",
                        description: "Super Admin Bearer token"
                    },
                    {
                        key: "Content-Type",
                        value: "application/json"
                    },
                    {
                        key: "Accept",
                        value: "application/json"
                    }
                ],
                pathParams: [
                    {
                        key: "userId",
                        type: "string",
                        required: true,
                        value: "10",
                        description: "Target user ID to toggle (e.g. 10 or usr_10)"
                    }
                ],
                queryParams: {},
                bodyParams: [
                    {
                        key: "wallet_enabled",
                        type: "boolean",
                        required: true,
                        value: "true",
                        description: "true to enable wallet, false to disable. Also accepted as 'walletEnabled'."
                    }
                ],
                responses: {
                    200: {
                        success: true,
                        message: "Wallet enabled for this user",
                        data: {
                            userId: "usr_10",
                            walletEnabled: true,
                            wallet_enabled: true,
                            balance: 1500.5
                        }
                    }
                }
            },
            {
                id: "endpoint-admin-wallet-manual-history",
                name: "Admin: Super Admin Manual Adjustment History",
                method: "GET",
                path: "\/api\/v1\/admin\/wallet\/manual-history",
                auth: true,
                description: "Dedicated platform audit ledger of all manual credit\/debit adjustments performed by Super Admin across all users (source = 'admin'). Filter by user, credit\/debit type, date range, or search keyword.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    {
                        key: "Authorization",
                        value: "Bearer {token}",
                        description: "Super Admin Bearer token"
                    },
                    {
                        key: "Accept",
                        value: "application/json"
                    }
                ],
                pathParams: {},
                queryParams: [
                    {
                        key: "user_id",
                        type: "string",
                        required: false,
                        value: "10",
                        description: "Filter by specific user ID"
                    },
                    {
                        key: "type",
                        type: "string",
                        required: false,
                        value: "credit",
                        description: "Filter by adjustment type: 'credit' or 'debit'"
                    },
                    {
                        key: "search",
                        type: "string",
                        required: false,
                        value: "bonus",
                        description: "Search by note, reference ID, user name, email, or company"
                    },
                    {
                        key: "date_from",
                        type: "string",
                        required: false,
                        value: "2026-10-01",
                        description: "Start date filter (YYYY-MM-DD)"
                    },
                    {
                        key: "date_to",
                        type: "string",
                        required: false,
                        value: "2026-10-31",
                        description: "End date filter (YYYY-MM-DD)"
                    },
                    {
                        key: "page",
                        type: "integer",
                        required: false,
                        value: "1",
                        description: "Page number (default: 1)"
                    },
                    {
                        key: "limit",
                        type: "integer",
                        required: false,
                        value: "20",
                        description: "Page size (default: 20, max: 100)"
                    }
                ],
                bodyParams: {},
                responses: {
                    200: {
                        success: true,
                        data: [
                            {
                                id: 142,
                                transactionId: "TXN-142",
                                transaction_type: "wallet",
                                is_plan: false,
                                is_wallet: true,
                                userId: 10,
                                user: {
                                    id: "usr_10",
                                    dbId: 10,
                                    name: "Rahul Sharma",
                                    email: "rahul.sharma@example.com",
                                    phone: "919173129119",
                                    companyName: "Sharma Logistics Solutions",
                                    walletEnabled: true,
                                    walletBalance: 1500.5
                                },
                                type: "credit",
                                source: "admin",
                                amount: 500,
                                balanceAfter: 1500.5,
                                balance_after: 1500.5,
                                description: "Compensation adjustment approved by Admin",
                                createdAt: "2026-10-13T12:00:00Z",
                                created_at: "2026-10-13T12:00:00Z"
                            }
                        ],
                        summary: {
                            totalCredit: 10000,
                            totalDebit: 1500,
                            net: 8500,
                            currency: "INR"
                        },
                        meta: {
                            total: 18,
                            page: 1,
                            limit: 20,
                            totalPages: 1
                        }
                    }
                }
            },
            {
                id: "endpoint-admin-wallet-transactions",
                name: "Admin: Platform All Wallet Transactions Ledger",
                method: "GET",
                path: "\/api\/v1\/admin\/wallet\/transactions",
                auth: true,
                description: "Complete platform transaction ledger across all users. Filter by transaction type ('wallet' vs 'plan'), source ('razorpay', 'admin', 'usage', 'refund'), category ('MARKETING', 'UTILITY', 'AUTHENTICATION'), date range, and keyword search.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    {
                        key: "Authorization",
                        value: "Bearer {token}",
                        description: "Super Admin Bearer token"
                    },
                    {
                        key: "Accept",
                        value: "application/json"
                    }
                ],
                pathParams: {},
                queryParams: [
                    {
                        key: "transaction_type",
                        type: "string",
                        required: false,
                        value: "wallet",
                        description: "Filter 'wallet' (prepaid conversation credits & recharges) or 'plan' (subscription orders/renewals)"
                    },
                    {
                        key: "source",
                        type: "string",
                        required: false,
                        value: "admin",
                        description: "Filter by source: 'razorpay', 'admin', 'usage', 'refund'"
                    },
                    {
                        key: "type",
                        type: "string",
                        required: false,
                        value: "credit",
                        description: "Filter 'credit' or 'debit'"
                    },
                    {
                        key: "category",
                        type: "string",
                        required: false,
                        value: "MARKETING",
                        description: "Filter WhatsApp message template category: 'MARKETING', 'UTILITY', 'AUTHENTICATION'"
                    },
                    {
                        key: "user_id",
                        type: "string",
                        required: false,
                        value: "10",
                        description: "Filter by target user ID"
                    },
                    {
                        key: "date_from",
                        type: "string",
                        required: false,
                        value: "2026-10-01",
                        description: "Start date (YYYY-MM-DD)"
                    },
                    {
                        key: "date_to",
                        type: "string",
                        required: false,
                        value: "2026-10-31",
                        description: "End date (YYYY-MM-DD)"
                    },
                    {
                        key: "date",
                        type: "string",
                        required: false,
                        value: "",
                        description: "Single specific date (YYYY-MM-DD)"
                    },
                    {
                        key: "search",
                        type: "string",
                        required: false,
                        value: "recharge",
                        description: "Search description, reference ID, user name, or company"
                    },
                    {
                        key: "page",
                        type: "integer",
                        required: false,
                        value: "1",
                        description: "Page number (default: 1)"
                    },
                    {
                        key: "limit",
                        type: "integer",
                        required: false,
                        value: "20",
                        description: "Records per page (default: 20, max: 100)"
                    }
                ],
                bodyParams: {},
                responses: {
                    200: {
                        success: true,
                        data: [
                            {
                                id: 142,
                                transactionId: "TXN-142",
                                transaction_type: "wallet",
                                is_plan: false,
                                is_wallet: true,
                                userId: 10,
                                user: {
                                    id: "usr_10",
                                    dbId: 10,
                                    name: "Rahul Sharma",
                                    email: "rahul.sharma@example.com",
                                    phone: "919173129119",
                                    companyName: "Sharma Logistics Solutions",
                                    walletEnabled: true,
                                    walletBalance: 1500.5
                                },
                                type: "credit",
                                source: "razorpay",
                                amount: 1000,
                                balanceAfter: 1500.5,
                                balance_after: 1500.5,
                                referenceId: "pay_Nx8Z98765Qwert",
                                reference_id: "pay_Nx8Z98765Qwert",
                                description: "Razorpay Wallet Recharge - Order #order_Nx8Y12345Abcde",
                                createdAt: "2026-10-13T11:45:00Z",
                                created_at: "2026-10-13T11:45:00Z"
                            }
                        ],
                        summary: {
                            totalCredit: 85000,
                            totalDebit: 72549.5,
                            net: 12450.5,
                            currency: "INR"
                        },
                        meta: {
                            total: 142,
                            page: 1,
                            limit: 20,
                            totalPages: 8
                        }
                    }
                }
            },
            {
                id: "endpoint-admin-wallet-pricing",
                name: "Admin: Update WhatsApp Message Pricing Matrix",
                method: "PUT",
                path: "\/api\/v1\/admin\/settings\/credentials\/wallet-pricing",
                auth: true,
                description: "Configure per-message deduction rates (INR) for each WhatsApp template conversation category: Marketing, Utility, and Authentication.",
                contentType: "application\/json",
                bodyMode: "json",
                headers: [
                    {
                        key: "Authorization",
                        value: "Bearer {token}",
                        description: "Super Admin Bearer token"
                    },
                    {
                        key: "Content-Type",
                        value: "application/json"
                    },
                    {
                        key: "Accept",
                        value: "application/json"
                    }
                ],
                pathParams: {},
                queryParams: {},
                bodyParams: [
                    {
                        key: "marketing",
                        type: "numeric",
                        required: true,
                        value: "0.85",
                        description: "Per-message rate for Marketing conversation template (INR)"
                    },
                    {
                        key: "utility",
                        type: "numeric",
                        required: true,
                        value: "0.35",
                        description: "Per-message rate for Utility conversation template (INR)"
                    },
                    {
                        key: "authentication",
                        type: "numeric",
                        required: true,
                        value: "0.30",
                        description: "Per-message rate for Authentication / OTP template (INR)"
                    },
                    {
                        key: "service",
                        type: "numeric",
                        required: false,
                        value: "0.00",
                        description: "Per-message rate for Service window replies (INR, default 0.00)"
                    }
                ],
                responses: {
                    200: {
                        success: true,
                        message: "Wallet pricing updated successfully",
                        data: {
                            marketing: 0.85,
                            utility: 0.35,
                            authentication: 0.3,
                            service: 0
                        }
                    }
                }
            }
        ]
    },
    {
        name: "Email Templates Management",
        icon: "fas fa-envelope-open-text",
        description: "Admin management for dynamic database email templates (Plan Purchase, Upgrade, Renewal, Custom Plan), HTML editing, image upload, live preview, and test emailing.",
        endpoints: [
            {
                id: "endpoint-admin-email-templates-list",
                name: "Admin: List Email Templates",
                method: "GET",
                path: "/api/v1/admin/email-templates",
                auth: true,
                description: "List all dynamic email templates stored in database.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    { key: "Authorization", value: "Bearer {token}", description: "Admin Bearer token" },
                    { key: "Accept", value: "application/json" }
                ],
                pathParams: [],
                queryParams: [
                    { key: "type", type: "string", required: false, value: "plan", description: "Filter by type (plan, marketing, etc.)" },
                    { key: "is_active", type: "boolean", required: false, value: "true", description: "Filter active or inactive" },
                    { key: "search", type: "string", required: false, value: "purchase", description: "Search by name, slug, or subject" }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        status: "success",
                        data: [
                            { id: 1, name: "Plan Purchase Notification", slug: "plan_purchase", type: "plan", subject: "Subscription Activated: @{{plan_name}} - @{{app_name}}", is_active: true }
                        ],
                        total: 4
                    }
                }
            },
            {
                id: "endpoint-admin-email-templates-placeholders",
                name: "Admin: Get Template Placeholders",
                method: "GET",
                path: "/api/v1/admin/email-templates/placeholders",
                auth: true,
                description: "Get list of all dynamic placeholder variables available for embedding in subject and body HTML.",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" }
                ],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        status: "success",
                        data: [
                            { key: "user_name", description: "User full name", sample: "John Doe" },
                            { key: "plan_name", description: "Subscription package name", sample: "Premium Business" },
                            { key: "total_amount", description: "Total payment amount", sample: "1,768.82" }
                        ]
                    }
                }
            },
            {
                id: "endpoint-admin-email-templates-show",
                name: "Admin: Get Email Template Details",
                method: "GET",
                path: "/api/v1/admin/email-templates/{id}",
                auth: true,
                description: "Get full details of a specific email template by ID or slug (e.g. plan_purchase).",
                contentType: "none",
                bodyMode: "none",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" }
                ],
                pathParams: [
                    { key: "id", type: "string", required: true, value: "1", description: "Template numeric ID or slug (e.g. plan_purchase)" }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        status: "success",
                        data: {
                            id: 1,
                            name: "Plan Purchase Notification",
                            slug: "plan_purchase",
                            subject: "Subscription Activated: @{{plan_name}} - @{{app_name}}",
                            body_html: "<!DOCTYPE html>...",
                            is_active: true
                        }
                    }
                }
            },
            {
                id: "endpoint-admin-email-templates-update",
                name: "Admin: Update Email Template",
                method: "PUT",
                path: "/api/v1/admin/email-templates/{id}",
                auth: true,
                description: "Update an existing email template's subject, HTML body, status, or name.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [
                    { key: "id", type: "string", required: true, value: "1", description: "Template ID or slug" }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "name", type: "string", required: false, value: "Plan Purchase Notification", description: "Template display name" },
                    { key: "subject", type: "string", required: false, value: "Welcome to @{{app_name}} - Plan Activated: @{{plan_name}}", description: "Email subject line" },
                    { key: "body_html", type: "string", required: false, value: "<!DOCTYPE html><html>...</html>", description: "Full HTML template content" },
                    { key: "is_active", type: "boolean", required: false, value: "true", description: "Active status" }
                ],
                responses: {
                    "200": {
                        success: true,
                        status: "success",
                        message: "Email template updated successfully"
                    }
                }
            },
            {
                id: "endpoint-admin-email-templates-upload-image",
                name: "Admin: Upload Template Image",
                method: "POST",
                path: "/api/v1/admin/email-templates/upload-image",
                auth: true,
                description: "Upload an image (PNG, JPG, SVG, WEBP up to 10MB) for embedding into email templates. Returns direct public URL and ready-to-use HTML snippet.",
                contentType: "multipart/form-data",
                bodyMode: "formdata",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" }
                ],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "image", type: "file", required: true, value: "banner.png", description: "Image file to upload" }
                ],
                responses: {
                    "200": {
                        success: true,
                        status: "success",
                        message: "Image uploaded successfully for email template",
                        data: {
                            url: "https://zapreach.com/uploads/email-templates/img_1791442272_UfmJrggPOp.png",
                            relative_url: "/uploads/email-templates/img_1791442272_UfmJrggPOp.png",
                            filename: "img_1791442272_UfmJrggPOp.png",
                            html_snippet: "<img src=\"https://zapreach.com/uploads/email-templates/img_1791442272_UfmJrggPOp.png\" alt=\"Image\" style=\"max-width: 100%; height: auto; border: 0;\" />"
                        }
                    }
                }
            },
            {
                id: "endpoint-admin-email-templates-preview",
                name: "Admin: Live Preview Template",
                method: "POST",
                path: "/api/v1/admin/email-templates/{id}/preview",
                auth: true,
                description: "Live preview rendered HTML of the email template with test data.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" }
                ],
                pathParams: [
                    { key: "id", type: "string", required: true, value: "1", description: "Template ID or slug" }
                ],
                queryParams: [
                    { key: "format", type: "string", required: false, value: "html", description: "Pass format=html to return direct rendered HTML" }
                ],
                bodyParams: [
                    { key: "subject", type: "string", required: false, value: "Test Subject", description: "Optional override subject" },
                    { key: "body_html", type: "string", required: false, value: "<p>Hello @{{user_name}}</p>", description: "Optional override HTML body" }
                ],
                responses: {
                    "200": {
                        success: true,
                        status: "success",
                        data: {
                            rendered_subject: "Subscription Activated: Premium Business - WhatsApp Bulk",
                            rendered_html: "<!DOCTYPE html>..."
                        }
                    }
                }
            },
            {
                id: "endpoint-admin-email-templates-test-send",
                name: "Admin: Send Test Email",
                method: "POST",
                path: "/api/v1/admin/email-templates/{id}/test-send",
                auth: true,
                description: "Send a test email using this template to a designated email address.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [
                    { key: "id", type: "string", required: true, value: "1", description: "Template ID or slug" }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "recipient_email", type: "string", required: true, value: "admin@example.com", description: "Destination email for test delivery" }
                ],
                responses: {
                    "200": {
                        success: true,
                        status: "success",
                        message: "Test email successfully dispatched to admin@example.com"
                    }
                }
            }
        ]
    },
    {
        name: "Invoice Templates Management",
        icon: "fas fa-file-invoice",
        description: "Admin customization of dynamic tax invoice design, branding, logo, and unified GST calculation (HTML & PDF).",
        endpoints: [
            {
                id: "endpoint-admin-invoice-templates-placeholders",
                name: "Admin: Get Invoice Placeholders",
                method: "GET",
                path: "/api/v1/admin/invoice-templates/placeholders",
                auth: true,
                description: "List all dynamic tokens available for invoice design customization (e.g. @{{invoice_number}}, @{{taxable_base}}, @{{gst_amount}}, @{{grand_total}}).",
                contentType: "application/json",
                bodyMode: "none",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Accept", value: "application/json" }
                ],
                pathParams: [],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        status: "success",
                        data: [
                            { key: "invoice_number", description: "Invoice sequential number" },
                            { key: "taxable_base", description: "Taxable base value formatted" },
                            { key: "gst_amount", description: "Unified GST tax amount" },
                            { key: "grand_total", description: "Final grand total amount" }
                        ]
                    }
                }
            },
            {
                id: "endpoint-admin-invoice-templates-list",
                name: "Admin: List Invoice Templates",
                method: "GET",
                path: "/api/v1/admin/invoice-templates",
                auth: true,
                description: "List all invoice design templates with active status and details.",
                contentType: "application/json",
                bodyMode: "none",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Accept", value: "application/json" }
                ],
                pathParams: [],
                queryParams: [
                    { key: "is_active", type: "boolean", required: false, value: "true", description: "Filter by active status" }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        status: "success",
                        data: [
                            {
                                id: 1,
                                name: "Default GST Tax Invoice",
                                slug: "default-gst-invoice",
                                subject_title: "TAX INVOICE",
                                accent_color: "#2563eb",
                                is_active: true
                            }
                        ],
                        total: 1
                    }
                }
            },
            {
                id: "endpoint-admin-invoice-templates-show",
                name: "Admin: Get Invoice Template",
                method: "GET",
                path: "/api/v1/admin/invoice-templates/{id}",
                auth: true,
                description: "Get full details including HTML template body and terms.",
                contentType: "application/json",
                bodyMode: "none",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Accept", value: "application/json" }
                ],
                pathParams: [
                    { key: "id", type: "string", required: true, value: "1", description: "Template ID or slug" }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        status: "success",
                        data: {
                            id: 1,
                            name: "Default GST Tax Invoice",
                            slug: "default-gst-invoice",
                            body_html: "<!DOCTYPE html>...",
                            is_active: true
                        }
                    }
                }
            },
            {
                id: "endpoint-admin-invoice-templates-upload-logo",
                name: "Admin: Upload Invoice Logo",
                method: "POST",
                path: "/api/v1/admin/invoice-templates/upload-logo",
                auth: true,
                description: "Upload invoice company brand logo to public storage.",
                contentType: "multipart/form-data",
                bodyMode: "formdata",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" }
                ],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "logo", type: "file", required: true, value: "logo.png", description: "Logo image file (png, jpg, svg, webp, max 3MB)" }
                ],
                responses: {
                    "200": {
                        success: true,
                        status: "success",
                        message: "Logo uploaded successfully",
                        data: {
                            filename: "logo_1791440000.png",
                            path: "uploads/invoice-templates/logo_1791440000.png",
                            url: "https://zapreach.com/uploads/invoice-templates/logo_1791440000.png"
                        }
                    }
                }
            },
            {
                id: "endpoint-admin-invoice-templates-update",
                name: "Admin: Update Invoice Template",
                method: "PUT",
                path: "/api/v1/admin/invoice-templates/{id}",
                auth: true,
                description: "Update template name, HTML layout, terms, accent color, and title.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [
                    { key: "id", type: "string", required: true, value: "1", description: "Template ID or slug" }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "subject_title", type: "string", required: false, value: "TAX INVOICE", description: "Invoice title" },
                    { key: "accent_color", type: "string", required: false, value: "#2563eb", description: "Hex accent color" },
                    { key: "body_html", type: "string", required: false, value: "<!DOCTYPE html>...", description: "Full HTML template" }
                ],
                responses: {
                    "200": {
                        success: true,
                        status: "success",
                        message: "Invoice template updated successfully"
                    }
                }
            },
            {
                id: "endpoint-admin-invoice-templates-toggle-status",
                name: "Admin: Activate Template",
                method: "POST",
                path: "/api/v1/admin/invoice-templates/{id}/toggle-status",
                auth: true,
                description: "Set this invoice template as the active default template for all invoice downloads.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [
                    { key: "id", type: "string", required: true, value: "1", description: "Template ID or slug" }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "is_active", type: "boolean", required: false, value: true, description: "Active status flag" }
                ],
                responses: {
                    "200": {
                        success: true,
                        status: "success",
                        message: "Invoice template activated"
                    }
                }
            },
            {
                id: "endpoint-admin-invoice-templates-preview",
                name: "Admin: Preview Invoice (HTML or PDF)",
                method: "POST",
                path: "/api/v1/admin/invoice-templates/{id}/preview",
                auth: true,
                description: "Preview rendered invoice HTML or stream PDF output.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [
                    { key: "id", type: "string", required: true, value: "1", description: "Template ID or slug" }
                ],
                queryParams: [],
                bodyParams: [
                    { key: "invoice_id", type: "string", required: false, value: "1", description: "Optional real invoice ID or blank for preview sample" },
                    { key: "format", type: "string", required: false, value: "html", description: "Output format: 'html' or 'pdf'" }
                ],
                responses: {
                    "200": {
                        success: true,
                        status: "success",
                        data: {
                            template_id: 1,
                            name: "Default GST Tax Invoice",
                            html: "<!DOCTYPE html>..."
                        }
                    }
                }
            },
            {
                id: "endpoint-admin-invoice-templates-reset-default",
                name: "Admin: Reset to Baseline Layout",
                method: "POST",
                path: "/api/v1/admin/invoice-templates/{id}/reset-default",
                auth: true,
                description: "Revert the template body_html and settings back to default factory baseline layout.",
                contentType: "application/json",
                bodyMode: "none",
                headers: [
                    { key: "Authorization", value: "Bearer {token}" },
                    { key: "Content-Type", value: "application/json" }
                ],
                pathParams: [
                    { key: "id", type: "string", required: true, value: "1", description: "Template ID or slug" }
                ],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        success: true,
                        status: "success",
                        message: "Template reset to default layout successfully"
                    }
                }
            }
        ]
    },
{
        name: "Knowledge Base & FAQs",
        icon: "fas fa-question-circle",
        description: "Manage business Q&A and knowledge items used by the AI Chatbot to answer customer queries accurately on WhatsApp.",
        endpoints: [
            {
                id: "endpoint-kb-list",
                name: "List FAQs / Knowledge Items",
                method: "GET",
                path: "/api/v1/knowledge-base",
                auth: true,
                description: "Retrieve all FAQs with optional search, category filters, and pagination.",
                contentType: "none",
                bodyMode: "none",
                headers: [{ key: "Authorization", value: "Bearer {token}" }],
                pathParams: [],
                queryParams: [
                    { key: "category", type: "string", required: false, value: "General", description: "Filter by category name" },
                    { key: "search", type: "string", required: false, value: "hours", description: "Search query string" },
                    { key: "page", type: "integer", required: false, value: "1", description: "Page number" },
                    { key: "limit", type: "integer", required: false, value: "20", description: "Items per page" }
                ],
                bodyParams: [],
                responses: {
                    "200": {
                        status: "success",
                        data: [
                            {
                                id: "faq_1712918400001",
                                numeric_id: 1,
                                question: "What are your business operating hours?",
                                answer: "We operate Monday to Saturday from 9:00 AM to 8:00 PM IST.",
                                category: "General",
                                keywords: ["hours", "timings", "open", "working time"],
                                source: "Manual",
                                is_published: true,
                                is_enabled: true
                            }
                        ],
                        total: 1
                    }
                }
            },
            {
                id: "endpoint-kb-store",
                name: "Create FAQ / Knowledge Item",
                method: "POST",
                path: "/api/v1/knowledge-base",
                auth: true,
                description: "Create a new question-and-answer pair for AI customer replies.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [{ key: "Authorization", value: "Bearer {token}" }],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "question", type: "string", required: true, value: "Which locations and areas do you serve?", description: "Customer question or topic" },
                    { key: "answer", type: "string", required: true, value: "We provide digital cloud solutions and WhatsApp marketing services nationwide across all cities in India.", description: "Verified business answer" },
                    { key: "category", type: "string", required: false, value: "Products & Services", description: "Category label" },
                    { key: "keywords", type: "array", required: false, value: "[\"area\", \"coverage\", \"location\"]", description: "Keywords for matching" },
                    { key: "source", type: "string", required: false, value: "Manual", description: "Source of knowledge item" },
                    { key: "is_published", type: "boolean", required: false, value: "true", description: "Published status" },
                    { key: "is_enabled", type: "boolean", required: false, value: "true", description: "Enabled for AI replies" }
                ],
                responses: {
                    "201": {
                        status: "success",
                        message: "Knowledge base item created successfully",
                        data: { id: "faq_1712918400002", question: "Which locations and areas do you serve?" }
                    }
                }
            },
            {
                id: "endpoint-kb-show",
                name: "Get FAQ Details",
                method: "GET",
                path: "/api/v1/knowledge-base/{id}",
                auth: true,
                description: "Retrieve a single knowledge item by ID or custom_id.",
                contentType: "none",
                bodyMode: "none",
                headers: [{ key: "Authorization", value: "Bearer {token}" }],
                pathParams: [{ key: "id", type: "string", required: true, value: "faq_1712918400002", description: "FAQ ID" }],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": {
                        status: "success",
                        data: { id: "faq_1712918400002", question: "Which locations and areas do you serve?" }
                    }
                }
            },
            {
                id: "endpoint-kb-update",
                name: "Update FAQ",
                method: "PUT",
                path: "/api/v1/knowledge-base/{id}",
                auth: true,
                description: "Update question, answer, category, keywords, or toggle enable/publish status.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [{ key: "Authorization", value: "Bearer {token}" }],
                pathParams: [{ key: "id", type: "string", required: true, value: "faq_1712918400002", description: "FAQ ID" }],
                queryParams: [],
                bodyParams: [
                    { key: "question", type: "string", required: false, value: "Which locations and areas do you serve?", description: "Updated question" },
                    { key: "answer", type: "string", required: false, value: "We provide nationwide pan-India service and international WhatsApp solutions.", description: "Updated answer" },
                    { key: "category", type: "string", required: false, value: "Products & Services", description: "Category label" },
                    { key: "is_published", type: "boolean", required: false, value: "true", description: "Published status" },
                    { key: "is_enabled", type: "boolean", required: false, value: "true", description: "Enabled status" }
                ],
                responses: {
                    "200": {
                        status: "success",
                        message: "Knowledge base item updated successfully"
                    }
                }
            },
            {
                id: "endpoint-kb-delete",
                name: "Delete FAQ",
                method: "DELETE",
                path: "/api/v1/knowledge-base/{id}",
                auth: true,
                description: "Delete a knowledge item permanently.",
                contentType: "none",
                bodyMode: "none",
                headers: [{ key: "Authorization", value: "Bearer {token}" }],
                pathParams: [{ key: "id", type: "string", required: true, value: "faq_1712918400002", description: "FAQ ID" }],
                queryParams: [],
                bodyParams: [],
                responses: {
                    "200": { status: "success", message: "Knowledge base item deleted successfully" }
                }
            },
            {
                id: "endpoint-kb-import-url",
                name: "Import FAQs from Website URL",
                method: "POST",
                path: "/api/v1/knowledge-base/import-url",
                auth: true,
                description: "Scrape a website FAQ URL and automatically import structured Q&A items.",
                contentType: "application/json",
                bodyMode: "json",
                headers: [{ key: "Authorization", value: "Bearer {token}" }],
                pathParams: [],
                queryParams: [],
                bodyParams: [
                    { key: "url", type: "string", required: true, value: "https://zapreach.com/faq", description: "Website URL to scrape" },
                    { key: "category", type: "string", required: false, value: "Website Knowledge", description: "Category to assign" }
                ],
                responses: {
                    "200": {
                        status: "success",
                        message: "Successfully imported 5 FAQ item(s) from website.",
                        imported_count: 5
                    }
                }
            }
        ]
    }
];

    </script>
    <script>
        let currentBaseUrl = 'https://zapreach.com';
        let currentToken = '';

        function renderApiDocs() {
            const sidebarMenu = document.getElementById('sidebar-menu-list');
            const epContainer = document.getElementById('endpoints-container');
            
            if (!sidebarMenu || !epContainer || !window.API_MODULES) return;

            sidebarMenu.innerHTML = '';
            epContainer.innerHTML = '';

            window.API_MODULES.forEach((module, modIdx) => {
                const folderId = 'folder-' + modIdx;

                // 1. Sidebar Folder
                const folderDiv = document.createElement('div');
                folderDiv.className = 'folder-item' + (modIdx > 1 ? ' collapsed' : '');
                folderDiv.id = folderId;

                let folderHeaderHtml = `
                    <div class="folder-header" onclick="toggleFolder('${folderId}')">
                        <span class="folder-name-container">
                            <i class="${module.icon} folder-icon"></i>
                            ${module.name}
                        </span>
                        <i class="fas fa-chevron-down folder-caret"></i>
                    </div>
                    <div class="folder-content">
                `;

                module.endpoints.forEach(ep => {
                    folderHeaderHtml += `
                        <a href="#${ep.id}" class="endpoint-link" data-id="${ep.id}" data-name="${ep.name.toLowerCase()}" data-path="${ep.path.toLowerCase()}" data-method="${ep.method.toLowerCase()}">
                            <span class="method-badge method-${ep.method}">${ep.method}</span>
                            <span>${ep.name}</span>
                        </a>
                    `;
                });

                folderHeaderHtml += `</div>`;
                folderDiv.innerHTML = folderHeaderHtml;
                sidebarMenu.appendChild(folderDiv);

                // 2. Main Endpoint Sections
                module.endpoints.forEach(ep => {
                    const epSection = document.createElement('div');
                    epSection.className = 'api-section';
                    epSection.id = ep.id;
                    epSection.style.display = 'none';

                    // Headers HTML
                    let headersHtml = '';
                    if (ep.auth || (ep.headers && ep.headers.length > 0)) {
                        headersHtml = `
                            <table class="params-table">
                                <thead>
                                    <tr>
                                        <th>Header Key</th>
                                        <th>Example Value</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${ep.auth ? `
                                    <tr>
                                        <td class="param-name">Authorization</td>
                                        <td class="param-example">Bearer {access_token}</td>
                                        <td class="param-desc">Bearer token obtained via <code>/api/auth/login</code> or <code>/api/auth/register</code>.</td>
                                    </tr>` : ''}
                                    ${(ep.headers || []).map(h => `
                                    <tr>
                                        <td class="param-name">${h.key}</td>
                                        <td class="param-example">${h.value}</td>
                                        <td class="param-desc">${h.description}</td>
                                    </tr>`).join('')}
                                </tbody>
                            </table>
                        `;
                    } else {
                        headersHtml = `<div class="no-params-msg">No special headers required.</div>`;
                    }

                    // Path Params HTML
                    let pathParamsHtml = '';
                    if (ep.pathParams && ep.pathParams.length > 0) {
                        pathParamsHtml = `
                            <div class="section-title">
                                <i class="fas fa-link"></i> Path Parameters
                            </div>
                            <table class="params-table">
                                <thead>
                                    <tr>
                                        <th>Parameter</th>
                                        <th>Type</th>
                                        <th>Example</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${ep.pathParams.map(pp => `
                                    <tr>
                                        <td class="param-name">${pp.key}</td>
                                        <td>
                                            <span class="param-type-badge">${pp.type}</span>
                                            <span class="param-req req-true">REQUIRED</span>
                                        </td>
                                        <td class="param-example">${pp.value}</td>
                                        <td class="param-desc">${pp.description}</td>
                                    </tr>`).join('')}
                                </tbody>
                            </table>
                        `;
                    }

                    // Query Params HTML
                    let queryParamsHtml = '';
                    if (ep.queryParams && ep.queryParams.length > 0) {
                        queryParamsHtml = `
                            <div class="section-title">
                                <i class="fas fa-filter"></i> Query Parameters
                            </div>
                            <table class="params-table">
                                <thead>
                                    <tr>
                                        <th>Parameter</th>
                                        <th>Type</th>
                                        <th>Example</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${ep.queryParams.map(qp => `
                                    <tr>
                                        <td class="param-name">${qp.key}</td>
                                        <td>
                                            <span class="param-type-badge">${qp.type}</span>
                                            <span class="param-req ${qp.required ? 'req-true' : 'req-false'}">${qp.required ? 'REQUIRED' : 'OPTIONAL'}</span>
                                        </td>
                                        <td class="param-example">${qp.value}</td>
                                        <td class="param-desc">${qp.description}</td>
                                    </tr>`).join('')}
                                </tbody>
                            </table>
                        `;
                    }

                    // Request Body HTML
                    let bodyParamsHtml = '';
                    if (ep.bodyParams && ep.bodyParams.length > 0) {
                        bodyParamsHtml = `
                            <table class="params-table">
                                <thead>
                                    <tr>
                                        <th>Field</th>
                                        <th>Type</th>
                                        <th>Example Value</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${ep.bodyParams.map(bp => `
                                    <tr>
                                        <td class="param-name">${bp.key}</td>
                                        <td>
                                            <span class="param-type-badge">${bp.type}</span>
                                            <span class="param-req ${bp.required ? 'req-true' : 'req-false'}">${bp.required ? 'REQUIRED' : 'OPTIONAL'}</span>
                                        </td>
                                        <td class="param-example"><code>${escapeHtml(bp.value)}</code></td>
                                        <td class="param-desc">${bp.description}</td>
                                    </tr>`).join('')}
                                </tbody>
                            </table>
                        `;
                    } else {
                        bodyParamsHtml = `<div class="no-params-msg">No request body required for this request.</div>`;
                    }

                    // Responses HTML
                    const resCodes = Object.keys(ep.responses || {});
                    let responseTabsHtml = '';
                    let responsePanelsHtml = '';

                    resCodes.forEach((code, cIdx) => {
                        const isErr = parseInt(code) >= 400;
                        const label = code === '200' ? '200 OK' : (code === '201' ? '201 CREATED' : (code === '400' ? '400 BAD REQUEST' : (code === '401' ? '401 UNAUTHORIZED' : (code === '403' ? '403 FORBIDDEN' : code + ' RESPONSE'))));
                        
                        responseTabsHtml += `
                            <button class="res-tab-btn ${cIdx === 0 ? 'active' : ''} ${isErr ? 'err-tab' : ''}" onclick="switchResponseTab('${ep.id}', '${code}', this)">
                                ${label}
                            </button>
                        `;

                        responsePanelsHtml += `
                            <div class="code-container res-panel-${ep.id}" id="res-${ep.id}-${code}" style="${cIdx === 0 ? 'display: block;' : 'display: none;'}">
                                <div class="code-header">
                                    <span>JSON Response (HTTP ${code})</span>
                                    <button class="btn-copy" onclick="copySnippet(this)">
                                        <i class="far fa-copy"></i> Copy
                                    </button>
                                </div>
                                <pre class="code-block"><code>${escapeHtml(JSON.stringify(ep.responses[code], null, 2))}</code></pre>
                            </div>
                        `;
                    });

                    epSection.innerHTML = `
                        <div class="api-section-header">
                            <div class="api-breadcrumbs">
                                <span>API</span>
                                <i class="fas fa-chevron-right" style="font-size: 0.6rem;"></i>
                                <span>${module.name}</span>
                            </div>
                            <div class="api-title-row">
                                <span class="method-badge method-${ep.method}" style="font-size: 0.85rem; padding: 4px 10px; width: auto;">${ep.method}</span>
                                <h2 class="api-title">${ep.name}</h2>
                                ${ep.auth ? `<span class="auth-badge auth-required"><i class="fas fa-lock"></i> Bearer Auth Required</span>` : `<span class="auth-badge auth-public"><i class="fas fa-globe"></i> Public Endpoint</span>`}
                            </div>
                            <div class="api-url-bar">
                                <span class="api-url ep-url-display" data-path="${ep.path}">${currentBaseUrl}${ep.path}</span>
                                <button class="btn-copy" onclick="copyEndpointUrl(this)" title="Copy URL">
                                    <i class="far fa-copy"></i> Copy
                                </button>
                            </div>
                        </div>

                        <div class="api-body">
                            <div class="api-left">
                                <div class="endpoint-desc">${ep.description}</div>

                                <div class="section-title">
                                    <i class="fas fa-id-card"></i> Request Headers
                                </div>
                                ${headersHtml}

                                ${pathParamsHtml}

                                ${queryParamsHtml}

                                <div class="section-title">
                                    <i class="fas fa-database"></i> Request Body (${(ep.bodyMode || 'none').toUpperCase()})
                                </div>
                                ${bodyParamsHtml}
                            </div>

                            <div class="api-right">
                                <div>
                                    <div class="section-title">
                                        <i class="fas fa-terminal"></i> cURL Command
                                    </div>
                                    <div class="code-container">
                                        <div class="code-header">
                                            <span>BASH / CURL</span>
                                            <button class="btn-copy" onclick="copySnippet(this)" title="Copy cURL">
                                                <i class="far fa-copy"></i> Copy
                                            </button>
                                        </div>
                                        <pre class="code-block"><code class="curl-code-snippet" data-ep='${escapeAttribute(JSON.stringify(ep))}'></code></pre>
                                    </div>
                                </div>

                                <div>
                                    <div class="section-title">
                                        <i class="fas fa-code"></i> Expected Response Schema
                                    </div>
                                    <div class="response-tabs" id="tabs-${ep.id}">
                                        ${responseTabsHtml}
                                    </div>
                                    ${responsePanelsHtml}
                                </div>
                            </div>
                        </div>
                    `;

                    epContainer.appendChild(epSection);
                });
            });

            handleRoute();
            renderAllCurlSnippets();
        }

        function escapeHtml(str) {
            if (typeof str !== 'string') str = String(str);
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function escapeAttribute(str) {
            if (typeof str !== 'string') str = String(str);
            return str.replace(/'/g, '&#39;').replace(/"/g, '&quot;');
        }

        function toggleFolder(folderId) {
            const folder = document.getElementById(folderId);
            if (folder) folder.classList.toggle('collapsed');
        }

        function copyEndpointUrl(btn) {
            const parent = btn.closest('.api-url-bar');
            const urlSpan = parent.querySelector('.ep-url-display');
            const path = urlSpan.getAttribute('data-path') || '';
            const fullUrl = currentBaseUrl + path;
            
            navigator.clipboard.writeText(fullUrl).then(() => {
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check" style="color: var(--primary);"></i> Copied!';
                setTimeout(() => { btn.innerHTML = originalHtml; }, 2000);
            });
        }

        function copySnippet(btn) {
            const pre = btn.closest('.code-container').querySelector('code');
            navigator.clipboard.writeText(pre.innerText).then(() => {
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check" style="color: var(--primary);"></i> Copied!';
                setTimeout(() => { btn.innerHTML = originalHtml; }, 2000);
            });
        }

        function switchResponseTab(epId, code, btn) {
            const container = document.getElementById('tabs-' + epId);
            container.querySelectorAll('.res-tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            document.querySelectorAll('.res-panel-' + epId).forEach(panel => panel.style.display = 'none');
            const target = document.getElementById('res-' + epId + '-' + code);
            if (target) target.style.display = 'block';
        }

        function updateBaseUrl(newUrl) {
            currentBaseUrl = newUrl;
            document.querySelectorAll('.ep-url-display').forEach(el => {
                const path = el.getAttribute('data-path') || '';
                el.innerText = currentBaseUrl + path;
            });
            renderAllCurlSnippets();
        }

        function updateBearerToken(token) {
            currentToken = token.trim();
            renderAllCurlSnippets();
        }

        function renderAllCurlSnippets() {
            document.querySelectorAll('.curl-code-snippet').forEach(el => {
                const rawData = el.getAttribute('data-ep');
                if (!rawData) return;
                let ep;
                try {
                    ep = JSON.parse(rawData);
                } catch (e) {
                    return;
                }

                let fullUrl = currentBaseUrl + ep.path;
                if (ep.queryParams && ep.queryParams.length > 0) {
                    const qpStrings = ep.queryParams.map(q => encodeURIComponent(q.key) + '=' + encodeURIComponent(q.value));
                    fullUrl += '?' + qpStrings.join('&');
                }

                let cmd = "curl --location --request " + ep.method + " '" + fullUrl + "' \\\n";

                if (ep.auth) {
                    const tokenStr = currentToken ? currentToken : '{access_token}';
                    cmd += "--header 'Authorization: Bearer " + tokenStr + "' \\\n";
                }

                if (ep.headers && ep.headers.length > 0) {
                    ep.headers.forEach(h => {
                        cmd += "--header '" + h.key + ": " + h.value + "' \\\n";
                    });
                }

                if (ep.bodyMode === 'json' && ep.bodyParams && ep.bodyParams.length > 0) {
                    cmd += "--header 'Content-Type: application/json' \\\n";
                    const jsonObj = {};
                    ep.bodyParams.forEach(bp => {
                        try {
                            if (bp.value.startsWith('{') || bp.value.startsWith('[')) {
                                jsonObj[bp.key] = JSON.parse(bp.value);
                            } else if (bp.type === 'integer' || bp.type === 'numeric') {
                                jsonObj[bp.key] = Number(bp.value);
                            } else if (bp.type === 'boolean') {
                                jsonObj[bp.key] = bp.value === 'true';
                            } else {
                                jsonObj[bp.key] = bp.value;
                            }
                        } catch (e) {
                            jsonObj[bp.key] = bp.value;
                        }
                    });
                    cmd += "--data-raw '" + JSON.stringify(jsonObj, null, 2).replace(/'/g, "\\'") + "' \\\n";
                } else if (ep.bodyMode === 'formdata' && ep.bodyParams && ep.bodyParams.length > 0) {
                    ep.bodyParams.forEach(bp => {
                        if (bp.type === 'file') {
                            cmd += "--form '" + bp.key + "=@\"/path/to/" + bp.value + "\"' \\\n";
                        } else {
                            cmd += "--form '" + bp.key + "=\"" + bp.value + "\"' \\\n";
                        }
                    });
                }

                cmd = cmd.replace(/ \\\n$/, '');
                el.innerText = cmd;
            });
        }

        // Routing
        window.addEventListener('hashchange', handleRoute);

        function handleRoute() {
            const hash = window.location.hash;
            const welcome = document.getElementById('welcome-screen');
            
            document.querySelectorAll('.api-section').forEach(s => s.style.display = 'none');
            document.querySelectorAll('.endpoint-link').forEach(l => l.classList.remove('active'));

            if (!hash) {
                welcome.style.display = 'flex';
                return;
            }

            welcome.style.display = 'none';
            const targetSection = document.querySelector(hash);
            if (targetSection) {
                targetSection.style.display = 'block';
                const activeLink = document.querySelector(`.endpoint-link[href="${hash}"]`);
                if (activeLink) {
                    activeLink.classList.add('active');
                    let parent = activeLink.closest('.folder-item');
                    while (parent) {
                        parent.classList.remove('collapsed');
                        parent = parent.parentElement.closest('.folder-item');
                    }
                }
            } else {
                welcome.style.display = 'flex';
            }
        }

        // Instant Search Filter
        document.getElementById('search').addEventListener('input', function(e) {
            const val = e.target.value.toLowerCase().trim();
            const links = document.querySelectorAll('.endpoint-link');
            
            links.forEach(link => {
                const name = link.getAttribute('data-name') || '';
                const path = link.getAttribute('data-path') || '';
                const method = link.getAttribute('data-method') || '';
                
                if (!val || name.includes(val) || path.includes(val) || method.includes(val)) {
                    link.style.display = 'flex';
                } else {
                    link.style.display = 'none';
                }
            });

            document.querySelectorAll('.folder-item').forEach(folder => {
                if (!val) {
                    folder.style.display = 'block';
                    return;
                }
                const visibleChildren = folder.querySelectorAll('.endpoint-link[style="display: flex;"]');
                if (visibleChildren.length > 0) {
                    folder.style.display = 'block';
                    folder.classList.remove('collapsed');
                } else {
                    folder.style.display = 'none';
                }
            });
        });

        // Initialize
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', renderApiDocs);
        } else {
            renderApiDocs();
        }
    </script>
</body>
</html>
