<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // ✅ Get all departments with department head name
        $departments = Department::select(
                'departments.id',
                'departments.department_name',
                'users.name as department_head_name'
            )
            ->leftJoin('users', 'users.id', '=', 'departments.department_head')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $departments
        ]);
    }
    
    public function getUserDepartment()
    {
        $userId = Auth::id(); // ✅ get logged-in user ID

        $department = Department::where('department_head', $userId)->first();

        if ($department) {
            return response()->json([
                'success' => true,
                'data' => $department
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'User is not assigned as a department head'
            ]);
        }
    }

    public function getUsers()
    {
        // ✅ Get all user
        $users = User::all();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
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
        // ✅ Validate input
        $request->validate([
            'department_name' => 'required|string|max:255',
            'department_head' => 'nullable|string|max:255',
        ]);

        // ✅ Create new department record
        $department = Department::create([
            'department_name' => $request->department_name,
            'department_head' => $request->department_head,
            'company_id' => $request->company_id,
        ]);

        // ✅ Return JSON or redirect based on your use case
        return response()->json([
            'success' => true,
            'message' => 'Department added successfully.',
            'data' => $department
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Department  $department
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // ✅ Find department by ID
        $department = Department::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $department
        ]);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Department  $department
     * @return \Illuminate\Http\Response
     */
    public function edit(Department $department)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Department  $department
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // ✅ Validate input
        $request->validate([
            'department_name' => 'required|string|max:255',
            'department_head' => 'nullable|string|max:255',
        ]);

        // ✅ Find department
        $department = Department::findOrFail($id);

        // ✅ Update fields
        $department->update([
            'department_name' => $request->department_name,
            'department_head' => $request->department_head,
            'company_id' => $request->company_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Department updated successfully.',
            'data' => $department
        ]);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Department  $department
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $department = Department::findOrFail($id);
        $department->delete();

        return response()->json([
            'success' => true,
            'message' => 'Department deleted successfully.'
        ]);
    }
}
