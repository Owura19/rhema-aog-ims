<?php

namespace App\Http\Controllers\Api\V1\Auth\Concerns;

use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait ValidatesPhone
{
    /**
     * The request's phone, normalised to +233XXXXXXXXX. An unreadable number is a
     * 422 on "phone" — a number's FORMAT isn't secret, whether it's a member is.
     */
    protected function validatedPhone(Request $request): string
    {
        $request->validate(['phone' => ['required', 'string', 'max:30']]);

        $phone = PhoneNumber::normalise($request->input('phone'));

        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid Ghana phone number.']);
        }

        return $phone;
    }
}
