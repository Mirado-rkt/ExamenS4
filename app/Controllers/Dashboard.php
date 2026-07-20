<?php

namespace App\Controllers;

use App\Libraries\MobileMoneyRepository;

class Dashboard extends BaseController
{
    private MobileMoneyRepository $repository;

    public function __construct()
    {
        $this->repository = new MobileMoneyRepository();
    }

    public function index()
    {
        $clientId = (int) session()->get('client_id');

        if ($clientId <= 0) {
            return redirect()->to(site_url('login'));
        }

        $client = $this->repository->getClientSummary($clientId);

        if ($client === null) {
            session()->destroy();

            return redirect()->to(site_url('login'))->with('error', 'Compte introuvable.');
        }

        return view('dashboard', [
            'title' => 'Tableau de bord client',
            'client' => $client,
            'history' => $this->repository->getClientHistory($clientId),
            'feeBands' => $this->repository->getFeeBands((int) $client['operateur_id']),
        ]);
    }

    public function deposit()
    {
        return $this->handleClientOperation('deposit');
    }

    public function withdraw()
    {
        return $this->handleClientOperation('withdraw');
    }

    public function transfer()
    {
        $clientId = (int) session()->get('client_id');

        if ($clientId <= 0) {
            return redirect()->to(site_url('login'));
        }

        $amount = (float) $this->request->getPost('amount');
        $recipientPhone = (string) $this->request->getPost('recipient_phone');
        $includeWithdrawal = (bool) $this->request->getPost('include_withdrawal_fees');

        try {
            $result = $this->repository->transfer($clientId, $recipientPhone, $amount, $includeWithdrawal);

            return redirect()->to(site_url('dashboard'))->with('success', sprintf(
                'Transfert effectué vers %s. Frais: %s Ar, solde restant: %s Ar.',
                $result['recipient_phone'],
                $this->formatMoney($result['fee']),
                $this->formatMoney($result['balance_after'])
            ));
        } catch (\Throwable $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }
    }

    public function transferMultiple()
    {
        $clientId = (int) session()->get('client_id');

        if ($clientId <= 0) {
            return redirect()->to(site_url('login'));
        }

        $phonesRaw = (string) $this->request->getPost('recipient_phones');
        $totalAmount = (float) $this->request->getPost('total_amount');

        $phones = array_filter(array_map('trim', preg_split('/[\n,;]+/', $phonesRaw)));

        try {
            $result = $this->repository->transferMultiple($clientId, $phones, $totalAmount);

            return redirect()->to(site_url('dashboard'))->with('success', sprintf(
                'Transfert multiple effectué. Solde restant: %s Ar.',
                $this->formatMoney($result['balance_after'])
            ));
        } catch (\Throwable $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }
    }

    private function handleClientOperation(string $operation): \CodeIgniter\HTTP\RedirectResponse
    {
        $clientId = (int) session()->get('client_id');

        if ($clientId <= 0) {
            return redirect()->to(site_url('login'));
        }

        $amount = (float) $this->request->getPost('amount');

        try {
            if ($operation === 'deposit') {
                $result = $this->repository->deposit($clientId, $amount);

                return redirect()->to(site_url('dashboard'))->with('success', sprintf(
                    'Dépôt validé. Nouveau solde: %s Ar.',
                    $this->formatMoney($result['balance_after'])
                ));
            }

            $result = $this->repository->withdraw($clientId, $amount);

            return redirect()->to(site_url('dashboard'))->with('success', sprintf(
                'Retrait validé. Frais: %s Ar, solde restant: %s Ar.',
                $this->formatMoney($result['fee']),
                $this->formatMoney($result['balance_after'])
            ));
        } catch (\Throwable $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 0, ',', ' ');
    }
}