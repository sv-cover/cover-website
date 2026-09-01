<?php

namespace App\Controller;

use App\DataModel\DataModelCommissie;
use App\Exception\UnauthorizedException;
use App\Legacy\Authentication\Authentication;
use App\Legacy\Policy\Policy;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;

class CommitteesController extends AbstractController
{
    public function __construct(
        private DataModelCommissie $model,
        private Policy $policy,
    ) {
    }

    #[Route('/committees', name: 'committees.list', methods: ['GET'])]
    public function list(): Response
    {
        $committees = $this->model->get(DataModelCommissie::TYPE_COMMITTEE);
        $working_groups = $this->model->get(DataModelCommissie::TYPE_WORKING_GROUP);

        return $this->render('committees/list.html.twig', [
            'committees' => array_filter($committees, [$this->policy, 'userCanRead']),
            'working_groups' => array_filter($working_groups, [$this->policy, 'userCanRead']),
        ]);
    }

    #[Route('/committees/slide/{slug}', name: 'committees.slide', methods: ['GET'])]
    public function slide(?string $slug = null): Response
    {
        if (isset($slug))
            $committee = $this->model->find_one(['login' => $slug]);
        else
            // Pick a random committee
            $committee = $this->model->get_random(DataModelCommissie::TYPE_COMMITTEE, true);

        return $this->render('committees/slide.html.twig', ['committee' => $committee]);
    }

	#[Route('/committees/{slug}', name: 'committees.single', methods: ['GET'], priority: -1)]
	public function single(string $slug): Response
	{
		$iter = $this->model->find_one(['login' => $slug]);
		$newUrl = $iter->get_url();

		return $this->redirect($newUrl);
	}

    #[Route('/committees/{slug}/interest', name: 'committees.interest', methods: ['POST'])]
    public function interest(Authentication $auth, MailerInterface $mailer, Request $request, string $slug): RedirectResponse
    {
        if (!$auth->getIdentity()->is_member())
            throw new UnauthorizedException('Only members can apply for a committee.');

        $iter = $this->model->find_one(['login' => $slug]);

        if (!$this->policy->userCanRead($iter))
            throw new UnauthorizedException('You are not allowed to see this committee.');

        $form = $this->createFormBuilder($iter, ['csrf_token_id' => 'committee_interest_' . $iter['id']])
            ->add('submit', SubmitType::class, ['label' => __('Delete'), 'color' => 'danger'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $member = $auth->getIdentity()->member();

            if ($this->getParameter('app.committee_interest_log'))
                error_log(sprintf(
                    "%s - %s (%d) is interested in %s.\n",
                    date('c'),
                    $member['full_name'],
                    $member['id'],
                    $iter['naam']
                ), 3, $this->getParameter('app.committee_interest_log'));

            $email = (new TemplatedEmail())
                ->to('intern@svcover.nl')
                ->cc($member['email'])
                ->replyTo($member['email'])
                ->subject("{$member['voornaam']} is interested in {$iter['naam']}")
                ->htmlTemplate('emails/committee_interest.html.twig')
                ->context([
                    'committee' => $iter,
                    'member' => $member,
                ])
            ;
            $mailer->send($email);

            $this->addFlash('committee_interest', __('Cool! We’ve notified the Commissioner of Internal Affairs for you!'));
        }

		$typeId = array_search($iter['type'], DataModelCommissie::TYPE_OPTIONS, strict: true);

		return $this->redirectToRoute('groups.single', ['slug' => $iter['login'], 'type' => $typeId]);
    }
}
