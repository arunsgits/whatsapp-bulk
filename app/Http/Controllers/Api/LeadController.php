<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\WhatsappConversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LeadController extends Controller
{
    /**
     * Create a lead from a conversation
     */
    public function createLead(Request $request, $conversationId)
    {
        $conversation = WhatsappConversation::find($conversationId);
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversation not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'lead_source_id' => 'nullable|integer',
            'lead_stage_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        if ($conversation->lead_id) {
            return response()->json(['success' => false, 'message' => 'Conversation already linked to a lead.'], 422);
        }

        $lead = Lead::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $conversation->phone_number,
            'whatsapp' => $conversation->phone_number,
            'lead_source_id' => $request->lead_source_id,
            'lead_stage_id' => $request->lead_stage_id,
            'status' => 'open',
            'user_id' => Auth::id(),
            'created_by' => Auth::id(),
        ]);

        $conversation->update(['lead_id' => $lead->id, 'contact_name' => $request->name]);

        return response()->json([
            'success' => true,
            'lead' => $lead,
        ]);
    }
}
