<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Catalog & Commerce API Docs | ZapReach</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            --purple: #a855f7;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'DM Sans', sans-serif;
            background: radial-gradient(1200px 600px at 10% -10%, #1a2744 0%, var(--bg) 45%);
            color: var(--text);
            line-height: 1.55;
            min-height: 100vh;
        }
        .wrap { max-width: 1000px; margin: 0 auto; padding: 32px 20px 64px; }
        .top {
            display: flex; flex-wrap: wrap; gap: 14px; justify-content: space-between; align-items: flex-start;
            margin-bottom: 24px;
        }
        h1 { font-size: 1.85rem; letter-spacing: -0.03em; display: flex; align-items: center; gap: 10px; }
        .sub { color: var(--muted); margin-top: 6px; max-width: 760px; font-size: 0.98rem; }
        .badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(34,211,238,.12); color: var(--cyan);
            border: 1px solid rgba(34,211,238,.35); border-radius: 999px;
            padding: 8px 16px; font-size: .85rem; font-weight: 600;
        }
        .card {
            background: var(--card); border: 1px solid var(--line); border-radius: 14px;
            padding: 20px; margin-bottom: 18px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .card h2 { font-size: 1.12rem; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; color: #fff; }
        .card p, .card li { color: var(--muted); font-size: .92rem; }
        .card ul { padding-left: 18px; }
        .card li { margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: .88rem; }
        th, td { text-align: left; padding: 10px 10px; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { color: var(--muted); font-weight: 600; font-size: .76rem; text-transform: uppercase; letter-spacing: .05em; background: rgba(0,0,0,0.2); }
        .param-name { font-family: 'IBM Plex Mono', monospace; font-weight: 600; color: #f1f5f9; }
        .param-type { font-family: 'IBM Plex Mono', monospace; font-size: 0.75rem; color: #60a5fa; }
        .param-req { font-size: 0.7rem; font-weight: 700; padding: 2px 6px; border-radius: 4px; display: inline-block; }
        .req-yes { background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
        .req-no { background: rgba(148,163,184,0.12); color: #94a3b8; }
        .endpoint {
            background: var(--panel); border: 1px solid var(--line); border-radius: 14px;
            margin-bottom: 22px; overflow: hidden; box-shadow: 0 6px 16px rgba(0,0,0,0.2);
        }
        .endpoint-head {
            display: flex; flex-wrap: wrap; gap: 12px; align-items: center;
            padding: 14px 18px; border-bottom: 1px solid var(--line);
            background: rgba(15, 23, 42, 0.7);
        }
        .method {
            font-family: 'IBM Plex Mono', monospace; font-size: .75rem; font-weight: 800;
            padding: 4px 10px; border-radius: 6px; color: #041016; text-transform: uppercase;
        }
        .get { background: var(--green); }
        .post { background: var(--blue); color: #fff; }
        .put { background: var(--amber); color: #111; }
        .delete { background: var(--red); color: #fff; }
        .path {
            font-family: 'IBM Plex Mono', monospace; font-size: .94rem; color: var(--cyan);
            font-weight: 600; word-break: break-all;
        }
        .endpoint-body { padding: 20px; }
        .label {
            font-size: .75rem; font-weight: 700; color: var(--muted);
            text-transform: uppercase; letter-spacing: .06em; margin: 16px 0 8px;
            display: flex; align-items: center; gap: 6px;
        }
        .endpoint-body > .label:first-child { margin-top: 0; }
        pre {
            background: #090e1a; border: 1px solid var(--line); border-radius: 10px;
            padding: 14px; overflow-x: auto;
            font-family: 'IBM Plex Mono', monospace; font-size: .82rem; color: #d7e3f7;
            position: relative;
        }
        code { font-family: 'IBM Plex Mono', monospace; font-size: .86rem; color: #c7d7f5; }
        .nav {
            display: flex; flex-wrap: wrap; gap: 8px; margin: 18px 0 12px;
        }
        .nav a {
            color: var(--text); text-decoration: none; font-size: .84rem;
            background: var(--panel); border: 1px solid var(--line);
            border-radius: 999px; padding: 7px 14px; transition: all 0.2s;
        }
        .nav a:hover { border-color: var(--cyan); color: var(--cyan); background: rgba(34,211,238,0.1); }
        .share { margin-top: 10px; font-size: .85rem; color: var(--muted); word-break: break-all; }
        .share strong { color: var(--text); }
        .section-title {
            font-size: 1.25rem; margin: 34px 0 14px; padding-bottom: 10px;
            border-bottom: 1px solid var(--line); color: var(--cyan);
            display: flex; align-items: center; gap: 8px;
        }
        footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid var(--line); color: var(--muted); font-size: .85rem; text-align: center; }
        a.main-docs { color: var(--cyan); text-decoration: none; font-weight: 600; }
        a.main-docs:hover { text-decoration: underline; }
        .steps { counter-reset: step; }
        .steps li { margin: 8px 0; }
        .copy-btn {
            position: absolute; right: 10px; top: 10px;
            background: rgba(255,255,255,0.08); border: 1px solid var(--line);
            color: var(--text); border-radius: 6px; padding: 4px 8px;
            font-size: 0.72rem; cursor: pointer; transition: all 0.2s;
        }
        .copy-btn:hover { background: var(--cyan); color: #041016; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1><i class="fas fa-store" style="color:var(--cyan)"></i> WhatsApp Catalog &amp; Commerce API Docs</h1>
            <p class="sub">Complete REST API reference for WhatsApp Commerce Catalogs. Manage product inventories on Meta, toggle WhatsApp storefront &amp; shopping cart visibility, and dispatch interactive Single/Multi Product Messages (SPM / MPM).</p>
            <p class="share"><strong>Share this page:</strong> <span id="shareUrl"></span></p>
        </div>
        <div class="badge"><i class="fab fa-whatsapp"></i> Meta Graph v21.0 · Commerce</div>
    </div>

    <div class="nav">
        <a href="#flow">Overview &amp; Flow</a>
        <a href="#auth">Base URL &amp; Auth</a>
        <a href="#endpoints-summary">Summary Table</a>
        <a href="#commerce-settings">Commerce Settings</a>
        <a href="#catalogs">Catalogs</a>
        <a href="#products">Products CRUD</a>
        <a href="#messaging">Interactive Messages</a>
        <a href="#inbound-commerce">Inbound &amp; Orders</a>
        <a href="#frontend-react">React/Axios Example</a>
    </div>

    <!-- How It Works Flow -->
    <div class="card" id="flow">
        <h2><i class="fas fa-network-wired" style="color:var(--cyan)"></i> Architecture &amp; Lifecycle</h2>
        <ol class="steps" style="padding-left:18px;color:var(--muted)">
            <li><strong>Catalog Discovery:</strong> The API discovers product catalogs linked to the business WhatsApp Business Account (WABA) from Meta via <code>GET /api/catalog/list</code>.</li>
            <li><strong>Commerce Settings:</strong> Businesses can toggle the storefront icon on their WhatsApp profile and enable or disable customer shopping carts with <code>POST /api/catalog/settings</code>.</li>
            <li><strong>Product Sync &amp; CRUD:</strong> Products can be added, updated, searched (by title, description, or SKU), or deleted on Meta directly via standard JSON REST endpoints.</li>
            <li><strong>Interactive Customer Messaging:</strong> Dispatch Single Product Messages (SPM) with photo/price, Multi-Product Messages (MPM) grouping up to 30 items in sections, or full Storefront catalog links.</li>
            <li><strong>Multi-Tenant Isolation:</strong> All requests use the authenticated company’s own WABA ID, Phone Number ID, and System Access Token stored in Settings. Staff users safely map to their parent company.</li>
        </ol>
    </div>

    <!-- Base URL & Auth -->
    <div class="card" id="auth">
        <h2><i class="fas fa-key" style="color:var(--cyan)"></i> Base URL &amp; Authentication</h2>
        <p><strong>Production API Base:</strong> <code id="baseUrl">https://zapreach.com/services/api/catalog</code></p>
        <p style="margin-top:8px"><strong>Local Development:</strong> <code>http://localhost:8000/api/catalog</code></p>
        <p style="margin-top:12px"><strong>Required Headers:</strong></p>
        <pre>Authorization: Bearer {your_api_token}
Content-Type: application/json
Accept: application/json</pre>
        <p style="margin-top:10px">Obtain your Bearer token via <code>POST /api/auth/login</code> or the Super Admin dashboard.</p>
    </div>

    <!-- Summary Table -->
    <div class="card" id="endpoints-summary">
        <h2><i class="fas fa-list-check" style="color:var(--cyan)"></i> Endpoint Directory (16 Endpoints)</h2>
        <table>
            <thead>
                <tr><th>Method</th><th>Endpoint</th><th>Description</th></tr>
            </thead>
            <tbody>
                <tr><td><span class="method get">GET</span></td><td><code>/api/catalog/diagnostics</code></td><td>Inspect Meta token scopes, valid status &amp; API readiness</td></tr>
                <tr><td><span class="method get">GET</span></td><td><code>/api/catalog/settings</code></td><td>Retrieve commerce settings (catalog &amp; cart visibility)</td></tr>
                <tr><td><span class="method post">POST</span></td><td><code>/api/catalog/settings</code></td><td>Update commerce settings &amp; default catalog ID</td></tr>
                <tr><td><span class="method get">GET</span></td><td><code>/api/catalog/list</code></td><td>List all Meta Catalogs connected to WABA</td></tr>
                <tr><td><span class="method post">POST</span></td><td><code>/api/catalog/select</code></td><td>Set active default catalog ID</td></tr>
                <tr><td><span class="method get">GET</span></td><td><code>/api/catalog/details/{catalog_id?}</code></td><td>Get metadata and product count for a catalog</td></tr>
                <tr><td><span class="method get">GET</span></td><td><code>/api/catalog/products</code></td><td>List &amp; search products with pagination and SKU filter</td></tr>
                <tr><td><span class="method get">GET</span></td><td><code>/api/catalog/products/{identifier}</code></td><td>Get single product details by SKU or Meta Product ID</td></tr>
                <tr><td><span class="method post">POST</span></td><td><code>/api/catalog/products</code></td><td>Create a new product in the Meta Catalog</td></tr>
                <tr><td><span class="method get">GET</span></td><td><code>/api/catalog/products/bulk/template</code></td><td>Download official Commerce Manager 31-column CSV/TSV template or JSON spec</td></tr>
                <tr><td><span class="method post">POST</span></td><td><code>/api/catalog/products/bulk</code></td><td>Bulk upload products via Commerce Manager CSV/TSV/XLSX or JSON array</td></tr>
                <tr><td><span class="method put">PUT</span></td><td><code>/api/catalog/products/{productId}</code></td><td>Update existing product attributes (price, stock, image)</td></tr>
                <tr><td><span class="method delete">DEL</span></td><td><code>/api/catalog/products/{productId}</code></td><td>Permanently delete product from Meta Catalog</td></tr>
                <tr><td><span class="method post">POST</span></td><td><code>/api/catalog/send-catalog</code></td><td>Send interactive Storefront Catalog message to customer</td></tr>
                <tr><td><span class="method post">POST</span></td><td><code>/api/catalog/send-product</code></td><td>Send Single Product Message (SPM) with "View / Add to Cart"</td></tr>
                <tr><td><span class="method post">POST</span></td><td><code>/api/catalog/send-product-list</code></td><td>Send Multi-Product Message (MPM) grouping up to 30 items</td></tr>
            </tbody>
        </table>
    </div>

    <!-- SECTION 1: COMMERCE SETTINGS -->
    <h2 class="section-title" id="commerce-settings"><i class="fas fa-sliders"></i> 1. Diagnostics &amp; Commerce Settings</h2>

    <!-- GET /api/catalog/diagnostics -->
    <div class="endpoint">
        <div class="endpoint-head">
            <span class="method get">GET</span>
            <span class="path">/api/catalog/diagnostics</span>
        </div>
        <div class="endpoint-body">
            <p style="color:var(--muted)">Inspect your active Meta Access Token, verified permissions (scopes), and API readiness. Detects if <code>catalog_management</code> or <code>whatsapp_business_messaging</code> are active.</p>
            <div class="label">Response JSON (200 OK)</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>{
  "success": true,
  "token_diagnostics": {
    "is_valid": true,
    "app_id": "1705027483899172",
    "system_user_id": "61581505132671",
    "granted_scopes": [
      "whatsapp_business_messaging",
      "whatsapp_business_management",
      "catalog_management"
    ]
  },
  "status": {
    "can_send_products_now": true,
    "can_fetch_catalog_products_api": true
  }
}</pre>
        </div>
    </div>

    <!-- GET /api/catalog/settings -->
    <div class="endpoint">
        <div class="endpoint-head">
            <span class="method get">GET</span>
            <span class="path">/api/catalog/settings</span>
        </div>
        <div class="endpoint-body">
            <p style="color:var(--muted)">Fetch current WhatsApp Commerce settings for the phone number from Meta Graph API (checks if storefront catalog visibility and shopping cart are active).</p>
            <div class="label">Response JSON (200 OK)</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>{
  "success": true,
  "data": {
    "phone_number_id": "104192305987654",
    "is_catalog_visible": true,
    "is_cart_enabled": true,
    "default_catalog_id": "987654321012345"
  }
}</pre>
        </div>
    </div>

    <!-- POST /api/catalog/settings -->
    <div class="endpoint">
        <div class="endpoint-head">
            <span class="method post">POST</span>
            <span class="path">/api/catalog/settings</span>
        </div>
        <div class="endpoint-body">
            <p style="color:var(--muted)">Update WhatsApp Commerce settings on Meta to toggle the shopping cart or catalog storefront visibility on/off, or set the default active catalog ID.</p>
            <div class="label">Request Body JSON</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>{
  "is_catalog_visible": true,
  "is_cart_enabled": true,
  "default_catalog_id": "987654321012345"
}</pre>
            <div class="label">Response JSON (200 OK)</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>{
  "success": true,
  "message": "WhatsApp commerce settings updated successfully.",
  "data": {
    "phone_number_id": "104192305987654",
    "is_catalog_visible": true,
    "is_cart_enabled": true,
    "default_catalog_id": "987654321012345"
  }
}</pre>
        </div>
    </div>

    <!-- SECTION 2: CATALOG DISCOVERY -->
    <h2 class="section-title" id="catalogs"><i class="fas fa-folder-tree"></i> 2. Catalogs Discovery &amp; Selection</h2>

    <!-- GET /api/catalog/list -->
    <div class="endpoint">
        <div class="endpoint-head">
            <span class="method get">GET</span>
            <span class="path">/api/catalog/list</span>
        </div>
        <div class="endpoint-body">
            <p style="color:var(--muted)">Discover and list all product catalogs linked to the business WhatsApp Business Account (WABA) from Meta.</p>
            <div class="label">Response JSON (200 OK)</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>{
  "success": true,
  "default_catalog_id": "987654321012345",
  "count": 1,
  "data": [
    {
      "id": "987654321012345",
      "name": "Ranaji Showroom Catalog",
      "vertical": "commerce",
      "product_count": 48,
      "is_default": true
    }
  ]
}</pre>
        </div>
    </div>

    <!-- POST /api/catalog/select -->
    <div class="endpoint">
        <div class="endpoint-head">
            <span class="method post">POST</span>
            <span class="path">/api/catalog/select</span>
        </div>
        <div class="endpoint-body">
            <p style="color:var(--muted)">Set the active default catalog ID for this business account.</p>
            <div class="label">Request Body JSON</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>{
  "catalog_id": "987654321012345"
}</pre>
            <div class="label">Response JSON (200 OK)</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>{
  "success": true,
  "message": "Default catalog selected successfully.",
  "data": {
    "catalog_id": "987654321012345"
  }
}</pre>
        </div>
    </div>

    <!-- GET /api/catalog/details/{catalog_id?} -->
    <div class="endpoint">
        <div class="endpoint-head">
            <span class="method get">GET</span>
            <span class="path">/api/catalog/details/{catalog_id?}</span>
        </div>
        <div class="endpoint-body">
            <p style="color:var(--muted)">Get metadata, vertical, and product counts for a specific catalog (auto-resolves default if omitted).</p>
            <div class="label">Response JSON (200 OK)</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>{
  "success": true,
  "data": {
    "id": "987654321012345",
    "name": "Ranaji Showroom Catalog",
    "vertical": "commerce",
    "product_count": 48
  }
}</pre>
        </div>
    </div>

    <!-- SECTION 3: PRODUCTS CRUD -->
    <h2 class="section-title" id="products"><i class="fas fa-boxes-stacked"></i> 3. Products in Meta Catalog</h2>

    <!-- GET /api/catalog/products -->
    <div class="endpoint">
        <div class="endpoint-head">
            <span class="method get">GET</span>
            <span class="path">/api/catalog/products</span>
        </div>
        <div class="endpoint-body">
            <p style="color:var(--muted)">List items with real-time price formatting, SKU codes, stock status, and filters (<code>?search=Loafers&amp;retailer_id=SKU-01&amp;limit=25</code>).</p>
            <div class="label">Query Parameters</div>
            <table>
                <thead><tr><th>Param</th><th>Type</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td class="param-name">search</td><td class="param-type">string</td><td>Filter title, description, or SKU</td></tr>
                    <tr><td class="param-name">retailer_id</td><td class="param-type">string</td><td>Filter by exact SKU</td></tr>
                    <tr><td class="param-name">limit</td><td class="param-type">integer</td><td>Items per page (default: 25, max: 100). Set <code>limit=100</code> to fetch up to 100 items.</td></tr>
                    <tr><td class="param-name">all</td><td class="param-type">boolean</td><td>Set <code>all=true</code> (or <code>limit=all</code>) to automatically retrieve all products across all pages in a single response.</td></tr>
                    <tr><td class="param-name">after</td><td class="param-type">string</td><td>Pagination cursor token (found in <code>pagination.after</code>)</td></tr>
                </tbody>
            </table>
            <div class="label">Response JSON (200 OK)</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>{
  "success": true,
  "catalog_id": "987654321012345",
  "count": 1,
  "data": [
    {
      "id": "10192837465",
      "retailer_id": "SKU-LOAFERS-01",
      "name": "लेदर लोफर्स",
      "description": "Handcrafted premium pure leather ethnic loafers.",
      "price": 1200.0,
      "currency": "INR",
      "formatted_price": "₹1,200.00",
      "availability": "in stock",
      "image_url": "https://zapreach.com/uploads/media/loafers.jpg",
      "url": "https://ranaji.com/products/leather-loafers",
      "category": "Apparel & Accessories > Shoes",
      "condition": "new",
      "visibility": "published",
      "review_status": "approved"
    }
  ],
  "pagination": { "has_next": false, "has_previous": false, "after": null, "before": null }
}</pre>
        </div>
    </div>

    <!-- GET /api/catalog/products/{identifier} -->
    <div class="endpoint">
        <div class="endpoint-head">
            <span class="method get">GET</span>
            <span class="path">/api/catalog/products/{identifier}</span>
        </div>
        <div class="endpoint-body">
            <p style="color:var(--muted)">Retrieve single product details by either SKU (e.g. <code>SKU-LOAFERS-01</code>) or Meta Product ID.</p>
            <div class="label">Response JSON (200 OK)</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>{
  "success": true,
  "data": {
    "id": "10192837465",
    "retailer_id": "SKU-LOAFERS-01",
    "name": "लेदर लोफर्स",
    "description": "Handcrafted premium pure leather ethnic loafers.",
    "price": 1200.0,
    "currency": "INR",
    "formatted_price": "₹1,200.00",
    "availability": "in stock",
    "image_url": "https://zapreach.com/uploads/media/loafers.jpg",
    "condition": "new",
    "review_status": "approved"
  }
}</pre>
        </div>
    </div>

    <!-- POST /api/catalog/products -->
    <div class="endpoint">
        <div class="endpoint-head">
            <span class="method post">POST</span>
            <span class="path">/api/catalog/products</span>
        </div>
        <div class="endpoint-body">
            <p style="color:var(--muted)">Add a new item to the Meta Catalog with title, SKU, price, currency, image, and website link. Supports <strong>BOTH</strong> direct image file uploads (<code>multipart/form-data</code> with <code>images</code> or <code>image</code>) and direct URL links (<code>image_url</code>).</p>
            
            <div class="label">Option A: Multipart Form-Data (Direct Image File Upload from Frontend)</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button># Headers: Content-Type: multipart/form-data
retailer_id: SKU-SHERWANI-02
name: Royal Silk Sherwani
price: 8500.00
currency: INR
images: &lt;Select image file from computer (.jpg, .png, .webp)&gt;
description: Designer wedding sherwani with intricate zari embroidery.
availability: in stock
condition: new
url: https://ranaji.com/products/royal-sherwani</pre>

            <div class="label">Option B: JSON Request Body (Image URL)</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>{
  "retailer_id": "SKU-SHERWANI-02",
  "name": "Royal Silk Sherwani",
  "price": 8500.00,
  "currency": "INR",
  "image_url": "https://zapreach.com/uploads/media/sherwani.jpg",
  "description": "Designer wedding sherwani with intricate zari embroidery.",
  "availability": "in stock",
  "condition": "new",
  "url": "https://ranaji.com/products/royal-sherwani"
}</pre>
            <div class="label">Response JSON (201 Created)</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>{
  "success": true,
  "message": "Product added to Meta Catalog successfully.",
  "product_id": "209384756102938",
  "catalog_id": "987654321012345",
  "retailer_id": "SKU-SHERWANI-02",
  "image_url": "https://zapreach.com/uploads/catalog/prod_1791200000.jpg"
}</pre>
        </div>
    </div>

    <!-- GET /api/catalog/products/bulk/template -->
    <div class="endpoint">
        <div class="endpoint-head">
            <span class="method get">GET</span>
            <span class="path">/api/catalog/products/bulk/template</span>
        </div>
        <div class="endpoint-body">
            <p style="color:var(--muted)">Download the official Meta Commerce Manager catalog data feed spreadsheet template with all 31 columns, row annotations (<code># Required</code> / <code># Optional</code>), and sample product row.</p>
            <div class="label">Query Parameters</div>
            <table>
                <thead>
                    <tr><th>Parameter</th><th>Type</th><th>Default</th><th>Description</th></tr>
                </thead>
                <tbody>
                    <tr><td><code>format</code></td><td>string</td><td><code>csv</code></td><td>Export format: <code>csv</code> (comma-separated) or <code>tsv</code> (tab-delimited for Excel/Sheets).</td></tr>
                    <tr><td><code>json</code></td><td>integer</td><td><code>0</code></td><td>Pass <code>1</code> to receive the column schema, required flags, and sample row in JSON format instead of a downloadable file.</td></tr>
                </tbody>
            </table>
            <div class="label">Download Example (cURL)</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button># Download TSV template for Excel/Sheets
curl -X GET "https://zapreach.com/api/catalog/products/bulk/template?format=tsv" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -o commerce_manager_catalog_template.tsv

# Or get column specs in JSON:
curl -X GET "https://zapreach.com/api/catalog/products/bulk/template?json=1" \
  -H "Authorization: Bearer YOUR_TOKEN"</pre>
            <div class="label">Response JSON (when ?json=1 is passed)</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>{
  "success": true,
  "message": "Commerce Manager feed template specification.",
  "total_columns": 31,
  "columns": [
    { "name": "id", "requirement": "# Required", "sample": "0", "description": "Unique product SKU or retailer ID" },
    { "name": "title", "requirement": "# Required", "sample": "Blue Facebook T-Shirt (Unisex)", "description": "Product Name / Title" },
    { "name": "description", "requirement": "# Required", "sample": "A vibrant blue crewneck T-shirt for all shapes and sizes. Made from 100% cotton.", "description": "Detailed product description" },
    { "name": "availability", "requirement": "# Required", "sample": "in stock", "description": "in stock / out of stock" },
    { "name": "condition", "requirement": "# Required", "sample": "new", "description": "new / refurbished / used" },
    { "name": "link", "requirement": "# Required", "sample": "https://www.facebook.com/facebook_t_shirt", "description": "Product page URL" },
    { "name": "image_link", "requirement": "# Required", "sample": "https://www.facebook.com/t_shirt_image_001.jpg", "description": "Product main photo URL" },
    { "name": "brand", "requirement": "# Optional", "sample": "Facebook", "description": "Brand name" },
    { "name": "price", "requirement": "# Required", "sample": "10.00 USD", "description": "Price with currency code (e.g. 10.00 USD, 199.00 INR)" },
    { "name": "google_product_category", "requirement": "# Optional", "sample": "Apparel & Accessories > Clothing" },
    { "name": "fb_product_category", "requirement": "# Optional", "sample": "Clothing & Accessories > Clothing" },
    { "name": "quantity_to_sell_on_facebook", "requirement": "# Optional", "sample": "75" },
    { "name": "sale_price", "requirement": "# Optional", "sample": "10.00 USD" },
    { "name": "sale_price_effective_date", "requirement": "# Optional", "sample": "2020-04-30T09:30-08:00/2020-05-30T23:59-08:00" },
    { "name": "gender", "requirement": "# Optional", "sample": "unisex" },
    { "name": "color", "requirement": "# Optional", "sample": "royal blue" },
    { "name": "size", "requirement": "# Optional", "sample": "M" },
    { "name": "material", "requirement": "# Optional", "sample": "cotton" },
    { "name": "pattern", "requirement": "# Optional", "sample": "stripes" },
    { "name": "shipping", "requirement": "# Optional", "sample": "US:CA:Ground:9.99 USD;US:NY:Air:15.99 USD" },
    { "name": "shipping_weight", "requirement": "# Optional", "sample": "10 kg" }
  ]
}</pre>
        </div>
    </div>

    <!-- POST /api/catalog/products/bulk -->
    <div class="endpoint">
        <div class="endpoint-head">
            <span class="method post">POST</span>
            <span class="path">/api/catalog/products/bulk</span>
        </div>
        <div class="endpoint-body">
            <p style="color:var(--muted)">Bulk create / sync products into Meta Commerce Catalog. Supports <strong>CSV/TSV/XLSX file upload</strong> (directly from Commerce Manager data feed), a <strong>JSON array</strong> of products, or raw copied spreadsheet text.</p>
            <div class="label">Supported Upload Methods</div>
            <ul>
                <li><strong style="color:var(--cyan)">1. File Upload (multipart/form-data):</strong> Send form field <code>file</code> with a <code>.csv</code>, <code>.tsv</code>, <code>.txt</code>, or <code>.xlsx</code> file matching Meta's Commerce Manager format.</li>
                <li><strong style="color:var(--cyan)">2. JSON Array (application/json):</strong> Pass <code>{"products": [ { "id": "0", "title": "...", "price": "10.00 USD", ... } ]}</code></li>
                <li><strong style="color:var(--cyan)">3. Raw TSV/CSV Text:</strong> Pass <code>{"feed_text": "id\ttitle\tprice\n0\tT-Shirt\t10.00 USD"}</code> in JSON payload.</li>
            </ul>

            <div class="label">Request Example: File Upload (cURL)</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>curl -X POST "https://zapreach.com/api/catalog/products/bulk" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@catalog_products.csv"</pre>

            <div class="label">Request Example: JSON Array Payload</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>{
  "catalog_id": "1818014785872279",
  "products": [
    {
      "id": "SKU-001",
      "title": "Blue Facebook T-Shirt (Unisex)",
      "description": "A vibrant blue crewneck T-shirt for all shapes and sizes. Made from 100% cotton.",
      "availability": "in stock",
      "condition": "new",
      "link": "https://www.facebook.com/facebook_t_shirt",
      "image_link": "https://www.facebook.com/t_shirt_image_001.jpg",
      "brand": "Facebook",
      "price": "10.00 USD",
      "google_product_category": "Apparel & Accessories > Clothing",
      "fb_product_category": "Clothing & Accessories > Clothing",
      "quantity_to_sell_on_facebook": "75",
      "color": "royal blue",
      "size": "M"
    }
  ]
}</pre>

            <div class="label">Response JSON (200 OK)</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>{
  "success": true,
  "message": "Batch product upload submitted successfully to Meta Commerce Catalog.",
  "catalog_id": "1818014785872279",
  "total": 1,
  "created_count": 1,
  "failed_count": 0,
  "method": "meta_items_batch",
  "created_products": [
    {
      "retailer_id": "SKU-001",
      "name": "Blue Facebook T-Shirt (Unisex)",
      "price": 10,
      "currency": "USD",
      "image_url": "https://www.facebook.com/t_shirt_image_001.jpg",
      "status": "submitted"
    }
  ],
  "errors": []
}</pre>
        </div>
    </div>
    <div class="endpoint">
        <div class="endpoint-head">
            <span class="method put">PUT</span>
            <span class="path">/api/catalog/products/{productId}</span>
        </div>
        <div class="endpoint-body">
            <p style="color:var(--muted)">Update product attributes (price, title, availability, description, or image) on Meta. <code>{productId}</code> can be passed as <strong>EITHER</strong> the merchant SKU / retailer_id (e.g. <code>SKU-SHERWANI-02</code>, <code>In facilis quis quib</code>) or the internal Meta numeric Product ID (e.g. <code>209384756102938</code>). Images can be updated via file upload (<code>images</code>) or direct URL (<code>image_url</code>).</p>
            <div class="label">Request Body JSON or Form-Data</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>{
  "name": "Royal Silk Sherwani (Festive Edition)",
  "price": 7999.00,
  "availability": "in stock",
  "description": "Special Festive Offer: Designer wedding sherwani with zari embroidery.",
  "image_url": "https://zapreach.com/uploads/media/new_sherwani.jpg"
}</pre>
            <div class="label">Response JSON (200 OK)</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>{
  "success": true,
  "message": "Product updated successfully in Meta Catalog.",
  "product_id": "209384756102938",
  "retailer_id": "SKU-SHERWANI-02"
}</pre>
        </div>
    </div>

    <!-- DELETE /api/catalog/products/{productId} -->
    <div class="endpoint">
        <div class="endpoint-head">
            <span class="method delete">DEL</span>
            <span class="path">/api/catalog/products/{productId}</span>
        </div>
        <div class="endpoint-body">
            <p style="color:var(--muted)">Permanently delete a product from the Meta Commerce Catalog.</p>
            <div class="label">Response JSON (200 OK)</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>{
  "success": true,
  "message": "Product deleted successfully from Meta Catalog.",
  "product_id": "209384756102938"
}</pre>
        </div>
    </div>

    <!-- SECTION 4: INTERACTIVE MESSAGING -->
    <h2 class="section-title" id="messaging"><i class="fas fa-paper-plane"></i> 4. Interactive WhatsApp Commerce Messages</h2>

    <!-- POST /api/catalog/send-catalog -->
    <div class="endpoint">
        <div class="endpoint-head">
            <span class="method post">POST</span>
            <span class="path">/api/catalog/send-catalog</span>
        </div>
        <div class="endpoint-body">
            <p style="color:var(--muted)">Send an interactive Storefront Catalog message to customer inviting them to view all products inside WhatsApp.</p>
            <div class="label">Request Body JSON</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>{
  "to": "919506658558",
  "body_text": "Hello Rohit! Check out our new wedding & festive collection.",
  "footer_text": "Ranaji Showroom",
  "thumbnail_product_retailer_id": "SKU-LOAFERS-01",
  "conversation_id": 48
}</pre>
            <div class="label">Response JSON (200 OK)</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>{
  "success": true,
  "message": "Catalog message sent successfully",
  "message_id": "wamid.HBgMOTE5NTA2NjU4NTU4FQIAERgSRjQ1..."
}</pre>
        </div>
    </div>

    <!-- POST /api/catalog/send-product -->
    <div class="endpoint">
        <div class="endpoint-head">
            <span class="method post">POST</span>
            <span class="path">/api/catalog/send-product</span>
        </div>
        <div class="endpoint-body">
            <p style="color:var(--muted)">Dispatch a Single Product Message (SPM) displaying the product picture, title, price, description, and an interactive "View" / "Add to Cart" button directly in the WhatsApp chat.</p>
            <div class="label">Request Body JSON</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>{
  "to": "919506658558",
  "product_retailer_id": "SKU-LOAFERS-01",
  "body_text": "Here is the footwear you were inquiring about.",
  "footer_text": "Ranaji Showroom",
  "conversation_id": 48
}</pre>
            <div class="label">Response JSON (200 OK)</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>{
  "success": true,
  "message": "Single product message sent successfully",
  "message_id": "wamid.HBgMOTE5NTA2NjU4NTU4FQIAERgSRjQ2..."
}</pre>
        </div>
    </div>

    <!-- POST /api/catalog/send-product-list -->
    <div class="endpoint">
        <div class="endpoint-head">
            <span class="method post">POST</span>
            <span class="path">/api/catalog/send-product-list</span>
        </div>
        <div class="endpoint-body">
            <p style="color:var(--muted)">Dispatch a Multi-Product Message (MPM) grouping up to 30 products into distinct sections/categories. Customers can open the list, select items, and submit their cart to your business.</p>
            <div class="label">Request Body JSON</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>{
  "to": "919506658558",
  "header_text": "Ranaji Festive Highlights",
  "body_text": "Browse our curated selection and add items to your cart directly inside WhatsApp.",
  "footer_text": "Ranaji Showroom",
  "sections": [
    {
      "title": "Ethnic Footwear",
      "product_items": [
        { "product_retailer_id": "SKU-LOAFERS-01" },
        { "product_retailer_id": "SKU-JUTTI-02" }
      ]
    },
    {
      "title": "Wedding Sherwanis",
      "product_items": [
        { "product_retailer_id": "SKU-SHERWANI-02" }
      ]
    }
  ],
  "conversation_id": 48
}</pre>
            <div class="label">Response JSON (200 OK)</div>
            <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>{
  "success": true,
  "message": "Multi-product message sent successfully",
  "message_id": "wamid.HBgMOTE5NTA2NjU4NTU4FQIAERgSRjQ3..."
}</pre>
        </div>
    </div>

    <!-- SECTION 5: INBOUND COMMERCE & CONVERSATIONS -->
    <h2 class="section-title" id="inbound-commerce"><i class="fas fa-cart-shopping"></i> 5. Inbound Commerce &amp; Conversation Handling</h2>

    <div class="card">
        <h2><i class="fas fa-bag-shopping" style="color:var(--cyan)"></i> Automatic Cart Orders &amp; Product Inquiries</h2>
        <p style="color:var(--muted);margin-bottom:14px">When customers interact with your WhatsApp catalog, Meta sends special webhook messages. The backend automatically parses and records these directly into the active customer conversation:</p>

        <h3 style="font-size:1rem;color:var(--text);margin-top:16px"><i class="fas fa-receipt" style="color:var(--green)"></i> 1. Customer Sends WhatsApp Cart (Order Message)</h3>
        <p style="color:var(--muted)">When a customer taps <strong>"View sent cart"</strong> and submits an order, the system formats the items, item prices, total quantity, and customer notes into the conversation:</p>
        <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>🛒 Order Placed: 1 item (Total: INR 500.00)
• Product #47 × 1 (INR 500.00)
Customer Note: Please pack nicely</pre>

        <h3 style="font-size:1rem;color:var(--text);margin-top:16px"><i class="fas fa-tag" style="color:var(--blue)"></i> 2. Customer Inquires About a Product ("Message Business")</h3>
        <p style="color:var(--muted)">When a customer views a product and taps <strong>"Message Business"</strong>, Meta attaches <code>referred_product</code>. The system automatically prefixes the inquiry so your agents and Auto AI know exactly what product the customer is asking about:</p>
        <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>🛍️ [Product Inquiry: #59]
Hi, is this available in size L?</pre>

        <h3 style="font-size:1rem;color:var(--text);margin-top:16px"><i class="fas fa-robot" style="color:var(--purple)"></i> 3. Auto AI Intelligent Acknowledgement</h3>
        <p style="color:var(--muted)">If Auto AI is enabled, it automatically recognizes cart orders and replies with an instant confirmation, while preserving product context when answering customer questions.</p>

        <h3 style="font-size:1rem;color:var(--text);margin-top:16px"><i class="fas fa-code" style="color:var(--cyan)"></i> 4. Conversation API Schema (`GET /api/conversations/{id}`)</h3>
        <p style="color:var(--muted)">The messages returned by the conversation endpoint contain structured flags inside <code>media_display</code> so your frontend chat UI can render rich order cards and product inquiry cards:</p>
        <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>// Inbound Order Message
{
  "id": 1420,
  "conversation_id": 48,
  "direction": "inbound",
  "type": "text",
  "content": "🛒 Order Placed: 1 item (Total: INR 500.00)\n• Product #47 × 1 (INR 500.00)",
  "media_display": {
    "type": "text",
    "content": "🛒 Order Placed: 1 item...",
    "is_text": true,
    "is_order": true,
    "order": {
      "catalog_id": "1818014785872279",
      "text": "Please pack nicely",
      "product_items": [
        {
          "product_retailer_id": "47",
          "quantity": 1,
          "item_price": 500.00,
          "currency": "INR"
        }
      ]
    }
  }
}

// Inbound Product Inquiry
{
  "id": 1421,
  "conversation_id": 48,
  "direction": "inbound",
  "type": "text",
  "content": "🛍️ [Product Inquiry: #59]\nHi, is this available in size L?",
  "media_display": {
    "type": "text",
    "is_text": true,
    "is_product_inquiry": true,
    "referred_product": {
      "catalog_id": "1818014785872279",
      "product_retailer_id": "59"
    }
  }
}</pre>
    </div>

    <!-- SECTION 6: FRONTEND INTEGRATION -->
    <div class="card" id="frontend-react">
        <h2><i class="fab fa-react" style="color:#61dafb"></i> Frontend React / Axios Implementation</h2>
        <p style="margin-bottom:12px">Here is how your frontend development team can consume the Catalog module in React:</p>
        <pre><button class="copy-btn" onclick="copyPre(this)">Copy</button>import axios from 'axios';

const api = axios.create({
  baseURL: 'https://zapreach.com/services/api/catalog',
  headers: {
    'Authorization': `Bearer ${localStorage.getItem('token')}`,
    'Accept': 'application/json'
  }
});

// 1. Fetch products with search and pagination
export const fetchCatalogProducts = async (search = '', limit = 20) => {
  const { data } = await api.get('/products', { params: { search, limit } });
  return data;
};

// 2. Send Single Product to Customer in Chat
export const sendProductInChat = async (phone, sku, conversationId) => {
  const { data } = await api.post('/send-product', {
    to: phone,
    product_retailer_id: sku,
    body_text: 'Check out this product from our showroom!',
    conversation_id: conversationId
  });
  return data;
};

// 3. Toggle Storefront Catalog & Cart on/off
export const updateCommerceSettings = async (isCatalogVisible, isCartEnabled) => {
  const { data } = await api.post('/settings', {
    is_catalog_visible: isCatalogVisible,
    is_cart_enabled: isCartEnabled
  });
  return data;
};</pre>
    </div>

    <footer>
        <p>ZapReach WhatsApp Commerce &bull; <a class="main-docs" href="/api-docs">View Complete API Suite</a></p>
    </footer>
</div>

<script>
    document.getElementById('shareUrl').innerText = window.location.href;
    const base = window.location.origin + (window.location.pathname.startsWith('/services') ? '/services' : '');
    document.getElementById('baseUrl').innerText = base + '/api/catalog';

    function copyPre(btn) {
        const pre = btn.parentElement;
        const text = pre.innerText.replace(/^Copy\n?/, '');
        navigator.clipboard.writeText(text).then(() => {
            const original = btn.innerText;
            btn.innerText = 'Copied!';
            btn.style.background = '#22c55e';
            btn.style.color = '#fff';
            setTimeout(() => {
                btn.innerText = original;
                btn.style.background = '';
                btn.style.color = '';
            }, 1800);
        });
    }
</script>
</body>
</html>
