<?php

namespace App\Controller;

use App\DataModel\DataModelPartner;
use App\DataModel\DataModelVacancy;
use App\Service\Policy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CareerController extends AbstractController
{
    #[Route('/career', name: 'career', methods: ['GET'])]
    public function career(
        DataModelPartner $partnerModel,
        DataModelVacancy $vacancyModel,
        Policy $policy
    ): Response
    {
        $partners = $partnerModel->find(['has_profile_visible' => 1]);

        // Apply policy
        $partners = array_filter($partners, [$policy, 'userCanRead']);

        usort($partners, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $this->render('career/career.html.twig', [
            'partners' => $partners,
            'vacancy_partners' => $vacancyModel->partners(), // Not all partners have vacancies
        ]);
    }
}
