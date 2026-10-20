<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsappAutomationRule;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AutomationController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id() ?: $request->user()?->id;

        $query = WhatsappAutomationRule::with('template');
        if ($userId && !$request->boolean('global', false) && !$request->boolean('all', false)) {
            $query->where(function($q) use ($userId) {
                $q->where('created_by', $userId)->orWhereNull('created_by');
            });
        }

        $rules = $query->orderByDesc('priority')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'rules' => $rules
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'trigger_type' => 'required|in:keyword,welcome,faq,drip,followup,scheduled',
            'trigger_keywords' => 'nullable|string',
            'template_id' => 'nullable|exists:templates,id',
            'response_message' => 'nullable|string',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0|max:100',
            'conditions' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->only([
            'name', 'trigger_type', 'trigger_keywords', 'template_id', 
            'response_message', 'is_active', 'priority', 'conditions'
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['created_by'] = Auth::id();

        $rule = WhatsappAutomationRule::create($data);

        return response()->json([
            'success' => true,
            'rule' => $rule
        ], 201);
    }

    public function show($id)
    {
        $rule = WhatsappAutomationRule::with('template')->find($id);

        if (!$rule) {
            return response()->json(['success' => false, 'message' => 'Rule not found'], 404);
        }

        return response()->json([
            'success' => true,
            'rule' => $rule
        ]);
    }

    public function update(Request $request, $id)
    {
        $rule = WhatsappAutomationRule::find($id);
        if (!$rule) {
            return response()->json(['success' => false, 'message' => 'Rule not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'trigger_type' => 'required|in:keyword,welcome,faq,drip,followup,scheduled',
            'trigger_keywords' => 'nullable|string',
            'template_id' => 'nullable|exists:templates,id',
            'response_message' => 'nullable|string',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->only([
            'name', 'trigger_type', 'trigger_keywords', 'template_id', 
            'response_message', 'is_active', 'priority'
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['updated_by'] = Auth::id();

        $rule->update($data);

        return response()->json([
            'success' => true,
            'rule' => $rule
        ]);
    }

    public function destroy($id)
    {
        $rule = WhatsappAutomationRule::find($id);
        if (!$rule) {
            return response()->json(['success' => false, 'message' => 'Rule not found'], 404);
        }

        $rule->delete();
        return response()->json(['success' => true]);
    }

    public function toggleStatus($id)
    {
        $rule = WhatsappAutomationRule::find($id);
        if (!$rule) {
            return response()->json(['success' => false, 'message' => 'Rule not found'], 404);
        }

        $rule->update(['is_active' => !$rule->is_active]);
        return response()->json(['success' => true, 'is_active' => $rule->is_active]);
    }
}
