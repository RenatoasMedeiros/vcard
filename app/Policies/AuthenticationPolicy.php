<?php

namespace App\Policies;

use App\Models\Authentication;

class AuthenticationPolicy
{
    
    public function view(Authentication $user, Authentication $model)
    {
        return $user->user_type == "A" || $user->id == $model->id;
    }

    public function update(Authentication $user, Authentication $model)
    {
        return $user->user_type == "A" || $user->id == $model->id;
    }

    public function updatePassword(Authentication $user, Authentication $model)
    {
        return $user->id == $model->id;
    }
}