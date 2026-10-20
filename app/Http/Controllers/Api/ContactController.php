<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ActivePackage;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetException;

class ContactController extends Controller
{
    /**
     * Get all contacts for the authenticated user
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->query('per_page', 20);
            $page = $request->query('page', 1);
            $search = $request->query('search');
    
            $query = Contact::where('userId', $request->user()->getCompanyId());
    
            // Search contacts
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('phone', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }
    
            $contactsPaginator = $query->paginate(
                $perPage,
                ['*'],
                'page',
                $page
            );
    
            $contacts = collect($contactsPaginator->items());
    
            // Add group details to each contact
            $contacts->transform(function ($contact) {
                $contact->groups = $contact->groups;
                return $contact;
            });
    
            return response()->json([
                'success' => true,
                'data' => $contacts,
                'limits' => $this->contactLimitPayload($request->user()->getCompanyId()),
                'pagination' => [
                    'current_page' => $contactsPaginator->currentPage(),
                    'last_page' => $contactsPaginator->lastPage(),
                    'per_page' => $contactsPaginator->perPage(),
                    'total' => $contactsPaginator->total(),
                    'from' => $contactsPaginator->firstItem(),
                    'to' => $contactsPaginator->lastItem(),
                ]
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new contact
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string',
            'email' => 'nullable|email',
            'tags' => 'nullable|array',
            'groupIds' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $userId = $request->user()->getCompanyId();

            // Duplicate check
            $existing = Contact::where('phone', $request->phone)
                ->where('userId', $userId)
                ->first();

            if ($existing) {
                return response()->json([
                    'status' => false,
                    'message' => 'Phone number already exists'
                ], 400);
            }

            // Check this user's assigned plan only (no global fallback)
            $activePackage = ActivePackage::forUser($userId);

            if (!$activePackage) {
                return response()->json([
                    'status' => false,
                    'message' => 'No active plan found. Please purchase a plan first.'
                ], 400);
            }

            $allowedContactCount = $activePackage->resolvedContactLimit();
            $currentContactCount = Contact::where('userId', $userId)->count();
            $remainingContacts = max(0, $allowedContactCount - $currentContactCount);

            Log::info("Contact Limit Check", [
                'userId' => $userId,
                'userName' => $request->user()->name,
                'packageName' => $activePackage->package->packageName ?? null,
                'allowedContactCount' => $allowedContactCount,
                'currentContactCount' => $currentContactCount,
                'remainingContacts' => $remainingContacts,
            ]);

            if ($currentContactCount >= $allowedContactCount) {
                return response()->json([
                    'status' => false,
                    'message' => "Contact limit reached. Your plan allows {$allowedContactCount} contacts. You currently have {$currentContactCount}.",
                    'limits' => [
                        'current' => $currentContactCount,
                        'allowed' => $allowedContactCount,
                        'remaining' => 0,
                    ],
                ], 403);
            }

            // Handle groups
            $groupIds = $request->groupIds ?? [];

            $contact = Contact::create([
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'tags' => $request->tags ?? [],
                'groupIds' => array_map('intval', $request->groupIds ?? []),
                'userId' => $userId,
            ]);

            $activePackage->incrementContactUsage(1);

            // Add group details to the created contact
            $contact->groups = $contact->groups;

            $newCount = $currentContactCount + 1;

            return response()->json([
                'status' => true,
                'message' => 'Contact created successfully',
                'data' => $contact,
                'limits' => [
                    'current' => $newCount,
                    'allowed' => $allowedContactCount,
                    'remaining' => max(0, $allowedContactCount - $newCount),
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific contact
     */
    public function show(Request $request, $id)
    {
        try {
            $contact = Contact::where('userId', $request->user()->getCompanyId())
                ->findOrFail($id);

            // Add group details to the contact
            $contact->groups = $contact->groups;

            return response()->json([
                'status' => true,
                'data' => $contact
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Contact not found'
            ], 404);
        }
    }

