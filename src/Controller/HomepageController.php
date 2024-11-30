<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomepageController extends AbstractController
{
    #[Route('/', name: 'homepage', methods: ['GET'])]
    public function homepage(): Response
    {
        return $this->render('homepage/homepage.html.twig');
    }
}
