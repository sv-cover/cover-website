<?php

namespace App\Controller;

use App\Utils\SearchUtils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(
        SearchUtils $utils,
        #[MapQueryParameter] ?string $query = null,
    ): Response
    {
        $resultset = null;

        if (!empty($query))
            $resultset = $utils->search($query);

        return $this->render('search/results.html.twig', [
            'query' => $query,
            'resultset' => $resultset
        ]);
    }
}
