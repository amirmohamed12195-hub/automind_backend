<?php

namespace App\Policies;

use App\Models\DiagnosticSession;
use App\Models\User;

class DiagnosticSessionPolicy
{
    public function view(User $user, DiagnosticSession $session): bool
    {
        return $session->user_id === $user->id;
    }

    public function update(User $user, DiagnosticSession $session): bool
    {
        return $session->user_id === $user->id;
    }

    public function delete(User $user, DiagnosticSession $session): bool
    {
        return $session->user_id === $user->id;
    }
}
