<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\User;
use App\Services\Auth\MemberPhoneDirectory;
use App\Support\Auth\TemporaryPassword;
use App\Support\PhoneNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Staff give a member a mobile app login (phone + temporary password), reset it or
 * remove it, from the member's page. Routes need 'edit members'.
 *  - Staff accounts are never managed here; give and reset also refuse deactivated logins.
 *  - A temporary password is shown once (session flash), never logged, and the member
 *    must change it before the app does anything else (users.must_change_password).
 */
class MemberAppLoginController extends Controller
{
    public const MSG_INVALID_PHONE = 'Enter a valid Ghana phone number.';
    public const MSG_NUMBER_TAKEN  = 'This phone number is already used by another app login.';
    public const MSG_ALREADY       = 'This member already has an app login. Remove it first to change the number.';
    public const MSG_INELIGIBLE    = 'Only Active or Inactive members can have an app login.';
    public const MSG_STAFF         = "This member's login is a staff account. Staff app logins are not managed here.";
    public const MSG_INACTIVE      = "This member's login is deactivated.";
    public const MSG_NO_APP_LOGIN  = 'This member has no app login.';

    public function store(Request $request, Member $member): RedirectResponse
    {
        $request->validate(['app_phone' => ['required', 'string', 'max:30']]);

        $phone = PhoneNumber::normalise($request->input('app_phone'));
        if ($phone === null) {
            throw ValidationException::withMessages(['app_phone' => self::MSG_INVALID_PHONE]);
        }

        if (! in_array($member->membership_status, MemberPhoneDirectory::ELIGIBLE_STATUSES, true)) {
            return back()->with('error', self::MSG_INELIGIBLE);
        }

        $user = $member->user;

        if ($user && ($refusal = $this->refusal($user))) {
            return back()->with('error', $refusal);
        }

        if ($user?->login_phone !== null) {
            return back()->with('error', self::MSG_ALREADY);
        }

        if (User::where('login_phone', $phone)->exists()) {
            throw ValidationException::withMessages(['app_phone' => self::MSG_NUMBER_TAKEN]);
        }

        $password = TemporaryPassword::generate();

        try {
            DB::transaction(function () use ($member, $user, $phone, $password) {
                if ($user === null) {
                    $user = new User();
                    $user->forceFill([
                        'name'        => $member->full_name,
                        'email'       => null,
                        'member_id'   => $member->id,
                        'is_active'   => true,
                    ]);
                }

                $user->login_phone = $phone;
                $user->password = $password;          // hashed by the cast; an existing user's tokens go (User hook)
                $user->must_change_password = true;
                $user->save();

                if ($user->getRoleNames()->isEmpty()) {
                    $user->assignRole(Role::findOrCreate('Member', 'web'));
                }
            });
        } catch (UniqueConstraintViolationException) {
            // Another login took this number at the same moment.
            throw ValidationException::withMessages(['app_phone' => self::MSG_NUMBER_TAKEN]);
        }

        return back()->with('app_login_created', $this->details($phone, $password));
    }

    public function resetPassword(Member $member): RedirectResponse
    {
        $user = $member->user;

        if (! $user || $user->login_phone === null) {
            return back()->with('error', self::MSG_NO_APP_LOGIN);
        }

        if ($refusal = $this->refusal($user)) {
            return back()->with('error', $refusal);
        }

        $password = TemporaryPassword::generate();

        $user->password = $password;          // the User hook signs out every device
        $user->must_change_password = true;
        $user->save();

        return back()->with('app_login_created', $this->details($user->login_phone, $password));
    }

    /**
     * An app-only login (no email) is deleted, so the member can activate the app
     * again themselves; a login with an email keeps its website access.
     */
    public function destroy(Member $member): RedirectResponse
    {
        $user = $member->user;

        if (! $user || $user->login_phone === null) {
            return back()->with('error', self::MSG_NO_APP_LOGIN);
        }

        if ($user->isStaff()) {
            return back()->with('error', self::MSG_STAFF);   // removing is allowed for a deactivated login
        }

        if ($user->email === null) {
            $user->delete();   // the User hook deletes the tokens first

            return back()->with('success', 'App login removed. The member can activate the app again themselves.');
        }

        DB::transaction(function () use ($user) {
            $user->login_phone = null;
            $user->must_change_password = false;
            $user->save();
            $user->tokens()->delete();   // changing login_phone alone doesn't trigger the User hook
        });

        return back()->with('success', 'App login removed. Their website login (email) still works.');
    }

    private function refusal(User $user): ?string
    {
        return match (true) {
            $user->isStaff()   => self::MSG_STAFF,
            ! $user->is_active => self::MSG_INACTIVE,
            default            => null,
        };
    }

    /** Shown once on the member page. Never logged. */
    private function details(string $e164, string $password): array
    {
        return ['phone' => PhoneNumber::display($e164), 'password' => $password];
    }
}
