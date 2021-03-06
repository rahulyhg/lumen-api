<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;

class User extends Model implements
    AuthenticatableContract,
    AuthorizableContract
{


    use Authenticatable, Authorizable;

    protected $table = 'user';
    public $timestamps = true;

    protected $primaryKey = 'person_id'; // or null

    public $incrementing = false;

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function getPerson()
    {
        return $this->hasOne('App\Person','id','person_id');
    }

    public function getPartner()
    {
        return $this->hasOne('App\Partner','user_id','person_id');
    }

    public function getChildren()
    {
        return $this->hasMany('App\Child','user_id','person_id');
    }

    public function getUserFiles()
    {
        $userFiles = $this->hasMany('App\UserFile','person_id')->get();

        return $userFiles;
    }

    public function getEmployeeFiles()
    {
        $employeeFiles = array();
        $useryears = $this->hasMany('App\UserYear','person_id')->get();

        foreach ($useryears as $useryear) {
            array_push($employeeFiles,$useryear->getEmployeeFiles()->get());
        }

        return $employeeFiles;
    }
    public function getUserQuestions()
    {
        return $this->hasOne('App\Partner','person_id','partner_id');
    }


}