<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MemberPortalAccessController extends Controller
{
    /**
     * Create a portal login account for a member (admin action).
     */
    public function create(Member $member)
    {
        // Member must have an email to receive/use a login
        if (empty($member->email)) {
            return back()->with('error', 'This member has no email address. Add one first, then create their login.');
        }

        // Prevent duplicate accounts
        if ($member->user) {
            return back()->with('error', 'This member already has a portal login.');
        }

        // Ensure the email isn't already used by another account
        if (User::where('email', $member->email)->exists()) {
            return back()->with('error', 'A login already exists with this email address.');
        }

        // Generate a temporary password
        $tempPassword = Str::password(10);

        $user = User::create([
            'member_id' => $member->id,
            'name'      => $member->full_name,
            'email'     => $member->email,
            'password'  => Hash::make($tempPassword),
        ]);

        // Give them the Member role (restricted access)
        $user->assignRole('Member');

        // Show the temp password once so the admin can share it with the member
        return back()->with('portal_created', $this->loginDetails($user, $tempPassword));
    }

    /**
     * Reset a member's portal password (admin action).
     * Saving the new password also signs the member out of every app device (User model hook).
     */
    public function resetPassword(Member $member)
    {
        if (!$member->user) {
            return back()->with('error', 'This member has no portal login yet.');
        }

        $tempPassword = Str::password(10);
        $member->user->update(['password' => Hash::make($tempPassword)]);

        return back()->with('portal_created', $this->loginDetails($member->user, $tempPassword));
    }

    /**
     * Revoke (delete) a member's portal login.
     */
    public function revoke(Member $member)
    {
        if ($member->user) {
            $member->user->delete();
        }

        return back()->with('success', 'Portal login revoked.');
    }

    /** What to show once: how this login signs in (phone or email) and the temporary password. */
    private function loginDetails(User $user, string $tempPassword): array
    {
        return [
            'login_label' => $user->login_phone ? 'Phone' : 'Email',
            'login'       => $user->login_phone ? PhoneNumber::display($user->login_phone) : $user->email,
            'password'    => $tempPassword,
        ];
    }
}
