<?php

namespace App\Controller;

use App\DataModel\DataModelCommissie;
use App\Exception\UnauthorizedException;
use App\Form\CommitteeType;
use App\Form\DataTransformer\IntToBooleanTransformer;
use App\Form\Type\CommitteeIdType;
use App\Form\Type\CalendarType;
use App\Legacy\Authentication\Authentication;
use App\Legacy\Policy\Policy;
use App\SignUp\Fields\ChoiceField;
use App\SignUp\Fields\PhoneField;
use phpDocumentor\Reflection\PseudoTypes\True_;
use PhpParser\Node\Name;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber as AssertPhoneNumber;

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


    #[Route('/committees/join/{mode}', name: 'committees.join', defaults: ['mode' => 'join'], methods: ['GET', 'POST'])]
    public function joins(
        Authentication $auth,
        MailerInterface $mailer,
        Request $request,
        string $mode,
    ): Response
    {
        if (!$auth->getIdentity()->is_member())
            throw new UnauthorizedException();

        $member = $auth->identity->member();

        $data = [
            'name' => $member['full_name'],
            'email' => $member['email'],
            'phone' => $member['telefoonnummer'],
        ];

        if ($mode == 'join')
        {
            $form = $this->createFormBuilder($data)
                ->add('name', TextType::class, [
                    'label' => __('Name'),
                    'required' => true,
                ])
                ->add('email', TextType::class, [
                    'label' => __('Email'),
                    'required' => true,
                    'constraints' => [
                        new Assert\NotBlank(),
                        new Assert\Email(),
                    ]  
                ])
                ->add('phone', TelType::class, [
                    'label'=> __('Phone number'),
                    'required'=> false,
                    'constraints' => [
                        new AssertPhoneNumber(defaultRegion: 'NL'),
                    ]
                ])
                ->add('committee', CommitteeIdType::class, [
                    'required' => true,
                    'show_all' => true,
                    'show_own' => false,
                    'multiple' => true,
                    'expanded' => true,
                    'chips' => true,
                    'show_all_types' => false,
                    'label' => __('Which committee(s) do you want to plan an interview for?'),
                ])
                ->add('calendar', CalendarType::class, [
                    'label' => __('What is your availability for an interview in the upcoming week'),
                    'required' => true,
                    'multiple' => true,
                    'expanded' => true,
                    'chips' => true
                ])
                ->add('submit', SubmitType::class)
                ->getForm();
                $form->handleRequest($request);


        } else if ($mode == 'interest')
        {

            $form = $this->createFormBuilder($data,)
                ->add('name', TextType::class, [
                    'label' => __('Name'),
                    'required' => true,
                ])
                ->add('email', TextType::class, [
                    'label' => __('Email'),
                    'required' => true,
                    'constraints' => [
                        new Assert\NotBlank(),
                        new Assert\Email(),
                    ]  
                ])
                ->add('phone', TelType::class, [
                    'label'=> __('Phone number'),
                    'required'=> false,
                    'constraints' => [
                        new AssertPhoneNumber(defaultRegion: 'NL'),
                    ]
                ])
                ->add('committee', CommitteeIdType::class, [
                    'required' => true,
                    'show_all' => true,
                    'show_own' => false,
                    'multiple' => true,
                    'expanded' => true,
                    'chips' => true,
                    'show_all_types' => false,
                    'label' => __('Which committee(s) do you have questions about?'),
                ])
                ->add('questions', TextareaType::class, [
                    'label'=> __('Ask your questions here'),
                    'required' => false,
                ])
                ->add('submit', SubmitType::class)
                ->getForm();
            
            $form->handleRequest($request);
        }

        if ($form->isSubmitted() && $form->isValid())
        {
            if ($mode == 'join')
            {

                $committeeChoices = "";
                foreach ($form['committee']->getData() as $committee)
                {
                    $committeeChoices .= $this->model->get_naam($committee) . ', ';
                }

                $calendarTimes = "";
                foreach ($form['calendar']->getData() as $time)
                {
                    $calendarTimes .= $time . ', ';
                }

                $email = (new TemplatedEmail())
                    ->to($form->get('email')->getData())
                    ->subject("{$form->get('name')->getData()} wants to join one or more committees")
                    ->htmlTemplate('emails/committee_join.html.twig')
                    ->context([
                        'member' => $member,
                        'committees' => $committeeChoices,
                        'calendarTimes' => $calendarTimes,
                    ])
                ;
            } else if ($mode == 'interest')
            {
                $committeeChoices = "";
                foreach ($form['committee']->getData() as $committee)
                {
                    $committeeChoices .= $this->model->get_naam($committee) . ', ';
                }

                
                $email = (new TemplatedEmail())
                    ->to($form->get('email')->getData())
                    ->subject("{$form->get('name')->getData()} wants more information about committees")
                    ->htmlTemplate('emails/committee_interest_form.html.twig')
                    ->context([
                        'member' => $member,
                        'committees' => $committeeChoices,
                        'questions' => $form['questions']->getData(),
                    ])
                ;
            } 
            
            
            $mailer->send($email);

            $this->addFlash('Success', __('The intern has been notified'));
        }


        return $this->render('committees/joinform.html.twig', [
            'activeMode' => $mode,
            'form'=> $form,
        ]);

    }


    /**
     * The Thrash! All (including deleted) committees/groups/others/etc
     */
    #[Route('/committees/archive', name: 'committees.archive', methods: ['GET'])]
    public function archive(): Response
    {
        // If you can't create a committee, you won't need the archive either.
        if (!$this->policy->userCanCreate('DataModelCommissie'))
            throw new UnauthorizedException('You are not allowed to view the committee archive.');

        $iters = $this->model->get(null, true);

        return $this->render('committees/archive.html.twig', ['iters' => $iters]);
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
                ->htmlTemplate(template: 'emails/committee_interest.html.twig')
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