    /**
     * Update a contact
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string',
            'email' => 'nullable|email',
            'tags' => 'nullable',
            'groupIds' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $contact = Contact::where('userId', $request->user()->getCompanyId())->findOrFail($id);

            // Handle tags and groupIds arrays properly
            $updateData = $request->only(['name', 'phone', 'email']);

            // Handle tags - check for both tags and tags[0] format
            $tags = [];
            if ($request->has('tags') && is_array($request->tags)) {
                $tags = $request->tags;
            } elseif ($request->has('tags') && is_string($request->tags)) {
                $tags = explode(',', $request->tags);
            } elseif ($request->has('tags[0]')) {
                // Handle tags[0], tags[1], etc. format
                $allTags = $request->all();
                foreach ($allTags as $key => $value) {
                    if (strpos($key, 'tags[') === 0 && strpos($key, ']') === strlen($key) - 1) {
                        $tags[] = $value;
                    }
                }
            }

            if (!empty($tags)) {
                $updateData['tags'] = array_filter($tags); // Remove empty values
            }

            // Handle groupIds - check for both groupIds and groupIds[0] format
            $groupIds = [];
            if ($request->has('groupIds') && is_array($request->groupIds)) {
                $groupIds = $request->groupIds;
            } elseif ($request->has('groupIds') && is_string($request->groupIds)) {
                $groupIds = explode(',', $request->groupIds);
            } elseif ($request->has('groupIds[0]')) {
                // Handle groupIds[0], groupIds[1], etc. format
                $allGroupIds = $request->all();
                foreach ($allGroupIds as $key => $value) {
                    if (strpos($key, 'groupIds[') === 0 && strpos($key, ']') === strlen($key) - 1) {
                        $groupIds[] = $value;
                    }
                }
            }

            if (!empty($groupIds)) {
                $updateData['groupIds'] = array_map('intval', array_filter($groupIds)); // Convert to integers and remove empty values
            }

            $contact->update($updateData);

            // Add group details to the updated contact
            $contact->groups = $contact->groups;

            return response()->json([
                'status' => true,
                'message' => 'Contact updated successfully',
                'data' => $contact
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Contact not found'
            ], 404);
        }
    }

    /**
     * Delete a contact
     */
    public function destroy(Request $request, $id)
    {
        try {
            $contact = Contact::where('userId', $request->user()->getCompanyId())->findOrFail($id);
            $contact->delete();

            return response()->json([
                'status' => true,
                'message' => 'Contact deleted successfully'
            ]);
        }
         catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Contact not found'
            ], 404);
        }
    }

    public function removeContacts(Request $request)
    {
        try {
            $userId = $request->user()->getCompanyId();

            // ✅ Case 1: Multiple delete (when contact_ids array is provided)
            $contactIds = $request->input('contact_ids');

            if (!empty($contactIds) && is_array($contactIds)) {
                $deleted = Contact::where('userId', $userId)
                    ->whereIn('id', $contactIds)
                    ->delete();

                return response()->json([
                    'status' => true,
                    'message' => "{$deleted} contact(s) deleted successfully",
                    'deleted_count' => $deleted,
                ]);
            }

         
            // ❌ No ID or array provided
            return response()->json([
                'status' => false,
                'message' => 'No contact ID(s) provided.',
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Contact not found or could not be deleted',
                'error' => $e->getMessage(),
            ], 404);
        }
    }




    /**
     * Find or create group by name for the authenticated user
     */
    private function findOrCreateGroup($groupName, $userId)
    {
        if (empty($groupName)) {
            return null;
        }

        // First, try to find existing group by name for this user
        $group = Group::where('userId', $userId)
            ->where('name', trim($groupName))
            ->first();

        if ($group) {
            return $group->id;
        }

        // If group doesn't exist, create a new one
        $newGroup = Group::create([
            'name' => trim($groupName),
            'description' => 'Auto-created during contact import',
            'userId' => $userId,
            'status' => 1
        ]);

        // Log group creation for tracking
        Log::info("Group created during contact import", [
            'userId' => $userId,
            'groupName' => $groupName,
            'groupId' => $newGroup->id
        ]);

        return $newGroup->id;
    }

    /**
     * Import contacts from CSV or Excel file
     *
     * Supported File Formats:
     * - CSV (.csv)
     * - Excel (.xlsx, .xls)
     *
     * Column Format:
     * - name (required): Contact name
     * - phone (required): Contact phone number
     * - email (optional): Contact email
     * - tags (optional): Comma-separated tags
     * - groupName (optional): Group name - will create group if it doesn't exist
     * - groupIds (optional): Comma-separated group IDs (legacy support)
     *
     * Example (all columns):
     * name,phone,email,tags,groupName
     * John Doe,+1234567890,john@example.com,"customer,vip",VIP Customers
     * Jane Smith,+1987654321,jane@example.com,"customer",Regular Customers
     *
     * Example (minimal - only required fields):
     * name,phone
     * John Doe,+1234567890
     * Jane Smith,+1987654321
     *
     * Example (mixed - some rows with groupName, some without):
     * name,phone,email,groupName
     * John Doe,+1234567890,john@example.com,VIP Customers
     * Jane Smith,+1987654321,jane@example.com,
     * Bob Wilson,+1555123456,bob@example.com,Regular Customers
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contacts' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $userId = $request->user()->getCompanyId();
            $file = $request->file('contacts');
            $importedCount = 0;
            $errors = [];

            // Check this user's assigned plan only (no global fallback)
            $activePackage = ActivePackage::forUser($userId);

            if (!$activePackage) {
                return response()->json([
                    'status' => false,
                    'message' => 'No active plan found. Please purchase a plan first.'
                ], 400);
            }

            $allowedContactCount = $activePackage->resolvedContactLimit();
            $currentContactCount = Contact::where('userId', $userId)->count();

            Log::info("Contact Import Limit Check", [
                'userId' => $userId,
                'userName' => $request->user()->name,
                'packageName' => $activePackage->package->packageName ?? null,
                'allowedContactCount' => $allowedContactCount,
                'currentContactCount' => $currentContactCount,
                'remainingContacts' => max(0, $allowedContactCount - $currentContactCount),
            ]);

            if ($currentContactCount >= $allowedContactCount) {
                return response()->json([
                    'status' => false,
                    'message' => "Contact limit reached. Your plan allows {$allowedContactCount} contacts. You currently have {$currentContactCount}.",
                    'limits' => [
                        'current' => $currentContactCount,
                        'allowed' => $allowedContactCount,
                        'remaining' => 0,
                    ],
                ], 403);
            }

            if ($file->isValid()) {
                $path = $file->getRealPath();
                $extension = strtolower($file->getClientOriginalExtension());
                
                // Parse file based on extension
                if (in_array($extension, ['xlsx', 'xls'])) {
                    // Handle Excel files
                    $result = $this->parseExcelFile($path);
                } else {
                    // Handle CSV files
                    $result = $this->parseCsvFile($path);
                }
                
                $header = $result['header'];
                $data = $result['data'];

                // Log import structure for debugging
                Log::info("Contact Import Structure", [
                    'userId' => $userId,
                    'fileType' => $extension,
                    'header' => $header,
                    'totalRows' => count($data),
                    'headerCount' => count($header)
                ]);

                foreach ($data as $index => $row) {
                    try {
                        // Skip empty rows
                        if (empty(array_filter($row))) {
                            continue;
                        }

                        // Ensure header and row have the same number of elements
                        if (count($header) !== count($row)) {
                            // Pad row with empty values if it's shorter than header
                            if (count($row) < count($header)) {
                                $row = array_pad($row, count($header), '');
                            } else {
                                // If row has more columns than header, truncate it
                                $row = array_slice($row, 0, count($header));
                            }
                        }

                        $contactData = array_combine($header, $row);

                        // Ensure all expected fields exist with default values
                        $contactData = array_merge([
                            'name' => '',
                            'phone' => '',
                            'email' => '',
                            'tags' => '',
                            'groupName' => '',
                            'groupIds' => ''
                        ], $contactData);

                        // Normalize phone number (convert scientific notation, add country code)
                        $contactData['phone'] = $this->normalizePhoneNumber($contactData['phone']);

                        // Validate required fields
                        if (empty($contactData['name']) || empty($contactData['phone'])) {
                            $errors[] = "Row " . ($index + 2) . ": Name and phone are required";
                            continue;
                        }

                        // Check if contact already exists
                        $existingContact = Contact::where('userId', $userId)
                            ->where('phone', $contactData['phone'])
                            ->first();

                        // Handle group assignment
                        $groupIds = [];

                        // Check for groupName field (new approach)
                        if (!empty($contactData['groupName'])) {
                            $groupId = $this->findOrCreateGroup($contactData['groupName'], $userId);
                            if ($groupId) {
                                $groupIds[] = $groupId;
                            }
                        }

                        // Also handle legacy groupIds field for backward compatibility
                        if (!empty($contactData['groupIds'])) {
                            $legacyGroupIds = explode(',', $contactData['groupIds']);
                            $groupIds = array_merge($groupIds, $legacyGroupIds);
                        }

                        // Add group IDs selected in the UI
                        if (!empty($request->groupIds) && is_array($request->groupIds)) {
                            $groupIds = array_merge($groupIds, $request->groupIds);
                        }

                        if ($existingContact) {
                            // If contact exists, reuse it and just update the groups if any are provided
                            if (!empty($groupIds)) {
                                $existingContact->groupIds = array_values(array_unique(array_merge($existingContact->groupIds ?? [], $groupIds)));
                                $existingContact->save();
                            }
                            // Do not increment importedCount as this is not a newly imported contact per se, 
                            // though depending on business rules you might want to.
                            // The doc says: "Existing Contact + No GroupName -> Reuse the existing contact... Mark the row as existing/skipped"
                            // "Existing Contact + New Group -> Reuse contact + assign group. Mark the row as successful/updated"
                            // We will consider it processed without taking up a plan limit slot, but we can increment importedCount for UI success purposes.
                            $importedCount++;
                            continue;
                        }

                        // Check if adding this contact would exceed the global limit (only for new contacts)
                        if (($currentContactCount + $importedCount) >= $allowedContactCount) {
                            $errors[] = "Row " . ($index + 2) . ": Contact limit reached. You can only add {$allowedContactCount} contacts as per your plan.";
                            continue;
                        }

                        Contact::create([
                            'name' => $contactData['name'],
                            'phone' => $contactData['phone'],
                            'email' => $contactData['email'] ?? null,
                            'tags' => !empty($contactData['tags']) ? explode(',', $contactData['tags']) : [],
                            'groupIds' => array_values(array_unique(array_map('strval', $groupIds))),
                            'userId' => $userId,
                        ]);

                        $importedCount++;
                    } catch (\Exception $e) {
                        $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                    }
                }
            }

            if ($importedCount > 0) {
                $activePackage->incrementContactUsage($importedCount);
            }

            $finalCount = Contact::where('userId', $userId)->count();

            return response()->json([
                'status' => true,
                'message' => "Import completed. {$importedCount} contacts imported successfully.",
                'data' => [
                    'imported_count' => $importedCount,
                    'errors' => $errors,
                    'total_errors' => count($errors),
                    'limits' => [
                        'current' => $finalCount,
                        'allowed' => $allowedContactCount,
                        'remaining' => max(0, $allowedContactCount - $finalCount),
                    ],
                ]
            ]);
        } catch (\Exception $e) {
            
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Parse CSV file and return header and data rows
     */
    private function parseCsvFile($path)
    {
        // Read file content and handle different line endings
        $content = file_get_contents($path);
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines = explode("\n", $content);
        $lines = array_filter($lines, function($line) {
            return !empty(trim($line));
        });

        $data = [];
        foreach ($lines as $line) {
            $data[] = str_getcsv($line);
        }

        $header = array_shift($data); // Remove header row

        // Clean header - remove any BOM or extra characters
        $header = array_map(function($col) {
            return trim($col, "\xEF\xBB\xBF \t\n\r\0\x0B");
        }, $header);

        return [
            'header' => $header,
            'data' => $data
        ];
    }

    /**
     * Parse Excel file (.xlsx, .xls) and return header and data rows
     */
    private function parseExcelFile($path)
    {
        try {
            $spreadsheet = IOFactory::load($path);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            if (empty($rows)) {
                return [
                    'header' => [],
                    'data' => []
                ];
            }

            $header = array_shift($rows); // First row is header
            
            // Clean header - remove any extra whitespace and convert to string
            $header = array_map(function($col) {
                return trim((string)$col);
            }, $header);
            
            // Clean data rows - convert all values to strings and trim
            // Handle numeric values (like phone numbers stored as numbers in Excel)
            $data = array_map(function($row) {
                return array_map(function($cell) {
                    // If cell is a float/numeric, convert without scientific notation
                    if (is_numeric($cell) && !is_string($cell)) {
                        // Use number_format to avoid scientific notation
                        return number_format((float)$cell, 0, '', '');
                    }
                    return trim((string)$cell);
                }, $row);
            }, $rows);
            
            // Filter out completely empty rows
            $data = array_filter($data, function($row) {
                return !empty(array_filter($row, function($cell) {
                    return !empty($cell);
                }));
            });
            
            // Re-index array after filtering
            $data = array_values($data);

            return [
                'header' => $header,
                'data' => $data
            ];
        } catch (SpreadsheetException $e) {
            Log::error("Excel parsing error: " . $e->getMessage());
            throw new \Exception("Failed to parse Excel file: " . $e->getMessage());
        }
    }

    /**
     * Normalize phone number:
     * - Convert scientific notation (e.g., 9.1816E+11) to regular number
     * - Remove spaces, dashes, and other formatting characters
     * - Add +91 country code if no country code present
     * 
     * @param string $phone
     * @return string
     */
    private function normalizePhoneNumber($phone)
    {
        if (empty($phone)) {
            return '';
        }

        // Convert to string if not already
        $phone = (string) $phone;

        // Handle scientific notation (e.g., 9.1816E+11, 9.1816e+11)
        if (preg_match('/^[\d.]+[eE][+\-]?\d+$/', $phone)) {
            // Convert scientific notation to regular number
            $phone = number_format((float) $phone, 0, '', '');
        }

        // Remove all non-numeric characters except + at the beginning
        $hasPlus = (substr($phone, 0, 1) === '+');
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // If original had +, add it back
        if ($hasPlus) {
            $phone = '+' . $phone;
        }

        // If phone doesn't start with + (no country code), add +91
        if (substr($phone, 0, 1) !== '+') {
            // Check if it starts with 91 and is long enough (12+ digits = 91 + 10 digit number)
            if (substr($phone, 0, 2) === '91' && strlen($phone) >= 12) {
                $phone = '+' . $phone;
            } else {
                // Add +91 country code
                $phone = '+91' . $phone;
            }
        }

        return $phone;
    }

    /**
     * Get user-wise contact limits and usage
     */
    public function getLimits(Request $request)
    {
        try {
            $userId = $request->user()->getCompanyId();
            $payload = $this->contactLimitPayload($userId);

            if (! ($payload['hasPlan'] ?? false)) {
                return response()->json([
                    'status' => false,
                    'message' => 'No active plan found',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $payload,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Plan contact limit + current usage for this user.
     */
    protected function contactLimitPayload(int $userId): array
    {
        $activePackage = ActivePackage::forUser($userId);
        $currentContactCount = Contact::where('userId', $userId)->count();

        if (! $activePackage) {
            return [
                'hasPlan' => false,
                'package' => null,
                'contacts' => [
                    'current' => $currentContactCount,
                    'allowed' => 0,
                    'remaining' => 0,
                    'monthlyAdded' => 0,
                ],
            ];
        }

        $activePackage->checkAndPerformMonthlyReset();
        $allowedContactCount = $activePackage->resolvedContactLimit();
        $allowedMsgCount = $activePackage->resolvedMessageLimit();
        $usedMsgCount = $activePackage->is_yearly 
            ? (int) ($activePackage->monthlyUsedMsgCount ?? 0)
            : (int) ($activePackage->usedMsgCount ?? 0);

        return [
            'hasPlan' => true,
            'package' => [
                'name' => $activePackage->package->packageName ?? 'Standard',
                'contactLimit' => $allowedContactCount,
                'messageLimit' => $allowedMsgCount,
                'templateLimit' => $activePackage->resolvedTemplateLimit(),
                'baseMsgCount' => $activePackage->baseMessageLimit(),
                'addonMsgCount' => $activePackage->addonMessageCount(),
                'baseContactCount' => $activePackage->baseContactLimit(),
                'addonContactCount' => $activePackage->addonContactCount(),
                'startDate' => $activePackage->startDate,
                'endDate' => $activePackage->endDate,
                'renewalDate' => optional($activePackage->nextRenewalDate())->toIso8601String(),
                'usedMessages' => $usedMsgCount,
                'remainingMessages' => max(0, $allowedMsgCount - $usedMsgCount),
            ],
            'contacts' => [
                'current' => $currentContactCount,
                'allowed' => $allowedContactCount,
                'remaining' => max(0, $allowedContactCount - $currentContactCount),
                'monthlyAdded' => (int) ($activePackage->monthlyUsedContactCount ?? 0),
            ],
        ];
    }
}
