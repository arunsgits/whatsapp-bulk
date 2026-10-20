<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Settings;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppCatalogService
{
    protected string $graphApiVersion = 'v21.0';

    /**
     * Resolve settings for user.
     */
    public function resolveSettings(?int $userId = null): ?Settings
    {
        if ($userId) {
            return Settings::where('userId', $userId)->where('isActive', true)->first()
                ?: Settings::where('userId', $userId)->first();
        }

        // Never fall back to another company/user's WhatsApp settings
        return null;
    }

    /**
     * Get validated credentials for WhatsApp Graph API calls.
     * Returns [phoneNumberId, waba_id, accessToken]
     */
    public function getCredentials(?int $userId = null): array
    {
        $settings = $this->resolveSettings($userId);
        if (!$settings) {
            throw new \Exception('No WhatsApp settings found for this account.');
        }

        $phoneNumberId = trim((string) ($settings->phoneNumberId ?? ''));
        $accessToken = trim((string) ($settings->access_token ?? ''));
        $wabaId = trim((string) ($settings->waba_id ?? ''));

        if ($accessToken === '') {
            throw new \Exception('WhatsApp Access Token is not configured in settings.');
        }

        return [$phoneNumberId, $wabaId, $accessToken, $settings];
    }

    /**
     * Normalize phone number (digits only, India 91 prefix if 10 digits).
     */
    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 10 && preg_match('/^[6-9]/', $phone)) {
            return '91' . $phone;
        }
        return $phone;
    }

    // =========================================================================
    // 1. COMMERCE SETTINGS (Catalog & Cart visibility on Phone Number)
    // =========================================================================

    /**
     * Get WhatsApp Commerce Settings for phone number (is_catalog_visible, is_cart_enabled).
     */
    public function getCommerceSettings(?int $userId = null): array
    {
        try {
            [$phoneNumberId, $wabaId, $accessToken] = $this->getCredentials($userId);

            if ($phoneNumberId === '') {
                return [
                    'success' => false,
                    'error' => 'Phone Number ID is missing in WhatsApp settings.'
                ];
            }

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("https://graph.facebook.com/{$this->graphApiVersion}/{$phoneNumberId}/whatsapp_commerce_settings");

            if ($response->successful()) {
                $data = $response->json();
                $setting = $data['data'][0] ?? $data;

                return [
                    'success' => true,
                    'data' => [
                        'phone_number_id' => $phoneNumberId,
                        'is_catalog_visible' => (bool) ($setting['is_catalog_visible'] ?? false),
                        'is_cart_enabled' => (bool) ($setting['is_cart_enabled'] ?? false),
                        'id' => $setting['id'] ?? null
                    ]
                ];
            }

            $err = $response->json();
            return [
                'success' => false,
                'error' => $err['error']['message'] ?? 'Failed to fetch commerce settings from Meta.',
                'meta_error' => $err['error'] ?? null
            ];
        } catch (\Exception $e) {
            Log::error('getCommerceSettings error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update WhatsApp Commerce Settings for phone number (Toggle catalog visibility / cart).
     */
    public function updateCommerceSettings(bool $isCatalogVisible, bool $isCartEnabled, ?int $userId = null): array
    {
        try {
            [$phoneNumberId, $wabaId, $accessToken] = $this->getCredentials($userId);

            if ($phoneNumberId === '') {
                return [
                    'success' => false,
                    'error' => 'Phone Number ID is missing in WhatsApp settings.'
                ];
            }

            // Meta Graph API for whatsapp_commerce_settings expects form data / query params
            $params = [
                'is_catalog_visible' => $isCatalogVisible ? 'true' : 'false',
                'is_cart_enabled' => $isCartEnabled ? 'true' : 'false',
            ];

            // 1. Try sending as standard form data with Bearer token
            $response = Http::timeout(30)->asForm()->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->post("https://graph.facebook.com/{$this->graphApiVersion}/{$phoneNumberId}/whatsapp_commerce_settings", $params);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'WhatsApp commerce settings updated successfully.',
                    'data' => [
                        'phone_number_id' => $phoneNumberId,
                        'is_catalog_visible' => $isCatalogVisible,
                        'is_cart_enabled' => $isCartEnabled
                    ]
                ];
            }

            // 2. If is_catalog_visible: true failed (common when Meta requires storefront catalog to be chosen in WhatsApp Manager UI first),
            // attempt enabling is_cart_enabled individually so customer carts still function properly
            if ($isCatalogVisible) {
                $cartOnlyResponse = Http::timeout(30)->asForm()->withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                ])->post("https://graph.facebook.com/{$this->graphApiVersion}/{$phoneNumberId}/whatsapp_commerce_settings", [
                    'is_cart_enabled' => $isCartEnabled ? 'true' : 'false',
                ]);

                if ($cartOnlyResponse->successful()) {
                    return [
                        'success' => true,
                        'message' => 'Cart setting updated. Note: Storefront catalog icon requires catalog to be selected in Meta WhatsApp Manager (Catalog tab) before is_catalog_visible can be enabled.',
                        'data' => [
                            'phone_number_id' => $phoneNumberId,
                            'is_catalog_visible' => false,
                            'is_cart_enabled' => $isCartEnabled,
                            'notice' => 'Select catalog in Meta WhatsApp Manager UI (left menu > Catalog) to show the storefront icon on your profile.'
                        ]
                    ];
                }
            }

            $err = $response->json();
            return [
                'success' => false,
                'error' => $err['error']['message'] ?? 'Failed to update commerce settings on Meta.',
                'meta_error' => $err['error'] ?? null
            ];
        } catch (\Exception $e) {
            Log::error('updateCommerceSettings error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // =========================================================================
    // 2. CATALOG DISCOVERY & MANAGEMENT
    // =========================================================================

    /**
     * Get list of catalogs connected to WABA or Business.
     */
    public function getCatalogs(?int $userId = null): array
    {
        try {
            [$phoneNumberId, $wabaId, $accessToken, $settings] = $this->getCredentials($userId);

            if ($wabaId === '') {
                return [
                    'success' => false,
                    'error' => 'WABA ID (WhatsApp Business Account ID) is missing in WhatsApp settings.'
                ];
            }

            // 1. Try querying connected product catalogs from WABA
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("https://graph.facebook.com/{$this->graphApiVersion}/{$wabaId}/product_catalogs", [
                'fields' => 'id,name,product_count'
            ]);

            $catalogs = [];

            if ($response->successful()) {
                $catalogs = $response->json()['data'] ?? [];
            }

            // 2. Fallback: try owned_product_catalogs on WABA
            if (empty($catalogs)) {
                $responseOwned = Http::timeout(30)->withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                ])->get("https://graph.facebook.com/{$this->graphApiVersion}/{$wabaId}/owned_product_catalogs", [
                    'fields' => 'id,name,product_count'
                ]);

                if ($responseOwned->successful()) {
                    $catalogs = $responseOwned->json()['data'] ?? [];
                }
            }

            // 3. Fallback: try parent Business Portfolio owned_product_catalogs
            if (empty($catalogs)) {
                try {
                    $wabaInfoRes = Http::timeout(15)->withHeaders([
                        'Authorization' => "Bearer {$accessToken}",
                    ])->get("https://graph.facebook.com/{$this->graphApiVersion}/{$wabaId}", [
                        'fields' => 'id,name,owner_business_info'
                    ]);

                    $bizId = $wabaInfoRes->json()['owner_business_info']['id'] ?? null;
                    if ($bizId) {
                        $bizCatRes = Http::timeout(20)->withHeaders([
                            'Authorization' => "Bearer {$accessToken}",
                        ])->get("https://graph.facebook.com/{$this->graphApiVersion}/{$bizId}/owned_product_catalogs", [
                            'fields' => 'id,name,product_count'
                        ]);

                        if ($bizCatRes->successful()) {
                            $catalogs = $bizCatRes->json()['data'] ?? [];
                        }
                    }
                } catch (\Throwable $t) {
                    // Ignore business portfolio fallback error
                }
            }

            $defaultCatalogId = $this->getDefaultCatalogId($userId);

            // If catalogs is still empty, but user has a defaultCatalogId configured, ensure it is included in response
            if (empty($catalogs) && $defaultCatalogId) {
                $singleDetails = $this->getCatalogDetails($defaultCatalogId, $userId);
                if ($singleDetails['success'] && !empty($singleDetails['data'])) {
                    $catalogs[] = $singleDetails['data'];
                } else {
                    $catalogs[] = [
                        'id' => (string) $defaultCatalogId,
                        'name' => 'Catalog #' . $defaultCatalogId,
                        'vertical' => 'commerce',
                        'product_count' => null
                    ];
                }
            }

            // Mark default catalog
            $formattedCatalogs = array_map(function ($cat) use ($defaultCatalogId) {
                return [
                    'id' => (string) ($cat['id'] ?? ''),
                    'name' => $cat['name'] ?? 'Catalog #' . ($cat['id'] ?? ''),
                    'vertical' => $cat['vertical'] ?? 'commerce',
                    'product_count' => $cat['product_count'] ?? null,
                    'is_default' => (string) ($cat['id'] ?? '') === (string) $defaultCatalogId
                ];
            }, $catalogs);

            return [
                'success' => true,
                'default_catalog_id' => $defaultCatalogId ?: ($catalogs[0]['id'] ?? null),
                'count' => count($formattedCatalogs),
                'data' => $formattedCatalogs
            ];
        } catch (\Exception $e) {
            Log::error('getCatalogs error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Link / connect a Meta Commerce Catalog to the WhatsApp Business Account.
     */
    public function linkCatalogToWaba(string $catalogId, ?int $userId = null): array
    {
        try {
            [$phoneNumberId, $wabaId, $accessToken] = $this->getCredentials($userId);

            if ($wabaId === '') {
                return [
                    'success' => false,
                    'error' => 'WABA ID is missing in WhatsApp settings.'
                ];
            }

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json'
            ])->post("https://graph.facebook.com/{$this->graphApiVersion}/{$wabaId}/product_catalogs", [
                'catalog_id' => $catalogId
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Catalog linked to WhatsApp Business Account on Meta successfully.'
                ];
            }

            $err = $response->json();
            return [
                'success' => false,
                'error' => $err['error']['message'] ?? 'Failed to link catalog to WABA on Meta.',
                'meta_error' => $err['error'] ?? null
            ];
        } catch (\Exception $e) {
            Log::error('linkCatalogToWaba error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create a new Catalog on Meta Commerce Manager under the Business Portfolio and auto-link to WABA.
     */
    public function createCatalog(string $name, ?string $businessId = null, ?int $userId = null): array
    {
        try {
            [$phoneNumberId, $wabaId, $accessToken] = $this->getCredentials($userId);

            $bizId = trim((string) $businessId);

            // Auto-discover Business ID if not supplied
            if ($bizId === '' && $wabaId !== '') {
                try {
                    $wabaRes = Http::timeout(15)->withHeaders([
                        'Authorization' => "Bearer {$accessToken}",
                    ])->get("https://graph.facebook.com/{$this->graphApiVersion}/{$wabaId}", [
                        'fields' => 'id,name,owner_business_info'
                    ]);
                    if ($wabaRes->successful()) {
                        $bizId = (string) ($wabaRes->json()['owner_business_info']['id'] ?? '');
                    }
                } catch (\Throwable $t) {
                    Log::warning('Auto-resolving business ID failed: ' . $t->getMessage());
                }
            }

            if ($bizId === '') {
                return [
                    'success' => false,
                    'error' => 'Business Portfolio ID could not be auto-detected. Please pass "business_id" in the request.'
                ];
            }

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json'
            ])->post("https://graph.facebook.com/{$this->graphApiVersion}/{$bizId}/owned_product_catalogs", [
                'name' => trim($name),
                'vertical' => 'commerce'
            ]);

            if ($response->successful()) {
                $catData = $response->json();
                $newCatalogId = (string) ($catData['id'] ?? '');

                // Auto link to WABA
                $linked = false;
                if ($newCatalogId !== '' && $wabaId !== '') {
                    $linkRes = $this->linkCatalogToWaba($newCatalogId, $userId);
                    $linked = $linkRes['success'] ?? false;
                }

                // If user currently has no default catalog, set this as default
                if (!$this->getDefaultCatalogId($userId)) {
                    $this->setDefaultCatalogId($newCatalogId, $userId);
                }

                return [
                    'success' => true,
                    'message' => 'New catalog created successfully on Meta Commerce Manager.',
                    'catalog_id' => $newCatalogId,
                    'name' => trim($name),
                    'business_id' => $bizId,
                    'is_linked_to_waba' => $linked
                ];
            }

            $err = $response->json();
            return [
                'success' => false,
                'error' => $err['error']['message'] ?? 'Failed to create catalog on Meta.',
                'meta_error' => $err['error'] ?? null
            ];
        } catch (\Exception $e) {
            Log::error('createCatalog error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get list of Product Sets in a Catalog.
     */
    public function getProductSets(?string $catalogId = null, ?int $userId = null): array
    {
        try {
            [$phoneNumberId, $wabaId, $accessToken] = $this->getCredentials($userId);
            $effectiveCatalogId = $catalogId ?: $this->getDefaultCatalogId($userId);

            if (!$effectiveCatalogId) {
                return [
                    'success' => false,
                    'error' => 'No catalog ID specified or found for this account.'
                ];
            }

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("https://graph.facebook.com/{$this->graphApiVersion}/{$effectiveCatalogId}/product_sets", [
                'fields' => 'id,name,filter,product_count'
            ]);

            if ($response->successful()) {
                $sets = $response->json()['data'] ?? [];
                return [
                    'success' => true,
                    'catalog_id' => $effectiveCatalogId,
                    'count' => count($sets),
                    'data' => $sets
                ];
            }

            $err = $response->json();
            return [
                'success' => false,
                'error' => $err['error']['message'] ?? 'Failed to fetch product sets.',
                'meta_error' => $err['error'] ?? null
            ];
        } catch (\Exception $e) {
            Log::error('getProductSets error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get details for a specific Product Set.
     */
    public function getProductSetDetails(string $setId, ?int $userId = null): array
    {
        try {
            [$phoneNumberId, $wabaId, $accessToken] = $this->getCredentials($userId);

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("https://graph.facebook.com/{$this->graphApiVersion}/{$setId}", [
                'fields' => 'id,name,filter,product_count,catalog'
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            $err = $response->json();
            return [
                'success' => false,
                'error' => $err['error']['message'] ?? 'Product set not found.',
                'meta_error' => $err['error'] ?? null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create a Product Set in Meta Commerce Catalog.
     */
    public function createProductSet(string $name, array $options = [], ?string $catalogId = null, ?int $userId = null): array
    {
        try {
            [$phoneNumberId, $wabaId, $accessToken] = $this->getCredentials($userId);
            $effectiveCatalogId = $catalogId ?: ($options['catalog_id'] ?? $this->getDefaultCatalogId($userId));

            if (!$effectiveCatalogId) {
                return [
                    'success' => false,
                    'error' => 'No catalog ID specified or found.'
                ];
            }

            // Build filter
            $filter = [];
            if (!empty($options['retailer_ids']) && is_array($options['retailer_ids'])) {
                $skus = array_values(array_filter(array_map('strval', $options['retailer_ids'])));
                $filter = ['retailer_id' => ['is_any' => $skus]];
            } elseif (!empty($options['product_ids']) && is_array($options['product_ids'])) {
                $ids = array_values(array_filter(array_map('strval', $options['product_ids'])));
                $filter = ['id' => ['is_any' => $ids]];
            } elseif (!empty($options['filter'])) {
                $filter = is_string($options['filter']) ? json_decode($options['filter'], true) : $options['filter'];
            }

            $payload = [
                'name' => trim($name),
            ];
            if (!empty($filter)) {
                $payload['filter'] = json_encode($filter);
            }

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json'
            ])->post("https://graph.facebook.com/{$this->graphApiVersion}/{$effectiveCatalogId}/product_sets", $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message' => 'Product set created successfully in Meta Commerce Catalog.',
                    'product_set_id' => $data['id'] ?? null,
                    'catalog_id' => $effectiveCatalogId,
                    'name' => trim($name),
                    'filter' => $filter
                ];
            }

            $err = $response->json();
            return [
                'success' => false,
                'error' => $err['error']['message'] ?? 'Failed to create product set.',
                'meta_error' => $err['error'] ?? null
            ];
        } catch (\Exception $e) {
            Log::error('createProductSet error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get products belonging to a specific Product Set.
     */
    public function getProductsBySet(string $setId, array $params = [], ?int $userId = null): array
    {
        try {
            [$phoneNumberId, $wabaId, $accessToken] = $this->getCredentials($userId);

            $fetchAll = !empty($params['all']) || !empty($params['fetch_all']);
            $targetLimit = $fetchAll ? 2000 : max(1, (int) ($params['limit'] ?? 25));
            $batchLimit = min(100, $targetLimit);

            $queryParams = [
                'fields' => 'id,retailer_id,name,description,price,currency,sale_price,availability,image_url,url,category,condition,visibility,review_status,brand',
                'limit' => $batchLimit
            ];
            if (!empty($params['after'])) $queryParams['after'] = $params['after'];
            if (!empty($params['before'])) $queryParams['before'] = $params['before'];

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("https://graph.facebook.com/{$this->graphApiVersion}/{$setId}/products", $queryParams);

            if ($response->successful()) {
                $json = $response->json();
                $items = $json['data'] ?? [];
                $paging = $json['paging'] ?? [];

                // Automatically follow cursors if all=true or requested limit exceeds 100
                if (($fetchAll || $targetLimit > 100) && count($items) < $targetLimit) {
                    $pageCount = 1;
                    $maxPages = (int) ceil($targetLimit / 100);
                    $afterCursor = $paging['cursors']['after'] ?? null;

                    while (!empty($afterCursor) && $pageCount < $maxPages && count($items) < $targetLimit) {
                        $nextQueryParams = $queryParams;
                        $nextQueryParams['after'] = $afterCursor;
                        $nextQueryParams['limit'] = min(100, $targetLimit - count($items));

                        $nextRes = Http::timeout(30)->withHeaders([
                            'Authorization' => "Bearer {$accessToken}",
                        ])->get("https://graph.facebook.com/{$this->graphApiVersion}/{$setId}/products", $nextQueryParams);

                        if ($nextRes->successful()) {
                            $nextJson = $nextRes->json();
                            $nextItems = $nextJson['data'] ?? [];
                            if (empty($nextItems)) {
                                break;
                            }
                            $items = array_merge($items, $nextItems);
                            $paging = $nextJson['paging'] ?? [];
                            $afterCursor = $paging['cursors']['after'] ?? null;
                            $pageCount++;
                        } else {
                            break;
                        }
                    }
                }

                $formatted = array_map(function ($item) {
                    return $this->formatProductItem($item);
                }, $items);

                return [
                    'success' => true,
                    'product_set_id' => $setId,
                    'count' => count($formatted),
                    'data' => $formatted,
                    'pagination' => [
                        'has_next' => !empty($paging['cursors']['after']),
                        'has_previous' => !empty($paging['cursors']['before']),
                        'after' => $paging['cursors']['after'] ?? null,
                        'before' => $paging['cursors']['before'] ?? null
                    ]
                ];
            }

            $err = $response->json();
            return [
                'success' => false,
                'error' => $err['error']['message'] ?? 'Failed to fetch products for this set.',
                'meta_error' => $err['error'] ?? null
            ];
        } catch (\Exception $e) {
            Log::error('getProductsBySet error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Add products (by SKU / retailer_id) to a Product Set.
     */
    public function addProductsToSet(string $setId, array $retailerIds, ?int $userId = null): array
    {
        try {
            [$phoneNumberId, $wabaId, $accessToken] = $this->getCredentials($userId);

            $setDetails = $this->getProductSetDetails($setId, $userId);
            if (!$setDetails['success']) {
                return $setDetails;
            }

            $rawFilter = $setDetails['data']['filter'] ?? [];
            if (is_string($rawFilter)) {
                $rawFilter = json_decode($rawFilter, true) ?: [];
            }

            $existingSkus = $rawFilter['retailer_id']['is_any'] ?? [];
            if (!is_array($existingSkus)) {
                $existingSkus = [];
            }

            $newSkus = array_values(array_unique(array_merge($existingSkus, array_map('strval', $retailerIds))));
            $updatedFilter = $rawFilter;
            $updatedFilter['retailer_id'] = ['is_any' => $newSkus];

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json'
            ])->post("https://graph.facebook.com/{$this->graphApiVersion}/{$setId}", [
                'filter' => json_encode($updatedFilter)
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Products added to product set successfully.',
                    'product_set_id' => $setId,
                    'added_count' => count($retailerIds),
                    'total_skus' => count($newSkus)
                ];
            }

            $err = $response->json();
            return [
                'success' => false,
                'error' => $err['error']['message'] ?? 'Failed to add products to set.',
                'meta_error' => $err['error'] ?? null
            ];
        } catch (\Exception $e) {
            Log::error('addProductsToSet error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Remove products (by SKU / retailer_id) from a Product Set.
     */
    public function removeProductsFromSet(string $setId, array $retailerIds, ?int $userId = null): array
    {
        try {
            [$phoneNumberId, $wabaId, $accessToken] = $this->getCredentials($userId);

            $setDetails = $this->getProductSetDetails($setId, $userId);
            if (!$setDetails['success']) {
                return $setDetails;
            }

            $rawFilter = $setDetails['data']['filter'] ?? [];
            if (is_string($rawFilter)) {
                $rawFilter = json_decode($rawFilter, true) ?: [];
            }

            $existingSkus = $rawFilter['retailer_id']['is_any'] ?? [];
            if (!is_array($existingSkus)) {
                $existingSkus = [];
            }

            $removeMap = array_flip(array_map('strval', $retailerIds));
            $remainingSkus = array_values(array_filter($existingSkus, fn($s) => !isset($removeMap[$s])));

            $updatedFilter = $rawFilter;
            $updatedFilter['retailer_id'] = ['is_any' => $remainingSkus];

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json'
            ])->post("https://graph.facebook.com/{$this->graphApiVersion}/{$setId}", [
                'filter' => json_encode($updatedFilter)
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Products removed from product set successfully.',
                    'product_set_id' => $setId,
                    'removed_count' => count($existingSkus) - count($remainingSkus),
                    'total_skus' => count($remainingSkus)
                ];
            }

            $err = $response->json();
            return [
                'success' => false,
                'error' => $err['error']['message'] ?? 'Failed to remove products from set.',
                'meta_error' => $err['error'] ?? null
            ];
        } catch (\Exception $e) {
            Log::error('removeProductsFromSet error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete a Product Set from Meta Commerce Catalog.
     */
    public function deleteProductSet(string $setId, ?int $userId = null): array
    {
        try {
            [$phoneNumberId, $wabaId, $accessToken] = $this->getCredentials($userId);

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->delete("https://graph.facebook.com/{$this->graphApiVersion}/{$setId}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Product set deleted successfully from Meta Commerce Catalog.',
                    'product_set_id' => $setId
                ];
            }

            $err = $response->json();
            return [
                'success' => false,
                'error' => $err['error']['message'] ?? 'Failed to delete product set.',
                'meta_error' => $err['error'] ?? null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get list of Facebook / Meta Commerce Product Categories taxonomy.
     */
    public function getFacebookCategories(?string $search = null, int $limit = 50): array
    {
        $categories = [
            'Apparel & Accessories > Clothing',
            'Apparel & Accessories > Clothing > Coats & Jackets',
            'Apparel & Accessories > Clothing > Traditional & Cultural Wear',
            'Apparel & Accessories > Clothing > Shirts & Tops',
            'Apparel & Accessories > Clothing > Pants & Trousers',
            'Apparel & Accessories > Clothing > Suits',
            'Apparel & Accessories > Clothing Accessories',
            'Apparel & Accessories > Clothing Accessories > Belts',
            'Apparel & Accessories > Clothing Accessories > Scarves & Shawls',
            'Apparel & Accessories > Clothing Accessories > Wedding Accessories',
            'Apparel & Accessories > Jewelry',
            'Apparel & Accessories > Shoes',
            'Apparel & Accessories > Shoes > Boots',
            'Apparel & Accessories > Shoes > Formal Shoes',
            'Apparel & Accessories > Handbags, Wallets & Cases',
            'Electronics',
            'Electronics > Audio',
            'Electronics > Communications > Telephony > Mobile Phones',
            'Electronics > Computers',
            'Health & Beauty',
            'Health & Beauty > Personal Care',
            'Home & Garden',
            'Home & Garden > Decor',
            'Home & Garden > Kitchen & Dining',
            'Sporting Goods',
            'Sporting Goods > Exercise & Fitness',
            'Sporting Goods > Outdoor Recreation',
            'Toys & Games',
            'Toys & Games > Games & Puzzles',
            'Travel & Luggage',
            'Travel & Luggage > Suitcases',
            'Travel & Luggage > Duffle Bags',
            'Vehicle Parts & Accessories',
            'Food, Beverages & Tobacco',
            'Baby & Toddler',
            'Office Supplies',
            'Hardware'
        ];

        if (!empty($search)) {
            $term = strtolower(trim($search));
            $categories = array_values(array_filter($categories, function ($cat) use ($term) {
                return str_contains(strtolower($cat), $term);
            }));
        }

        $limited = array_slice($categories, 0, max(1, min(200, $limit)));

        return [
            'success' => true,
            'total' => count($categories),
            'count' => count($limited),
            'data' => $limited
        ];
    }

    /**
     * Get user's default/selected catalog ID.
     */
    public function getDefaultCatalogId(?int $userId = null): ?string
    {
        if (!$userId || $userId <= 0) {
            return null;
        }

        // Never return a catalog if the user does not have catalog permission enabled
        $user = \App\Models\User::find($userId);
        if ($user && method_exists($user, 'hasPermission') && !$user->hasPermission('catalog')) {
            return null;
        }

        $key = 'whatsapp_catalog_id_' . $userId;
        $cached = Cache::get($key);
        if ($cached) {
            return (string) $cached;
        }

        // Try to auto-resolve from Meta for this specific user only
        try {
            [$phoneNumberId, $wabaId, $accessToken] = $this->getCredentials($userId);
            if ($wabaId !== '') {
                $res = Http::timeout(10)->withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                ])->get("https://graph.facebook.com/{$this->graphApiVersion}/{$wabaId}/product_catalogs");

                if ($res->successful()) {
                    $data = $res->json()['data'] ?? [];
                    if (!empty($data[0]['id'])) {
                        $catalogId = (string) $data[0]['id'];
                        $this->setDefaultCatalogId($catalogId, $userId);
                        return $catalogId;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore error in auto-resolve
        }

        return null;
    }

    /**
     * Set user's default/selected catalog ID.
     */
    public function setDefaultCatalogId(string $catalogId, ?int $userId = null): void
    {
        if (!$userId || $userId <= 0) {
            return;
        }
        $key = 'whatsapp_catalog_id_' . $userId;
        Cache::forever($key, $catalogId);
    }

    /**
     * Get specific catalog metadata from Meta.
     */
    public function getCatalogDetails(string $catalogId, ?int $userId = null): array
    {
        try {
            [$phoneNumberId, $wabaId, $accessToken] = $this->getCredentials($userId);

            // 1. Try with fields id,name,product_count
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("https://graph.facebook.com/{$this->graphApiVersion}/{$catalogId}", [
                'fields' => 'id,name,product_count'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $data['vertical'] = 'commerce';
                return [
                    'success' => true,
                    'data' => $data
                ];
            }

            // 2. Fallback without fields parameter
            $fallbackRes = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("https://graph.facebook.com/{$this->graphApiVersion}/{$catalogId}");

            if ($fallbackRes->successful()) {
                $data = $fallbackRes->json();
                $data['vertical'] = 'commerce';
                return [
                    'success' => true,
                    'data' => $data
                ];
            }

            $err = $response->json();
            $msg = $err['error']['message'] ?? 'Failed to fetch catalog details.';
            $errCode = (int) ($err['error']['code'] ?? 0);
            if (in_array($errCode, [100, 200]) && str_contains(strtolower($msg), 'not been approved')) {
                $msg = "Meta Permission Missing: Your Access Token does not have 'catalog_management' permission. Please generate a new System User token in Meta Business Settings (Users > System Users > Select user > Generate token) with 'catalog_management' checked, then update it in CRM Settings.";
            }

            return [
                'success' => false,
                'error' => $msg,
                'meta_error' => $err['error'] ?? null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // =========================================================================
    // 3. PRODUCTS (Read, Search, Pagination)
    // =========================================================================

    /**
     * List products from Meta Commerce Catalog.
     */
    public function getProducts(?string $catalogId = null, array $params = [], ?int $userId = null): array
    {
        try {
            [$phoneNumberId, $wabaId, $accessToken] = $this->getCredentials($userId);

            $effectiveCatalogId = $catalogId ?: $this->getDefaultCatalogId($userId);

            if (!$effectiveCatalogId) {
                return [
                    'success' => false,
                    'error' => 'No catalog specified and no connected catalog found for this account. Please specify a catalog_id.'
                ];
            }

            $fetchAll = !empty($params['all']) || !empty($params['fetch_all']);
            $targetLimit = $fetchAll ? 2000 : max(1, (int) ($params['limit'] ?? 25));
            $batchLimit = min(100, $targetLimit);

            $queryParams = [
                'fields' => 'id,retailer_id,name,description,price,currency,availability,image_url,url,category,condition,visibility,review_status',
                'limit' => $batchLimit
            ];

            if (!empty($params['after'])) {
                $queryParams['after'] = $params['after'];
            }
            if (!empty($params['before'])) {
                $queryParams['before'] = $params['before'];
            }

            // If retailer_id filter requested
            if (!empty($params['retailer_id'])) {
                $queryParams['filter'] = json_encode([
                    'retailer_id' => ['eq' => $params['retailer_id']]
                ]);
            }

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("https://graph.facebook.com/{$this->graphApiVersion}/{$effectiveCatalogId}/products", $queryParams);

            if ($response->successful()) {
                $json = $response->json();
                $items = $json['data'] ?? [];
                $paging = $json['paging'] ?? [];

                // Automatically follow cursors if all=true or requested limit exceeds 100
                if (($fetchAll || $targetLimit > 100) && count($items) < $targetLimit) {
                    $pageCount = 1;
                    $maxPages = (int) ceil($targetLimit / 100);
                    $afterCursor = $paging['cursors']['after'] ?? null;

                    while (!empty($afterCursor) && $pageCount < $maxPages && count($items) < $targetLimit) {
                        $nextQueryParams = $queryParams;
                        $nextQueryParams['after'] = $afterCursor;
                        $nextQueryParams['limit'] = min(100, $targetLimit - count($items));

                        $nextRes = Http::timeout(30)->withHeaders([
                            'Authorization' => "Bearer {$accessToken}",
                        ])->get("https://graph.facebook.com/{$this->graphApiVersion}/{$effectiveCatalogId}/products", $nextQueryParams);

                        if ($nextRes->successful()) {
                            $nextJson = $nextRes->json();
                            $nextItems = $nextJson['data'] ?? [];
                            if (empty($nextItems)) {
                                break;
                            }
                            $items = array_merge($items, $nextItems);
                            $paging = $nextJson['paging'] ?? [];
                            $afterCursor = $paging['cursors']['after'] ?? null;
                            $pageCount++;
                        } else {
                            break;
                        }
                    }
                }

                // Format products cleanly
                $formatted = array_map(function ($item) {
                    return $this->formatProductItem($item);
                }, $items);

                // Optional search filtering by text (name, description, SKU)
                if (!empty($params['search'])) {
                    $search = strtolower(trim((string) $params['search']));
                    $formatted = array_values(array_filter($formatted, function ($p) use ($search) {
                        return str_contains(strtolower($p['name']), $search)
                            || str_contains(strtolower($p['retailer_id']), $search)
                            || str_contains(strtolower($p['description']), $search);
                    }));
                }

                return [
                    'success' => true,
                    'catalog_id' => $effectiveCatalogId,
                    'count' => count($formatted),
                    'data' => $formatted,
                    'pagination' => [
                        'has_next' => !empty($paging['cursors']['after']),
                        'has_previous' => !empty($paging['cursors']['before']),
                        'after' => $paging['cursors']['after'] ?? null,
                        'before' => $paging['cursors']['before'] ?? null
                    ]
                ];
            }

            $err = $response->json();
            $msg = $err['error']['message'] ?? 'Failed to fetch products from Meta Catalog.';
            $errCode = (int) ($err['error']['code'] ?? 0);
            if (in_array($errCode, [100, 200]) && str_contains(strtolower($msg), 'not been approved')) {
                $msg = "Meta Permission Missing: Your Access Token requires 'catalog_management' permission to query products via Meta API. In Meta Business Settings (Users > System Users > Select user > Generate token), ensure 'catalog_management' is checked when generating the token.";
            }

            return [
                'success' => false,
                'error' => $msg,
                'meta_error' => $err['error'] ?? null
            ];
        } catch (\Exception $e) {
            Log::error('getProducts error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get a single product by its SKU / retailer_id.
     */
    public function getProductByRetailerId(string $retailerId, ?string $catalogId = null, ?int $userId = null): array
    {
        return $this->getProducts($catalogId, ['retailer_id' => $retailerId, 'limit' => 1], $userId);
    }

    /**
     * Get a single product by Meta internal product ID.
     */
    public function getProductById(string $productId, ?int $userId = null): array
    {
        try {
            [$phoneNumberId, $wabaId, $accessToken] = $this->getCredentials($userId);

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("https://graph.facebook.com/{$this->graphApiVersion}/{$productId}", [
                'fields' => 'id,retailer_id,name,description,price,currency,availability,image_url,url,category,condition,visibility,review_status'
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $this->formatProductItem($response->json())
                ];
            }

            $err = $response->json();
            return [
                'success' => false,
                'error' => $err['error']['message'] ?? 'Product not found.',
                'meta_error' => $err['error'] ?? null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Invalidate catalog products cache.
     */
    public function invalidateCatalogProductsCache(?string $catalogId = null, ?int $userId = null): void
    {
        $effectiveCatalogId = $catalogId ?: $this->getDefaultCatalogId($userId);
        if ($effectiveCatalogId) {
            Cache::forget("whatsapp_catalog_prods_{$effectiveCatalogId}_{$userId}");
            Cache::forget("whatsapp_catalog_prods_{$effectiveCatalogId}_all");
        }
    }

    /**
     * Get all catalog products with caching (10 minutes).
     */
    public function getCachedCatalogProducts(?string $catalogId = null, ?int $userId = null, bool $forceRefresh = false): array
    {
        $effectiveCatalogId = $catalogId ?: $this->getDefaultCatalogId($userId);
        if (!$effectiveCatalogId) {
            return [];
        }

        $cacheKey = "whatsapp_catalog_prods_{$effectiveCatalogId}_{$userId}";

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 600, function () use ($effectiveCatalogId, $userId) {
            try {
                $res = $this->getProducts($effectiveCatalogId, ['limit' => 100], $userId);
                if (!empty($res['success']) && is_array($res['data'] ?? null)) {
                    return $res['data'];
                }
            } catch (\Throwable $e) {
                Log::warning('getCachedCatalogProducts error: ' . $e->getMessage());
            }
            return [];
        });
    }

    /**
     * Search catalog products intelligently for Auto AI replies.
     * Distinguishes between exact single product matches, variety requests (multiple products), and full catalog browse.
     */
    public function searchCatalogProducts(string $query, ?string $catalogId = null, ?int $userId = null): array
    {
        $raw = trim($query);
        $lower = mb_strtolower($raw);

        // 0. Logistics, shipping, delivery, payment, and policy questions should NEVER be treated as catalog product queries
        $logisticsRegex = '/\b(delivery|shipping|home\s+delivery|courier|dispatch|cod|cash\s+on\s+delivery|suvidha|uplabdh|available|return|returns|refund|refunds|exchange|warranty|guarantee|timing|hours|open|close|address|location|office|visit)\b/iu';
        if (preg_match($logisticsRegex, $lower) && !preg_match('/\b(catalog|catalogue|menu|store|shop|collection)\b/iu', $lower)) {
            $nonLogistics = trim(preg_replace($logisticsRegex, ' ', $lower));
            $nonLogistics = trim(preg_replace('/\b(do|you|have|any|please|can|i|get|hai|kya|h|batao|ki|ke|ka|ko|me|mai|mein|se|par|the|is|are|service|charges?)\b/iu', ' ', $nonLogistics));
            if ($nonLogistics === '') {
                // Pure logistics/service inquiry (e.g. "delivery ki suvidha uplabdh hai?", "COD available hai?")
                return [
                    'is_catalog_query' => false,
                    'is_variety_query' => false,
                    'exact_match' => null,
                    'matches' => [],
                    'total' => 0,
                    'search_term' => '',
                ];
            }
        }

        // 1. Detect variety intent
        $varietyPatterns = [
            '/\b(variety|varieties|options?|different|designs?|patterns?|styles?|aur dikhao|aur options?|range|multiple|types?|all styles)\b/iu',
            '/\b(aur|aur bhi|aur kuch|more|other)\b/iu'
        ];
        $isVarietyQuery = false;
        foreach ($varietyPatterns as $vp) {
            if (preg_match($vp, $lower)) {
                $isVarietyQuery = true;
                break;
            }
        }

        // 2. Detect catalog intent (catches "Catalog", "Send me catalog", "send me catalogue", "catalog bhejo", "share catalog", etc.)
        $isCatalogQuery = (bool) preg_match('/\b(catalog|catalogue|catalogs|catalogues)\b/iu', $lower)
            || (bool) preg_match('/^(menu|store|shop|collection|collections|all products?|kya kya hai|kya items? hai|products?)[\s!.]*$/iu', $lower)
            || (bool) preg_match('/\b(show|send|bhejo|share|view|see|give|dikhao|batao)\b.*\b(catalog|catalogue|collection|collections|all products?|products?|menu|store)\b/iu', $lower)
            || (bool) preg_match('/\b(catalog|catalogue|collection)\b.*\b(bhejo|dikhao|share|send|de do|chahiye)\b/iu', $lower);

        $effectiveCatalogId = $catalogId ?: $this->getDefaultCatalogId($userId);
        if (!$effectiveCatalogId) {
            return [
                'is_catalog_query' => $isCatalogQuery,
                'is_variety_query' => $isVarietyQuery,
                'exact_match' => null,
                'matches' => [],
                'total' => 0,
                'search_term' => '',
            ];
        }

        $products = $this->getCachedCatalogProducts($effectiveCatalogId, $userId);
        if (empty($products)) {
            return [
                'is_catalog_query' => $isCatalogQuery,
                'is_variety_query' => $isVarietyQuery,
                'exact_match' => null,
                'matches' => [],
                'total' => 0,
                'search_term' => '',
            ];
        }

        // Filter out obvious placeholder dummy products (Latin faker items like reiciendis, voluptate)
        $realProducts = array_values(array_filter($products, function ($p) {
            $name = mb_strtolower($p['name'] ?? '');
            foreach (['reiciendis', 'voluptate', 'cupiditate', 'lorem', 'ipsum', 'dolor'] as $dummy) {
                if (str_contains($name, $dummy)) {
                    return false;
                }
            }
            return true;
        }));
        $effectiveProducts = !empty($realProducts) ? $realProducts : $products;

        // 3. Clean search term: remove common conversational noise and media/catalog qualifiers
        $noiseRegex = '/\b(do|you|have|any|please|can|i|get|show|me|send|give|want|looking|for|details|of|about|price|bhejo|dikhao|chahiye|hai|kya|batao|rate|cost|in|a|the|with|and|or|variety|varieties|options?|different|designs?|types?|item|product|catalog|catalogue|catalogs|catalogues|collection|collections|menu|store|shop|from|share|properly|image|images|photo|photos|pic|pics|picture|pictures|tasveer|link|wrong|galat|de do)\b/iu';
        $cleanTerm = preg_replace($noiseRegex, ' ', $lower);
        $cleanTerm = trim(preg_replace('/[^\p{L}\p{N}\s_-]+/u', ' ', $cleanTerm));
        $cleanTerm = trim(preg_replace('/\s+/', ' ', $cleanTerm));

        // If query was purely asking for catalog with no specific product mentioned
        if ($cleanTerm === '' && $isCatalogQuery) {
            return [
                'is_catalog_query' => true,
                'is_variety_query' => false,
                'exact_match' => null,
                'matches' => array_slice($effectiveProducts, 0, 10),
                'total' => count($effectiveProducts),
                'search_term' => 'Catalog',
            ];
        }

        // If query was purely asking for variety with no specific product mentioned (e.g. "show me variety", "options please")
        if ($cleanTerm === '' && $isVarietyQuery) {
            return [
                'is_catalog_query' => false,
                'is_variety_query' => true,
                'exact_match' => null,
                'matches' => array_slice($effectiveProducts, 0, 10),
                'total' => count($effectiveProducts),
                'search_term' => 'Variety',
            ];
        }

        if ($cleanTerm === '') {
            return [
                'is_catalog_query' => false,
                'is_variety_query' => false,
                'exact_match' => null,
                'matches' => [],
                'total' => 0,
                'search_term' => '',
            ];
        }

        // Singular stem for common plurals (e.g. kurtas -> kurta, sherwanis -> sherwani)
        $stem = $cleanTerm;
        if (str_ends_with($stem, 'ies') && strlen($stem) > 4) {
            $stem = substr($stem, 0, -3) . 'y';
        } elseif (str_ends_with($stem, 'es') && strlen($stem) > 3) {
            $stem = substr($stem, 0, -2);
        } elseif (str_ends_with($stem, 's') && strlen($stem) > 2) {
            $stem = substr($stem, 0, -1);
        }

        $synonyms = $this->resolveVernacularSynonyms($cleanTerm, $userId);

        $matches = [];
        $exactMatch = null;

        foreach ($effectiveProducts as $p) {
            $pName = mb_strtolower($p['name'] ?? '');
            $pSku = mb_strtolower($p['retailer_id'] ?? '');
            $pCategory = mb_strtolower($p['category'] ?? '');
            $pDesc = mb_strtolower($p['description'] ?? '');

            // Exact SKU match
            if ($pSku === $cleanTerm) {
                $exactMatch = $p;
                $matches = [$p];
                break;
            }

            // Exact Name match
            if ($pName === $cleanTerm) {
                $exactMatch = $p;
                $matches = [$p];
                break;
            }

            // Name match: check substring in both directions
            $nameMatch = str_contains($pName, $cleanTerm)
                || ($stem !== '' && str_contains($pName, $stem))
                || (mb_strlen($pName) >= 3 && str_contains($cleanTerm, $pName))
                || (mb_strlen($pName) >= 3 && str_contains($lower, $pName));

            // Category match
            $categoryMatch = str_contains($pCategory, $cleanTerm) || ($stem !== '' && str_contains($pCategory, $stem));

            // Description match
            $descMatch = (strlen($cleanTerm) >= 3 && str_contains($pDesc, $cleanTerm));

            // Synonym match
            $synonymMatch = false;
            foreach ($synonyms as $syn) {
                $syn = mb_strtolower(trim($syn));
                if ($syn === '' || mb_strlen($syn) < 3) {
                    continue;
                }
                if (str_contains($pName, $syn) || str_contains($pCategory, $syn) || str_contains($pDesc, $syn) || (mb_strlen($pName) >= 3 && str_contains($syn, $pName))) {
                    $synonymMatch = true;
                    break;
                }
            }

            // Word-level token match (e.g. "ledar" matching "लेदर", "loafers" matching "लोफर्स")
            $tokenMatch = false;
            $words = array_filter(explode(' ', $cleanTerm), fn($w) => mb_strlen($w) >= 3);
            if (!empty($words) && count($words) > 1) {
                foreach ($words as $w) {
                    $wSyns = $this->resolveVernacularSynonyms($w, $userId);
                    foreach (array_merge([$w], $wSyns) as $subSyn) {
                        $subSyn = mb_strtolower(trim($subSyn));
                        if (mb_strlen($subSyn) >= 3 && (str_contains($pName, $subSyn) || str_contains($pDesc, $subSyn))) {
                            $tokenMatch = true;
                            break 2;
                        }
                    }
                }
            }

            if ($nameMatch || $categoryMatch || $descMatch || $synonymMatch || $tokenMatch) {
                $matches[] = $p;
            }
        }

        // If exact match not yet set, check if one of matches matches cleanTerm or any synonym
        if (!$exactMatch && !empty($matches)) {
            if (count($matches) === 1) {
                // If only 1 product matched the user's query, it is the exact/single product match
                $exactMatch = $matches[0];
            } else {
                foreach ($matches as $p) {
                    $pn = mb_strtolower(trim($p['name'] ?? ''));
                    $psku = mb_strtolower(trim($p['retailer_id'] ?? ''));
                    if ($pn === $cleanTerm || $psku === $cleanTerm || in_array($pn, $synonyms, true)) {
                        $exactMatch = $p;
                        break;
                    }
                }
            }
        }

        // Ensure exact match is placed first in matches array
        if ($exactMatch) {
            $otherMatches = array_values(array_filter($matches, function ($p) use ($exactMatch) {
                return (string)($p['retailer_id'] ?? '') !== (string)($exactMatch['retailer_id'] ?? '')
                    || ($p['name'] ?? '') !== ($exactMatch['name'] ?? '');
            }));
            $matches = array_merge([$exactMatch], $otherMatches);
        }

        // If multiple products matched, mark as variety
        if (count($matches) >= 2) {
            $isVarietyQuery = true;
        }

        // If no products matched directly, but user had expressed catalog/variety intent:
        if (empty($matches) && $isCatalogQuery) {
            return [
                'is_catalog_query' => true,
                'is_variety_query' => false,
                'exact_match' => null,
                'matches' => array_slice($effectiveProducts, 0, 10),
                'total' => count($effectiveProducts),
                'search_term' => 'Catalog',
            ];
        }

        if (empty($matches) && $isVarietyQuery) {
            return [
                'is_catalog_query' => false,
                'is_variety_query' => true,
                'exact_match' => null,
                'matches' => array_slice($effectiveProducts, 0, 10),
                'total' => count($effectiveProducts),
                'search_term' => 'Variety',
            ];
        }

        return [
            'is_catalog_query' => false,
            'is_variety_query' => $isVarietyQuery,
            'exact_match' => $exactMatch,
            'matches' => $matches,
            'total' => count($matches),
            'search_term' => $cleanTerm,
        ];
    }

    /**
     * Resolve regional, vernacular, and Indian retail synonyms for product search.
     * Merges built-in dictionary (e.g. Mojadi/Mojari -> Jutti/Footwear, हंटिंग कुर्ता -> hunting kurta) + user custom keywords.
     */
    public function resolveVernacularSynonyms(string $term, ?int $userId = null): array
    {
        $term = mb_strtolower(trim($term));
        if ($term === '') {
            return [];
        }

        $builtIn = [
            'mojadi' => ['mojari', 'jutti', 'jooti', 'nagra', 'footwear', 'shoes', 'leather', 'मोजड़ी', 'मोजडी'],
            'mojdi' => ['mojari', 'jutti', 'jooti', 'nagra', 'footwear', 'shoes', 'leather', 'मोजड़ी', 'मोजडी'],
            'mojri' => ['mojari', 'jutti', 'jooti', 'nagra', 'footwear', 'shoes', 'leather', 'मोजड़ी', 'मोजरी'],
            'mojari' => ['mojadi', 'jutti', 'jooti', 'nagra', 'footwear', 'shoes', 'मोजड़ी', 'मोजरी'],
            'jutti' => ['mojari', 'mojadi', 'jooti', 'nagra', 'footwear', 'जूती'],
            'juti' => ['mojari', 'mojadi', 'jutti', 'footwear', 'जूती'],
            'shoes' => ['mojari', 'mojadi', 'jutti', 'footwear', 'loafers', 'loafer', 'जूते', 'जूता', 'लोफर्स', 'shoes', 'sneakers'],
            'shoe' => ['shoes', 'footwear', 'loafers', 'loafer', 'जूते', 'जूता', 'लोफर्स'],
            'footwear' => ['mojari', 'mojadi', 'jutti', 'shoes', 'loafers', 'chappal', 'जूते', 'लोफर्स', 'मोजड़ी'],
            'loafers' => ['loafer', 'लोफर्स', 'लोफर', 'ledar', 'leather', 'shoes', 'footwear', 'लेदर लोफर्स'],
            'loafer' => ['loafers', 'लोफर्स', 'लोफर', 'ledar', 'leather', 'shoes', 'footwear', 'लेदर लोफर्स'],
            'ledar' => ['leather', 'लेदर', 'चमड़ा', 'loafers', 'shoes', 'लोफर्स', 'लेदर लोफर्स'],
            'leather' => ['ledar', 'लेदर', 'चमड़ा', 'loafers', 'shoes', 'लोफर्स', 'लेदर लोफर्स'],
            'ledar loafers' => ['लेदर लोफर्स', 'leather loafers', 'loafers', 'shoes', 'लोफर्स'],
            'leather loafers' => ['लेदर लोफर्स', 'ledar loafers', 'loafers', 'shoes', 'लोफर्स'],
            'chappal' => ['slippers', 'चप्पल', 'sandals', 'footwear'],
            'slippers' => ['chappal', 'चप्पल', 'sandals', 'footwear'],
            'safa' => ['pagdi', 'feta', 'turban', 'pagari', 'साफा', 'पगड़ी'],
            'pagdi' => ['safa', 'feta', 'turban', 'साफा', 'पगड़ी'],
            'feta' => ['safa', 'pagdi', 'turban', 'साफा'],
            'kurta' => ['kurti', 'hunting kurta', 'short kurta', 'pathani', 'cotton kurta', 'कुर्ता'],
            'kurti' => ['kurta', 'कुर्ती'],
            'shirt' => ['sanganeri shirt', 'printed shirt', 'cotton shirt', 'formal shirt', 'शर्ट'],
            'sherwani' => ['indo western', 'wedding wear', 'blazer', 'suit', 'achkan', 'शेरवानी'],
            'pant' => ['formal pant', 'trouser', 'chinos', 'pajama', 'पैंट'],
            'trouser' => ['pant', 'formal pant', 'पैंट'],

            // Devanagari / Hindi script terms & vernacular variants
            'लेदर लोफर्स' => ['ledar loafers', 'leather loafers', 'loafers', 'loafer', 'shoes', 'ledar', 'leather', 'लोफर्स', 'लेदर'],
            'लेदर' => ['leather', 'ledar', 'चमड़ा', 'loafers', 'shoes', 'लोफर्स', 'लेदर लोफर्स'],
            'लोफर्स' => ['loafers', 'loafer', 'shoes', 'ledar', 'leather', 'footwear', 'लेदर लोफर्स'],
            'लोफर' => ['loafers', 'loafer', 'shoes', 'ledar', 'leather', 'footwear', 'लेदर लोफर्स'],
            'जूते' => ['shoes', 'shoe', 'footwear', 'loafers', 'mojari', 'जूता'],
            'जूता' => ['shoes', 'shoe', 'footwear', 'loafers', 'mojari', 'जूते'],
            'चप्पल' => ['chappal', 'slippers', 'sandals', 'footwear'],
            'शर्ट' => ['shirt', 'printed shirt', 'cotton shirt', 'sanganeri shirt'],
            'हंटिंग kurta' => ['hunting kurta', 'kurta', 'hunting', 'short kurta'],
            'हंटिंग कुर्ता' => ['hunting kurta', 'kurta', 'hunting', 'short kurta'],
            'हंटिंग' => ['hunting', 'hunting kurta', 'kurta'],
            'hunting kurta' => ['hunting', 'kurta', 'हंटिंग कुर्ता'],
            'hunting' => ['hunting kurta', 'हंटिंग', 'kurta'],
            'कुर्ता' => ['kurta', 'hunting kurta', 'short kurta', 'pathani', 'cotton kurta', 'kurti'],
            'कुर्ती' => ['kurti', 'kurta'],
            'मोजड़ी' => ['mojadi', 'mojari', 'jutti', 'footwear', 'shoes'],
            'मोजडी' => ['mojadi', 'mojari', 'jutti', 'footwear', 'shoes'],
            'मोजरी' => ['mojari', 'mojadi', 'jutti', 'footwear', 'shoes'],
            'जूती' => ['jutti', 'jooti', 'mojari', 'mojadi', 'footwear'],
            'शेरवानी' => ['sherwani', 'indo western', 'wedding wear', 'blazer', 'achkan'],
            'साफा' => ['safa', 'pagdi', 'feta', 'turban'],
            'पगड़ी' => ['pagdi', 'safa', 'feta', 'turban'],
            'पगडी' => ['pagdi', 'safa', 'feta', 'turban'],
            'धोती' => ['dhoti', 'dhoti kurta'],
            'पायजामा' => ['pyjama', 'pajama', 'pant', 'trouser'],
            'पैंट' => ['pant', 'trouser', 'chinos'],
            'ब्लेज़र' => ['blazer', 'suit', 'coat'],
            'ब्लेजर' => ['blazer', 'suit', 'coat'],
            'कोट' => ['coat', 'blazer', 'suit'],
            'broche' => ['brooch', 'ब्रोच', 'broch', 'accessory', 'pin'],
            'brooch' => ['broche', 'ब्रोच', 'broch', 'accessory', 'pin'],
            'ब्रोच' => ['broche', 'brooch', 'broch', 'accessory'],
            'rajputana shirt' => ['राजपूताना शर्ट', 'rajputana', 'shirt', 'printed shirt'],
            'राजपूताना शर्ट' => ['rajputana shirt', 'rajputana', 'shirt', 'printed shirt'],
            'rajputana' => ['राजपूताना', 'rajputana shirt'],
            'राजपूताना' => ['rajputana', 'rajputana shirt'],
        ];

        $matchedSynonyms = $builtIn[$term] ?? [];

        if (empty($matchedSynonyms)) {
            foreach ($builtIn as $key => $syns) {
                if (mb_strpos($term, (string) $key) !== false || mb_strpos((string) $key, $term) !== false) {
                    $matchedSynonyms = array_merge($matchedSynonyms, $syns, [(string) $key]);
                }
            }
        }

        // Check configured keywords from KnowledgeBase table
        try {
            $kbItems = \App\Models\KnowledgeBase::where('user_id', $userId)
                ->where('is_published', true)
                ->where('is_enabled', true)
                ->get();
            foreach ($kbItems as $kb) {
                $kw = is_array($kb->keywords) ? $kb->keywords : (is_string($kb->keywords) ? json_decode($kb->keywords, true) ?: [] : []);
                if (in_array($term, array_map('mb_strtolower', $kw), true)) {
                    $matchedSynonyms[] = mb_strtolower($kb->question);
                }
            }
        } catch (\Throwable $e) {
            // Ignore knowledge base error
        }

        return array_values(array_unique(array_filter($matchedSynonyms)));
    }

    /**
     * Helper to format Meta product object into clean, frontend-ready structure.
     */
    protected function formatProductItem(array $item): array
    {
        $rawPrice = $item['price'] ?? 0;
        $currency = $item['currency'] ?? 'INR';

        // Parse price into readable float
        $numericPrice = 0.0;
        if (is_numeric($rawPrice)) {
            // If Meta returns price in cents/minor units (e.g. 120000 for 1200.00), adjust if needed
            // Meta Graph API returns formatted string like "₹1,200.00" or raw float depending on version
            $numericPrice = (float) $rawPrice;
        } elseif (is_string($rawPrice)) {
            $cleaned = preg_replace('/[^0-9.]/', '', $rawPrice);
            $numericPrice = (float) $cleaned;
        }

        $currencySymbols = [
            'INR' => '₹',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'AED' => 'AED '
        ];
        $symbol = $currencySymbols[strtoupper($currency)] ?? ($currency . ' ');
        $formattedPrice = $symbol . number_format($numericPrice, 2);

        // Parse optional sale price
        $rawSalePrice = $item['sale_price'] ?? null;
        $numericSalePrice = null;
        $formattedSalePrice = null;
        if ($rawSalePrice !== null && $rawSalePrice !== '') {
            if (is_numeric($rawSalePrice)) {
                $numericSalePrice = (float) $rawSalePrice;
            } elseif (is_string($rawSalePrice)) {
                $cleanedSale = preg_replace('/[^0-9.]/', '', $rawSalePrice);
                $numericSalePrice = (float) $cleanedSale;
            }
            if ($numericSalePrice !== null) {
                $formattedSalePrice = $symbol . number_format($numericSalePrice, 2);
            }
        }

        return [
            'id' => (string) ($item['id'] ?? ''),
            'retailer_id' => (string) ($item['retailer_id'] ?? ''),
            'name' => $item['name'] ?? '',
            'description' => $item['description'] ?? '',
            'price' => $numericPrice,
            'currency' => strtoupper($currency),
            'formatted_price' => $formattedPrice,
            'sale_price' => $numericSalePrice,
            'formatted_sale_price' => $formattedSalePrice,
            'brand' => $item['brand'] ?? null,
            'availability' => $item['availability'] ?? 'in stock',
            'image_url' => $item['image_url'] ?? null,
            'url' => $item['url'] ?? null,
            'category' => $item['category'] ?? ($item['fb_product_category'] ?? null),
            'fb_product_category' => $item['fb_product_category'] ?? ($item['category'] ?? null),
            'condition' => $item['condition'] ?? 'new',
            'visibility' => $item['visibility'] ?? 'published',
            'status' => ($item['visibility'] ?? 'published') === 'published' ? 'show' : 'hide',
            'review_status' => $item['review_status'] ?? 'approved'
        ];
    }

    /**
     * Resolve product details by SKU / retailer_id with caching.
     */
    public function resolveProductDetails(string $retailerId, ?string $catalogId = null, ?int $userId = null): ?array
    {
        $retailerId = trim($retailerId);
        if ($retailerId === '') {
            return null;
        }

        $effectiveCatalogId = $catalogId ?: $this->getDefaultCatalogId($userId);
        $cacheKey = 'catalog_prod_' . ($effectiveCatalogId ?: 'def') . '_' . $retailerId;

        return Cache::remember($cacheKey, 1800, function () use ($retailerId, $effectiveCatalogId, $userId) {
            try {
                $result = $this->getProductByRetailerId($retailerId, $effectiveCatalogId, $userId);
                if (!empty($result['success']) && !empty($result['data'][0])) {
                    return $result['data'][0];
                }

                // Fallback: search in catalog products list
                $allRes = $this->getProducts($effectiveCatalogId, ['limit' => 100], $userId);
                if (!empty($allRes['success']) && !empty($allRes['data'])) {
                    foreach ($allRes['data'] as $p) {
                        if ((string) ($p['retailer_id'] ?? '') === (string) $retailerId || (string) ($p['id'] ?? '') === (string) $retailerId) {
                            return $p;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('resolveProductDetails error: ' . $e->getMessage(), [
                    'retailer_id' => $retailerId,
                    'catalog_id' => $effectiveCatalogId
                ]);
            }

            return null;
        });
    }

    /**
     * Enrich an order payload (with product_items) with catalog product details (name, image_url, etc.)
     */
    public function enrichOrderData(array $order, ?int $userId = null): array
    {
        $catalogId = $order['catalog_id'] ?? $this->getDefaultCatalogId($userId);
        $items = $order['product_items'] ?? [];
        $enrichedItems = [];
        $totalPrice = 0.0;
        $totalQty = 0;
        $currency = 'INR';

        foreach ($items as $item) {
            $sku = (string) ($item['product_retailer_id'] ?? ($item['retailer_id'] ?? ''));
            $qty = (int) ($item['quantity'] ?? 1);
            $price = (float) ($item['item_price'] ?? 0);
            $cur = $item['currency'] ?? 'INR';
            $currency = $cur;
            $totalQty += $qty;
            $totalPrice += ($qty * $price);

            $prod = $sku !== '' ? $this->resolveProductDetails($sku, $catalogId, $userId) : null;

            $enrichedItems[] = array_merge($item, [
                'product_retailer_id' => $sku,
                'retailer_id' => $sku,
                'name' => $prod['name'] ?? ($item['name'] ?? "Product #{$sku}"),
                'title' => $prod['name'] ?? ($item['name'] ?? "Product #{$sku}"),
                'description' => $prod['description'] ?? ($item['description'] ?? null),
                'image_url' => $prod['image_url'] ?? ($item['image_url'] ?? null),
                'url' => $prod['url'] ?? ($item['url'] ?? null),
                'availability' => $prod['availability'] ?? 'in stock',
                'formatted_price' => $prod['formatted_price'] ?? ($cur . ' ' . number_format($price, 2)),
                'quantity' => $qty,
                'item_price' => $price,
                'currency' => $cur,
            ]);
        }

        $primaryImage = null;
        foreach ($enrichedItems as $ei) {
            if (!empty($ei['image_url'])) {
                $primaryImage = $ei['image_url'];
                break;
            }
        }

        return [
            'catalog_id' => $catalogId,
            'text' => $order['text'] ?? null,
            'total_items' => $totalQty,
            'total_price' => $totalPrice,
            'currency' => $currency,
            'formatted_total' => $currency . ' ' . number_format($totalPrice, 2),
            'image_url' => $primaryImage,
            'product_items' => $enrichedItems,
        ];
    }

    /**
     * Enrich a referred_product payload with catalog product details.
     */
    public function enrichReferredProduct(array $referred, ?int $userId = null): array
    {
        $catalogId = $referred['catalog_id'] ?? $this->getDefaultCatalogId($userId);
        $sku = (string) ($referred['product_retailer_id'] ?? ($referred['retailer_id'] ?? ''));

        $prod = $sku !== '' ? $this->resolveProductDetails($sku, $catalogId, $userId) : null;

        return array_merge($referred, [
            'catalog_id' => $catalogId,
            'product_retailer_id' => $sku,
            'retailer_id' => $sku,
            'name' => $prod['name'] ?? ($referred['name'] ?? "Product #{$sku}"),
            'title' => $prod['name'] ?? ($referred['name'] ?? "Product #{$sku}"),
            'description' => $prod['description'] ?? ($referred['description'] ?? null),
            'image_url' => $prod['image_url'] ?? ($referred['image_url'] ?? null),
            'url' => $prod['url'] ?? ($referred['url'] ?? null),
            'price' => $prod['price'] ?? null,
            'currency' => $prod['currency'] ?? null,
            'formatted_price' => $prod['formatted_price'] ?? null,
            'availability' => $prod['availability'] ?? 'in stock',
        ]);
    }

    /**
     * Intelligently resolve catalog product image and product details for conversation records.
     * Supports:
     * - Already stored media URL
     * - Direct image_url in message metadata
     * - Interactive Single Product Message (SPM) payload product_retailer_id
     * - Content containing "🛍️ Product: {sku}"
     * - Content containing "🛍️ *{product_name}*" (Auto AI exact catalog matches)
     */
    public function resolveCatalogProductMedia(string $content, array $metadata = [], ?string $existingMediaUrl = null, ?int $userId = null): ?array
    {
        // 1. Direct valid URL in media_url (non-numeric)
        if (!empty($existingMediaUrl) && !is_numeric($existingMediaUrl) && (str_starts_with($existingMediaUrl, 'http://') || str_starts_with($existingMediaUrl, 'https://'))) {
            return [
                'image_url' => $existingMediaUrl,
                'product' => $metadata['product'] ?? ($metadata['catalog_product'] ?? null),
            ];
        }

        // 2. Direct image_url in metadata
        if (!empty($metadata['image_url']) && is_string($metadata['image_url']) && (str_starts_with($metadata['image_url'], 'http://') || str_starts_with($metadata['image_url'], 'https://'))) {
            return [
                'image_url' => $metadata['image_url'],
                'product' => $metadata['product'] ?? ($metadata['catalog_product'] ?? null),
            ];
        }

        // 3. Product in metadata
        if (!empty($metadata['product']['image_url'])) {
            return [
                'image_url' => $metadata['product']['image_url'],
                'product' => $metadata['product'],
            ];
        }
        if (!empty($metadata['catalog_product']['image_url'])) {
            return [
                'image_url' => $metadata['catalog_product']['image_url'],
                'product' => $metadata['catalog_product'],
            ];
        }

        // 4. From interactive payload (SPM)
        $interactive = $metadata['interactive'] ?? [];
        $sku = $interactive['action']['product_retailer_id']
            ?? ($interactive['action']['parameters']['thumbnail_product_retailer_id']
            ?? ($metadata['product_retailer_id']
            ?? ($metadata['retailer_id'] ?? null)));
        $catalogId = $interactive['action']['catalog_id'] ?? ($metadata['catalog_id'] ?? null);

        if ($sku) {
            $prod = $this->resolveProductDetails((string) $sku, $catalogId, $userId);
            if (!empty($prod['image_url'])) {
                return [
                    'image_url' => $prod['image_url'],
                    'product' => $prod,
                ];
            }
        }

        // 5. From content pattern: "🛍️ Product: {sku}"
        if (preg_match('/🛍️\s*Product:\s*([^\s-]+)/u', $content, $skuMatch)) {
            $sku = trim($skuMatch[1]);
            if ($sku !== '') {
                $prod = $this->resolveProductDetails($sku, $catalogId, $userId);
                if (!empty($prod['image_url'])) {
                    return [
                        'image_url' => $prod['image_url'],
                        'product' => $prod,
                    ];
                }
            }
        }

        // 6. From content pattern: "🛍️ *{product_name}*" (Auto AI exact catalog product reply)
        if (preg_match('/🛍️\s*\*([^*]+)\*/u', $content, $nameMatch)) {
            $name = trim($nameMatch[1]);
            if ($name !== '') {
                $search = $this->searchCatalogProducts($name, $catalogId, $userId);
                if (!empty($search['exact_match']['image_url'])) {
                    return [
                        'image_url' => $search['exact_match']['image_url'],
                        'product' => $search['exact_match'],
                    ];
                } elseif (!empty($search['matches'][0]['image_url'])) {
                    return [
                        'image_url' => $search['matches'][0]['image_url'],
                        'product' => $search['matches'][0],
                    ];
                }
            }
        }

        return null;
    }



    // =========================================================================
    // 4. PRODUCT MANAGEMENT (Create, Update, Delete in Meta Catalog)
    // =========================================================================

    /**
     * Resolve product image from either a file upload, base64 data URI, array of images, or URL string.
     * Automatically uploads binary images to public/uploads/catalog and returns a publicly accessible URL.
     */
    public function resolveUploadedOrProvidedImageUrl($request, ?string $fallbackUrl = null): ?string
    {
        // 1. Check for multipart file uploads ('images', 'image', 'image_file', 'file')
        $file = null;
        if ($request instanceof \Illuminate\Http\Request) {
            if ($request->hasFile('images')) {
                $raw = $request->file('images');
                $file = is_array($raw) ? ($raw[0] ?? null) : $raw;
            } elseif ($request->hasFile('image')) {
                $file = $request->file('image');
            } elseif ($request->hasFile('image_file')) {
                $file = $request->file('image_file');
            } elseif ($request->hasFile('file')) {
                $file = $request->file('file');
            }
        }

        if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
            $folder = public_path('uploads/catalog');
            if (!file_exists($folder)) {
                @mkdir($folder, 0755, true);
            }

            $ext = strtolower($file->getClientOriginalExtension()) ?: 'jpg';
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'])) {
                $ext = 'jpg';
            }
            $filename = 'prod_' . time() . '_' . \Illuminate\Support\Str::random(10) . '.' . $ext;
            $file->move($folder, $filename);

            $isProduction = app()->isProduction();
            $relPath = $isProduction ? '/public/uploads/catalog/' . $filename : '/uploads/catalog/' . $filename;
            return rtrim(config('app.url'), '/') . '/' . ltrim($relPath, '/');
        }

        // 2. Check for base64 data URI or string inputs in Request or array
        $candidate = null;
        if ($request instanceof \Illuminate\Http\Request) {
            $imagesInput = $request->input('images');
            if (!empty($imagesInput)) {
                if (is_array($imagesInput)) {
                    $candidate = $imagesInput[0] ?? null;
                } elseif (is_string($imagesInput)) {
                    if (str_starts_with(trim($imagesInput), '[') && ($decoded = json_decode($imagesInput, true)) && is_array($decoded)) {
                        $candidate = $decoded[0] ?? null;
                    } else {
                        $candidate = trim($imagesInput);
                    }
                }
            }

            if (!$candidate && $request->filled('image')) {
                $candidate = trim((string) $request->input('image'));
            }
            if (!$candidate && $request->filled('image_url')) {
                $candidate = trim((string) $request->input('image_url'));
            }
            if (!$candidate && $request->filled('image_link')) {
                $candidate = trim((string) $request->input('image_link'));
            }
        } elseif (is_array($request)) {
            $candidate = $request['image_url'] ?? ($request['image_link'] ?? ($request['image'] ?? null));
            if (!$candidate && !empty($request['images'])) {
                $candidate = is_array($request['images']) ? ($request['images'][0] ?? null) : $request['images'];
            }
        }

        if (empty($candidate) || !is_string($candidate)) {
            return $fallbackUrl;
        }

        $candidate = trim($candidate);

        // 3. Handle base64 data URI (e.g. data:image/png;base64,iVBORw0KGgo...)
        if (str_starts_with($candidate, 'data:image/') && str_contains($candidate, ';base64,')) {
            try {
                [$meta, $base64Data] = explode(';base64,', $candidate, 2);
                $ext = 'jpg';
                if (str_contains($meta, 'png')) $ext = 'png';
                elseif (str_contains($meta, 'webp')) $ext = 'webp';
                elseif (str_contains($meta, 'gif')) $ext = 'gif';

                $decoded = base64_decode($base64Data);
                if ($decoded !== false) {
                    $folder = public_path('uploads/catalog');
                    if (!file_exists($folder)) {
                        @mkdir($folder, 0755, true);
                    }
                    $filename = 'prod_' . time() . '_' . \Illuminate\Support\Str::random(10) . '.' . $ext;
                    file_put_contents($folder . '/' . $filename, $decoded);

                    $isProduction = app()->isProduction();
                    $relPath = $isProduction ? '/public/uploads/catalog/' . $filename : '/uploads/catalog/' . $filename;
                    return rtrim(config('app.url'), '/') . '/' . ltrim($relPath, '/');
                }
            } catch (\Throwable $e) {
                Log::warning('Failed decoding base64 catalog image: ' . $e->getMessage());
            }
        }

        // 4. If candidate is standard HTTP/HTTPS URL
        if (filter_var($candidate, FILTER_VALIDATE_URL) || str_starts_with($candidate, 'http://') || str_starts_with($candidate, 'https://')) {
            return $candidate;
        }

        return $fallbackUrl;
    }

    /**
     * Add a product to the Meta Commerce Catalog.
     */
    public function createProduct(array $data, ?string $catalogId = null, ?int $userId = null): array
    {
        try {
            [$phoneNumberId, $wabaId, $accessToken] = $this->getCredentials($userId);

            $effectiveCatalogId = $catalogId ?: $this->getDefaultCatalogId($userId);

            if (!$effectiveCatalogId) {
                return [
                    'success' => false,
                    'error' => 'No catalog ID specified or found for this account.'
                ];
            }

            // Resolve image from image_url, image_link, images, or image
            $imageUrl = $data['image_url'] ?? ($data['image_link'] ?? null);
            if (!$imageUrl && !empty($data['images'])) {
                $imageUrl = is_array($data['images']) ? ($data['images'][0] ?? null) : $data['images'];
            }
            if (!$imageUrl && !empty($data['image']) && is_string($data['image'])) {
                $imageUrl = $data['image'];
            }

            if (empty($imageUrl)) {
                return [
                    'success' => false,
                    'error' => 'Product image is required. Provide either \'images\' (file upload or URL) or \'image_url\' (public URL).'
                ];
            }

            // Price in cents/minor units (Meta requirement: price * 100 for integer minor units)
            $numericPrice = (float) ($data['price'] ?? 0);
            $currency = strtoupper($data['currency'] ?? 'INR');
            $priceInCents = (int) round($numericPrice * 100);

            $payload = [
                'retailer_id' => (string) ($data['retailer_id'] ?? ($data['id'] ?? ($data['content_id'] ?? ''))),
                'name' => (string) ($data['name'] ?? ($data['title'] ?? '')),
                'description' => $data['description'] ?? ($data['name'] ?? ($data['title'] ?? '')),
                'price' => $priceInCents,
                'currency' => $currency,
                'availability' => $data['availability'] ?? 'in stock',
                'image_url' => $imageUrl,
                'condition' => strtolower($data['condition'] ?? 'new'),
            ];

            // Optional sale price
            if (isset($data['sale_price']) && is_numeric($data['sale_price']) && (float) $data['sale_price'] > 0) {
                $payload['sale_price'] = (int) round(((float) $data['sale_price']) * 100);
            }

            // Optional URL
            if (!empty($data['url']) || !empty($data['link'])) {
                $payload['url'] = $data['url'] ?? $data['link'];
            }

            // Optional Brand
            if (!empty($data['brand'])) {
                $payload['brand'] = $data['brand'];
            }

            // Optional Category / Facebook Product Category
            $category = $data['fb_product_category'] ?? ($data['category'] ?? null);
            if (!empty($category)) {
                $payload['category'] = $category;
                $payload['fb_product_category'] = $category;
            }

            // Optional Status / Visibility (published = Show, staging = Hide)
            if (!empty($data['visibility']) || !empty($data['status'])) {
                $statusVal = strtolower((string) ($data['visibility'] ?? $data['status']));
                $payload['visibility'] = in_array($statusVal, ['published', 'show', 'active', 'in_stock']) ? 'published' : 'staging';
            }

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json'
            ])->post("https://graph.facebook.com/{$this->graphApiVersion}/{$effectiveCatalogId}/products", $payload);

            if ($response->successful()) {
                $this->invalidateCatalogProductsCache($effectiveCatalogId, $userId);
                $resData = $response->json();

                // Auto-assign to product set if specified
                $assignedToSet = null;
                if (!empty($data['product_set_id'])) {
                    $setRes = $this->addProductsToSet((string) $data['product_set_id'], [$payload['retailer_id']], $userId);
                    $assignedToSet = $setRes['success'] ?? false;
                }

                return [
                    'success' => true,
                    'message' => 'Product added to Meta Catalog successfully.',
                    'product_id' => $resData['id'] ?? null,
                    'catalog_id' => $effectiveCatalogId,
                    'retailer_id' => $payload['retailer_id'],
                    'image_url' => $imageUrl,
                    'product_set_id' => $data['product_set_id'] ?? null,
                    'assigned_to_set' => $assignedToSet
                ];
            }

            $err = $response->json();
            return [
                'success' => false,
                'error' => $err['error']['message'] ?? 'Failed to create product in Meta Catalog.',
                'meta_error' => $err['error'] ?? null
            ];
        } catch (\Exception $e) {
            Log::error('createProduct error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Parse price string like "10.00 USD", "$10.00", "199.00 INR" or raw float into [price, currency].
     */
    public function parsePriceString($priceInput, string $defaultCurrency = 'INR'): array
    {
        if (is_numeric($priceInput)) {
            $num = (float) $priceInput;
            $cur = strtoupper($defaultCurrency ?: 'INR');
            return [
                'price' => $num,
                'currency' => $cur,
                'formatted' => number_format($num, 2) . ' ' . $cur
            ];
        }

        $str = trim((string) $priceInput);
        if ($str === '') {
            $cur = strtoupper($defaultCurrency ?: 'INR');
            return [
                'price' => 0.0,
                'currency' => $cur,
                'formatted' => '0.00 ' . $cur
            ];
        }

        $currency = strtoupper($defaultCurrency ?: 'INR');

        // Check common currency symbols
        if (str_starts_with($str, '$')) {
            $currency = 'USD';
        } elseif (str_starts_with($str, '₹') || str_contains($str, 'INR')) {
            $currency = 'INR';
        } elseif (str_starts_with($str, '€')) {
            $currency = 'EUR';
        } elseif (str_starts_with($str, '£')) {
            $currency = 'GBP';
        } elseif (str_contains(strtoupper($str), 'AED')) {
            $currency = 'AED';
        }

        // Check 3-letter currency code in string (e.g. "10.00 USD" or "USD 10.00")
        if (preg_match('/([A-Za-z]{3})/i', $str, $curMatch)) {
            $code = strtoupper($curMatch[1]);
            if (in_array($code, ['USD', 'INR', 'EUR', 'GBP', 'AED', 'CAD', 'AUD', 'SGD'])) {
                $currency = $code;
            }
        }

        // Clean price number
        $cleanNum = preg_replace('/[^0-9.]/', '', $str);
        $price = is_numeric($cleanNum) ? (float) $cleanNum : 0.0;

        return [
            'price' => $price,
            'currency' => $currency,
            'formatted' => number_format($price, 2) . ' ' . $currency
        ];
    }

    /**
     * Parse Commerce Manager bulk upload file (CSV, TSV, XLSX, XLS).
     * Handles Meta's "# Required / # Optional" row if present, extracts products.
     */
    public function parseCatalogFeedFile(\Illuminate\Http\UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $rows = [];

        if (in_array($ext, ['xlsx', 'xls']) && class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray(null, true, true, false);
            } catch (\Throwable $e) {
                Log::warning('PhpSpreadsheet parse error: ' . $e->getMessage());
            }
        }

        // Fallback or CSV / TSV / TXT parsing
        if (empty($rows)) {
            $handle = fopen($file->getRealPath(), 'r');
            if ($handle) {
                $firstLine = fgets($handle);
                rewind($handle);
                $delimiter = str_contains($firstLine, "\t") ? "\t" : (str_contains($firstLine, ";") ? ";" : ",");
                while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                    $rows[] = $data;
                }
                fclose($handle);
            }
        }

        return $this->parseCatalogFeedRows($rows);
    }

    /**
     * Parse raw CSV or TSV string into product list.
     */
    public function parseCatalogFeedString(string $content): array
    {
        $content = trim($content);
        if ($content === '') {
            return [];
        }

        $fp = fopen('php://memory', 'r+');
        fwrite($fp, $content);
        rewind($fp);

        $firstLine = fgets($fp);
        rewind($fp);

        $delimiter = str_contains((string) $firstLine, "\t") ? "\t" : (str_contains((string) $firstLine, ";") ? ";" : ",");

        $rows = [];
        while (($data = fgetcsv($fp, 0, $delimiter)) !== false) {
            $rows[] = $data;
        }
        fclose($fp);

        return $this->parseCatalogFeedRows($rows);
    }

    /**
     * Parse 2D row array (from CSV, TSV, or spreadsheet) into normalized product items.
     * Automatically handles Commerce Manager "# Required / # Optional" header comment row.
     */
    public function parseCatalogFeedRows(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        // Detect if Row 0 is the "# Required / # Optional" comments row
        $headerRowIndex = 0;
        $firstCell = trim((string) ($rows[0][0] ?? ''));
        if (str_starts_with($firstCell, '#') || str_contains(strtolower($firstCell), 'required') || str_contains(strtolower($firstCell), 'optional')) {
            $headerRowIndex = 1;
        }

        if (!isset($rows[$headerRowIndex])) {
            return [];
        }

        // Normalize headers
        $rawHeaders = $rows[$headerRowIndex];
        $headers = [];
        foreach ($rawHeaders as $idx => $h) {
            $cleaned = trim((string) $h);
            $cleaned = strtolower($cleaned);
            $headers[$idx] = $cleaned;
        }

        $products = [];
        $totalRows = count($rows);

        for ($i = $headerRowIndex + 1; $i < $totalRows; $i++) {
            $row = $rows[$i];
            if (!is_array($row) || empty(array_filter($row, fn($c) => trim((string) $c) !== ''))) {
                continue;
            }

            $item = [];
            foreach ($headers as $colIdx => $colName) {
                if ($colName !== '') {
                    $item[$colName] = trim((string) ($row[$colIdx] ?? ''));
                }
            }

            // Map Meta feed columns to standard fields
            $retailerId = $item['id'] ?? ($item['retailer_id'] ?? ($item['sku'] ?? ''));
            $title = $item['title'] ?? ($item['name'] ?? '');

            if ($retailerId === '' && $title === '') {
                continue;
            }

            $effectiveId = ($retailerId !== '') ? (string) $retailerId : ('prod_' . $i);

            $priceParsed = $this->parsePriceString($item['price'] ?? '0');
            $salePriceParsed = !empty($item['sale_price']) ? $this->parsePriceString($item['sale_price'], $priceParsed['currency']) : null;

            $product = [
                'retailer_id' => $effectiveId,
                'id' => $effectiveId,
                'name' => $title,
                'title' => $title,
                'description' => $item['description'] ?? $title,
                'availability' => in_array(strtolower($item['availability'] ?? ''), ['in stock', 'available for order']) ? 'in stock' : (strtolower($item['availability'] ?? 'in stock')),
                'condition' => strtolower($item['condition'] ?? 'new') ?: 'new',
                'url' => $item['link'] ?? ($item['url'] ?? null),
                'link' => $item['link'] ?? ($item['url'] ?? null),
                'image_url' => $item['image_link'] ?? ($item['image_url'] ?? null),
                'image_link' => $item['image_link'] ?? ($item['image_url'] ?? null),
                'brand' => $item['brand'] ?? null,
                'price' => $priceParsed['price'],
                'currency' => $priceParsed['currency'],
                'formatted_price' => $priceParsed['formatted'],
                'sale_price' => $salePriceParsed ? $salePriceParsed['price'] : null,
                'sale_price_effective_date' => $item['sale_price_effective_date'] ?? null,
                'category' => $item['fb_product_category'] ?? ($item['google_product_category'] ?? ($item['category'] ?? null)),
                'fb_product_category' => $item['fb_product_category'] ?? null,
                'google_product_category' => $item['google_product_category'] ?? null,
                'quantity_to_sell_on_facebook' => $item['quantity_to_sell_on_facebook'] ?? null,
                'item_group_id' => $item['item_group_id'] ?? null,
                'gender' => $item['gender'] ?? null,
                'color' => $item['color'] ?? null,
                'size' => $item['size'] ?? null,
                'age_group' => $item['age_group'] ?? null,
                'material' => $item['material'] ?? null,
                'pattern' => $item['pattern'] ?? null,
                'shipping' => $item['shipping'] ?? null,
                'shipping_weight' => $item['shipping_weight'] ?? null,
                'offer_disclaimer' => $item['offer_disclaimer'] ?? null,
                'offer_disclaimer_url' => $item['offer_disclaimer_url'] ?? null,
                'gtin' => $item['gtin'] ?? null,
                'raw_attributes' => $item
            ];

            $products[] = $product;
        }

        return $products;
    }

    /**
     * Bulk create / upload products to Meta Commerce Catalog.
     * Supports Commerce Manager feed format, uses Meta Batch API or per-item creation fallback.
     */
    public function bulkCreateProducts(array $products, ?string $catalogId = null, ?int $userId = null): array
    {
        try {
            [$phoneNumberId, $wabaId, $accessToken] = $this->getCredentials($userId);
            $effectiveCatalogId = $catalogId ?: $this->getDefaultCatalogId($userId);

            if (!$effectiveCatalogId) {
                return [
                    'success' => false,
                    'error' => 'No catalog ID specified or found for this account.'
                ];
            }

            if (empty($products)) {
                return [
                    'success' => false,
                    'error' => 'No valid products provided for bulk creation.'
                ];
            }

            // Standardize products list
            $standardized = [];
            foreach ($products as $p) {
                $sku = trim((string) ($p['retailer_id'] ?? ($p['id'] ?? '')));
                $name = trim((string) ($p['name'] ?? ($p['title'] ?? '')));
                $img = trim((string) ($p['image_url'] ?? ($p['image_link'] ?? '')));

                if ($sku === '' || $name === '') {
                    continue;
                }

                $priceParsed = $this->parsePriceString($p['price'] ?? 0, $p['currency'] ?? 'INR');

                $standardized[] = [
                    'retailer_id' => $sku,
                    'name' => $name,
                    'description' => $p['description'] ?? $name,
                    'availability' => strtolower($p['availability'] ?? 'in stock'),
                    'condition' => strtolower($p['condition'] ?? 'new'),
                    'image_url' => $img,
                    'url' => $p['url'] ?? ($p['link'] ?? null),
                    'brand' => $p['brand'] ?? null,
                    'price' => $priceParsed['price'],
                    'currency' => $priceParsed['currency'],
                    'category' => $p['category'] ?? ($p['fb_product_category'] ?? ($p['google_product_category'] ?? null)),
                ];
            }

            if (empty($standardized)) {
                return [
                    'success' => false,
                    'error' => 'All provided items were missing required fields (id/retailer_id and title/name).'
                ];
            }

            // 1. Try Meta Batch API (/items_batch or /batch)
            $batchRequests = [];
            foreach ($standardized as $item) {
                $priceInCents = (int) round($item['price'] * 100);
                $batchData = [
                    'title' => $item['name'],
                    'description' => $item['description'],
                    'availability' => $item['availability'],
                    'condition' => $item['condition'],
                    'price' => $priceInCents,
                    'currency' => $item['currency'],
                ];
                if (!empty($item['image_url'])) {
                    $batchData['image_link'] = $item['image_url'];
                }
                if (!empty($item['url'])) {
                    $batchData['link'] = $item['url'];
                }
                if (!empty($item['brand'])) {
                    $batchData['brand'] = $item['brand'];
                }

                $batchRequests[] = [
                    'method' => 'CREATE',
                    'retailer_id' => $item['retailer_id'],
                    'data' => $batchData
                ];
            }

            if (count($batchRequests) <= 500) {
                try {
                    $batchRes = Http::timeout(60)->withHeaders([
                        'Authorization' => "Bearer {$accessToken}",
                        'Content-Type' => 'application/json'
                    ])->post("https://graph.facebook.com/{$this->graphApiVersion}/{$effectiveCatalogId}/items_batch", [
                        'requests' => $batchRequests
                    ]);

                    if ($batchRes->successful()) {
                        $batchJson = $batchRes->json();
                        return [
                            'success' => true,
                            'message' => 'Batch product upload submitted successfully to Meta Commerce Catalog.',
                            'catalog_id' => $effectiveCatalogId,
                            'total' => count($standardized),
                            'created_count' => count($standardized),
                            'failed_count' => 0,
                            'method' => 'meta_items_batch',
                            'batch_handle' => $batchJson['handles'] ?? ($batchJson['id'] ?? null),
                            'created_products' => array_map(fn($item) => [
                                'retailer_id' => $item['retailer_id'],
                                'name' => $item['name'],
                                'price' => $item['price'],
                                'currency' => $item['currency'],
                                'image_url' => $item['image_url'],
                                'status' => 'submitted'
                            ], $standardized),
                            'errors' => []
                        ];
                    }
                } catch (\Throwable $e) {
                    Log::warning('items_batch attempt failed, falling back to direct create: ' . $e->getMessage());
                }
            }

            // 2. Sequential fallback via createProduct
            $created = [];
            $errors = [];

            foreach ($standardized as $item) {
                $createRes = $this->createProduct($item, $effectiveCatalogId, $userId);
                if (!empty($createRes['success'])) {
                    $created[] = [
                        'retailer_id' => $item['retailer_id'],
                        'name' => $item['name'],
                        'product_id' => $createRes['product_id'] ?? null,
                        'status' => 'created'
                    ];
                } else {
                    $errors[] = [
                        'retailer_id' => $item['retailer_id'],
                        'name' => $item['name'],
                        'error' => $createRes['error'] ?? 'Failed to create product in Meta'
                    ];
                }
            }

            if (count($created) > 0) {
                $this->invalidateCatalogProductsCache($effectiveCatalogId, $userId);
            }

            return [
                'success' => count($created) > 0,
                'message' => count($created) . ' out of ' . count($standardized) . ' products created successfully.',
                'catalog_id' => $effectiveCatalogId,
                'total' => count($standardized),
                'created_count' => count($created),
                'failed_count' => count($errors),
                'method' => 'sequential_direct',
                'created_products' => $created,
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            Log::error('bulkCreateProducts error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get Meta Commerce Manager catalog feed template specification.
     */
    public function getCatalogTemplateColumns(): array
    {
        return [
            ['name' => 'id', 'requirement' => '# Required', 'sample' => '0', 'description' => 'Unique product SKU or retailer ID'],
            ['name' => 'title', 'requirement' => '# Required', 'sample' => 'Blue Facebook T-Shirt (Unisex)', 'description' => 'Product Name / Title'],
            ['name' => 'description', 'requirement' => '# Required', 'sample' => 'A vibrant blue crewneck T-shirt for all shapes and sizes. Made from 100% cotton.', 'description' => 'Detailed product description'],
            ['name' => 'availability', 'requirement' => '# Required', 'sample' => 'in stock', 'description' => 'in stock / out of stock'],
            ['name' => 'condition', 'requirement' => '# Required', 'sample' => 'new', 'description' => 'new / refurbished / used'],
            ['name' => 'link', 'requirement' => '# Required', 'sample' => 'https://www.facebook.com/facebook_t_shirt', 'description' => 'Product page URL on website'],
            ['name' => 'image_link', 'requirement' => '# Required', 'sample' => 'https://www.facebook.com/t_shirt_image_001.jpg', 'description' => 'Product main photo public URL'],
            ['name' => 'brand', 'requirement' => '# Optional', 'sample' => 'Facebook', 'description' => 'Brand name'],
            ['name' => 'price', 'requirement' => '# Required', 'sample' => '10.00 USD', 'description' => 'Price formatted with currency code (e.g. 10.00 USD, 199.00 INR)'],
            ['name' => 'google_product_category', 'requirement' => '# Optional', 'sample' => 'Apparel & Accessories > Clothing', 'description' => 'Google taxonomy category'],
            ['name' => 'fb_product_category', 'requirement' => '# Optional', 'sample' => 'Clothing & Accessories > Clothing', 'description' => 'Meta Facebook taxonomy category'],
            ['name' => 'quantity_to_sell_on_facebook', 'requirement' => '# Optional', 'sample' => '75', 'description' => 'Stock inventory count'],
            ['name' => 'sale_price', 'requirement' => '# Optional', 'sample' => '10.00 USD', 'description' => 'Discounted sale price with currency'],
            ['name' => 'sale_price_effective_date', 'requirement' => '# Optional', 'sample' => '2020-04-30T09:30-08:00/2020-05-30T23:59-08:00', 'description' => 'ISO date range for sale'],
            ['name' => 'item_group_id', 'requirement' => '# Optional', 'sample' => '', 'description' => 'Parent SKU to group variants (color/size)'],
            ['name' => 'gender', 'requirement' => '# Optional', 'sample' => 'unisex', 'description' => 'unisex / male / female'],
            ['name' => 'color', 'requirement' => '# Optional', 'sample' => 'royal blue', 'description' => 'Color'],
            ['name' => 'size', 'requirement' => '# Optional', 'sample' => 'M', 'description' => 'Size (e.g. S, M, L, XL)'],
            ['name' => 'age_group', 'requirement' => '# Optional', 'sample' => 'adult', 'description' => 'adult / all ages / teen / kids'],
            ['name' => 'material', 'requirement' => '# Optional', 'sample' => 'cotton', 'description' => 'Fabric / material'],
            ['name' => 'pattern', 'requirement' => '# Optional', 'sample' => 'stripes', 'description' => 'Pattern design'],
            ['name' => 'shipping', 'requirement' => '# Optional', 'sample' => 'US:CA:Ground:9.99 USD;US:NY:Air:15.99 USD', 'description' => 'Shipping rules'],
            ['name' => 'shipping_weight', 'requirement' => '# Optional', 'sample' => '10 kg', 'description' => 'Shipping weight with unit'],
            ['name' => 'offer_disclaimer', 'requirement' => '# Optional', 'sample' => 'Valid while supplies last. Terms and conditions apply.', 'description' => 'Offer terms'],
            ['name' => 'offer_disclaimer_url', 'requirement' => '# Optional', 'sample' => 'https://example.com/terms-and-conditions', 'description' => 'Offer terms link'],
            ['name' => 'video[0].url', 'requirement' => '# Optional', 'sample' => 'http://www.facebook.com/a0.mp4', 'description' => 'Video link'],
            ['name' => 'video[0].tag[0]', 'requirement' => '# Optional', 'sample' => 'Gym', 'description' => 'Video tag'],
            ['name' => 'gtin', 'requirement' => '# Optional', 'sample' => '8.80609E+12', 'description' => 'UPC / EAN / GTIN barcode'],
            ['name' => 'product_tags[0]', 'requirement' => '# Optional', 'sample' => 'some_string', 'description' => 'Product tag 1'],
            ['name' => 'product_tags[1]', 'requirement' => '# Optional', 'sample' => 'other', 'description' => 'Product tag 2'],
            ['name' => 'style[0]', 'requirement' => '# Optional', 'sample' => 'Bodycon', 'description' => 'Style tag']
        ];
    }

    /**
     * Generate standard Commerce Manager CSV/TSV template string.
     */
    public function getCatalogTemplateCsv(string $delimiter = ","): string
    {
        $cols = $this->getCatalogTemplateColumns();

        $row1 = array_map(fn($c) => $c['requirement'], $cols);
        $row2 = array_map(fn($c) => $c['name'], $cols);
        $row3 = array_map(fn($c) => $c['sample'], $cols);

        $fp = fopen('php://memory', 'r+');
        fputcsv($fp, $row1, $delimiter);
        fputcsv($fp, $row2, $delimiter);
        fputcsv($fp, $row3, $delimiter);
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return (string) $csv;
    }

    /**
     * Update an existing product in Meta Commerce Catalog.
     * Supports both Meta internal numerical product ID (e.g. 24192847561)
     * AND merchant SKU / retailer_id (e.g. 'In facilis quis quib', 'SKU-001').
     */
    public function updateProduct(string $productId, array $data, ?int $userId = null, ?string $catalogId = null): array
    {
        try {
            [$phoneNumberId, $wabaId, $accessToken] = $this->getCredentials($userId);

            $productId = trim(urldecode($productId));
            $effectiveCatalogId = $catalogId ?: (!empty($data['catalog_id']) ? $data['catalog_id'] : $this->getDefaultCatalogId($userId));

            // Normalize image from image_url, image_link, images, or image
            $imageUrl = $data['image_url'] ?? ($data['image_link'] ?? null);
            if (!$imageUrl && !empty($data['images'])) {
                $imageUrl = is_array($data['images']) ? ($data['images'][0] ?? null) : $data['images'];
            }
            if (!$imageUrl && !empty($data['image']) && is_string($data['image'])) {
                $imageUrl = $data['image'];
            }

            $payload = [];
            $batchItemData = [];

            if (isset($data['name']) || isset($data['title'])) {
                $title = $data['name'] ?? $data['title'];
                $payload['name'] = $title;
                $batchItemData['title'] = $title;
            }
            if (isset($data['description'])) {
                $payload['description'] = $data['description'];
                $batchItemData['description'] = $data['description'];
            }
            if (isset($data['availability'])) {
                $payload['availability'] = $data['availability'];
                $batchItemData['availability'] = $data['availability'];
            }
            if (isset($data['condition'])) {
                $cond = strtolower(trim((string) $data['condition']));
                $payload['condition'] = $cond;
                $batchItemData['condition'] = $cond;
            }
            if (!empty($imageUrl)) {
                $payload['image_url'] = $imageUrl;
                $batchItemData['image_link'] = $imageUrl;
            }
            if (isset($data['url']) || isset($data['link'])) {
                $url = $data['url'] ?? $data['link'];
                $payload['url'] = $url;
                $batchItemData['link'] = $url;
            }
            if (isset($data['brand'])) {
                $payload['brand'] = $data['brand'];
                $batchItemData['brand'] = $data['brand'];
            }
            if (isset($data['category']) || isset($data['fb_product_category'])) {
                $cat = $data['fb_product_category'] ?? $data['category'];
                $payload['category'] = $cat;
                $payload['fb_product_category'] = $cat;
                $batchItemData['category'] = $cat;
                $batchItemData['fb_product_category'] = $cat;
            }

            if (isset($data['sale_price'])) {
                $numSale = (float) $data['sale_price'];
                $saleInCents = $numSale > 0 ? (int) round($numSale * 100) : null;
                $payload['sale_price'] = $saleInCents;
                $batchItemData['sale_price'] = $saleInCents;
            }

            if (isset($data['visibility']) || isset($data['status'])) {
                $statusVal = strtolower((string) ($data['visibility'] ?? $data['status']));
                $vis = in_array($statusVal, ['published', 'show', 'active', 'in_stock']) ? 'published' : 'staging';
                $payload['visibility'] = $vis;
                $batchItemData['visibility'] = $vis;
            }

            if (isset($data['price'])) {
                $numericPrice = (float) $data['price'];
                $priceInCents = (int) round($numericPrice * 100);
                $payload['price'] = $priceInCents;
                $batchItemData['price'] = $priceInCents;
            }
            if (isset($data['currency'])) {
                $curr = strtoupper($data['currency']);
                $payload['currency'] = $curr;
                $batchItemData['currency'] = $curr;
            }

            $lastError = null;

            // Strategy 1: If productId looks like a Meta internal Node ID (numeric, >= 8 digits), try direct node update
            if (ctype_digit($productId) && strlen($productId) >= 8) {
                $response = Http::timeout(30)->withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json'
                ])->post("https://graph.facebook.com/{$this->graphApiVersion}/{$productId}", $payload);

                if ($response->successful()) {
                    return [
                        'success' => true,
                        'message' => 'Product updated successfully in Meta Catalog.',
                        'product_id' => $productId,
                        'catalog_id' => $effectiveCatalogId
                    ];
                }

                $lastError = $response->json();
            }

            // Strategy 2: If productId is a SKU/retailer_id or Strategy 1 missed, resolve the Meta numeric Node ID
            $resolvedProd = $this->resolveProductDetails($productId, $effectiveCatalogId, $userId);
            if (!empty($resolvedProd['id']) && ctype_digit((string) $resolvedProd['id'])) {
                $metaNodeId = (string) $resolvedProd['id'];
                $nodeResponse = Http::timeout(30)->withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json'
                ])->post("https://graph.facebook.com/{$this->graphApiVersion}/{$metaNodeId}", $payload);

                if ($nodeResponse->successful()) {
                    return [
                        'success' => true,
                        'message' => 'Product updated successfully in Meta Catalog.',
                        'product_id' => $metaNodeId,
                        'retailer_id' => $resolvedProd['retailer_id'] ?? $productId,
                        'catalog_id' => $effectiveCatalogId
                    ];
                }

                $lastError = $nodeResponse->json();
            }

            // Strategy 3: Update directly via Meta Commerce Catalog items_batch API using retailer_id
            if ($effectiveCatalogId && !empty($batchItemData)) {
                $batchPayload = [
                    'requests' => [
                        [
                            'method' => 'UPDATE',
                            'retailer_id' => $productId,
                            'data' => $batchItemData
                        ]
                    ]
                ];

                $batchRes = Http::timeout(30)->withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json'
                ])->post("https://graph.facebook.com/{$this->graphApiVersion}/{$effectiveCatalogId}/items_batch", $batchPayload);

                if ($batchRes->successful()) {
                    $this->invalidateCatalogProductsCache($effectiveCatalogId, $userId);
                    $batchJson = $batchRes->json();
                    return [
                        'success' => true,
                        'message' => 'Product update submitted successfully to Meta Catalog.',
                        'product_id' => $productId,
                        'retailer_id' => $productId,
                        'catalog_id' => $effectiveCatalogId,
                        'batch_handle' => $batchJson['handles'] ?? ($batchJson['id'] ?? null)
                    ];
                }

                $lastError = $batchRes->json();
            }

            $errMsg = $lastError['error']['message'] ?? "Product with ID or SKU '{$productId}' does not exist in Meta Catalog #{$effectiveCatalogId}. Please ensure the product SKU exists or create it first.";

            return [
                'success' => false,
                'error' => $errMsg,
                'meta_error' => $lastError['error'] ?? null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete a product from Meta Commerce Catalog.
     * Supports both Meta internal numerical product ID AND merchant SKU / retailer_id.
     */
    public function deleteProduct(string $productId, ?int $userId = null, ?string $catalogId = null): array
    {
        try {
            [$phoneNumberId, $wabaId, $accessToken] = $this->getCredentials($userId);

            $productId = trim(urldecode($productId));
            $effectiveCatalogId = $catalogId ?: $this->getDefaultCatalogId($userId);

            // Strategy 1: If numeric Meta Node ID, direct DELETE
            if (ctype_digit($productId) && strlen($productId) >= 8) {
                $response = Http::timeout(30)->withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                ])->delete("https://graph.facebook.com/{$this->graphApiVersion}/{$productId}");

                if ($response->successful()) {
                    return [
                        'success' => true,
                        'message' => 'Product deleted successfully from Meta Catalog.',
                        'product_id' => $productId
                    ];
                }
            }

            // Strategy 2: Resolve Meta Node ID via SKU
            $resolvedProd = $this->resolveProductDetails($productId, $effectiveCatalogId, $userId);
            if (!empty($resolvedProd['id']) && ctype_digit((string) $resolvedProd['id'])) {
                $metaNodeId = (string) $resolvedProd['id'];
                $nodeResponse = Http::timeout(30)->withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                ])->delete("https://graph.facebook.com/{$this->graphApiVersion}/{$metaNodeId}");

                if ($nodeResponse->successful()) {
                    return [
                        'success' => true,
                        'message' => 'Product deleted successfully from Meta Catalog.',
                        'product_id' => $metaNodeId,
                        'retailer_id' => $productId
                    ];
                }
            }

            // Strategy 3: Delete via items_batch API using retailer_id
            if ($effectiveCatalogId) {
                $batchPayload = [
                    'requests' => [
                        [
                            'method' => 'DELETE',
                            'retailer_id' => $productId
                        ]
                    ]
                ];

                $batchRes = Http::timeout(30)->withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json'
                ])->post("https://graph.facebook.com/{$this->graphApiVersion}/{$effectiveCatalogId}/items_batch", $batchPayload);

                if ($batchRes->successful()) {
                    $this->invalidateCatalogProductsCache($effectiveCatalogId, $userId);
                    return [
                        'success' => true,
                        'message' => 'Product delete request submitted successfully to Meta Catalog.',
                        'product_id' => $productId,
                        'retailer_id' => $productId
                    ];
                }

                $err = $batchRes->json();
                return [
                    'success' => false,
                    'error' => $err['error']['message'] ?? "Failed to delete product '{$productId}' from Meta Catalog.",
                    'meta_error' => $err['error'] ?? null
                ];
            }

            return [
                'success' => false,
                'error' => "Product '{$productId}' not found in Meta Catalog."
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // =========================================================================
    // 5. INTERACTIVE CATALOG MESSAGING (Storefront, Single Product, Multi-Product)
    // =========================================================================

    /**
     * Send Storefront Catalog link message (interactive type: catalog_message).
     */
    public function sendCatalogMessage(
        string $to,
        string $bodyText,
        ?string $footerText = null,
        ?string $thumbnailRetailerId = null,
        ?int $userId = null,
        ?int $conversationId = null,
        bool $isAutoAi = false
    ): array {
        try {
            [$phoneNumberId, $wabaId, $accessToken] = $this->getCredentials($userId);
            $normalizedPhone = $this->normalizePhone($to);

            $interactivePayload = [
                'type' => 'catalog_message',
                'body' => [
                    'text' => Str::limit($bodyText, 1000, '')
                ],
                'action' => [
                    'name' => 'catalog_message'
                ]
            ];

            if ($footerText) {
                $interactivePayload['footer'] = ['text' => Str::limit($footerText, 60, '')];
            }

            if ($thumbnailRetailerId) {
                $interactivePayload['action']['parameters'] = [
                    'thumbnail_product_retailer_id' => $thumbnailRetailerId
                ];
            }

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json'
            ])->post("https://graph.facebook.com/{$this->graphApiVersion}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $normalizedPhone,
                'type' => 'interactive',
                'interactive' => $interactivePayload
            ]);

            if ($response->successful()) {
                $metaMessageId = $response->json()['messages'][0]['id'] ?? null;

                // Record outbound message in database
                $outbound = $this->recordOutboundInteractiveMessage(
                    $normalizedPhone,
                    $metaMessageId,
                    'catalog_message',
                    $bodyText,
                    $interactivePayload,
                    $userId,
                    $conversationId,
                    $isAutoAi
                );

                return [
                    'success' => true,
                    'message' => 'Catalog message sent successfully',
                    'message_id' => $metaMessageId,
                    'data' => $outbound
                ];
            }

            $err = $response->json();
            return [
                'success' => false,
                'error' => $err['error']['message'] ?? 'Failed to send catalog message.',
                'meta_error' => $err['error'] ?? null
            ];
        } catch (\Exception $e) {
            Log::error('sendCatalogMessage error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send Single Product Message (SPM) (interactive type: product).
     */
    public function sendSingleProductMessage(
        string $to,
        string $productRetailerId,
        ?string $catalogId = null,
        ?string $bodyText = null,
        ?string $footerText = null,
        ?int $userId = null,
        ?int $conversationId = null,
        bool $isAutoAi = false
    ): array {
        try {
            [$phoneNumberId, $wabaId, $accessToken] = $this->getCredentials($userId);
            $normalizedPhone = $this->normalizePhone($to);
            $effectiveCatalogId = $catalogId ?: $this->getDefaultCatalogId($userId);

            if (!$effectiveCatalogId) {
                return [
                    'success' => false,
                    'error' => 'No catalog ID specified or found for this account.'
                ];
            }

            $interactivePayload = [
                'type' => 'product',
                'action' => [
                    'catalog_id' => (string) $effectiveCatalogId,
                    'product_retailer_id' => $productRetailerId
                ]
            ];

            if ($bodyText) {
                $interactivePayload['body'] = ['text' => Str::limit($bodyText, 1000, '')];
            }
            if ($footerText) {
                $interactivePayload['footer'] = ['text' => Str::limit($footerText, 60, '')];
            }

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json'
            ])->post("https://graph.facebook.com/{$this->graphApiVersion}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $normalizedPhone,
                'type' => 'interactive',
                'interactive' => $interactivePayload
            ]);

            if ($response->successful()) {
                $metaMessageId = $response->json()['messages'][0]['id'] ?? null;

                $prodDetails = $this->resolveProductDetails($productRetailerId, $effectiveCatalogId, $userId);
                $imageUrl = $prodDetails['image_url'] ?? null;

                $contentPreview = "🛍️ Product: {$productRetailerId}" . ($bodyText ? " - {$bodyText}" : '');
                $outbound = $this->recordOutboundInteractiveMessage(
                    $normalizedPhone,
                    $metaMessageId,
                    'product',
                    $contentPreview,
                    $interactivePayload,
                    $userId,
                    $conversationId,
                    $isAutoAi,
                    $imageUrl,
                    $prodDetails
                );

                return [
                    'success' => true,
                    'message' => 'Single product message sent successfully',
                    'message_id' => $metaMessageId,
                    'data' => $outbound
                ];
            }

            $err = $response->json();
            return [
                'success' => false,
                'error' => $err['error']['message'] ?? 'Failed to send product message.',
                'meta_error' => $err['error'] ?? null
            ];
        } catch (\Exception $e) {
            Log::error('sendSingleProductMessage error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send Multi-Product Message (MPM) (interactive type: product_list).
     * $sections structure: [ ['title' => 'Shoes', 'product_items' => [ ['product_retailer_id' => 'SKU1'], ... ]] ]
     */
    public function sendMultiProductMessage(
        string $to,
        array $sections,
        ?string $catalogId = null,
        ?string $headerText = null,
        ?string $bodyText = null,
        ?string $footerText = null,
        ?int $userId = null,
        ?int $conversationId = null,
        bool $isAutoAi = false
    ): array {
        try {
            [$phoneNumberId, $wabaId, $accessToken] = $this->getCredentials($userId);
            $normalizedPhone = $this->normalizePhone($to);
            $effectiveCatalogId = $catalogId ?: $this->getDefaultCatalogId($userId);

            if (!$effectiveCatalogId) {
                return [
                    'success' => false,
                    'error' => 'No catalog ID specified or found for this account.'
                ];
            }

            // Sanitize sections: title max 24 chars, total product items <= 30
            $sanitizedSections = [];
            $totalItems = 0;
            foreach ($sections as $sec) {
                if ($totalItems >= 30) {
                    break;
                }
                $secTitle = Str::limit($sec['title'] ?? 'Collection', 24, '');
                $items = [];
                foreach ($sec['product_items'] ?? [] as $item) {
                    if ($totalItems >= 30) {
                        break;
                    }
                    if (!empty($item['product_retailer_id'])) {
                        $items[] = ['product_retailer_id' => (string) $item['product_retailer_id']];
                        $totalItems++;
                    }
                }
                if (!empty($items)) {
                    $sanitizedSections[] = [
                        'title' => $secTitle,
                        'product_items' => $items
                    ];
                }
            }

            if (empty($sanitizedSections)) {
                return [
                    'success' => false,
                    'error' => 'Multi-product message requires at least one section with valid products.'
                ];
            }

            $interactivePayload = [
                'type' => 'product_list',
                'header' => [
                    'type' => 'text',
                    'text' => Str::limit($headerText ?: 'Our Products', 60, '')
                ],
                'body' => [
                    'text' => Str::limit($bodyText ?: 'Browse items below and add to your cart.', 1000, '')
                ],
                'action' => [
                    'catalog_id' => (string) $effectiveCatalogId,
                    'sections' => $sanitizedSections
                ]
            ];

            if ($footerText) {
                $interactivePayload['footer'] = ['text' => Str::limit($footerText, 60, '')];
            }

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json'
            ])->post("https://graph.facebook.com/{$this->graphApiVersion}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $normalizedPhone,
                'type' => 'interactive',
                'interactive' => $interactivePayload
            ]);

            if ($response->successful()) {
                $metaMessageId = $response->json()['messages'][0]['id'] ?? null;

                $contentPreview = "🛍️ Collection: " . ($headerText ?: 'Products') . ($bodyText ? " - {$bodyText}" : '');
                $outbound = $this->recordOutboundInteractiveMessage(
                    $normalizedPhone,
                    $metaMessageId,
                    'product_list',
                    $contentPreview,
                    $interactivePayload,
                    $userId,
                    $conversationId,
                    $isAutoAi
                );

                return [
                    'success' => true,
                    'message' => 'Multi-product message sent successfully',
                    'message_id' => $metaMessageId,
                    'data' => $outbound
                ];
            }

            $err = $response->json();
            return [
                'success' => false,
                'error' => $err['error']['message'] ?? 'Failed to send multi-product message.',
                'meta_error' => $err['error'] ?? null
            ];
        } catch (\Exception $e) {
            Log::error('sendMultiProductMessage error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Record outbound interactive message safely without breaking MySQL enum restrictions.
     */
    protected function recordOutboundInteractiveMessage(
        string $phone,
        ?string $metaMessageId,
        string $interactiveSubtype,
        string $contentPreview,
        array $interactivePayload,
        ?int $userId,
        ?int $conversationId = null,
        bool $isAutoAi = false,
        ?string $imageUrl = null,
        ?array $productDetails = null
    ): ?Message {
        try {
            $ownerId = $userId ?: 1;

            // Resolve conversation
            $conversation = null;
            if ($conversationId) {
                $conversation = Conversation::where('id', $conversationId)
                    ->where('user_id', $ownerId)
                    ->first();
            }

            if (!$conversation) {
                $conversation = Conversation::where('phone_number', $phone)
                    ->where('user_id', $ownerId)
                    ->first();
            }

            if (!$conversation) {
                $conversation = Conversation::create([
                    'whatsapp_id' => $phone,
                    'phone_number' => $phone,
                    'user_id' => $ownerId,
                    'status' => 'active',
                    'last_message_at' => now(),
                    'last_message_preview' => substr($contentPreview, 0, 100),
                    'is_unread' => false
                ]);
            } else {
                $conversation->update([
                    'last_message_at' => now(),
                    'last_message_preview' => substr($contentPreview, 0, 100),
                    'is_unread' => false
                ]);
            }

            // Note: `messages.type` enum is ['text', 'image', 'video', 'audio', 'document', 'location', 'contact', 'sticker']
            $msgMeta = [
                'type' => 'interactive',
                'interactive_type' => $interactiveSubtype,
                'interactive' => $interactivePayload
            ];
            if ($imageUrl) {
                $msgMeta['image_url'] = $imageUrl;
            }
            if ($productDetails) {
                $msgMeta['product'] = $productDetails;
            }
            if ($isAutoAi) {
                $msgMeta['auto_ai'] = true;
                $msgMeta['auto_ai_provider'] = 'catalog_' . $interactiveSubtype;
            }

            $outboundMessage = Message::create([
                'conversation_id' => $conversation->id,
                'whatsapp_message_id' => $metaMessageId ?: ('local_' . uniqid()),
                'direction' => 'outbound',
                'type' => $imageUrl ? 'image' : 'text',
                'content' => $contentPreview,
                'media_url' => $imageUrl,
                'media_type' => $imageUrl ? 'image/jpeg' : null,
                'metadata' => $msgMeta,
                'status' => 'sent',
                'whatsapp_timestamp' => now(),
                'is_read' => true
            ]);

            // Mirror into whatsapp_conversations / whatsapp_messages
            try {
                $inboxConv = WhatsappConversation::firstOrCreate(
                    ['phone_number' => $phone],
                    [
                        'contact_name' => $conversation->contact_name,
                        'status' => 'open',
                        'assigned_user_id' => $ownerId,
                        'metadata' => ['owner_user_id' => $ownerId, 'legacy_conversation_id' => $conversation->id]
                    ]
                );

                if ($metaMessageId) {
                    $waMsgMeta = [
                        'interactive_type' => $interactiveSubtype,
                        'interactive' => $interactivePayload
                    ];
                    if ($imageUrl) {
                        $waMsgMeta['image_url'] = $imageUrl;
                    }
                    if ($productDetails) {
                        $waMsgMeta['product'] = $productDetails;
                    }
                    if ($isAutoAi) {
                        $waMsgMeta['auto_ai'] = true;
                        $waMsgMeta['auto_ai_provider'] = 'catalog_' . $interactiveSubtype;
                    }

                    WhatsappMessage::create([
                        'conversation_id' => $inboxConv->id,
                        'direction' => 'outgoing',
                        'message' => $contentPreview,
                        'message_type' => $imageUrl ? 'image' : 'interactive',
                        'media_url' => $imageUrl,
                        'media_type' => $imageUrl ? 'image/jpeg' : null,
                        'meta_message_id' => $metaMessageId,
                        'status' => 'sent',
                        'sent_at' => now(),
                        'metadata' => $waMsgMeta
                    ]);
                }
            } catch (\Throwable $mirrorErr) {
                Log::warning('Mirror interactive message warning: ' . $mirrorErr->getMessage());
            }

            return $outboundMessage;
        } catch (\Throwable $e) {
            Log::error('recordOutboundInteractiveMessage error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Inspect active Meta Access Token, permissions, and connected assets.
     */
    public function getDiagnostics(?int $userId = null): array
    {
        try {
            [$phoneNumberId, $wabaId, $accessToken, $settings] = $this->getCredentials($userId);

            // 1. Query Meta debug_token
            $debugRes = Http::timeout(15)->get("https://graph.facebook.com/debug_token", [
                'input_token' => $accessToken,
                'access_token' => $accessToken
            ]);

            $debugData = $debugRes->successful() ? ($debugRes->json()['data'] ?? []) : [];
            $scopes = $debugData['scopes'] ?? [];
            $hasCatalogManagement = in_array('catalog_management', $scopes);
            $hasMessaging = in_array('whatsapp_business_messaging', $scopes);
            $hasManagement = in_array('whatsapp_business_management', $scopes);

            $defaultCatalogId = $this->getDefaultCatalogId($userId);

            return [
                'success' => true,
                'token_diagnostics' => [
                    'is_valid' => $debugData['is_valid'] ?? false,
                    'app_id' => $debugData['app_id'] ?? null,
                    'system_user_id' => $debugData['user_id'] ?? null,
                    'application_name' => $debugData['application'] ?? null,
                    'type' => $debugData['type'] ?? 'UNKNOWN',
                    'granted_scopes' => $scopes,
                ],
                'api_capabilities' => [
                    'whatsapp_messaging_ready' => $hasMessaging,
                    'meta_catalog_api_ready' => $hasCatalogManagement,
                    'waba_management_ready' => $hasManagement,
                ],
                'configured_assets' => [
                    'phone_number_id' => $phoneNumberId,
                    'waba_id' => $wabaId,
                    'default_catalog_id' => $defaultCatalogId
                ],
                'status' => [
                    'can_send_products_now' => $hasMessaging,
                    'can_fetch_catalog_products_api' => $hasCatalogManagement,
                ],
                'instructions' => !$hasCatalogManagement
                    ? "Your token is missing 'catalog_management'. Go to Meta Business Settings > Users > System Users > click Generate Token, choose App ({$debugData['app_id']}) and check 'catalog_management'. Note: You can ALREADY send products on WhatsApp because messaging permissions are active!"
                    : "Token permissions are fully configured for both Catalog & WhatsApp messaging."
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
