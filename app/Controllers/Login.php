<?php

namespace App\Controllers;

use App\Libraries\MobileMoneyRepository;

class Login extends BaseController
{
    private MobileMoneyRepository $repository;

    public function __construct()
    {
        $this->repository = new MobileMoneyRepository();
    }

    public function index()
    {
        if (session()->get('client_id')) {
            return redirect()->to(site_url('dashboard'));
        }

        return view('login', [
            'title' => 'Connexion mobile money',
        ]);
    }

    public function authenticate()
    {
        $phone = (string) $this->request->getPost('phone');

        try {
            $client = $this->repository->createOrGetClientByPhone($phone);

            session()->set([
                'client_id' => (int) $client['id'],
                'client_phone' => $client['telephone'],
                'client_solde' => (float) $client['solde'],
                'operator_id' => (int) $client['operateur_id'],
                'operator_code' => $client['operateur_code'],
                'operator_name' => $client['operateur_nom'],
            ]);

            return redirect()->to(site_url('dashboard'))->with('success', 'Connexion automatique réussie.');
        } catch (\Throwable $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }
    }

    public function logout()
    {
        session()->destroy();

        return redirect()->to(site_url('login'))->with('success', 'Déconnexion effectuée.');
    }
}