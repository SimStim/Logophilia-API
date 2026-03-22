<?php

namespace App\Controllers;

class UserController
{
    public function index()
    {
        return [
            'users' => [
                ['id' => 1, 'username' => 'user1'],
                ['id' => 2, 'username' => 'user2']
            ]
        ];
    }
}
