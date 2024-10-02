<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Requests;

class RequestPolicy
{
    public function create(User $user)
    {
        return in_array($user->user_type, ['Administrator', 'DeanHead']);
    }

    public function update(User $user, Requests $request)
    {
        return in_array($user->user_type, ['Administrator']);
    }

    public function delete(User $user, Requests $request)
    {
        return $user->user_type === 'Administrator';
    }

    public function viewAny(User $user)
    {
        return in_array($user->user_type, ['Administrator', 'Supervisor', 'TeamLeader', 'Controller', 'DeanHead']);
    }

    public function view(User $user, Requests $request)
    {
        return in_array($user->user_type, ['Administrator', 'Supervisor', 'TeamLeader', 'Controller', 'DeanHead']);
    }
}
