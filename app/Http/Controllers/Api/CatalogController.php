<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppCatalogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CatalogController extends Controller
{
    protected WhatsAppCatalogService $catalogService;

    public function __construct(WhatsAppCatalogService $catalogService)
    {
        $this->catalogService = $catalogService;
    }

    /**
     * Resolve company owner user ID (staff maps to parent company).
     */
    protected function resolveUserId(Request $request): int
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        return (int) $user->getCompanyId();
    }

    // =========================================================================
    // 1. COMMERCE SETTINGS (Catalog visibility & Cart)
    // =========================================================================

    /**
     * GET /api/catalog/diagnostics
     * Inspect Meta token scopes, valid status, and API readiness.
     */
    public function diagnostics(Request $request)
    {
        $userId = $this->resolveUserId($request);
        $result = $this->catalogService->getDiagnostics($userId);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to diagnose token permissions.'
            ], 400);
        }

        return response()->json($result);
    }

    /**
     * GET /api/catalog/settings
     * Fetch WhatsApp Commerce settings (is_catalog_visible, is_cart_enabled).
     */
    public function getSettings(Request $request)
    {
        $userId = $this->resolveUserId($request);
        $result = $this->catalogService->getCommerceSettings($userId);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to retrieve WhatsApp commerce settings.',
                'meta_error' => $result['meta_error'] ?? null
            ], 400);
        }

        // Attach default catalog ID
        $result['data']['default_catalog_id'] = $this->catalogService->getDefaultCatalogId($userId);

        return response()->json([
            'success' => true,
            'data' => $result['data']
        ]);
    }

    /**
     * POST /api/catalog/settings
     * Toggle WhatsApp Commerce settings and/or set default catalog ID.
     */
    public function updateSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'is_catalog_visible' => 'nullable|boolean',
            'is_cart_enabled' => 'nullable|boolean',
            'default_catalog_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = $this->resolveUserId($request);

        // Update default catalog ID if provided
        if ($request->filled('default_catalog_id')) {
            $catId = trim($request->input('default_catalog_id'));
            $this->catalogService->setDefaultCatalogId($catId, $userId);
            $this->catalogService->linkCatalogToWaba($catId, $userId);
        }

        $isVisible = $request->has('is_catalog_visible')
            ? (bool) $request->boolean('is_catalog_visible')
            : true;
        $isCart = $request->has('is_cart_enabled')
            ? (bool) $request->boolean('is_cart_enabled')
            : true;

        $result = $this->catalogService->updateCommerceSettings($isVisible, $isCart, $userId);

        if (!$result['success']) {
            if ($request->filled('default_catalog_id')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Default catalog updated successfully.',
                    'data' => [
                        'default_catalog_id' => $this->catalogService->getDefaultCatalogId($userId),
                        'is_catalog_visible' => false,
                        'is_cart_enabled' => $isCart,
                        'meta_notice' => $result['error'] ?? 'Storefront visibility toggle on phone profile requires selecting the catalog in Meta WhatsApp Manager first.'
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to update WhatsApp commerce settings.',
                'meta_error' => $result['meta_error'] ?? null
            ], 400);
        }

        $result['data']['default_catalog_id'] = $this->catalogService->getDefaultCatalogId($userId);

        return response()->json([
            'success' => true,
            'message' => 'WhatsApp commerce settings updated successfully.',
            'data' => $result['data']
        ]);
    }

    // =========================================================================
    // 2. CATALOGS DISCOVERY
    // =========================================================================

    /**
     * GET /api/catalog/list
     * List all catalogs connected to WABA.
     */
    public function listCatalogs(Request $request)
    {
        $userId = $this->resolveUserId($request);
        $result = $this->catalogService->getCatalogs($userId);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to retrieve catalogs.',
                'meta_error' => $result['meta_error'] ?? null
            ], 400);
        }

        return response()->json($result);
    }

    /**
     * POST /api/catalog/select
     * Set default active catalog ID for this business account.
     */
    public function selectCatalog(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'catalog_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'catalog_id is required',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = $this->resolveUserId($request);
        $catalogId = trim((string) $request->input('catalog_id'));

        $this->catalogService->setDefaultCatalogId($catalogId, $userId);

        // Also attempt to link the catalog to the WABA on Meta
        $linkMetaResult = $this->catalogService->linkCatalogToWaba($catalogId, $userId);

        return response()->json([
            'success' => true,
            'message' => 'Default catalog selected successfully.',
            'data' => [
                'catalog_id' => $catalogId,
                'meta_linked' => $linkMetaResult['success'] ?? false,
                'meta_note' => $linkMetaResult['success']
                    ? 'Catalog connected to WhatsApp Business Account on Meta.'
                    : ($linkMetaResult['error'] ?? 'Catalog selected in app; ensure catalog is shared with WABA in Meta Business Manager if sending fails.')
            ]
        ]);
    }

    /**
     * GET /api/catalog/details/{catalog_id?}
     * Get details for a specific catalog.
     */
    public function getCatalogDetails(Request $request, $catalog_id = null)
    {
        $userId = $this->resolveUserId($request);
        $targetId = $catalog_id ?: $request->query('catalog_id') ?: $this->catalogService->getDefaultCatalogId($userId);

        if (!$targetId) {
            return response()->json([
                'success' => false,
                'message' => 'No catalog ID specified or found.'
            ], 400);
        }

        $result = $this->catalogService->getCatalogDetails($targetId, $userId);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to retrieve catalog details.',
                'meta_error' => $result['meta_error'] ?? null
            ], 400);
        }

        return response()->json($result);
    }

    /**
     * POST /api/catalog/create
     * Create a new Catalog on Meta Commerce Manager under Business Portfolio and link to WABA.
     */
    public function createCatalog(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'business_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = $this->resolveUserId($request);
        $result = $this->catalogService->createCatalog(
            $request->input('name'),
            $request->input('business_id'),
            $userId
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to create catalog on Meta.',
                'meta_error' => $result['meta_error'] ?? null
            ], 400);
        }

        return response()->json($result, 201);
    }

    /**
     * GET /api/catalog/categories
     * Search and list Facebook / Meta Commerce Product Categories taxonomy.
     */
    public function getCategories(Request $request)
    {
        $search = $request->query('search', $request->query('q'));
        $limit = (int) $request->query('limit', 50);

        $result = $this->catalogService->getFacebookCategories($search, $limit);

        return response()->json($result);
    }

    // =========================================================================
    // PRODUCT SETS MANAGEMENT (Meta Product Sets)
    // =========================================================================

    /**
     * GET /api/catalog/sets
     * List all Product Sets in catalog.
     */
    public function getSets(Request $request)
    {
        $userId = $this->resolveUserId($request);
        $catalogId = $request->query('catalog_id');

        $result = $this->catalogService->getProductSets($catalogId, $userId);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to fetch product sets.',
                'meta_error' => $result['meta_error'] ?? null
            ], 400);
        }

        return response()->json($result);
    }

    /**
     * GET /api/catalog/sets/{setId}
     * Get details for a specific Product Set.
     */
    public function getSet(Request $request, $setId)
    {
        $userId = $this->resolveUserId($request);
        $result = $this->catalogService->getProductSetDetails((string) $setId, $userId);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Product set not found.',
                'meta_error' => $result['meta_error'] ?? null
            ], 404);
        }

        return response()->json($result);
    }

    /**
     * POST /api/catalog/sets
     * Create a new Product Set in Meta Commerce Catalog.
     */
    public function createSet(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'catalog_id' => 'nullable|string',
            'retailer_ids' => 'nullable|array',
            'product_ids' => 'nullable|array',
            'filter' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = $this->resolveUserId($request);
        $result = $this->catalogService->createProductSet(
            $request->input('name'),
            $request->all(),
            $request->input('catalog_id'),
            $userId
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to create product set in Meta Catalog.',
                'meta_error' => $result['meta_error'] ?? null
            ], 400);
        }

        return response()->json($result, 201);
    }

    /**
     * GET /api/catalog/sets/{setId}/products
     * Fetch products belonging to a specific Product Set.
     */
    public function getSetProducts(Request $request, $setId)
    {
        $userId = $this->resolveUserId($request);
        $params = [
            'limit' => $request->query('limit', 25),
            'after' => $request->query('after'),
            'before' => $request->query('before'),
            'all' => $request->boolean('all') || $request->boolean('fetch_all') || $request->query('limit') === 'all',
        ];

        $result = $this->catalogService->getProductsBySet((string) $setId, $params, $userId);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to fetch products for this product set.',
                'meta_error' => $result['meta_error'] ?? null
            ], 400);
        }

        return response()->json($result);
    }

    /**
     * POST /api/catalog/sets/{setId}/products
     * Add products (by SKU list / retailer_ids) to an existing Product Set.
     */
    public function addProductsToSet(Request $request, $setId)
    {
        $validator = Validator::make($request->all(), [
            'retailer_ids' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = $this->resolveUserId($request);
        $result = $this->catalogService->addProductsToSet(
            (string) $setId,
            $request->input('retailer_ids'),
            $userId
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to add products to set.',
                'meta_error' => $result['meta_error'] ?? null
            ], 400);
        }

        return response()->json($result);
    }

    /**
     * DELETE /api/catalog/sets/{setId}/products
     * Remove products (by SKU list / retailer_ids) from an existing Product Set.
     */
    public function removeProductsFromSet(Request $request, $setId)
    {
        $validator = Validator::make($request->all(), [
            'retailer_ids' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = $this->resolveUserId($request);
        $result = $this->catalogService->removeProductsFromSet(
            (string) $setId,
            $request->input('retailer_ids'),
            $userId
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to remove products from set.',
                'meta_error' => $result['meta_error'] ?? null
            ], 400);
        }

        return response()->json($result);
    }

    /**
     * DELETE /api/catalog/sets/{setId}
     * Delete a Product Set from Meta Commerce Catalog.
     */
    public function deleteSet(Request $request, $setId)
    {
        $userId = $this->resolveUserId($request);
        $result = $this->catalogService->deleteProductSet((string) $setId, $userId);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to delete product set.',
                'meta_error' => $result['meta_error'] ?? null
            ], 400);
        }

        return response()->json($result);
    }

    // =========================================================================
    // 3. PRODUCTS (Read, Search, Pagination)
    // =========================================================================

    /**
     * GET /api/catalog/products
     * Fetch products from Meta catalog.
     */
    public function getProducts(Request $request)
    {
        $userId = $this->resolveUserId($request);
        $catalogId = $request->query('catalog_id');
        $params = [
            'limit' => $request->query('limit', 25),
            'after' => $request->query('after'),
            'before' => $request->query('before'),
            'search' => $request->query('search'),
            'retailer_id' => $request->query('retailer_id'),
            'all' => $request->boolean('all') || $request->boolean('fetch_all') || $request->query('limit') === 'all',
        ];

        $result = $this->catalogService->getProducts($catalogId, $params, $userId);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to fetch catalog products.',
                'meta_error' => $result['meta_error'] ?? null
            ], 400);
        }

        return response()->json($result);
    }

    /**
     * GET /api/catalog/products/{identifier}
     * Get product by Meta ID or SKU/retailer_id.
     */
    public function getProduct(Request $request, $identifier)
    {
        $userId = $this->resolveUserId($request);
        $catalogId = $request->query('catalog_id');

        // Check if identifier is numeric Meta internal product ID
        if (is_numeric($identifier) && strlen((string) $identifier) > 10) {
            $result = $this->catalogService->getProductById((string) $identifier, $userId);
            if ($result['success']) {
                return response()->json($result);
            }
        }

        // Otherwise search by retailer_id
        $result = $this->catalogService->getProductByRetailerId((string) $identifier, $catalogId, $userId);

        if (!$result['success'] || empty($result['data'])) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'][0] ?? $result['data']
        ]);
    }

    // =========================================================================
    // 4. PRODUCT MANAGEMENT (Create, Bulk Create, Update, Delete)
    // =========================================================================

    /**
     * GET /api/catalog/products/bulk/template
     * Download or view Meta Commerce Manager product feed template (CSV, TSV, or JSON specification).
     */
    public function downloadBulkTemplate(Request $request)
    {
        $format = strtolower($request->query('format', 'csv'));
        $asJson = $request->boolean('json') || $request->wantsJson();

        if ($asJson) {
            return response()->json([
                'success' => true,
                'message' => 'Commerce Manager feed template specification.',
                'total_columns' => count($this->catalogService->getCatalogTemplateColumns()),
                'columns' => $this->catalogService->getCatalogTemplateColumns(),
                'sample_feed_rows' => [
                    'row_1_annotations' => '# Required / # Optional tags',
                    'row_2_headers' => array_column($this->catalogService->getCatalogTemplateColumns(), 'name'),
                    'row_3_sample_data' => array_column($this->catalogService->getCatalogTemplateColumns(), 'sample')
                ]
            ]);
        }

        $isTsv = ($format === 'tsv' || $format === 'tab');
        $delimiter = $isTsv ? "\t" : ",";
        $csvContent = $this->catalogService->getCatalogTemplateCsv($delimiter);
        $filename = $isTsv ? 'commerce_manager_catalog_template.tsv' : 'commerce_manager_catalog_template.csv';
        $contentType = $isTsv ? 'text/tab-separated-values' : 'text/csv';

        return response($csvContent, 200, [
            'Content-Type' => $contentType . '; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ]);
    }

    /**
     * POST /api/catalog/products/bulk
     * Bulk create products in Meta Commerce Catalog.
     * Supports:
     * 1. File upload (CSV, TSV, TXT, XLSX, XLS) matching Commerce Manager format.
     * 2. JSON array payload: {"products": [ {...}, ... ]}
     * 3. Raw text string: {"feed_text": "..."} or {"raw_data": "..."}
     */
    public function bulkStoreProducts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'nullable|file|mimes:csv,tsv,txt,xlsx,xls|max:51200', // 50MB max
            'products' => 'nullable|array|min:1',
            'feed_text' => 'nullable|string',
            'raw_data' => 'nullable|string',
            'catalog_id' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = $this->resolveUserId($request);
        $catalogId = $request->input('catalog_id');

        $products = [];

        // 1. Check for file upload
        if ($request->hasFile('file') && $request->file('file')->isValid()) {
            $products = $this->catalogService->parseCatalogFeedFile($request->file('file'));
        }
        // 2. Check for raw string (TSV / CSV copy-pasted)
        elseif ($request->filled('feed_text') || $request->filled('raw_data')) {
            $rawText = $request->input('feed_text') ?: $request->input('raw_data');
            $products = $this->catalogService->parseCatalogFeedString($rawText);
        }
        // 3. Check for JSON array of products
        elseif ($request->has('products') && is_array($request->input('products'))) {
            $products = $request->input('products');
        }

        if (empty($products)) {
            return response()->json([
                'success' => false,
                'message' => 'No products found. Please provide an uploaded file (CSV, TSV, XLSX), a "products" JSON array, or a "feed_text" string.',
                'template_url' => url('/api/catalog/products/bulk/template')
            ], 400);
        }

        $result = $this->catalogService->bulkCreateProducts($products, $catalogId, $userId);

        $status = ($result['success'] ?? false) ? 200 : 400;
        return response()->json($result, $status);
    }

    /**
     * POST /api/catalog/products
     * Create product in Meta Commerce Catalog.
     * Supports both 'image_url' (URL string) and 'images'/'image' (file upload, array of URLs, or base64).
     */
    public function storeProduct(Request $request)
    {
        // 1. Resolve uploaded image or provided URL (supports file upload 'images'/'image', base64, array of URLs, or 'image_url')
        $imageUrl = $this->catalogService->resolveUploadedOrProvidedImageUrl($request);

        // Support both retailer_id and content_id
        if (!$request->filled('retailer_id') && $request->filled('content_id')) {
            $request->merge(['retailer_id' => $request->input('content_id')]);
        }

        $validator = Validator::make($request->all(), [
            'retailer_id' => 'required_without:content_id|nullable|string|max:100',
            'content_id' => 'nullable|string|max:100',
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'image_url' => 'nullable|string',
            'images' => 'nullable',
            'image' => 'nullable',
            'description' => 'nullable|string|max:5000',
            'availability' => 'nullable|in:in stock,out of stock,in_stock,out_of_stock',
            'url' => 'nullable|url',
            'category' => 'nullable|string',
            'fb_product_category' => 'nullable|string',
            'brand' => 'nullable|string|max:100',
            'condition' => 'nullable|in:new,refurbished,used,used_like_new,used_good,used_fair',
            'status' => 'nullable|in:show,hide,published,staging,active,hidden',
            'visibility' => 'nullable|in:show,hide,published,staging,active,hidden',
            'product_set_id' => 'nullable|string',
            'catalog_id' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if (empty($imageUrl)) {
            return response()->json([
                'success' => false,
                'message' => 'Product image is required. Provide either \'images\' (file upload or URL) or \'image_url\' (public URL).'
            ], 422);
        }

        $userId = $this->resolveUserId($request);
        $catalogId = $request->input('catalog_id');

        $data = $request->all();
        $data['image_url'] = $imageUrl;
        $data['currency'] = strtoupper($request->input('currency', 'INR'));

        $result = $this->catalogService->createProduct($data, $catalogId, $userId);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to create product in Meta Catalog.',
                'meta_error' => $result['meta_error'] ?? null
            ], 400);
        }

        return response()->json($result, 201);
    }

    /**
     * PUT/POST /api/catalog/products/{productId}
     * Update product in Meta Commerce Catalog.
     * Supports both Meta internal numeric product ID AND merchant SKU / retailer_id.
     * Supports updating image via file upload 'images'/'image' or 'image_url'.
     */
    public function updateProduct(Request $request, $productId)
    {
        $imageUrl = $this->catalogService->resolveUploadedOrProvidedImageUrl($request);

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'image_url' => 'nullable|string',
            'images' => 'nullable',
            'image' => 'nullable',
            'description' => 'nullable|string|max:5000',
            'availability' => 'nullable|in:in stock,out of stock,in_stock,out_of_stock',
            'url' => 'nullable|url',
            'category' => 'nullable|string',
            'fb_product_category' => 'nullable|string',
            'brand' => 'nullable|string|max:100',
            'condition' => 'nullable|in:new,refurbished,used,used_like_new,used_good,used_fair',
            'status' => 'nullable|in:show,hide,published,staging,active,hidden',
            'visibility' => 'nullable|in:show,hide,published,staging,active,hidden',
            'catalog_id' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = $this->resolveUserId($request);
        $catalogId = $request->input('catalog_id');
        $data = $request->all();
        if (!empty($imageUrl)) {
            $data['image_url'] = $imageUrl;
        }

        $result = $this->catalogService->updateProduct((string) $productId, $data, $userId, $catalogId);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to update product in Meta Catalog.',
                'meta_error' => $result['meta_error'] ?? null
            ], 400);
        }

        return response()->json($result);
    }

    /**
     * DELETE /api/catalog/products/{productId}
     * Delete product from Meta Commerce Catalog.
     * Supports both Meta internal numeric product ID AND merchant SKU / retailer_id.
     */
    public function deleteProduct(Request $request, $productId)
    {
        $userId = $this->resolveUserId($request);
        $catalogId = $request->input('catalog_id');
        $result = $this->catalogService->deleteProduct((string) $productId, $userId, $catalogId);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to delete product from Meta Catalog.',
                'meta_error' => $result['meta_error'] ?? null
            ], 400);
        }

        return response()->json($result);
    }

    // =========================================================================
    // 5. SEND INTERACTIVE CATALOG MESSAGES
    // =========================================================================

    /**
     * POST /api/catalog/send-catalog
     * Send Storefront Catalog interactive link message.
     */
    public function sendCatalog(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'to' => 'required_without:conversation_id|nullable|string',
            'body_text' => 'required|string|max:1024',
            'footer_text' => 'nullable|string|max:60',
            'thumbnail_product_retailer_id' => 'nullable|string',
            'conversation_id' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = $this->resolveUserId($request);
        $to = trim((string) $request->input('to', ''));
        if ($to === '' && $request->filled('conversation_id')) {
            $conv = \App\Models\Conversation::find($request->input('conversation_id'));
            if ($conv && $conv->phone_number) {
                $to = $conv->phone_number;
            }
        }

        $result = $this->catalogService->sendCatalogMessage(
            $to,
            $request->input('body_text'),
            $request->input('footer_text'),
            $request->input('thumbnail_product_retailer_id'),
            $userId,
            $request->input('conversation_id')
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to send catalog message.',
                'meta_error' => $result['meta_error'] ?? null
            ], 400);
        }

        return response()->json($result);
    }

    /**
     * POST /api/catalog/send-product
     * Send Single Product Message (SPM).
     */
    public function sendProduct(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'to' => 'required_without:conversation_id|nullable|string',
            'product_retailer_id' => 'required|string',
            'catalog_id' => 'nullable|string',
            'body_text' => 'nullable|string|max:1024',
            'footer_text' => 'nullable|string|max:60',
            'conversation_id' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = $this->resolveUserId($request);
        $to = trim((string) $request->input('to', ''));
        if ($to === '' && $request->filled('conversation_id')) {
            $conv = \App\Models\Conversation::find($request->input('conversation_id'));
            if ($conv && $conv->phone_number) {
                $to = $conv->phone_number;
            }
        }

        $result = $this->catalogService->sendSingleProductMessage(
            $to,
            $request->input('product_retailer_id'),
            $request->input('catalog_id'),
            $request->input('body_text'),
            $request->input('footer_text'),
            $userId,
            $request->input('conversation_id')
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to send product message.',
                'meta_error' => $result['meta_error'] ?? null
            ], 400);
        }

        return response()->json($result);
    }

    /**
     * POST /api/catalog/send-product-list
     * Send Multi-Product Message (MPM).
     */
    public function sendProductList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'to' => 'required_without:conversation_id|nullable|string',
            'sections' => 'required|array|min:1',
            'sections.*.title' => 'required|string|max:24',
            'sections.*.product_items' => 'required|array|min:1',
            'sections.*.product_items.*.product_retailer_id' => 'required|string',
            'catalog_id' => 'nullable|string',
            'header_text' => 'nullable|string|max:60',
            'body_text' => 'required|string|max:1024',
            'footer_text' => 'nullable|string|max:60',
            'conversation_id' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = $this->resolveUserId($request);
        $to = trim((string) $request->input('to', ''));
        if ($to === '' && $request->filled('conversation_id')) {
            $conv = \App\Models\Conversation::find($request->input('conversation_id'));
            if ($conv && $conv->phone_number) {
                $to = $conv->phone_number;
            }
        }

        $result = $this->catalogService->sendMultiProductMessage(
            $to,
            $request->input('sections'),
            $request->input('catalog_id'),
            $request->input('header_text'),
            $request->input('body_text'),
            $request->input('footer_text'),
            $userId,
            $request->input('conversation_id')
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to send multi-product message.',
                'meta_error' => $result['meta_error'] ?? null
            ], 400);
        }

        return response()->json($result);
    }
}
