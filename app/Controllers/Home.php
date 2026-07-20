<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        if (session()->get('client_id')) {
            return redirect()->to(site_url('dashboard'));
        }

        return redirect()->to(site_url('login'));
    }
}
