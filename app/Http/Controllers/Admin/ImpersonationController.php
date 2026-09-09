<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class ImpersonationController extends Controller
{
    /**
     * Login as a user (impersonation)
     */
    public function loginAs(Request $request, $userId)
    {
        // Only allow admins to impersonate
        if (!Auth::user()->isAdmin()) {
            abort(403, 'Only administrators can impersonate users.');
        }

        $targetUser = User::withoutGlobalScopes()->findOrFail($userId);

        // Don't allow impersonating another admin
        if ($targetUser->isAdmin()) {
            return redirect()->back()->with('error', 'Cannot impersonate another administrator.');
        }

        // Store the original admin user ID in session
        Session::put('impersonating', true);
        Session::put('original_user_id', Auth::id());

        // Log in as the target user
        Auth::login($targetUser);

        return redirect()->route('dashboard')->with('success', "You are now logged in as {$targetUser->name}");
    }

    /**
     * Stop impersonation and return to admin account
     */
    public function stopImpersonating()
    {
        if (!Session::has('impersonating')) {
            return redirect()->route('dashboard');
        }

        $originalUserId = Session::get('original_user_id');
        $originalUser = User::withoutGlobalScopes()->findOrFail($originalUserId);

        // Clear impersonation session
        Session::forget('impersonating');
        Session::forget('original_user_id');

        // Log back in as the original admin
        Auth::login($originalUser);

        return redirect()->route('admin.dashboard')->with('success', 'Returned to your admin account');
    }
}
