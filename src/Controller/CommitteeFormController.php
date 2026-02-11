<?php

namespace App\Controller;

use App\DataModel\DataModelCommissie;
use App\DataModel\DataModelCommitteeMembers;
use App\Exception\UnauthorizedException;
use App\Legacy\Authentication\Authentication;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class CommitteeFormController extends AbstractController
{
    #[Route('/committee_join', name: 'committee_join', methods: ['GET'])]
    public function list(
        Authentication $auth,
        DataModelCommitteeMembers $model,
    ): Response
    {
        return $this->render('committees/joinform.html.twig');
    }
}
