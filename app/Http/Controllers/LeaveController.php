<?php

namespace App\Http\Controllers;

use App\Models\Leave;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;
use App\Models\BiometricHistoryList;

class LeaveController extends Controller
{
    protected $biometricHistoryList;

    public function __construct(BiometricHistoryList $biometricHistoryList)
    {
        $this->biometricHistoryList = $biometricHistoryList;
    }

    public function getLeaveStatusSummary()
    {
        $biometricImportId = $this->biometricHistoryList->getLoadedRecordId();

        // Group by status and count
        $statusSummary = Leave::where('biometric_imports_id', $biometricImportId)
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->get();

        // Prepare chart labels and data
        $labels = $statusSummary->pluck('status');
        $data = $statusSummary->pluck('total');

        return response()->json([
            'labels' => $labels,
            'data' => $data
        ]);
    }
    public function approve($id)
    {
        try {
            // Find the leave record
            $leave = Leave::findOrFail($id);

            // Update the status
            $leave->status = 'approved';
            $leave->save();

            return response()->json([
                'success' => true,
                'message' => 'Leave request has been approved successfully.',
                'data' => $leave
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve leave request.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function cancel($id)
    {
        try {
            // Find the leave record
            $leave = Leave::findOrFail($id);

            // Update the status
            $leave->status = 'cancelled';
            $leave->save();

            return response()->json([
                'success' => true,
                'message' => 'Leave request has been cancelled successfully.',
                'data' => $leave
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel leave request.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $status = $request->get('status'); // optional filter
            $userRole = $request->get('userRole');
            $department = $request->get('department');
            $biometricImportId = $this->biometricHistoryList->getLoadedRecordId();

            // 🔹 Build query with join to employee_management
            $data = Leave::join('employee_management', 'employee_management.id', '=', 'leaves.employee_management_id')
                ->select(
                    'leaves.id',
                    'employee_management.employee_name',
                    'employee_management.department',
                    'leaves.reason',
                    'employee_management.schedule_shift',
                    'leaves.record_date',
                    'leaves.leave_type',
                    'leaves.with_pay',
                    'leaves.status',
                    'leaves.created_at',
                    'leaves.updated_at'
                )
                ->orderBy('employee_management.employee_name', 'asc');

            // ✅ Apply biometric_imports_id filter only if it has a value
            if (!empty($biometricImportId)) {
                $data->where('leaves.biometric_imports_id', $biometricImportId);
            }

            // ✅ Optional filter: by status (e.g., Pending / Approved)
            if (!empty($status)) {
                $data->where('leaves.status', $status);
            }

            // ✅ Optional filter: restrict non-admins to their department
            if ($userRole === 'user' && $department) {
                $data->where('employee_management.department', $department);
            }

            // ✅ Return DataTables JSON
            return DataTables::of($data)
                ->filterColumn('employee_name', function($query, $keyword) {
                    $query->whereRaw('LOWER(employee_management.employee_name) LIKE ?', ["%{$keyword}%"]);
                })
                ->filterColumn('department', function($query, $keyword) {
                    $query->whereRaw('LOWER(employee_management.department) LIKE ?', ["%{$keyword}%"]);
                })
                ->filterColumn('leave_type', function($query, $keyword) {
                    $query->whereRaw('LOWER(leaves.leave_type) LIKE ?', ["%{$keyword}%"]);
                })
                ->filterColumn('reason', function($query, $keyword) {
                    $query->whereRaw('LOWER(leaves.reason) LIKE ?', ["%{$keyword}%"]);
                })
                ->make(true);
        }

        return view('leaves.index');
    }

    public function getLeavesCounts(Request $request)
    {
        $userRole = $request->get('userRole');
        $department = $request->get('department');
        $biometricImportId = $this->biometricHistoryList->getLoadedRecordId();

        $query = Leave::join(
            'employee_management',
            'employee_management.id',
            '=',
            'leaves.employee_management_id'
        );

        // ✅ Apply department filter only if user is not admin
        if ($userRole === 'user' && $department) {
            $query->where('employee_management.department', $department);
        }
        if (!empty($biometricImportId)) {
            $query->where('biometric_imports_id', $biometricImportId);
        }

        $counts = [
            'pending' => (clone $query)->where('leaves.status', 'pending')->count(),
            'approved' => (clone $query)->where('leaves.status', 'approved')->count(),
            'cancelled' => (clone $query)->where('leaves.status', 'cancelled')->count(),
        ];

        return response()->json($counts);
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function store(Request $request)
    {
        $biometricImportId = $this->biometricHistoryList->getLoadedRecordId();
        $request->validate([
            'leaveArray' => 'required|array',
            'leaveArray.*.employee_management_id' => 'required|integer',
            'leaveArray.*.record_date' => [
                'required',
                'date',
                function ($attribute, $value, $fail) use ($request) {
                    $index = explode('.', $attribute)[1];
                    $employeeId = $request->leaveArray[$index]['employee_management_id'];

                    $exists = Leave::where('employee_management_id', $employeeId)
                        ->where('record_date', $value)
                        ->exists();

                    if ($exists) {
                        $fail("Leave for employee ID {$employeeId} on {$value} already exists.");
                    }
                }
            ],
        ]);

        foreach ($request->leaveArray as $leave) {
            Leave::create([
                'employee_management_id' => $leave['employee_management_id'],
                'leave_type' => $leave['leave_type'],
                'reason' => $leave['reason'] ?? null,
                'record_date' => $leave['record_date'],
                'status' => $leave['status'] ?? 'pending',
                'with_pay' => $leave['with_pay'] ?? 'No',
                'biometric_imports_id' => $biometricImportId,
            ]);
        }

        return response()->json(['message' => 'Leave records saved successfully']);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Leave  $leave
     * @return \Illuminate\Http\Response
     */
    public function show(Leave $leave)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Leave  $leave
     * @return \Illuminate\Http\Response
     */
    public function edit(Leave $leave)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Leave  $leave
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Leave $leave)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Leave  $leave
     * @return \Illuminate\Http\Response
     */
    public function destroy(Leave $leave)
    {
        //
    }
}
