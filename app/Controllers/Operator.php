<?php

namespace App\Controllers;

use App\Libraries\MobileMoneyRepository;

class Operator extends BaseController
{
    private MobileMoneyRepository $repository;

    public function __construct()
    {
        $this->repository = new MobileMoneyRepository();
    }

    public function index()
    {
        return view('operator', [
            'title' => 'Vue opérateur',
            'operators' => $this->repository->getOperators(),
            'prefixes' => $this->repository->getOperatorPrefixes(),
            'types' => $this->repository->getTypesOperation(),
            'feeBands' => $this->repository->getFeeBands(),
            'gains' => $this->repository->getOperatorGains(),
            'clients' => $this->repository->getClientSituation(),
            'commissions' => $this->repository->getCommissionsInterOperators(),
            'montants_a_envoyer' => $this->repository->getMontantsAEnvoyer(),
        ]);
    }

    public function addPrefix()
    {
        try {
            $operatorId = (int) $this->request->getPost('operateur_id');
            $prefix = (string) $this->request->getPost('prefixe');

            $this->repository->addPrefix($operatorId, $prefix);

            return redirect()->to(site_url('operateur'))->with('success', 'Préfixe ajouté.');
        } catch (\Throwable $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
    }

    public function updateFeeBand()
    {
        try {
            $bandId = (int) $this->request->getPost('band_id');
            $frais = (float) $this->request->getPost('frais');

            $this->repository->updateFeeBand($bandId, $frais);

            return redirect()->to(site_url('operateur'))->with('success', 'Barème mis à jour.');
        } catch (\Throwable $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
    }

    public function setCommission()
    {
        try {
            $operateurId = (int) $this->request->getPost('operateur_id');
            $pourcentage = (float) $this->request->getPost('pourcentage');

            $this->repository->setCommissionInterOperateur($operateurId, $pourcentage);

            return redirect()->to(site_url('operateur'))->with('success', 'Commission mise à jour.');
        } catch (\Throwable $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
    }

    public function deleteCommission()
    {
        try {
            $operateurId = (int) $this->request->getPost('operateur_id');

            $this->repository->deleteCommissionInterOperateur($operateurId);

            return redirect()->to(site_url('operateur'))->with('success', 'Commission supprimée.');
        } catch (\Throwable $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
    }
}