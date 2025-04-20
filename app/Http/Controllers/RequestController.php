<?php

namespace App\Http\Controllers;


use Illuminate\Support\Facades\Mail;
use App\Helpers\AuditLogger;
use App\Models\Category;
use App\Models\Department;
use App\Models\Location;
use App\Models\User;
use Exception;
use DB;
use App\Models\Division;
use App\Models\Office;
use App\Models\Requests;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use Throwable;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;


class RequestController extends Controller
{
    // Method to create a new request

    public function createRequest(Request $request)
    {
        // Validate the incoming data using the Requests model's validateRequest method
        $validator = Requests::validateRequest($request->all());

        if ($validator->fails()) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ];
            $this->logAPICalls('createRequest', '', [], $response);

            return response()->json($response, 400);
        }

        $controlNo = Requests::generateControlNo();
        $filePath = null;
        $fileUrl = null;

        if ($request->hasFile('file_path')) {
            // Get the uploaded file
            $file = $request->file('file_path');

            // Define the target directory and file name
            $directory = public_path('img/asset');
            $fileName = 'Request-' . $controlNo . '-' . now()->format('YmdHis') . '.' . $file->getClientOriginalExtension();

            // Ensure the directory exists
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            // Move the file to the target directory
            $file->move($directory, $fileName);

            // Generate the relative file path
            $filePath = 'img/asset/' . $fileName;

            // Generate the file URL
            $fileUrl = asset($filePath);
        }

        try {
            $user = auth()->user();

            // Extract user details
            $firstName = $user->first_name ?? 'N/A';
            $lastName = $user->last_name ?? 'N/A';
            $division = $user->division->division_name ?? 'N/A'; // Assumes user has a relation to division
            $department = $user->department->department_name ?? 'N/A'; // Assumes user has a relation to department
            $officeLocation = $user->division->office_location ?? 'N/A'; // Fetching office location

            // Get the current timestamp using Carbon
            $dateRequested = Carbon::now();

            // Create the new request record
            $newRequest = Requests::create([
                'control_no' => $controlNo,
                'request_title' => $request->input('request_title'),
                'description' => $request->input('description'),
                'category' => $request->input('category'),
                'file_path' => $filePath,
                'status' => $request->input('status', 'Pending'),
                'requested_by' => $user->id,
                'date_requested' => $dateRequested, // Save Carbon instance
                'is_archived' => false,
            ]);

            // 🔹 Audit Logging: Before = "N/A", After = "Pending"
            AuditLogger::log('createRequest', 'N/A', 'Pending');

             // 🔹 Send email here
             Mail::raw("Hello $firstName $lastName,\n\nYour request titled '{$newRequest->request_title}' has been successfully submitted with control number: $controlNo.\n\nThank you!", function ($message) use ($user, $filePath) {
                $message->to($user->email)
                        ->subject('Request Submitted')
                        ->attach(public_path($filePath), [
                            'as' => 'Request-' . basename($filePath),  // Optional: specify a custom filename for the attachment
                            'mime' => 'application/octet-stream',    // Optional: specify MIME type
                        ]);
            });

            $response = [
                'isSuccess' => true,
                'message' => 'Request successfully created.',
                'request' => [
                    'id' => $newRequest->id,
                    'control_no' => $controlNo,
                    'request_title' => $newRequest->request_title,
                    'description' => $newRequest->description,
                    'category' => $newRequest->category,
                    'file_url' => $fileUrl,
                    'status' => $newRequest->status,
                    'requested_by' => [
                        'id' => $user->id,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'division' => $division,
                        'department' => $department,
                        'office_location' => $officeLocation,
                    ],
                    'date_requested' => $dateRequested->format('d-m-Y'), // Format with day, month, and year
                ],
            ];

            $this->logAPICalls('createRequest', $newRequest->id, [], $response);

            return response()->json($response, 201);
        } catch (Throwable $e) {
            // Handle any exceptions
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the request.',
                'error' => $e->getMessage(),
            ];

            $this->logAPICalls('createRequest', '', [], $response);

            return response()->json($response, 500);
        }
    }


    public function acceptRequest($id)
    {
        try {
            // Find the request by its ID
            $requestRecord = Requests::findOrFail($id);

            // Get the authenticated user
            $authUser = auth()->user();

            // Capture before state for audit logging
            $before = [
                'status' => $requestRecord->status ?? 'N/A',
                'note' => $requestRecord->note ?? 'N/A',
            ];

            // Update the status and set note column to null
            $requestRecord->status = 'For Process';
            $requestRecord->note = null;
            $requestRecord->save();

            // Capture after state for audit logging
            $after = [
                'status' => $requestRecord->status,
                'note' => $requestRecord->note,
            ];

            // Log the audit
            AuditLogger::log('acceptRequest', 'Pending', 'For Process');

            // Fetch the user who made the request
            $user = $requestRecord->user;

            // Fetch user's division and office location
            $division = $user->division ? $user->division->division_name : 'N/A';
            $officeLocation = $user->division ? $user->division->office_location : 'N/A';

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => 'Request has been accepted and is now For Review.',
                'request' => [
                    'id' => $requestRecord->id,
                    'control_no' => $requestRecord->control_no,
                    'status' => $requestRecord->status,
                    'requested_by' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name ?? 'N/A',
                        'last_name' => $user->last_name ?? 'N/A',
                        'division' => $division,
                        'office_location' => $officeLocation,
                    ],
                    'date_accepted' => now()->toDateTimeString(),
                ],
            ];

            Mail::raw("Hello {$user->first_name} {$user->last_name},\n\nYour request titled '{$requestRecord->request_title}' has been successfully accepted and is now in 'For Process' status.\n\nThank you!", function ($message) use ($user) {
                $message->to($user->email)
                        ->subject('Your Request Has Been Accepted');
            });

            $this->logAPICalls('acceptRequest', $id, [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to accept the request.',
                'error' => $e->getMessage(),
            ];

            $this->logAPICalls('acceptRequest', $id, [], $response);

            return response()->json($response, 500);
        }
    }


    // Reject a request and change its status to "Returned"
    public function rejectRequest(Request $request, $id)
    {
        try {
            // Validate the note input
            $request->validate([
                'note' => 'required|string|max:255',
            ]);

            // Find the request by its ID
            $requestRecord = Requests::findOrFail($id);

            // Get the authenticated user
            $authUser = auth()->user();

            // Capture before state for audit logging
            $before = [
                'status' => $requestRecord->status ?? 'N/A',
                'note' => $requestRecord->note ?? 'N/A',
            ];

            // Update the status and note
            $requestRecord->status = 'Returned';
            $requestRecord->note = $request->note; // Save the note to the database
            $requestRecord->save();

            // Capture after state for audit logging
            $after = [
                'status' => $requestRecord->status,
                'note' => $requestRecord->note,
            ];

            // Log the audit
            AuditLogger::log('rejectRequest', 'Pending', 'Returned');

            // Fetch the user who made the request
            $user = $requestRecord->user;

            $division = $user->division ? $user->division->division_name : 'N/A';
            $officeLocation = $user->division ? $user->division->office_location : 'N/A';

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => 'Request has been rejected and is now Returned.',
                'request' => [
                    'id' => $requestRecord->id,
                    'control_no' => $requestRecord->control_no,
                    'status' => $requestRecord->status,
                    'note' => $requestRecord->note, // Include the note in the response
                    'requested_by' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name ?? 'N/A',
                        'last_name' => $user->last_name ?? 'N/A',
                        'division' => $division,
                        'office_location' => $officeLocation,
                    ],
                    'date_rejected' => now()->toDateTimeString(),
                ],
            ];

            Mail::raw("Hello {$user->first_name} {$user->last_name},\n\nYour request titled '{$requestRecord->request_title}' has been reject please review it first and send a new request.\n\nThank you!", function ($message) use ($user) {
                $message->to($user->email)
                        ->subject('Your Request Has Been Rejected');
            });


            $this->logAPICalls('rejectRequest', $id, $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to reject the request.',
                'error' => $e->getMessage(),
            ];

            $this->logAPICalls('rejectRequest', $id, $request->all(), $response);

            return response()->json($response, 500);
        }
    }

    public function getRequests(Request $request)
    {
        try {
            $userId = $request->user()->id;
            $role = $request->user()->role_name;
    
            // Pagination settings
            $perPage = $request->input('per_page', 10);
            $searchTerm = $request->input('search', null);
    
            // Initialize query
            $query = Requests::select(
                'requests.id',
                'requests.control_no',
                'requests.request_title',
                'requests.description',
                'requests.file_path',
                'requests.file_completion',
                'requests.category_id',
                'requests.feedback',
                'requests.rating',
                'requests.status',
                'requests.date_requested',
                'requests.date_completed',
                'requests.personnel_ids',
                'users.id as requested_by_id',
                'users.first_name',
                'users.last_name',
            )
            ->leftJoin('users', 'users.id', '=', 'requests.requested_by')
            ->where('requests.is_archived', '=', '0')
            ->when($searchTerm, function ($query, $searchTerm) {
                return $query->where('requests.control_no', 'like', '%' . $searchTerm . '%');
            });
    
            // Apply filters
            if ($request->has('type')) {
                if ($request->type === 'Returned') {
                    $query->where('requests.status', 'Returned');
                } elseif ($request->type === 'For Overtime') {
                    $query->where('requests.overtime', '>', 0);
                }
            }
    
            $query->when($request->filled('location'), function ($query) use ($request) {
                $query->where('requests.location_name', $request->location);
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('requests.status', $request->status);
            })
            ->when($request->filled('category'), function ($query) use ($request) {
                $query->where('requests.category_id', $request->category);
            })
            ->when($request->filled('year'), function ($query) use ($request) {
                $query->where('requests.fiscal_year', $request->year);
            });
    
            // Role-based filtering
            switch ($role) {
                case 'admin':
                    break;
                case 'head':
                    $query->whereIn('requests.status', ['Pending']);
                    break;
                case 'staff':
                    $query->where('requests.requested_by', $userId);
                    break;
                case 'personnel':
                    // If user is personnel, but also a team lead, let them access requests in their categories
                    $isTeamLead = $request->user()->categories()->wherePivot('is_team_lead', true)->exists();
    
                    if ($isTeamLead) {
                        $categories = $request->user()->categories()->wherePivot('is_team_lead', true)->get();
                        $categoryIds = $categories->pluck('id');
    
                        // Fetch "To Assign" requests in the categories where the user is a team lead
                        $query->whereIn('requests.category_id', $categoryIds)
                              ->where('requests.status', 'To Assign');
                    } else {
                        // Default personnel behavior: only fetch requests related to the user
                        $query->whereRaw("JSON_CONTAINS(requests.personnel_ids, ?)", [json_encode((int) $userId)]);
                    }
                    break;
                case 'team_lead':
                    // Handle case if user is a team lead
                    $categories = $request->user()->categories()->wherePivot('is_team_lead', true)->get();
                    if ($categories->isNotEmpty()) {
                        $categoryIds = $categories->pluck('id');
                        $query->whereIn('requests.category_id', $categoryIds)
                              ->where('requests.status', 'To Assign');
                    }
                    break;
                default:
                    $query->whereRaw('1 = 0');
                    break;
            }
    
            // Execute query with pagination
            $result = $query->paginate($perPage);
    
            if ($result->isEmpty()) {
                return response()->json([
                    'isSuccess' => true,
                    'message' => 'No requests found.'
                ], 200);
            }
    
            // Format response
            $formattedRequests = $result->getCollection()->transform(function ($request) {
                $personnelIds = json_decode($request->personnel_ids, true) ?? [];
                $personnelInfo = User::whereIn('id', $personnelIds)
                    ->select('id', DB::raw("CONCAT(first_name, ' ', last_name) as name"))
                    ->get();
    
                // Fetch team lead information
                $teamLeadInfo = null;
                if ($request->category_id) {
                    $category = Category::find($request->category_id);
                    if ($category) {
                        $teamLeadId = $category->personnel()->wherePivot('is_team_lead', true)->first()->id ?? null;
                        if ($teamLeadId) {
                            $teamLeadInfo = User::find($teamLeadId);
                        }
                    }
                }
    
                return [
                    'id' => $request->id,
                    'control_no' => $request->control_no,
                    'request_title' => $request->request_title,
                    'description' => $request->description,
                    'file_path' => $request->file_path,
                    'file_url' => $request->file_path ? asset($request->file_path) : null,
                    'file_completion' => $request->file_completion,
                    'file_completion_url' => $request->file_completion ? asset($request->file_completion) : null,
                    'category_id' => $request->category_id,
                    'category_name' => $request->category_id
                        ? DB::table('categories')->where('id', $request->category_id)->value('category_name')
                        : null,
                        'team_lead' => $teamLeadInfo ? [
                            'id' => $teamLeadInfo->id,
                            'first_name' => $teamLeadInfo->first_name,
                            'last_name' => $teamLeadInfo->last_name,
                        ] : null, // Null if no team lead is assigned
                    'personnel' => $personnelInfo->map(function ($personnel) {
                        return [
                            'id' => $personnel->id,
                            'name' => $personnel->name,
                        ];
                    }),
                    'feedback' => $request->feedback,
                    'rating' => $request->rating,
                    'status' => $request->status,
                    'requested_by' => [
                        'id' => $request->requested_by_id,
                        'first_name' => $request->first_name,
                        'last_name' => $request->last_name,
                    ],
                    'date_requested' => $request->date_requested,
                    'date_completed' => $request->date_completed,
                ];
            });
    
            return response()->json([
                'isSuccess' => true,
                'message' => 'Requests retrieved successfully.',
                'requests' => $formattedRequests,
                'pagination' => [
                    'total' => $result->total(),
                    'per_page' => $result->perPage(),
                    'current_page' => $result->currentPage(),
                    'last_page' => $result->lastPage(),
                ],
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve requests.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    





    // Method to delete (archive) a request
    public function getRequestById(Request $request, $id)
    {
        try {
            // Find the request by ID with category and user join
            $requestDetails = Requests::select(
                'requests.id',
                'requests.control_no',
                'requests.request_title',
                'requests.requested_by',
                'users.first_name as requested_by_first_name',
                'users.last_name as requested_by_last_name',
                'users.email as requested_by_email', // Include email
                'requests.description',
                'requests.file_path',
                'requests.file_completion', // Include completion file path
                'requests.category_id',
                'categories.category_name', // Fetch category name directly
                'requests.feedback',
                'requests.status',
                'requests.date_requested',
                'requests.date_completed', // Include date completed
                DB::raw("DATE_FORMAT(requests.updated_at, '%Y-%m-%d') as updated_at")
            )
                ->leftJoin('users', 'users.id', '=', 'requests.requested_by') // Join with users table to fetch user details
                ->leftJoin('categories', 'categories.id', '=', 'requests.category_id')
                ->where('requests.id', $id)
                ->where('requests.is_archived', '=', '0') // Filter out archived requests
                ->first();

            // Check if the request exists
            if (!$requestDetails) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Request not found.',
                ];
                $this->logAPICalls('getRequestById', $id, [], $response);

                return response()->json($response, 404);
            }

            // Format the response
            $formattedRequest = [
                'id' => $requestDetails->id,
                'control_no' => $requestDetails->control_no,
                'request_title' => $requestDetails->request_title,
                'requested_by' => $requestDetails->requested_by,
                'requested_by_name' => $requestDetails->requested_by_first_name . ' ' . $requestDetails->requested_by_last_name,
                'requested_by_email' => $requestDetails->requested_by_email, // Add requested by email
                'description' => $requestDetails->description,
                'category_id' => $requestDetails->category_id,
                'category_name' => $requestDetails->category_name, // Include category name
                'feedback' => $requestDetails->feedback,
                'status' => $requestDetails->status,
                'file_path' => $requestDetails->file_path,
                'file_url' => $requestDetails->file_path ? asset($requestDetails->file_path) : null,
                'file_completion' => $requestDetails->file_completion, // Add completion file
                'file_completion_url' => $requestDetails->file_completion ? asset($requestDetails->file_completion) : null, // Add completion file URL
                'date_requested' => $requestDetails->date_requested,
                'date_completed' => $requestDetails->date_completed, // Include date completed
                'updated_at' => $requestDetails->updated_at,
            ];

            // Successful response
            $response = [
                'isSuccess' => true,
                'message' => 'Request retrieved successfully.',
                'request' => $formattedRequest,
            ];
            $this->logAPICalls('getRequestById', $id, [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Error handling
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve request.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('getRequestById', $id, [], $response);

            return response()->json($response, 500);
        }
    }

    public function assignTeamLead(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $requests = Requests::findOrFail($id);
    
            // Capture before state
            $before = [
                'status' => $requests->status ?? 'N/A',
                'category_id' => $requests->category_id ?? 'N/A',
                'team_lead_id' => $requests->team_lead_id ?? null,
            ];
    
            // Validate input (only one team lead)
            $validatedData = $request->validate([
                'category_id' => 'required|exists:categories,id',
                'team_lead_id' => 'required|exists:users,id',
            ]);
    
            // Verify if the provided team lead is marked as is_team_lead in the category_personnel pivot
            $validTeamLead = DB::table('category_personnel')
                ->join('users', 'category_personnel.personnel_id', '=', 'users.id')
                ->where('category_personnel.category_id', $validatedData['category_id'])
                ->where('category_personnel.is_team_lead', 1)
                ->where('users.id', $validatedData['team_lead_id'])
                ->exists();
    
            if (!$validTeamLead) {
                throw new Exception("The selected team lead is either invalid or not marked as a team lead in the selected category.");
            }
    
            // Update request with the single team lead
            $requests->update([
                'status' => 'For Assign',
                'category_id' => $validatedData['category_id'],
                'team_lead_id' => $validatedData['team_lead_id'],
            ]);
    
            // Capture after state
            $after = [
                'status' => $requests->status,
                'category_id' => $requests->category_id,
                'team_lead_id' => $validatedData['team_lead_id'],
            ];
    
            AuditLogger::log('assignTeamLead', 'Assigning Team Lead', 'To Assign');
    
            $fullName = trim("{$user->first_name} {$user->middle_initial} {$user->last_name}");
    
            // Fetch the team lead's details
            $teamLead = User::find($validatedData['team_lead_id']);
    
            // Fetch the request sender's details
            $sender = User::find($requests->requested_by);
            $category = Category::find($validatedData['category_id']);
    
            // Send raw email to the request sender
            if ($sender && $sender->email) {
                Mail::raw(
                    "Hi {$sender->first_name},\n\n" .
                    "Your request titled \"{$requests->description}\" with Control No. {$requests->control_no} has been assigned a team lead.\n\n" .
                    "Category: {$category->category_name}\n" .
                    "Assigned Team Lead: {$teamLead->first_name} {$teamLead->last_name}\n" .
                    "Status: {$requests->status}\n\n" .
                    "Thank you!",
                    function ($message) use ($sender) {
                        $message->to($sender->email)
                                ->subject('Team Lead Assigned to Your Request');
                    }
                );
            }
    
            $response = [
                'isSuccess' => true,
                'message' => 'Team lead assigned successfully. Status updated to "To Assign".',
                'request_id' => $requests->id,
                'status' => $requests->status,
                'user_id' => $user->id,
                'user' => $fullName,
                'category' => [
                    'id' => $category->id,
                    'name' => $category->category_name,
                ],
                'team_lead' => [
                    'id' => $teamLead->id,
                    'first_name' => $teamLead->first_name,
                    'last_name' => $teamLead->last_name,
                    'status' => $teamLead->status,
                ],
            ];
    
            $this->logAPICalls('assignTeamLead', $requests->id, $request->all(), $response);
            return response()->json($response, 200);
    
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to assign team lead to the request.",
                'error' => $e->getMessage(),
            ];
    
            $this->logAPICalls('assignTeamLead', $id ?? '', $request->all(), $response);
            return response()->json($response, 500);
        }
    }
    
    



    public function assessRequest(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $requests = Requests::where('id', $id)->firstOrFail();
    
            // Capture before state
            $before = [
                'status' => $requests->status ?? 'N/A',
                'category_id' => $requests->category_id ?? 'N/A',
                'personnel_ids' => json_decode($requests->personnel_ids, true) ?? [],
            ];
    
            // Validate input (no more category_id)
            $validatedData = $request->validate([
                'personnel_ids' => 'required|array|min:1',
                'personnel_ids.*' => 'exists:users,id',
                'status' => 'sometimes|in:For Completion',
            ]);
    
            // Only get Active personnel
            $activePersonnelIds = User::where('status', 'Active')
                ->whereIn('id', $validatedData['personnel_ids'])
                ->pluck('id')
                ->toArray();
    
            $invalidPersonnelIds = array_diff($validatedData['personnel_ids'], $activePersonnelIds);
    
            if (!empty($invalidPersonnelIds)) {
                throw new Exception("The following personnel IDs are not active: " . implode(', ', $invalidPersonnelIds));
            }
    
            // Check if personnel belong to the same category as the request
            $personnelCategoryIds = \DB::table('category_personnel')
                ->whereIn('personnel_id', $validatedData['personnel_ids']) // changed from user_id to personnel_id
                ->pluck('category_id')
                ->unique();
    
            \Log::info('Request category_id: ' . $requests->category_id);
            \Log::info('Personnel category_ids: ' . implode(', ', $personnelCategoryIds->toArray()));
    
            if (!$personnelCategoryIds->contains($requests->category_id)) {
                throw new Exception("Selected personnel are not part of the same category as the request.");
            }
    
            // Get team lead from the requests table using team_lead_id
            $teamLeadData = null;
            if ($requests->team_lead_id) {
                $teamLeadData = User::find($requests->team_lead_id);
            }
    
            $statusToUpdate = $validatedData['status'] ?? 'For Completion';
    
            // Update request (category_id is untouched)
            $requests->update([
                'status' => $statusToUpdate,
                'personnel_ids' => json_encode($validatedData['personnel_ids']),
            ]);
    
            // Update personnel status to Assigned
            User::whereIn('id', $validatedData['personnel_ids'])->update(['status' => 'Assigned']);
    
            // Capture after state
            $after = [
                'status' => $requests->status,
                'category_id' => $requests->category_id,
                'personnel_ids' => $validatedData['personnel_ids'],
            ];
    
            // Log audit trail
            AuditLogger::log('assessRequest', 'For Process', 'For Completion');
    
            $fullName = trim("{$user->first_name} {$user->middle_initial} {$user->last_name}");
    
            $response = [
                'isSuccess' => true,
                'message' => 'Request assessed successfully. Personnel status updated to Assigned.',
                'request_id' => $requests->id,
                'status' => $requests->status,
                'user_id' => $user->id,
                'user' => $fullName,
                'category' => [
                    'id' => $requests->category_id,
                ],
                'personnel' => array_map(function ($personnelId) {
                    $personnel = User::find($personnelId);
                    return [
                        'id' => $personnel->id,
                        'first_name' => $personnel->first_name,
                        'last_name' => $personnel->last_name,
                        'status' => $personnel->status,
                    ];
                }, $validatedData['personnel_ids']),
                'team_lead' => $teamLeadData ? [
                    'id' => $teamLeadData->id,
                    'first_name' => $teamLeadData->first_name,
                    'last_name' => $teamLeadData->last_name,
                ] : null,
            ];

            $sender = User::find($requests->requested_by);

                 // Get category name (if needed for better email context)
                 $category = Category::find($requests->category_id);

                // Prepare assigned personnel names
                $assignedPersonnel = User::whereIn('id', $validatedData['personnel_ids'])->get();
                $personnelList = $assignedPersonnel->map(function ($p) {
                    return "{$p->first_name} {$p->last_name}";
                })->implode(', ');

                // Send email to requester
                if ($sender && $sender->email) {
                    Mail::raw(
                        "Hi {$sender->first_name},\n\n" .
                        "Your request titled \"{$requests->description}\" with Control No. {$requests->control_no} has been assessed.\n\n" .
                        "Category: {$category->category_name}\n" .
                        "Assigned Team Lead: " . ($teamLeadData ? "{$teamLeadData->first_name} {$teamLeadData->last_name}" : 'N/A') . "\n" .
                        "Assigned Personnel: {$personnelList}\n" .
                        "Status: {$requests->status}\n\n" .
                        "Thank you!",
                        function ($message) use ($sender) {
                            $message->to($sender->email)
                                    ->subject('Personnel Assigned to Your Request');
                        }
                    );
                }
    
            $this->logAPICalls('assessRequest', $requests->id, $request->all(), $response);
            return response()->json($response, 200);
    
        } catch (Throwable $e) {
            \Log::error('Assess Request error: ' . $e->getMessage());
    
            $response = [
                'isSuccess' => false,
                'message' => "Failed to assess the request.",
                'error' => $e->getMessage(),
            ];
    
            $this->logAPICalls('assessRequest', $id ?? '', $request->all(), $response);
            return response()->json($response, 500);
        }
    }
    


    






    public function submitCompletion(Request $request, $id)
    {
        try {
            // Find the request by its ID
            $requestRecord = Requests::findOrFail($id);
            $user = auth()->user();

            // Store previous data for audit logging
            $previousData = $requestRecord->toArray();

            // Initialize variables for file path and URL
            $fileCompletionPath = null;
            $fileCompletionUrl = null;

            if ($request->hasFile('file_completion')) {
                $file = $request->file('file_completion');

                $directory = public_path('img/asset');
                $fileName = 'Request-' . $requestRecord->control_no . '-' . now()->format('YmdHis') . '.' . $file->getClientOriginalExtension();

                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                }

                $file->move($directory, $fileName);

                $fileCompletionPath = 'img/asset/' . $fileName;
                $fileCompletionUrl = asset($fileCompletionPath);
            }


            $requestRecord->file_completion = $fileCompletionPath;
            $requestRecord->status = 'For Feedback';
            $requestRecord->date_completed = now();
            $requestRecord->save();


            $personnelIds = json_decode($requestRecord->personnel_ids, true);
            if (!empty($personnelIds)) {
                User::whereIn('id', $personnelIds)->update(['status' => 'Active']);
            }

           // Fetch the original requester
            $sender = User::find($requestRecord->requested_by);

            // Get the category for more context (optional)
            $category = Category::find($requestRecord->category_id);

            // Get assigned personnel names
            $assignedPersonnel = User::whereIn('id', $personnelIds)->get();
            $personnelList = $assignedPersonnel->map(function ($p) {
                return "{$p->first_name} {$p->last_name}";
            })->implode(', ');

            // Get team lead
            $teamLead = $requestRecord->team_lead_id ? User::find($requestRecord->team_lead_id) : null;

            // Send email to requester
            if ($sender && $sender->email) {
                // Define the path to the image file
                $imagePath = public_path($fileCompletionPath);

                Mail::raw(
                    "Hi {$sender->first_name},\n\n" .
                    "Your request with Control No. {$requestRecord->control_no} has been marked as completed and is now awaiting your feedback.\n\n" .
                    "Category: " . ($category ? $category->category_name : 'N/A') . "\n" .
                    "Team Lead: " . ($teamLead ? "{$teamLead->first_name} {$teamLead->last_name}" : 'N/A') . "\n" .
                    "Assigned Personnel: {$personnelList}\n\n" .
                    "Please find the completion image attached below.\n\n" .
                    "Thank you!",
                    function ($message) use ($sender, $imagePath) {
                        $message->to($sender->email)
                                ->subject('Your Request Has Been Completed');
                        
                        // Attach the image file directly
                        if (file_exists($imagePath)) {
                            $message->attach($imagePath, [
                                'as' => 'completion-file.jpg', // Customize the filename if needed
                                'mime' => 'image/jpeg', // MIME type for the image
                            ]);
                        }
                    }
                );
            }



            AuditLogger::log('submitCompletion', 'For Completion', 'For Feedback');

            return response()->json([
                'isSuccess' => true,
                'message' => 'Completion file has been submitted successfully.',
                'request' => [
                    'id' => $requestRecord->id,
                    'control_no' => $requestRecord->control_no,
                    'file_completion_url' => $fileCompletionUrl,
                    'file_completion_path' => $fileCompletionPath,
                    'status' => $requestRecord->status,
                    'date_completed' => $requestRecord->date_completed,
                    'submitted_by' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                    ],
                    'personnel_updated' => $personnelIds,
                ],
                
            ], 200);


            
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to submit the completion file.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function submitFeedback(Request $request, $id)
    {
        try {
            // Validate the input data
            $this->validate($request, [
                'feedback' => 'required|string|max:255',
                'rating' => 'required|numeric|min:0|max:5',
            ]);

            // Find the request by its ID
            $requestRecord = Requests::findOrFail($id);
            $user = auth()->user();

            // Store previous data for audit logging
            $previousData = $requestRecord->toArray();

            // Update the feedback and rating columns
            $requestRecord->feedback = $request->input('feedback');
            $requestRecord->rating = $request->input('rating');
            $requestRecord->status = 'Completed';
            $requestRecord->save();


            AuditLogger::log('submitFeedback', 'For Feedback', 'Complete');


            return response()->json([
                'isSuccess' => true,
                'message' => 'Feedback has been submitted successfully.',
                'request' => [
                    'id' => $requestRecord->id,
                    'control_no' => $requestRecord->control_no,
                    'feedback' => $requestRecord->feedback,
                    'rating' => $requestRecord->rating,
                    'status' => $requestRecord->status,
                    'rated_by' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                    ],
                ],
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to submit feedback.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }





    //Dropdown Request Location
    public function getDropdownOptionsRequestslocation(Request $request)
    {
        try {

            $location = Location::select('id', 'location_name')
                ->where('is_archived', '0')
                ->get();

            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'location' => $location
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionsRequestslocation', "", [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionsRequestslocation', "", [], $response);

            return response()->json($response, 500);
        }
    }

    //Dropdown Request Status
    public function getDropdownOptionsRequeststatus(Request $request)
    {
        try {

            $status = Requests::select(DB::raw('MIN(id) as id'), 'status')
                ->whereIn('status', ['Pending', 'For Inspection', 'On-Going', 'Completed', 'Returned'])
                ->where('is_archived', '0')
                ->groupBy('status')
                ->get();

            // Build the response
            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'status' => $status
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionsRequeststatus', "", [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionsRequeststatus', "", [], $response);

            return response()->json($response, 500);
        }
    }



    public function getCategoriesWithPersonnel()
    {
        try {
            // Fetch categories with their associated personnel
            $categoriesWithPersonnel = DB::table('categories')
                ->select('categories.id', 'categories.category_name')
                ->where('categories.is_archived', '0')
                ->leftJoin('category_personnel', 'categories.id', '=', 'category_personnel.category_id')
                ->leftJoin('users', 'category_personnel.personnel_id', '=', 'users.id')
                ->select(
                    'categories.id as category_id',
                    'categories.category_name',
                    'users.id as personnel_id',
                    'users.first_name',
                    'users.last_name',
                )
                ->get()
                ->groupBy('category_id')
                ->map(function ($category) {
                    return [
                        'id' => $category[0]->category_id,
                        'category_name' => $category[0]->category_name,
                        'personnel' => $category->map(function ($personnel) {
                            return $personnel->personnel_id ? [
                                'id' => $personnel->personnel_id,
                                'name' => trim($personnel->first_name . ' ' . $personnel->last_name),
                            ] : null;
                        })->filter() // Remove null entries
                    ];
                })
                ->values();

            $response = [
                'isSuccess' => true,
                'message' => 'Categories with personnel retrieved successfully.',
                'categories' => $categoriesWithPersonnel
            ];

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve categories and personnel.',
                'error' => $e->getMessage()
            ];

            return response()->json($response, 500);
        }
    }
    //Dropdown Request Status
    public function getUsersByCategory(Request $request)
    {
        try {
            // Validate that category_id is provided
            $request->validate([
                'category_id' => 'required|exists:categories,id'
            ]);

            $categoryId = $request->input('category_id');

            // Directly fetch personnel IDs from the pivot table
            $personnelIds = DB::table('category_personnel')
                ->where('category_id', $categoryId)
                ->pluck('personnel_id');

            // Fetch full user details for those personnel IDs
            $personnel = User::whereIn('id', $personnelIds)
                ->select('id', 'name', 'email')
                ->get();

            $response = [
                'isSuccess' => true,
                'message' => 'Personnel retrieved successfully.',
                'personnel' => $personnel
            ];

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve personnel.',
                'error' => $e->getMessage()
            ];

            return response()->json($response, 500);
        }
    }
    //Dropdown Request Division
    public function getDropdownOptionsRequestdivision(Request $request)
    {
        try {

            $div_name = Division::select('id', 'div_name')
                ->where('is_archived', '0')
                ->get();

            // Build the response
            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'div_name' => $div_name
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionsRequestdivision', "", [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionsRequestdivision', "", [], $response);

            return response()->json($response, 500);
        }
    }

    //  Dropdown Request Category
    public function getDropdownOptionsRequestcategory(Request $request)
    {
        try {

            $category = Category::select('id', 'category_name')
                ->where('is_archived', '0')
                ->get();


            // Build the response
            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'category' => $category
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionsRequestcategory', "", [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionsRequestcategory', "", [], $response);

            return response()->json($response, 500);
        }
    }

    //Dropdown Create Request office
    public function getDropdownOptionscreateRequestsoffice(Request $request)
    {
        try {
            $office = Department::select('id', 'department_name')
                ->where('is_archived', '0')
                ->get();

            // Build the response
            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'office' => $office,
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionscreateRequestsoffice', "", [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionscreateRequestsoffice', "", [], $response);

            return response()->json($response, 500);
        }
    }

    //Dropdown CreateRequest location
    public function getDropdownOptionscreateRequestslocation(Request $request)
    {
        try {

            $location = Location::select('id', 'location_name')
                ->where('is_archived', '0')
                ->get();

            // Build the response
            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'location' => $location,
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionscreateRequestslocation', "", [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionscreateRequestslocation', "", [], $response);

            return response()->json($response, 500);
        }
    }

    public function getSetting(string $code)
    {
        try {
            $value = DB::table('settings')
                ->where('setting_code', $code)
                ->value('setting_value');
        } catch (Throwable $e) {
            return $e->getMessage();
        }
        return $value;
    }

    // Log API calls for requests
    public function logAPICalls(string $methodName, string $requestId, array $param, array $resp)
    {
        try {
            \App\Models\ApiLog::create([
                'method_name' => $methodName,
                'request_id' => $requestId,
                'api_request' => json_encode($param),
                'api_response' => json_encode($resp),
            ]);
        } catch (Throwable $e) {
            return false;
        }
        return true;
    }
}
