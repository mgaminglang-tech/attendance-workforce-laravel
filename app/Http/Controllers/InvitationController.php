<?php

namespace App\Http\Controllers;

use App\Actions\Employees\AcceptEmployeeInvitation;
use App\Enums\AccountStatus;
use App\Http\Requests\Invitation\AcceptInvitationRequest;
use App\Models\EmployeeInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function show(string $token): View
    {
        $invitation = $this->validInvitation($token);

        return view('invitations.accept', [
            'token' => $token,
            'user' => $invitation->user,
        ]);
    }

    public function accept(
        AcceptInvitationRequest $request,
        string $token,
        AcceptEmployeeInvitation $acceptEmployeeInvitation,
    ): RedirectResponse {
        $acceptEmployeeInvitation->handle($token, $request->string('password')->toString());

        return redirect()
            ->route('login')
            ->with('status', 'Your account is active. You may now sign in.');
    }

    private function validInvitation(string $token): EmployeeInvitation
    {
        return EmployeeInvitation::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->whereHas('user', fn ($query) => $query->where('account_status', AccountStatus::Pending->value))
            ->firstOrFail();
    }
}
