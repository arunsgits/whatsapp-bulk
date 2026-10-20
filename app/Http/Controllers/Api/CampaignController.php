<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsappCampaign;
use App\Models\Template;
use App\Services\WhatsAppInboxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CampaignController extends Controller
{
    private WhatsAppInboxService $inboxService;

    public function __construct(WhatsAppInboxService $inboxService)
    {
        $this->inboxService = $inboxService;
    }

    /**
     * Resolve company/owner id (staff → parent). Admin may pass ?user_id=.
     */
    protected function resolveOwnerId(Request $request): int
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        if (strtolower((string) ($user->role ?? '')) === 'admin' && $request->filled('user_id')) {
            return (int) $request->query('user_id', $request->input('user_id'));
        }

        return (int) $user->getCompanyId();
    }

    /**
     * Campaigns for this user/company only.
     * Admin + ?all=1 can see every campaign.
     */
    protected function scopedQuery(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        $query = WhatsappCampaign::query();
        $isAdmin = strtolower((string) ($user->role ?? '')) === 'admin';

        if ($isAdmin && $request->boolean('all')) {
            return $query;
        }

        return $query->where('userId', $this->resolveOwnerId($request));
    }

    protected function findOwnedCampaign(Request $request, $id): ?WhatsappCampaign
    {
        return $this->scopedQuery($request)->find($id);
    }

    public function index(Request $request)
    {
        $ownerId = $this->resolveOwnerId($request);
        $query = $this->scopedQuery($request)->with('template');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $campaigns = $query->orderByDesc('created_at')->paginate(20);

        $statsQuery = $this->scopedQuery($request);
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'draft' => (clone $statsQuery)->where('status', 'draft')->count(),
            'scheduled' => (clone $statsQuery)->where('status', 'scheduled')->count(),
            'sending' => (clone $statsQuery)->where('status', 'sending')->count(),
            'completed' => (clone $statsQuery)->where('status', 'completed')->count(),
            'cancelled' => (clone $statsQuery)->where('status', 'cancelled')->count(),
            'total_recipients' => (int) (clone $statsQuery)->sum('total_recipients'),
            'sent_count' => (int) (clone $statsQuery)->sum('sent_count'),
            'delivered_count' => (int) (clone $statsQuery)->sum('delivered_count'),
            'read_count' => (int) (clone $statsQuery)->sum('read_count'),
            'failed_count' => (int) (clone $statsQuery)->sum('failed_count'),
        ];

        return response()->json([
            'success' => true,
            'campaigns' => $campaigns,
            'stats' => $stats,
            'meta' => [
                'userId' => $ownerId,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'template_id' => 'nullable|exists:templates,id',
            'type' => 'required|in:broadcast,drip,scheduled',
            'message' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'filters' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $ownerId = $this->resolveOwnerId($request);

        // Template must belong to this user when provided
        if ($request->filled('template_id')) {
            $ownsTemplate = Template::where('id', $request->template_id)
                ->where('userId', $ownerId)
                ->exists();
            if (! $ownsTemplate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template not found for your account',
                ], 404);
            }
        }

        $data = $request->only([
            'name', 'description', 'template_id', 'type', 'message', 'scheduled_at', 'filters',
        ]);
        $data['status'] = $request->scheduled_at ? 'scheduled' : 'draft';
        $data['userId'] = $ownerId;

        $campaign = WhatsappCampaign::create($data);

        return response()->json([
            'success' => true,
            'campaign' => $campaign,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $campaign = $this->findOwnedCampaign($request, $id);

        if (! $campaign) {
            return response()->json(['success' => false, 'message' => 'Campaign not found'], 404);
        }

        $campaign->load('template');

        return response()->json([
            'success' => true,
            'campaign' => $campaign,
        ]);
    }

    public function update(Request $request, $id)
    {
        $campaign = $this->findOwnedCampaign($request, $id);
        if (! $campaign) {
            return response()->json(['success' => false, 'message' => 'Campaign not found'], 404);
        }

        if (! in_array($campaign->status, ['draft', 'scheduled'], true)) {
            return response()->json(['success' => false, 'message' => 'Cannot edit a running or completed campaign.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'template_id' => 'nullable|exists:templates,id',
            'type' => 'required|in:broadcast,drip,scheduled',
            'message' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $ownerId = $this->resolveOwnerId($request);
        if ($request->filled('template_id')) {
            $ownsTemplate = Template::where('id', $request->template_id)
                ->where('userId', $ownerId)
                ->exists();
            if (! $ownsTemplate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template not found for your account',
                ], 404);
            }
        }

        $data = $request->only(['name', 'description', 'template_id', 'type', 'message', 'scheduled_at']);
        $campaign->update($data);

        return response()->json([
            'success' => true,
            'campaign' => $campaign->fresh(),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $campaign = $this->findOwnedCampaign($request, $id);
        if (! $campaign) {
            return response()->json(['success' => false, 'message' => 'Campaign not found'], 404);
        }

        $campaign->delete();

        return response()->json(['success' => true]);
    }

    public function launch(Request $request, $id)
    {
        $campaign = $this->findOwnedCampaign($request, $id);
        if (! $campaign) {
            return response()->json(['success' => false, 'message' => 'Campaign not found'], 404);
        }

        if (! in_array($campaign->status, ['draft', 'scheduled'], true)) {
            return response()->json(['success' => false, 'message' => 'Campaign cannot be launched.'], 422);
        }

        dispatch(function () use ($campaign) {
            $this->inboxService->sendCampaign($campaign);
        })->afterResponse();

        return response()->json(['success' => true, 'message' => 'Campaign launched successfully.']);
    }

    public function cancel(Request $request, $id)
    {
        $campaign = $this->findOwnedCampaign($request, $id);
        if (! $campaign) {
            return response()->json(['success' => false, 'message' => 'Campaign not found'], 404);
        }

        if (! in_array($campaign->status, ['draft', 'scheduled', 'sending'], true)) {
            return response()->json(['success' => false, 'message' => 'Campaign cannot be cancelled.'], 422);
        }

        $campaign->update(['status' => 'cancelled']);

        return response()->json(['success' => true]);
    }
}
