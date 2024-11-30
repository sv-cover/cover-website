<?php

namespace App\Controller;

require_once 'src/Model/DataModelCommissie.php'; // TODO SFY: namespaces!

use App\Exception\UnauthorizedException;
use App\Service\Authentication;
use App\Service\Database;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class CommitteeMembersController extends AbstractController
{
    #[Route('/committee_members', name: 'committee_members', methods: ['GET'])]
    public function list(
        Authentication $auth,
        Database $db,
        #[MapQueryParameter] ?string $type = null
    ): Response
    {
        if (!$auth->getIdentity()->member_in_committee(COMMISSIE_BESTUUR)
            && !$auth->getIdentity()->member_in_committee(COMMISSIE_KANDIBESTUUR))
            throw new UnauthorizedException();

        $type_id = in_array($type, \DataModelCommissie::TYPE_OPTIONS)
            ? array_search($type, \DataModelCommissie::TYPE_OPTIONS)
            : null;

        return $this->render('committee_members/list.html.twig', [
            'iters' => $db->getModel('DataModelCommitteeMembers')->get_active_members($type_id),
            'type' => $type,
        ]);
    }
}
