<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MemberRelationship;
use Illuminate\Http\Request;

class MemberRelationshipController extends Controller
{
    /**
     * Add a relationship (and its reverse) for a member.
     */
    public function store(Request $request, Member $member)
    {
        $validated = $request->validate([
            'related_member_id' => 'required|exists:members,id|different:member_id',
            'type'              => 'required|in:spouse,parent,child,sibling,guardian,other',
            'label'             => 'nullable|string|max:100',
        ]);

        if ((int) $validated['related_member_id'] === $member->id) {
            return back()->with('error', 'A member cannot be related to themselves.');
        }

        MemberRelationship::link(
            $member->id,
            (int) $validated['related_member_id'],
            $validated['type'],
            $validated['label'] ?? null
        );

        return back()->with('success', 'Relationship added.');
    }

    /**
     * Remove a relationship (and its reverse).
     */
    public function destroy(Member $member, Member $related)
    {
        MemberRelationship::unlink($member->id, $related->id);

        return back()->with('success', 'Relationship removed.');
    }
}