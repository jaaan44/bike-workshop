<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     *
     * Self-service deletion is intentionally unavailable for customers in
     * this release: bicycles.user_id/bookings.user_id both cascadeOnDelete,
     * so deleting a customer would silently, irreversibly destroy the
     * workshop's own operational/warranty records for every bicycle and
     * repair they ever had (Phase 10A finding). Preserving that history
     * safely (e.g. anonymizing the account instead of deleting it) is a
     * product decision beyond V1 scope, so the smallest safe fix for now is
     * to not offer the destructive path at all — see docs/PROJECT_STATUS.md.
     *
     * Staff/technician deletion remains available, but is blocked (with a
     * clear validation error rather than a raw DB failure) for any account
     * that has authored technician notes: technician_notes.user_id has no
     * nullOnDelete/cascadeOnDelete, unlike every other author-type FK in the
     * schema, so deleting such an account would otherwise throw an uncaught
     * QueryException (Phase 10A finding).
     *
     * Both checks are enforced here, not just hidden in the UI, so a
     * crafted request can't bypass either restriction.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->isCustomer()) {
            return Redirect::route('profile.edit')->withErrors([
                'account' => 'Account deletion isn\'t available for customer accounts in this release. Contact the workshop if you\'d like your account closed.',
            ], 'userDeletion');
        }

        if ($user->technicianNotes()->exists()) {
            return Redirect::route('profile.edit')->withErrors([
                'account' => 'This account can\'t be deleted because it has technician notes on file, which are kept as part of the repair history. Contact an administrator for help.',
            ], 'userDeletion');
        }

        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
