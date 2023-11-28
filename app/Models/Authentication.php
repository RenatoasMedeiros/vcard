<?php

// app\Models\Authentication.php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class Authentication extends Model implements Authenticatable
{
    use Notifiable, HasApiTokens;
    protected $table = 'view_auth_users'; // Set the table name for the \view

    // protected $primaryKey = 'id'; // Set the primary key for the view

    // Define the fillable columns if needed
    protected $fillable = [
        'id', 
        'user_type', 
        'username', 
        'password', 
        'name', 
        'email', 
        'blocked', 
        'confirmation_code', 
        #'photo_url', 
        #'deleted_at'
    ];

    protected $hidden = [
        'password',
    ];

    public function getAuthIdentifierName(){
        return 'id';
    }

    public function getAuthIdentifier() {
        return $this->getAttribute('id');
    }
    
    public function getAuthPassword() {
        return $this->getAttribute('password');
    }

    public function getRememberToken() { //implement if needed
    }
    public function setRememberToken($value) { //implement if needed
    }
    public function getRememberTokenName() { //implement if needed
    }

    public function findForPassport($username) {
        return $this->where('id', $username)->first();
    }

    
}