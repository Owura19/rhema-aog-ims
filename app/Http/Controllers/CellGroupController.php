<?php

namespace App\Http\Controllers;

use App\Models\CellGroup;
use App\Models\CellGroupMember;
use App\Models\Member;
use Illuminate\Http\Request;

class CellGroupController extends Controller
{
    public function index()
    {
        $groups = CellGroup::with(['leader', 'cellGroupMembers'])
            ->latest()
            ->paginate(20);

        $stats = [
            'total'       => CellGroup::count(),
            'active'      => CellGroup::where('status', 'Active')->count(),
            'cell_groups' => CellGroup::where('type', 'Cell Group')->count(),
            'departments' => CellGroup::where('type', 'Department')->count(),
        ];

        return view('cellgroups.index', compact('groups', 'stats'));
    }

    public function create()
    {
        $members = Member::where('membership_status', 'Active')
            ->orderBy('first_name')
            ->get();
        return view('cellgroups.create', compact('members'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                 => 'required|string|max:255',
            'type'                 => 'required|in:Cell Group,Department,Ministry,Team',
            'description'          => 'nullable|string',
            'meeting_day'          => 'nullable|string|max:20',
            'meeting_time'         => 'nullable',
            'meeting_venue'        => 'nullable|string|max:255',
            'leader_id'            => 'nullable|exists:members,id',
            'assistant_leader_id'  => 'nullable|exists:members,id',
            'status'               => 'required|in:Active,Inactive',
        ]);

        $validated['created_by'] = auth()->id();

        $group = CellGroup::create($validated);

        // Auto-add leader as a member
        if ($group->leader_id) {
            CellGroupMember::firstOrCreate(
                ['cell_group_id' => $group->id, 'member_id' => $group->leader_id],
                ['role' => 'Leader', 'joined_date' => now(), 'status' => 'Active']
            );
        }

        return redirect()->route('cellgroups.show', $group)
            ->with('success', "{$group->type} '{$group->name}' created successfully!");
    }

    public function show(CellGroup $cellgroup)
    {
        $cellgroup->load(['leader', 'assistantLeader', 'createdBy']);

        $members = CellGroupMember::where('cell_group_id', $cellgroup->id)
            ->with('member')
            ->orderBy('role')
            ->get();

        $availableMembers = Member::where('membership_status', 'Active')
            ->whereNotIn('id', $members->pluck('member_id'))
            ->orderBy('first_name')
            ->get();

        return view('cellgroups.show', compact('cellgroup', 'members', 'availableMembers'));
    }

    public function edit(CellGroup $cellgroup)
    {
        $members = Member::where('membership_status', 'Active')
            ->orderBy('first_name')
            ->get();
        return view('cellgroups.edit', compact('cellgroup', 'members'));
    }

    public function update(Request $request, CellGroup $cellgroup)
    {
        $validated = $request->validate([
            'name'                 => 'required|string|max:255',
            'type'                 => 'required|in:Cell Group,Department,Ministry,Team',
            'description'          => 'nullable|string',
            'meeting_day'          => 'nullable|string|max:20',
            'meeting_time'         => 'nullable',
            'meeting_venue'        => 'nullable|string|max:255',
            'leader_id'            => 'nullable|exists:members,id',
            'assistant_leader_id'  => 'nullable|exists:members,id',
            'status'               => 'required|in:Active,Inactive',
        ]);

        $cellgroup->update($validated);

        return redirect()->route('cellgroups.show', $cellgroup)
            ->with('success', "{$cellgroup->type} updated successfully!");
    }

    public function destroy(CellGroup $cellgroup)
    {
        $name = $cellgroup->name;
        $cellgroup->delete();
        return redirect()->route('cellgroups.index')
            ->with('success', "'{$name}' has been deleted.");
    }

    public function addMember(Request $request, CellGroup $cellgroup)
    {
        $validated = $request->validate([
            'member_id'   => 'required|exists:members,id',
            'role'        => 'required|string|max:50',
            'joined_date' => 'nullable|date',
        ]);

        CellGroupMember::updateOrCreate(
            [
                'cell_group_id' => $cellgroup->id,
                'member_id'     => $validated['member_id'],
            ],
            [
                'role'        => $validated['role'],
                'joined_date' => $validated['joined_date'] ?? now(),
                'status'      => 'Active',
            ]
        );

        return back()->with('success', 'Member added successfully!');
    }

    public function removeMember(CellGroup $cellgroup, Member $member)
    {
        CellGroupMember::where('cell_group_id', $cellgroup->id)
            ->where('member_id', $member->id)
            ->delete();

        return back()->with('success', 'Member removed from group.');
    }
}