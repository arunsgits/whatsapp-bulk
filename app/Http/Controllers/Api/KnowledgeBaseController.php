<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeBase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class KnowledgeBaseController extends Controller
{
    /**
     * Resolve current user/company ID.
     */
    protected function resolveOwnerId(Request $request): int
    {
        $user = $request->user();
        if ($user) {
            return (int) $user->getCompanyId();
        }

        $tokenUserId = (int) $request->attributes->get('oauth_user_id', 0);
        if ($tokenUserId > 0) {
            return $tokenUserId;
        }

        if ($request->has('user_id')) {
            return (int) $request->input('user_id');
        }

        return 1;
    }

    /**
     * GET /api/v1/knowledge-base
     * List all FAQs / Knowledge Items with optional category, search, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $ownerId = $this->resolveOwnerId($request);
            $query = KnowledgeBase::where('user_id', $ownerId);

            if ($request->filled('category')) {
                $query->where('category', $request->input('category'));
            }

            if ($request->filled('search')) {
                $search = trim((string) $request->input('search'));
                $query->where(function ($q) use ($search) {
                    $q->where('question', 'LIKE', "%{$search}%")
                      ->orWhere('answer', 'LIKE', "%{$search}%")
                      ->orWhere('category', 'LIKE', "%{$search}%");
                });
            }

            if ($request->has('is_published')) {
                $query->where('is_published', $request->boolean('is_published'));
            }

            if ($request->has('is_enabled')) {
                $query->where('is_enabled', $request->boolean('is_enabled'));
            }

            $total = $query->count();

            $limit = max(1, min(100, (int) $request->input('limit', 20)));
            $page = max(1, (int) $request->input('page', 1));
            $offset = ($page - 1) * $limit;

            $items = $query->orderBy('id', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get()
                ->map(fn (KnowledgeBase $kb) => $kb->toApiResponse());

            return response()->json([
                'status' => 'success',
                'data' => $items,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ]);
        } catch (\Throwable $e) {
            Log::error('KnowledgeBase index failed', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/knowledge-base
     * Create a new FAQ / Knowledge Item.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string',
            'answer' => 'required|string',
            'category' => 'nullable|string|max:150',
            'keywords' => 'nullable',
            'source' => 'nullable|string|max:50',
            'source_url' => 'nullable|string|max:1000',
            'is_published' => 'nullable|boolean',
            'is_enabled' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $ownerId = $this->resolveOwnerId($request);
            $rawKeywords = $request->input('keywords');
            $keywords = [];
            if (is_array($rawKeywords)) {
                $keywords = array_values($rawKeywords);
            } elseif (is_string($rawKeywords) && trim($rawKeywords) !== '') {
                $decoded = json_decode($rawKeywords, true);
                if (is_array($decoded)) {
                    $keywords = array_values($decoded);
                } else {
                    $keywords = array_values(array_filter(array_map('trim', explode(',', $rawKeywords))));
                }
            }

            $item = KnowledgeBase::create([
                'user_id' => $ownerId,
                'question' => trim((string) $request->input('question')),
                'answer' => trim((string) $request->input('answer')),
                'category' => trim((string) ($request->input('category') ?: 'General')),
                'keywords' => $keywords,
                'source' => trim((string) ($request->input('source') ?: 'Manual')),
                'source_url' => $request->input('source_url'),
                'is_published' => $request->has('is_published') ? $request->boolean('is_published') : true,
                'is_enabled' => $request->has('is_enabled') ? $request->boolean('is_enabled') : true,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Knowledge base item created successfully',
                'data' => $item->toApiResponse(),
            ], 201);
        } catch (\Throwable $e) {
            Log::error('KnowledgeBase store failed', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/knowledge-base/{id}
     * Get a specific Knowledge Item by ID or custom_id (e.g. faq_1712918400001).
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $ownerId = $this->resolveOwnerId($request);
        $item = $this->findItem($ownerId, $id);

        if (! $item) {
            return response()->json([
                'status' => 'error',
                'message' => 'Knowledge base item not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $item->toApiResponse(),
        ]);
    }

    /**
     * PUT / PATCH /api/v1/knowledge-base/{id}
     * Update an existing Knowledge Item or toggle its publish/enable status.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $ownerId = $this->resolveOwnerId($request);
        $item = $this->findItem($ownerId, $id);

        if (! $item) {
            return response()->json([
                'status' => 'error',
                'message' => 'Knowledge base item not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'question' => 'sometimes|required|string',
            'answer' => 'sometimes|required|string',
            'category' => 'nullable|string|max:150',
            'keywords' => 'nullable',
            'source' => 'nullable|string|max:50',
            'source_url' => 'nullable|string|max:1000',
            'is_published' => 'nullable|boolean',
            'is_enabled' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            if ($request->has('question')) {
                $item->question = trim((string) $request->input('question'));
            }
            if ($request->has('answer')) {
                $item->answer = trim((string) $request->input('answer'));
            }
            if ($request->has('category')) {
                $item->category = trim((string) $request->input('category'));
            }
            if ($request->has('keywords')) {
                $rawKeywords = $request->input('keywords');
                if (is_array($rawKeywords)) {
                    $item->keywords = array_values($rawKeywords);
                } elseif (is_string($rawKeywords)) {
                    $decoded = json_decode($rawKeywords, true);
                    $item->keywords = is_array($decoded) ? array_values($decoded) : array_values(array_filter(array_map('trim', explode(',', $rawKeywords))));
                }
            }
            if ($request->has('source')) {
                $item->source = trim((string) $request->input('source'));
            }
            if ($request->has('source_url')) {
                $item->source_url = $request->input('source_url');
            }
            if ($request->has('is_published')) {
                $item->is_published = $request->boolean('is_published');
            }
            if ($request->has('is_enabled')) {
                $item->is_enabled = $request->boolean('is_enabled');
            }

            $item->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Knowledge base item updated successfully',
                'data' => $item->fresh()->toApiResponse(),
            ]);
        } catch (\Throwable $e) {
            Log::error('KnowledgeBase update failed', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/v1/knowledge-base/{id}
     * Delete a Knowledge Item.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $ownerId = $this->resolveOwnerId($request);
        $item = $this->findItem($ownerId, $id);

        if (! $item) {
            return response()->json([
                'status' => 'error',
                'message' => 'Knowledge base item not found',
            ], 404);
        }

        try {
            $item->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Knowledge base item deleted successfully',
            ]);
        } catch (\Throwable $e) {
            Log::error('KnowledgeBase destroy failed', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/knowledge-base/import-url
     * Import FAQs directly by scraping a website URL.
     */
    public function importUrl(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url',
            'category' => 'nullable|string|max:150',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Valid URL is required',
                'errors' => $validator->errors(),
            ], 422);
        }

        $url = trim((string) $request->input('url'));
        $category = trim((string) ($request->input('category') ?: 'Website Knowledge'));
        $ownerId = $this->resolveOwnerId($request);

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ])
                ->get($url);

            if (! $response->successful()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Could not fetch web page. HTTP Status: ' . $response->status(),
                ], 400);
            }

            $html = (string) $response->body();
            $extractedFaqs = $this->extractFaqsFromHtml($html, $url);

            if (empty($extractedFaqs)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No structured FAQ items could be detected on the provided webpage. You can add them manually.',
                ], 422);
            }

            $importedItems = [];
            foreach ($extractedFaqs as $faq) {
                $q = trim((string) ($faq['question'] ?? ''));
                $a = trim((string) ($faq['answer'] ?? ''));
                if ($q === '' || $a === '') {
                    continue;
                }

                // Avoid duplicating exact question
                $existing = KnowledgeBase::where('user_id', $ownerId)
                    ->where('question', $q)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'answer' => $a,
                        'source' => 'Web Import',
                        'source_url' => $url,
                    ]);
                    $importedItems[] = $existing->fresh()->toApiResponse();
                } else {
                    $newItem = KnowledgeBase::create([
                        'user_id' => $ownerId,
                        'question' => $q,
                        'answer' => $a,
                        'category' => $category,
                        'keywords' => $this->deriveKeywordsFromText($q),
                        'source' => 'Web Import',
                        'source_url' => $url,
                        'is_published' => true,
                        'is_enabled' => true,
                    ]);
                    $importedItems[] = $newItem->toApiResponse();
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => sprintf('Successfully imported %d FAQ item(s) from website.', count($importedItems)),
                'imported_count' => count($importedItems),
                'data' => $importedItems,
            ]);
        } catch (\Throwable $e) {
            Log::error('KnowledgeBase importUrl failed', ['url' => $url, 'error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to import FAQ from URL: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Find item by custom_id (e.g. faq_1712918400001) or numeric ID.
     */
    protected function findItem(int $ownerId, string $id): ?KnowledgeBase
    {
        return KnowledgeBase::where('user_id', $ownerId)
            ->where(function ($q) use ($id) {
                $q->where('custom_id', $id)
                  ->orWhere('id', is_numeric($id) ? (int) $id : 0);
            })
            ->first();
    }

    /**
     * Extract FAQs from HTML using JSON-LD, Details/Summary, and common FAQ patterns.
     */
    protected function extractFaqsFromHtml(string $html, string $sourceUrl): array
    {
        $faqs = [];

        // 1. JSON-LD FAQPage (Schema.org standard)
        if (preg_match_all('/<script[^>]*type=[\'"]application\/ld\+json[\'"][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            foreach ($matches[1] as $jsonString) {
                try {
                    $data = json_decode(trim($jsonString), true);
                    if (is_array($data)) {
                        $graph = isset($data['@graph']) ? $data['@graph'] : [$data];
                        foreach ($graph as $entry) {
                            if (($entry['@type'] ?? '') === 'FAQPage' && !empty($entry['mainEntity'])) {
                                foreach ($entry['mainEntity'] as $qa) {
                                    $q = $qa['name'] ?? ($qa['question'] ?? '');
                                    $a = data_get($qa, 'acceptedAnswer.text', data_get($qa, 'answer', ''));
                                    if ($q && $a) {
                                        $faqs[] = [
                                            'question' => strip_tags((string) $q),
                                            'answer' => strip_tags((string) $a),
                                        ];
                                    }
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // Ignore JSON parsing errors in script tags
                }
            }
        }

        if (!empty($faqs)) {
            return $faqs;
        }

        // 2. HTML5 <details><summary> tags
        if (preg_match_all('/<details[^>]*>.*?<summary[^>]*>(.*?)<\/summary>(.*?)<\/details>/is', $html, $detailsMatches, PREG_SET_ORDER)) {
            foreach ($detailsMatches as $match) {
                $q = trim(strip_tags($match[1]));
                $a = trim(strip_tags($match[2]));
                if ($q !== '' && $a !== '' && mb_strlen($q) > 5) {
                    $faqs[] = [
                        'question' => $q,
                        'answer' => $a,
                    ];
                }
            }
        }

        if (!empty($faqs)) {
            return $faqs;
        }

        // 3. Question headers followed by paragraphs (e.g. <h3>What is ...?</h3><p>...</p>)
        if (preg_match_all('/<(h[2-4])[^>]*>(.*?\?)<\/\1>\s*<p[^>]*>(.*?)<\/p>/is', $html, $headerMatches, PREG_SET_ORDER)) {
            foreach ($headerMatches as $match) {
                $q = trim(strip_tags($match[2]));
                $a = trim(strip_tags($match[3]));
                if ($q !== '' && $a !== '' && mb_strlen($q) > 6 && mb_strlen($a) > 10) {
                    $faqs[] = [
                        'question' => $q,
                        'answer' => $a,
                    ];
                }
            }
        }

        return $faqs;
    }

    /**
     * Derive simple keyword tokens from question.
     */
    protected function deriveKeywordsFromText(string $text): array
    {
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', mb_strtolower($text));
        $words = array_values(array_filter(explode(' ', $clean), fn ($w) => mb_strlen($w) >= 3));
        $stopwords = ['what', 'when', 'where', 'which', 'who', 'whom', 'whose', 'why', 'how', 'your', 'have', 'does', 'with', 'from', 'this', 'that'];

        return array_values(array_slice(array_diff(array_unique($words), $stopwords), 0, 5));
    }
}
