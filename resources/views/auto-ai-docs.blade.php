<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto AI & User-wise API Docs | ZapReach</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0c1220;
            --panel: #121a2b;
            --card: #182235;
            --line: #2a364d;
            --text: #e8eef8;
            --muted: #93a0b8;
            --green: #22c55e;
            --blue: #3b82f6;
            --amber: #f59e0b;
            --red: #ef4444;
            --cyan: #22d3ee;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'DM Sans', sans-serif;
            background: radial-gradient(1200px 600px at 10% -10%, #1a2744 0%, var(--bg) 45%);
            color: var(--text);
            line-height: 1.55;
            min-height: 100vh;
        }
        .wrap { max-width: 980px; margin: 0 auto; padding: 32px 20px 64px; }
        .top {
            display: flex; flex-wrap: wrap; gap: 12px; justify-content: space-between; align-items: flex-start;
            margin-bottom: 28px;
        }
        h1 { font-size: 1.75rem; letter-spacing: -0.03em; }
        h3 { font-size: .95rem; margin: 16px 0 8px; color: var(--text); }
        .sub { color: var(--muted); margin-top: 6px; max-width: 720px; }
        .badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(34,197,94,.12); color: var(--green);
            border: 1px solid rgba(34,197,94,.35); border-radius: 999px;
            padding: 8px 14px; font-size: .85rem; font-weight: 600;
        }
        .card {
            background: var(--card); border: 1px solid var(--line); border-radius: 14px;
            padding: 18px 18px 16px; margin-bottom: 16px;
        }
        .card h2 { font-size: 1.05rem; margin-bottom: 10px; }
        .card p, .card li { color: var(--muted); font-size: .95rem; }
        .card ul { padding-left: 18px; }
        .card li { margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: .9rem; }
        th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { color: var(--muted); font-weight: 600; font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; }
        .endpoint {
            background: var(--panel); border: 1px solid var(--line); border-radius: 14px;
            margin-bottom: 18px; overflow: hidden;
        }
        .endpoint-head {
            display: flex; flex-wrap: wrap; gap: 10px; align-items: center;
            padding: 14px 16px; border-bottom: 1px solid var(--line);
        }
        .method {
            font-family: 'IBM Plex Mono', monospace; font-size: .75rem; font-weight: 700;
            padding: 4px 8px; border-radius: 6px; color: #041016;
        }
        .get { background: var(--green); }
        .post { background: var(--blue); color: #fff; }
        .put { background: var(--amber); color: #111; }
        .delete { background: var(--red); color: #fff; }
        .path {
            font-family: 'IBM Plex Mono', monospace; font-size: .9rem; color: var(--cyan);
            word-break: break-all;
        }
        .endpoint-body { padding: 16px; }
        .label {
            font-size: .75rem; font-weight: 700; color: var(--muted);
            text-transform: uppercase; letter-spacing: .05em; margin: 14px 0 8px;
        }
        .endpoint-body > .label:first-child { margin-top: 0; }
        pre {
            background: #0a101c; border: 1px solid var(--line); border-radius: 10px;
            padding: 12px 14px; overflow-x: auto;
            font-family: 'IBM Plex Mono', monospace; font-size: .82rem; color: #d7e3f7;
        }
        code { font-family: 'IBM Plex Mono', monospace; font-size: .86rem; color: #c7d7f5; }
        .nav {
            display: flex; flex-wrap: wrap; gap: 8px; margin: 18px 0 8px;
        }
        .nav a {
            color: var(--text); text-decoration: none; font-size: .85rem;
            background: var(--panel); border: 1px solid var(--line);
            border-radius: 999px; padding: 7px 12px;
        }
        .nav a:hover { border-color: var(--cyan); color: var(--cyan); }
        .share {
            margin-top: 8px; font-size: .85rem; color: var(--muted);
            word-break: break-all;
        }
        .share strong { color: var(--text); }
        .ok { color: var(--green); font-weight: 600; }
        .warn { color: var(--amber); }
        .section-title {
            font-size: 1.15rem; margin: 28px 0 12px; padding-bottom: 8px;
            border-bottom: 1px solid var(--line); color: var(--cyan);
        }
        footer { margin-top: 28px; color: var(--muted); font-size: .85rem; }
        a.main-docs { color: var(--cyan); }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1>Auto AI & User-wise API Docs</h1>
            <p class="sub">Frontend guide for Auto AI, Settings (API Configuration + Business Details), Media Library, Templates, Campaigns, and Wallet (Razorpay). All list/stats endpoints are scoped to the logged-in user (company account).</p>
            <p class="share"><strong>Share this page:</strong> <span id="shareUrl"></span></p>
        </div>
        <div class="badge">User-wise data · Groq / Gemini AI</div>
    </div>

    <div class="nav">
        <a href="#auth">Auth</a>
        <a href="#user-scope">User scope</a>
        <a href="#ai-providers">AI providers</a>
        <a href="#get-settings">Settings GET</a>
        <a href="#save-config">Save config</a>
        <a href="#update-auto-ai">Business details</a>
        <a href="#connect-facebook">Connect FB</a>
        <a href="#chat-toggle">Chat toggle</a>
        <a href="#media">Media</a>
        <a href="#templates">Templates</a>
        <a href="#campaigns">Campaigns</a>
        <a href="#wallet">Wallet</a>
    </div>

    <div class="card" id="auth">
        <h2>Base URL & Auth</h2>
        <p><strong>Base URL:</strong> <code id="baseUrl"></code></p>
        <p style="margin-top:8px"><strong>Headers:</strong></p>
        <pre>Authorization: Bearer {token}
Content-Type: application/json</pre>
        <p style="margin-top:10px">All routes below require a logged-in user token (Passport). Staff users inherit their parent company <code>userId</code>.</p>
    </div>

    <div class="card" id="user-scope">
        <h2>User-wise data (important)</h2>
        <p>Every module below returns data for the <strong>logged-in company only</strong> — not shared/global rows.</p>
        <table>
            <thead><tr><th>Rule</th><th>Detail</th></tr></thead>
            <tbody>
                <tr><td>Owner ID</td><td><code>userId = auth.user.getCompanyId()</code> (staff → parent company)</td></tr>
                <tr><td>No global fallback</td><td>If user has no row yet, API returns empty defaults — never another user's settings/media/templates</td></tr>
                <tr><td>Admin override</td><td><code>?user_id=12</code> — view/manage one account (admin only)</td></tr>
                <tr><td>Admin global</td><td><code>?all=1</code> — all users (admin panel only)</td></tr>
                <tr><td>Response meta</td><td>Most endpoints include <code>meta.userId</code> so frontend can verify scope</td></tr>
            </tbody>
        </table>
    </div>

    <div class="card" id="ai-providers">
        <h2>Auto AI providers (.env on server)</h2>
        <p>Auto replies try live AI first; static templates only if all providers fail.</p>
        <table>
            <thead><tr><th>Priority</th><th>Provider</th><th>Env key</th></tr></thead>
            <tbody>
                <tr><td>1</td><td>Claude / Anthropic (recommended)</td><td><code>CLAUDE_API_KEY</code> or <code>ANTHROPIC_API_KEY</code> + <code>CLAUDE_MODEL=claude-3-5-haiku-20241022</code></td></tr>
                <tr><td>2</td><td>Groq (fast)</td><td><code>GROQ_API_KEY</code> + <code>GROQ_MODEL=openai/gpt-oss-20b</code></td></tr>
                <tr><td>3</td><td>Gemini</td><td><code>GEMINI_API_KEY</code> + <code>GEMINI_MODEL=gemini-2.5-flash</code></td></tr>
                <tr><td>4</td><td>OpenAI</td><td><code>OPENAI_API_KEY</code> + <code>OPENAI_MODEL=gpt-4o-mini</code></td></tr>
                <tr><td>5</td><td>OpenRouter</td><td><code>OPENROUTER_API_KEY</code></td></tr>
                <tr><td>6</td><td>Pollinations (no key)</td><td>fallback only</td></tr>
            </tbody>
        </table>
        <p style="margin-top:10px">After changing server keys: <code>php artisan config:clear</code>. Outgoing messages include <code>metadata.auto_ai_provider</code> (e.g. <code>claude</code>, <code>groq</code>, <code>gemini</code>, <code>local_fallback</code>).</p>
    </div>

    <div class="card" id="logic">
        <h2>Auto AI toggle logic</h2>
        <ul>
            <li><strong>Settings Auto AI</strong> = per-user master switch (from that user's Business Details)</li>
            <li><strong>Chat Auto AI</strong> = per-conversation override (default ON)</li>
            <li>Uses business profile from <strong>this user's</strong> settings row + chat history</li>
            <li>Matches English / Hindi / Hinglish based on customer message</li>
        </ul>
        <table>
            <thead><tr><th>Global (user settings)</th><th>Chat</th><th>Auto reply?</th></tr></thead>
            <tbody>
                <tr><td>OFF</td><td>any</td><td class="warn">No</td></tr>
                <tr><td>ON</td><td>ON</td><td class="ok">Yes</td></tr>
                <tr><td>ON</td><td>OFF</td><td class="warn">No (that chat only)</td></tr>
            </tbody>
        </table>
    </div>

    <h2 class="section-title">Settings APIs</h2>

    <section class="endpoint" id="get-settings">
        <div class="endpoint-head">
            <span class="method get">GET</span>
            <span class="path">/api/settings</span>
        </div>
        <div class="endpoint-body">
            <div class="label">Description</div>
            <p style="color:var(--muted)">Load <strong>this user's</strong> API Configuration + Business Details. No global/shared fallback.</p>
            <div class="label">Query (admin only)</div>
            <pre>?user_id=12</pre>
            <div class="label">Response 200</div>
            <pre>{
  "status": true,
  "meta": { "userId": 12 },
  "data": {
    "userId": 12,
    "phoneNumberId": "947220191813759",
    "waba_id": "1222564922629604",
    "isActive": true,
    "auto_ai_enabled": true,
    "business_name": "Sharma Grocery",
    "business_type": "grocery",
    "business_details": "Rice, flour, oil, spices.",
    "business_hours": "9 AM - 9 PM",
    "auto_ai_tone": "friendly"
  },
  "apiConfiguration": {
    "fb_app_id": "...",
    "fb_app_secret": "...",
    "phoneNumberId": "...",
    "waba_id": "...",
    "access_token": "...",
    "webhook_url": "...",
    "isActive": true,
    "expires_in": 5184000,
    "expiry_date": "2026-12-01T00:00:00.000000Z"
  },
  "businessDetails": {
    "auto_ai_enabled": true,
    "business_name": "Sharma Grocery",
    "business_type": "grocery",
    "business_details": "Rice, flour, oil, spices.",
    "business_hours": "9 AM - 9 PM",
    "auto_ai_tone": "friendly"
  }
}</pre>
            <div class="label">Response 200 (no settings yet)</div>
            <pre>{
  "status": true,
  "message": "No settings saved yet for this account",
  "meta": { "userId": 12 },
  "data": { "userId": 12, "auto_ai_enabled": false, "isActive": false },
  "apiConfiguration": { ...empty... },
  "businessDetails": { ...empty... }
}</pre>
        </div>
    </section>

    <section class="endpoint" id="save-config">
        <div class="endpoint-head">
            <span class="method post">POST</span>
            <span class="path">/api/settings/save-config</span>
        </div>
        <div class="endpoint-body">
            <div class="label">Description</div>
            <p style="color:var(--muted)">Save API Configuration + Business Details for the logged-in user. Setting <code>isActive: true</code> only deactivates <strong>this user's</strong> other configs — not every account on the platform.</p>
            <div class="label">Request body</div>
            <pre>{
  "fb_app_id": "...",
  "fb_app_secret": "...",
  "phoneNumberId": "...",
  "waba_id": "...",
  "access_token": "...",
  "webhook_url": "...",
  "isActive": true,
  "auto_ai_enabled": true,
  "business_name": "Sharma Grocery",
  "business_type": "grocery",
  "business_details": "We sell rice, flour, oil, spices.",
  "business_hours": "9 AM - 9 PM",
  "auto_ai_tone": "friendly"
}</pre>
            <div class="label">Response 200</div>
            <pre>{
  "status": true,
  "message": "Settings saved successfully",
  "meta": { "userId": 12 },
  "data": { ... },
  "apiConfiguration": { ... },
  "businessDetails": { ... }
}</pre>
        </div>
    </section>

    <section class="endpoint" id="update-auto-ai">
        <div class="endpoint-head">
            <span class="method post">POST</span>
            <span class="path">/api/settings/auto-ai</span>
        </div>
        <div class="endpoint-body">
            <div class="label">Also allowed</div>
            <p style="color:var(--muted)"><code>PUT /api/settings/auto-ai</code></p>
            <div class="label">Description</div>
            <p style="color:var(--muted)">Update Business Details / Auto AI profile for this user only.</p>
            <div class="label">Request body</div>
            <pre>{
  "business_name": "ZapReach Solutions",
  "business_type": "B2B SaaS / Marketing Agency",
  "industry": "Software & Information Technology",
  "business_details": "We provide official WhatsApp Business API broadcast software, conversational AI chatbots, and lead automation tools for businesses to scale customer engagement and order handling.",
  "services": "WhatsApp Bulk Messaging, Official Meta API Setup, Automated Chatbot Flows, Customer Support Routing",
  "products": "Starter Messaging Plan, Growth Plan, Enterprise Unlimited Broadcast, Custom API Integration",
  "phone": "+91 98765 43210",
  "email": "support@zapreach.com",
  "website": "https://zapreach.com",
  "location": "Pan-India & Global Digital Services",
  "address": "Suite 402, Business Hub, Tech Park, Bangalore, Karnataka - 560001",
  "business_hours": "09:00 AM - 08:00 PM",
  "working_days": [
    "Monday",
    "Tuesday",
    "Wednesday",
    "Thursday",
    "Friday",
    "Saturday"
  ],
  "language": "English, Hindi, Hinglish",
  "auto_ai_enabled": true,
  "auto_ai_tone": "friendly",
  "first_time_message": "Hello! Welcome to ZapReach Solutions. How can we assist you today with your WhatsApp broadcast or software setup?"
}</pre>
            <div class="label">Fields</div>
            <table>
                <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Notes</th></tr></thead>
                <tbody>
                    <tr><td><code>business_name</code></td><td>string</td><td>No</td><td>Company or brand name (max 255)</td></tr>
                    <tr><td><code>business_type</code></td><td>string</td><td>No</td><td>Business category or model (max 255)</td></tr>
                    <tr><td><code>industry</code></td><td>string</td><td>No</td><td>Industry domain (e.g. Software & IT, Retail, Healthcare)</td></tr>
                    <tr><td><code>business_details</code></td><td>string</td><td>No</td><td>Knowledge base summary used in AI replies (max 5000)</td></tr>
                    <tr><td><code>services</code></td><td>string</td><td>No</td><td>List of services offered (max 5000)</td></tr>
                    <tr><td><code>products</code></td><td>string</td><td>No</td><td>Core products or packages (max 5000)</td></tr>
                    <tr><td><code>phone</code></td><td>string</td><td>No</td><td>Customer helpline / support phone number (alias: <code>support_phone</code>)</td></tr>
                    <tr><td><code>email</code></td><td>string</td><td>No</td><td>Official support email (alias: <code>support_email</code>)</td></tr>
                    <tr><td><code>website</code></td><td>string</td><td>No</td><td>Official website URL (alias: <code>website_url</code>)</td></tr>
                    <tr><td><code>location</code></td><td>string</td><td>No</td><td>Operating location or service coverage area</td></tr>
                    <tr><td><code>address</code></td><td>string</td><td>No</td><td>Physical office or store address (alias: <code>business_address</code>)</td></tr>
                    <tr><td><code>business_hours</code></td><td>string</td><td>No</td><td>Operating hours (e.g. 09:00 AM - 08:00 PM)</td></tr>
                    <tr><td><code>working_days</code></td><td>array</td><td>No</td><td>Array of operational weekdays, e.g. <code>["Monday", "Tuesday", ...]</code></td></tr>
                    <tr><td><code>language</code></td><td>string</td><td>No</td><td>Supported languages (e.g. English, Hindi, Hinglish)</td></tr>
                    <tr><td><code>auto_ai_enabled</code></td><td>boolean</td><td>No</td><td>Master switch for Auto AI replies (true / false)</td></tr>
                    <tr><td><code>auto_ai_tone</code></td><td>string</td><td>No</td><td>AI tone: <code>friendly</code>, <code>professional</code>, <code>concise</code></td></tr>
                    <tr><td><code>first_time_message</code></td><td>string</td><td>No</td><td>Welcome greeting sent to first-time customer messages</td></tr>
                </tbody>
            </table>
            <div class="label">Response 200</div>
            <pre>{
  "status": true,
  "message": "Auto AI settings updated successfully",
  "meta": { "userId": 12 },
  "data": { ... },
  "businessDetails": {
    "business_name": "ZapReach Solutions",
    "business_type": "B2B SaaS / Marketing Agency",
    "industry": "Software & Information Technology",
    "business_details": "We provide official WhatsApp Business API broadcast software...",
    "services": "WhatsApp Bulk Messaging, Official Meta API Setup...",
    "products": "Starter Messaging Plan, Growth Plan...",
    "phone": "+91 98765 43210",
    "email": "support@zapreach.com",
    "website": "https://zapreach.com",
    "location": "Pan-India & Global Digital Services",
    "address": "Suite 402, Business Hub, Tech Park, Bangalore, Karnataka - 560001",
    "business_hours": "09:00 AM - 08:00 PM",
    "working_days": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"],
    "language": "English, Hindi, Hinglish",
    "auto_ai_enabled": true,
    "auto_ai_tone": "friendly",
    "first_time_message": "Hello! Welcome to ZapReach Solutions..."
  }
}</pre>
        </div>
    </section>

    <h2 class="section-title">Knowledge Base & FAQs APIs</h2>

    <section class="endpoint" id="kb-list">
        <div class="endpoint-head">
            <span class="method get">GET</span>
            <span class="path">/api/v1/knowledge-base</span>
        </div>
        <div class="endpoint-body">
            <div class="label">Description</div>
            <p style="color:var(--muted)">Get All FAQs / Knowledge Base items for this user. Optional query filters: <code>?category=General&search=hours&page=1&limit=20</code>.</p>
            <div class="label">Response 200</div>
            <pre>{
  "status": "success",
  "data": [
    {
      "id": "faq_1712918400001",
      "question": "What are your business operating hours?",
      "answer": "We operate Monday to Saturday from 9:00 AM to 8:00 PM IST.",
      "category": "General",
      "keywords": ["hours", "timings", "open", "working time"],
      "source": "Manual",
      "source_url": null,
      "is_published": true,
      "is_enabled": true,
      "created_at": "2026-10-12T06:00:00.000Z",
      "updated_at": "2026-10-12T06:10:00.000Z"
    }
  ],
  "total": 1
}</pre>
        </div>
    </section>

    <section class="endpoint" id="kb-create">
        <div class="endpoint-head">
            <span class="method post">POST</span>
            <span class="path">/api/v1/knowledge-base</span>
        </div>
        <div class="endpoint-body">
            <div class="label">Description</div>
            <p style="color:var(--muted)">Create a new FAQ / Knowledge Base item for AI auto-replies.</p>
            <div class="label">Request body</div>
            <pre>{
  "question": "Which locations and areas do you serve?",
  "answer": "We provide digital cloud solutions and WhatsApp marketing services nationwide across all cities in India.",
  "category": "Products & Services",
  "keywords": ["area", "coverage", "location", "service area", "delivery"],
  "source": "Manual",
  "is_published": true,
  "is_enabled": true
}</pre>
            <div class="label">Response 201</div>
            <pre>{
  "status": "success",
  "message": "Knowledge base item created successfully",
  "data": {
    "id": "faq_1712918400002",
    "question": "Which locations and areas do you serve?",
    "answer": "We provide digital cloud solutions and WhatsApp marketing services nationwide across all cities in India.",
    "category": "Products & Services",
    "keywords": ["area", "coverage", "location", "service area", "delivery"],
    "source": "Manual",
    "source_url": null,
    "is_published": true,
    "is_enabled": true
  }
}</pre>
        </div>
    </section>

    <section class="endpoint" id="kb-update">
        <div class="endpoint-head">
            <span class="method put">PUT</span>
            <span class="path">/api/v1/knowledge-base/{id}</span>
        </div>
        <div class="endpoint-body">
            <div class="label">Description</div>
            <p style="color:var(--muted)">Update FAQ item or toggle status (enable/disable, publish/draft). Also supports PATCH / POST.</p>
            <div class="label">Request body</div>
            <pre>{
  "question": "Which locations and areas do you serve?",
  "answer": "We provide nationwide pan-India service and international WhatsApp solutions.",
  "category": "Products & Services",
  "keywords": ["area", "delivery", "locations", "india"],
  "is_published": true,
  "is_enabled": true
}</pre>
            <div class="label">Response 200</div>
            <pre>{
  "status": "success",
  "message": "Knowledge base item updated successfully",
  "data": { ... }
}</pre>
        </div>
    </section>

    <section class="endpoint" id="kb-delete">
        <div class="endpoint-head">
            <span class="method delete">DELETE</span>
            <span class="path">/api/v1/knowledge-base/{id}</span>
        </div>
        <div class="endpoint-body">
            <div class="label">Description</div>
            <p style="color:var(--muted)">Delete a Knowledge Base / FAQ item permanently.</p>
            <div class="label">Response 200</div>
            <pre>{
  "status": "success",
  "message": "Knowledge base item deleted successfully"
}</pre>
        </div>
    </section>

    <section class="endpoint" id="kb-import-url">
        <div class="endpoint-head">
            <span class="method post">POST</span>
            <span class="path">/api/v1/knowledge-base/import-url</span>
        </div>
        <div class="endpoint-body">
            <div class="label">Description</div>
            <p style="color:var(--muted)">Scrapes a website FAQ URL and automatically imports structured Q&A items into the knowledge base.</p>
            <div class="label">Request body</div>
            <pre>{
  "url": "https://zapreach.com/faq",
  "category": "Website Knowledge"
}</pre>
            <div class="label">Response 200</div>
            <pre>{
  "status": "success",
  "message": "Successfully imported 5 FAQ item(s) from website.",
  "imported_count": 5,
  "data": [ ... ]
}</pre>
        </div>
    </section>

    <section class="endpoint" id="connect-facebook">
        <div class="endpoint-head">
            <span class="method post">POST</span>
            <span class="path">/api/settings/connect-facebook</span>
        </div>
        <div class="endpoint-body">
            <div class="label">Description</div>
            <p style="color:var(--muted)">Connect Facebook/WhatsApp for this user only.</p>
            <div class="label">Request body</div>
            <pre>{
  "access_token": "...",
  "waba_id": "...",
  "phoneNumberId": "..."
}</pre>
        </div>
    </section>

    <section class="endpoint">
        <div class="endpoint-head">
            <span class="method post">POST</span>
            <span class="path">/api/settings/refresh-token</span>
        </div>
        <div class="endpoint-body">
            <div class="label">Description</div>
            <p style="color:var(--muted)">Refresh Meta token for the logged-in user's settings row.</p>
        </div>
    </section>

    <h2 class="section-title">Auto AI chat APIs</h2>

    <section class="endpoint" id="chat-toggle">
        <div class="endpoint-head">
            <span class="method post">POST</span>
            <span class="path">/api/conversations/{id}/ai-reply</span>
        </div>
        <div class="endpoint-body">
            <div class="label">Request body</div>
            <pre>{ "ai_reply_enabled": true }</pre>
            <div class="label">Response 200</div>
            <pre>{
  "success": true,
  "ai_reply_enabled": true,
  "auto_ai_enabled": true,
  "message": "Auto AI enabled for this chat"
}</pre>
        </div>
    </section>

    <section class="endpoint" id="inbox-toggle">
        <div class="endpoint-head">
            <span class="method post">POST</span>
            <span class="path">/api/whatsapp/inbox/{conversation}/ai-reply</span>
        </div>
        <div class="endpoint-body">
            <div class="label">Request body</div>
            <pre>{ "ai_reply_enabled": false }</pre>
        </div>
    </section>

    <section class="endpoint" id="get-conversation">
        <div class="endpoint-head">
            <span class="method get">GET</span>
            <span class="path">/api/conversations/{id}</span>
        </div>
        <div class="endpoint-body">
            <div class="label">Relevant fields</div>
            <pre>{
  "data": {
    "conversation": {
      "id": 4,
      "ai_reply_enabled": true,
      "auto_ai_enabled": true
    }
  }
}</pre>
        </div>
    </section>

    <h2 class="section-title">Media Library APIs</h2>

    <section class="endpoint" id="media">
        <div class="endpoint-head">
            <span class="method get">GET</span>
            <span class="path">/api/media</span>
        </div>
        <div class="endpoint-body">
            <div class="label">Description</div>
            <p style="color:var(--muted)">List media for logged-in user only.</p>
            <div class="label">Query params</div>
            <pre>?type=image|video|document
?search=logo
?user_id=12   (admin only)
?all=1        (admin only — all users)</pre>
            <div class="label">Response 200</div>
            <pre>{
  "success": true,
  "meta": { "userId": 12, "count": 3 },
  "data": [
    {
      "id": 1,
      "userId": 12,
      "name": "banner.png",
      "full_url": "https://example.com/public/uploads/media/....png",
      "mime_type": "image/png"
    }
  ]
}</pre>
        </div>
    </section>

    <section class="endpoint">
        <div class="endpoint-head">
            <span class="method get">GET</span>
            <span class="path">/api/media/stats</span>
        </div>
        <div class="endpoint-body">
            <div class="label">Response 200</div>
            <pre>{
  "success": true,
  "data": {
    "total_media": 3,
    "total_size": 1048576,
    "human_size": "1 MB",
    "image_count": 2,
    "video_count": 0,
    "document_count": 1,
    "userId": 12
  }
}</pre>
        </div>
    </section>

    <section class="endpoint">
        <div class="endpoint-head">
            <span class="method post">POST</span>
            <span class="path">/api/media</span>
        </div>
        <div class="endpoint-body">
            <div class="label">Request (multipart)</div>
            <pre>file: (required)
description: optional
alt_text: optional</pre>
            <p style="color:var(--muted);margin-top:8px">Upload always saves with <code>userId = getCompanyId()</code>.</p>
        </div>
    </section>

    <section class="endpoint">
        <div class="endpoint-head">
            <span class="method get">GET</span>
            <span class="path">/api/media/{id}</span>
        </div>
        <div class="endpoint-body">
            <p style="color:var(--muted)">Show / update / delete only work on media owned by this user (404 otherwise).</p>
        </div>
    </section>

    <h2 class="section-title">Templates APIs</h2>

    <section class="endpoint" id="templates">
        <div class="endpoint-head">
            <span class="method get">GET</span>
            <span class="path">/api/templates/user</span>
        </div>
        <div class="endpoint-body">
            <div class="label">Description</div>
            <p style="color:var(--muted)">Templates for this user only — no shared <code>type=system</code> rows from other accounts.</p>
            <div class="label">Related routes</div>
            <pre>GET  /api/templates/user/{templateId}
GET  /api/templates/filtered?type=custom&status=APPROVED
GET  /api/templates/limits
GET  /api/templates/sync-templates
POST /api/templates/custom
PUT  /api/templates/custom/{templateId}
DELETE /api/templates/custom/{templateId}</pre>
            <div class="label">Response 200</div>
            <pre>{
  "status": true,
  "count": 2,
  "meta": { "userId": 12 },
  "data": [ { "id": 1, "userId": 12, "name": "welcome_msg", "type": "custom" } ]
}</pre>
            <p style="color:var(--muted);margin-top:8px"><code>GET /api/templates/limits</code> returns plan limits for <strong>this user</strong> only (not global counts).</p>
        </div>
    </section>

    <h2 class="section-title">Campaigns APIs</h2>

    <section class="endpoint" id="campaigns">
        <div class="endpoint-head">
            <span class="method get">GET</span>
            <span class="path">/api/campaigns</span>
        </div>
        <div class="endpoint-body">
            <div class="label">Description</div>
            <p style="color:var(--muted)">WhatsApp broadcast campaigns for this user. Stats in the same response are user-scoped.</p>
            <div class="label">Query (admin)</div>
            <pre>?user_id=12
?all=1
?status=draft|scheduled|completed</pre>
            <div class="label">Response 200</div>
            <pre>{
  "success": true,
  "meta": { "userId": 12 },
  "campaigns": { "data": [ ... ], "current_page": 1 },
  "stats": {
    "total": 5,
    "draft": 1,
    "scheduled": 1,
    "sending": 0,
    "completed": 3,
    "cancelled": 0,
    "total_recipients": 1200,
    "sent_count": 980,
    "delivered_count": 900,
    "read_count": 400,
    "failed_count": 20
  }
}</pre>
        </div>
    </section>

    <section class="endpoint">
        <div class="endpoint-head">
            <span class="method get">GET</span>
            <span class="path">/api/campaigns</span>
            <span style="color:var(--muted);font-size:.85rem">(bulk reports list)</span>
        </div>
        <div class="endpoint-body">
            <div class="label">Alternate: bulk message campaigns + delivery stats</div>
            <pre>GET /api/campaigns?date_from=2026-01-01&date_to=2026-08-24&status=all&page=1&per_page=15</pre>
            <p style="color:var(--muted)">Returns paginated campaigns + <code>summary</code> counts scoped to logged-in user. Admin summary no longer includes all users unless <code>?all=1</code>.</p>
            <div class="label">Response summary (user-wise)</div>
            <pre>{
  "success": true,
  "filters": { "applied": { "userId": 12, ... } },
  "summary": {
    "totalMessages": 10,
    "sent": 800,
    "delivered": 750,
    "read": 300,
    "failed": 20,
    "pending": 0,
    "processing": 0,
    "scheduled": 0,
    "completed": 8
  },
  "messages": [ ... ],
  "pagination": { ... }
}</pre>
        </div>
    </section>

    <section class="endpoint">
        <div class="endpoint-head">
            <span class="method post">POST</span>
            <span class="path">/api/whatsapp/campaigns</span>
        </div>
        <div class="endpoint-body">
            <div class="label">Create / launch / cancel</div>
            <pre>POST /api/whatsapp/campaigns          — create draft
POST /api/whatsapp/campaigns/{id}/launch
POST /api/whatsapp/campaigns/{id}/cancel
GET  /api/whatsapp/campaigns            — list + stats (user-wise)</pre>
        </div>
    </section>

    <div class="card">
        <h2>Frontend wiring</h2>
        <table>
            <thead><tr><th>Screen</th><th>Control</th><th>API</th></tr></thead>
            <tbody>
                <tr>
                    <td>Settings — API Configuration</td>
                    <td>Facebook / WABA / token</td>
                    <td><code>GET /api/settings</code> → <code>apiConfiguration</code><br><code>POST /api/settings/save-config</code></td>
                </tr>
                <tr>
                    <td>Settings — Business Details</td>
                    <td>Auto AI + business form</td>
                    <td><code>GET /api/settings</code> → <code>businessDetails</code><br><code>POST /api/settings/auto-ai</code></td>
                </tr>
                <tr>
                    <td>Media Library</td>
                    <td>Upload / list / delete</td>
                    <td><code>GET /api/media</code> · <code>POST /api/media</code> · <code>GET /api/media/stats</code></td>
                </tr>
                <tr>
                    <td>Templates</td>
                    <td>List / create / sync</td>
                    <td><code>GET /api/templates/user</code> · <code>GET /api/templates/limits</code></td>
                </tr>
                <tr>
                    <td>Campaigns</td>
                    <td>List + stats</td>
                    <td><code>GET /api/campaigns</code> (check <code>meta.userId</code> + <code>stats</code>)</td>
                </tr>
                <tr>
                    <td>Chat header</td>
                    <td>Auto AI toggle</td>
                    <td><code>POST /api/conversations/{id}/ai-reply</code></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Quick examples</h2>
        <div class="label">Load settings for current user</div>
        <pre>GET /api/settings
Authorization: Bearer {token}

// Use response.apiConfiguration for WhatsApp form
// Use response.businessDetails for Auto AI form
// Verify response.meta.userId matches logged-in company id</pre>
        <div class="label">Save business details only</div>
        <pre>POST /api/settings/auto-ai
Authorization: Bearer {token}

{
  "auto_ai_enabled": true,
  "business_name": "Sharma Grocery",
  "business_type": "grocery",
  "business_details": "Rice, flour, oil. Delivery 5km.",
  "business_hours": "9 AM - 9 PM",
  "auto_ai_tone": "friendly"
}</pre>
        <div class="label">Disable Auto AI for one chat</div>
        <pre>POST /api/conversations/4/ai-reply
Authorization: Bearer {token}

{ "ai_reply_enabled": false }</pre>
    </div>

    <h2 class="section-title" id="wallet">Wallet &amp; Razorpay</h2>
    <div class="card">
        <h2>Per-user wallet</h2>
        <p>Super Admin enables wallet for a company user. That user then sees Wallet in the app and recharges with Razorpay. Money is added only to that user’s balance.</p>
        <p style="margin-top:8px">Full wallet API page: <a class="main-docs" href="{{ url('/docs/wallet') }}">{{ url('/docs/wallet') }}</a></p>
        <table>
            <thead><tr><th>Who</th><th>Endpoint</th></tr></thead>
            <tbody>
                <tr>
                    <td>Admin enable</td>
                    <td><code>PUT /api/v1/admin/users/{userId}/wallet</code> <code>{"walletEnabled": true}</code></td>
                </tr>
                <tr>
                    <td>Admin Razorpay keys</td>
                    <td><code>PUT /api/v1/admin/settings/credentials/razorpay</code></td>
                </tr>
                <tr>
                    <td>Show wallet</td>
                    <td><code>GET /api/wallet</code> — hide UI if <code>walletEnabled</code> is false (also on login/profile)</td>
                </tr>
                <tr>
                    <td>Create order</td>
                    <td><code>POST /api/wallet/create-order</code> <code>{"amount": 100}</code></td>
                </tr>
                <tr>
                    <td>Verify &amp; credit</td>
                    <td><code>POST /api/wallet/verify</code> with Razorpay <code>order_id</code>, <code>payment_id</code>, <code>signature</code></td>
                </tr>
                <tr>
                    <td>Bulk billing</td>
                    <td>If wallet enabled: min ₹250 to start; deduct ₹0.86 marketing / ₹0.17 utility on <strong>delivered</strong>; stop remaining when wallet empty</td>
                </tr>
            </tbody>
        </table>
    </div>

    <footer>
        Full CRM API docs:
        <a class="main-docs" href="{{ url('/api-docs') }}">{{ url('/api-docs') }}</a>
        · Wallet:
        <a class="main-docs" href="{{ url('/api-docs/wallet') }}">{{ url('/api-docs/wallet') }}</a>
        <br>
        © {{ date('Y') }} ZapReach — Auto AI & user-wise API docs
    </footer>
</div>
<script>
    (function () {
        var pageUrl = window.location.href.split('#')[0];
        var apiBase;
        if (window.location.pathname.indexOf('/services') !== -1 || window.location.href.indexOf('/services/') !== -1) {
            apiBase = window.location.origin + '/services/api';
        } else {
            apiBase = window.location.origin + '/api';
        }
        document.getElementById('shareUrl').textContent = pageUrl;
        document.getElementById('baseUrl').textContent = apiBase;
    })();
</script>
</body>
</html>
