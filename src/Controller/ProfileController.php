<?php

namespace App\Controller;

use App\Exception\UnauthorizedException;
use App\Form\PasswordType;
use App\Form\ProfilePictureType;
use App\Service\Authentication;
use App\Service\Database;
use App\Service\Incassomatic;
use App\Service\Kast;
use App\Service\Policy;
use App\Service\Secretary;
use JeroenDesloovere\VCard\VCard;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber as AssertPhoneNumber;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ProfileController extends AbstractController
{
    private \DataModelMember $model;

    public function __construct(
        private Authentication $auth,
        private Database $db,
        private Policy $policy,
    ){
        $this->model = $db->getModel('DataModelMember');
    }

    private function getPersonalForm(\DataIterMember $iter)
    {
        $form = $this->createFormBuilder($iter)
            ->add('adres', TextType::class, [
                'label' => __('Address'),
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['max' => 255]),
                ],
            ])
            ->add('postcode', TextType::class, [
                'label' => __('Postal code'),
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['max' => 7]),
                ],
            ])
            ->add('woonplaats', TextType::class, [
                'label' => __('Town'),
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['max' => 255]),
                ],
            ])
            ->add('telefoonnummer', TelType::class, [
                'label' => __('Phone'),
                'constraints' => [
                    new Assert\NotBlank(),
                    new AssertPhoneNumber(defaultRegion: 'NL'),
                    new Assert\Length(['max' => 20]),
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => __('Email'),
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Email(),
                    new Assert\Length(['max' => 255]),
                ],
                'setter' => function (\DataIterMember &$member, string $value, FormInterface $form) {
                    // Prevent normal flow by doing nothing. Email requires special treatment.
                },
            ])
            ->add('iban', TextType::class, [
                'label' => __('IBAN'),
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Iban(),
                ],
            ])
            ->add('bic', TextType::class, [
                'label' => __('BIC'),
                'required' => false,
                'constraints' => [
                    new Assert\Bic(),
                ],
                'help' => __("BIC is required if your IBAN does not start with 'NL'"), // This is never validated for better UX. Treasurer can always look it up.
            ])
            ->add('submit', SubmitType::class, ['label' => __('Save')])
            ->getForm();
        return $form;
    }

    private function getProfileForm(\DataIterMember $iter, FormFactoryInterface $formFactory)
    {
        $form = $formFactory->createNamedBuilder('profile', FormType::class, $iter)
            ->add('nick', TextType::class, [
                'label' => __('Nickname'),
                'required' => false,
                'constraints' => [
                    new Assert\Length(['max' => 50]),
                ]
            ])
            ->add('avatar', UrlType::class, [
                'label' => __('Avatar'),
                'required' => false,
                'default_protocol' => null, // if not, it renders as text type…
                'constraints' => [
                    new Assert\Url(),
                    new Assert\Length(['max' => 100]),
                ],
                'attr' => [
                    'placeholder' => 'https://',
                ],
            ])
            ->add('homepage', UrlType::class, [
                'label' => __('Website'),
                'required' => false,
                'default_protocol' => null, // if not, it renders as text type…
                'constraints' => [
                    new Assert\Url(),
                    new Assert\Length(['max' => 255]),
                ],
                'attr' => [
                    'placeholder' => 'https://',
                ],
            ])
            ->add('submit', SubmitType::class, ['label' => __('Save')])
            ->getForm();
        return $form;
    }

    private function getPrivacyForm(\DataIterMember $iter)
    {
        // TODO: These should really be stored in the database
        $labels = [
            'naam' => __('Name'),
            'geboortedatum' => __('Birthdate'),
            'beginjaar' => __('Starting year'),
            'adres' => __('Address'),
            'postcode' => __('Postal code'),
            'woonplaats' => __('Town'),
            'email' => __('Email'),
            'telefoonnummer' => __('Phone'),
            'foto' => __('Photo'),
        ];

        $data = [];
        $builder = $this->createFormBuilder();

        foreach ($this->model->get_privacy() as $field => $nr)
            $builder->add($field, ChoiceType::class, [
                'label' => $labels[$field] ?? $field,
                'choices'  => [
                    __('Everyone') => \DataModelMember::VISIBLE_TO_EVERYONE,
                    __('Members') => \DataModelMember::VISIBLE_TO_MEMBERS,
                    __('Nobody') => \DataModelMember::VISIBLE_TO_NONE,
                ],
                'expanded' => true,
                'chips' => true,
                'data' => ($iter['privacy'] >> ($nr * 3)) & 7, // Not ideal, but neater than constructing something to pass to createFormBuilder
            ]);

        $builder->add('submit', SubmitType::class);
        return $builder->getForm();
    }

    private function updateMember(Secretary $secretary, \DataIterMember $iter)
    {
        // Inform the board that member info has been changed.
        $subject = "Member details updated";
        $body = sprintf("%s updated their member details:", member_full_name($iter, IGNORE_PRIVACY)) . "\n\n";

        foreach ($iter->secretary_changed_values() as $field => $value)
            $body .= sprintf("%s:\t%s\n", $field, $value ?? "<deleted>");

        mail('administratie@svcover.nl', $subject, $body, "From: Study Association Cover <noreply@svcover.nl>\r\nContent-Type: text/plain; charset=UTF-8");
        mail('secretaris@svcover.nl', $subject, sprintf("%s updated their member details:\n\nYou can see the changes in sectary or in the administratie@svcover.nl mailbox", member_full_name($iter, IGNORE_PRIVACY)), "From: Study Association Cover <noreply@svcover.nl>\r\nContent-Type: text/plain; charset=UTF-8");

        try {
            $secretary->updatePersonFromIterChanges($iter);
        } catch (\RuntimeException $e) {
            // Todo: replace this with a serious more general logging call
            error_log($e, 1, 'webcie@rug.nl', "From: webcie-cover-php@svcover.nl");
        }
    }

    #[Route('/profile', name: 'profile', methods: ['GET'])]
    #[Route('/profile/{member_id<\d+>}', name: 'profile.member', methods: ['GET'])]
    #[Route('/profile/{member_id<\d+>}/public', name: 'profile.public', methods: ['GET'])]
    public function publicTab(?int $member_id = null): Response
    {
        if (isset($member_id))
            $iter = $this->model->get_iter($member_id);
        else
            $iter = $this->auth->getIdentity()->member();

        if (!isset($iter))
            throw new UnauthorizedException('Log in to view your profile.');

        if (!$this->policy->userCanRead($iter))
            throw new UnauthorizedException('This person is no longer a Cover member and therefore has no public profile.');

        $committees = $this->db->getModel('DataModelCommissie')->get_for_member($iter);

        return $this->render('profile/public_tab.html.twig', [
            'iter' => $iter,
            'committees' => $committees,
        ]);
    }

    #[Route('/profile/personal', methods: ['GET'])]
    #[Route('/profile/{member_id<\d+>}/personal', name: 'profile.personal', methods: ['GET', 'POST'])]
    public function personalTab(Request $request, Secretary $secretary, UriSigner $uriSigner, ?int $member_id = null): Response
    {
        if (isset($member_id))
            $iter = $this->model->get_iter($member_id);
        else
            $iter = $this->auth->getIdentity()->member();

        if (!$this->policy->userCanUpdate($iter))
            throw new UnauthorizedException();

        $form = $this->getPersonalForm($iter);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $updates = [];
            if ($iter->has_secretary_changes()) {
                $updates[] = 'other';
                $this->model->update($iter);
                $this->updateMember($secretary, $iter);
            }

            // If the email address has changed, add a confirmation.
            if ($form['email']->getData() != $iter['email']) {
                $updates[] = 'email';
                $token = $this->db->getModel('DataModelEmailConfirmationToken')->create_token($iter, $form['email']->getData());

                $url = $this->generateUrl('profile.confirm_email', ['token' => $token['key']], UrlGeneratorInterface::ABSOLUTE_URL);
                $signed_url = $uriSigner->sign($url, new \DateInterval('PT24H')); // Valid for 24 hours

                // Send the confirmation to the new email address
                \parse_email_object("profile_confirm_email.txt", [
                    'naam' => \member_first_name($iter, \IGNORE_PRIVACY),
                    'email' => $token['email'],
                    'link' => $signed_url,
                ])->send($token['email']);
            }

            return $this->render('profile/personal_tab_success.html.twig', [
                'iter' => $iter,
                'updates' => $updates,
            ]);
        }

        return $this->render('profile/personal_tab.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/profile/profile', methods: ['GET'])]
    #[Route('/profile/{member_id<\d+>}/profile', name: 'profile.profile', methods: ['GET', 'POST'])]
    public function profileTab(Request $request, FormFactoryInterface $formFactory, ?int $member_id = null): Response|RedirectResponse
    {
        if (isset($member_id))
            $iter = $this->model->get_iter($member_id);
        else
            $iter = $this->auth->getIdentity()->member();

        if (!$this->policy->userCanUpdate($iter))
            throw new UnauthorizedException();

        $profileForm = $this->getProfileForm($iter, $formFactory);
        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $this->model->update($iter);
            return $this->redirectToRoute('profile.profile', ['member_id' => $iter['id']]);
        }

        $passwordForm = $this->createForm(PasswordType::class, null, [
            'confirm_current' => true,
            'mapped' => false,
        ]);
        $passwordForm->handleRequest($request);

        $photoForm = $this->createForm(ProfilePictureType::class, null, ['mapped' => false]);
        $photoForm->handleRequest($request);

        return $this->render('profile/profile_tab.html.twig', [
            'iter' => $iter,
            'profile_form' => $profileForm->createView(),
            'photo_form' => $photoForm->createView(),
            'password_form' => $passwordForm->createView(),
        ]);
    }

    #[Route('/profile/privacy', methods: ['GET'])]
    #[Route('/profile/{member_id<\d+>}/privacy', name: 'profile.privacy', methods: ['GET', 'POST'])]
    public function privacyTab(Request $request, ?int $member_id = null): Response|RedirectResponse
    {
        if (isset($member_id))
            $iter = $this->model->get_iter($member_id);
        else
            $iter = $this->auth->getIdentity()->member();

        if (!$this->policy->userCanUpdate($iter))
            throw new UnauthorizedException();

        $form = $this->getPrivacyForm($iter);
        $form->handleRequest($request);

        // Handle submission
        if ($form->isSubmitted() && $form->isValid()) {
            // Build privacy mask
            $mask = 0;
            foreach ($this->model->get_privacy() as $field => $nr) {
                $value = $form[$field]->getData();
                $mask = $mask + ($value << ($nr * 3));
            }

            // Update settings
            $iter->set('privacy', $mask);
            $this->model->update($iter);

            return $this->redirectToRoute('profile.privacy', ['member_id' => $iter['id']]);
        }

        return $this->render('profile/privacy_tab.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/profile/mailing_lists', methods: ['GET'])]
    #[Route('/profile/{member_id<\d+>}/mailing_lists', name: 'profile.mailing_lists', methods: ['GET'])]
    public function mailingListsTab(?int $member_id = null): Response
    {
        if (isset($member_id))
            $iter = $this->model->get_iter($member_id);
        else
            $iter = $this->auth->getIdentity()->member();

        if (!$this->policy->userCanUpdate($iter))
            throw new UnauthorizedException();

        $lists = $this->db->getModel('DataModelMailinglist')->get_for_member($iter);

        // TODO: should we show all list a person is subscribed to?
        $lists = array_filter($lists, [$this->policy, 'userCanSubscribe']);

        return $this->render('profile/mailing_lists_tab.html.twig', [
            'iter' => $iter,
            'mailing_lists' => $lists,
        ]);
    }

    #[Route('/profile/sessions', methods: ['GET'])]
    #[Route('/profile/{member_id<\d+>}/sessions', name: 'profile.sessions', methods: ['GET'])]
    public function sessionsTab(?int $member_id = null): Response
    {
        if (isset($member_id))
            $iter = $this->model->get_iter($member_id);
        else
            $iter = $this->auth->getIdentity()->member();

        if (!$this->policy->userCanUpdate($iter))
            throw new UnauthorizedException();

        $model = $this->db->getModel('DataModelSession');

        return $this->render('profile/sessions_tab.html.twig', [
            'iter' => $iter,
            'sessions' => $model->getActive($iter->get_id()),
        ]);
    }

    #[Route('/profile/kast', methods: ['GET'])]
    #[Route('/profile/{member_id<\d+>}/kast', name: 'profile.kast', methods: ['GET'])]
    public function kastTab(Kast $kast, ?int $member_id = null): Response
    {
        if (isset($member_id))
            $iter = $this->model->get_iter($member_id);
        else
            $iter = $this->auth->getIdentity()->member();

        if (!$this->policy->userCanUpdate($iter))
            throw new UnauthorizedException();

        try {
            return $this->render('profile/kast_tab.html.twig', [
                'iter' => $iter,
                'status' => $kast->getStatus($iter),
                'history' => $kast->getHistory($iter, 20),
            ]);
        } catch (\Exception|\Error $exception) {
            \Sentry\captureException($exception);
            return $this->render('profile/kast_tab_exception.html.twig', [
                'iter' => $iter,
                'exception' => $exception,
            ]);
        }
    }

    #[Route('/profile/incass-o-matic', methods: ['GET'])]
    #[Route('/profile/{member_id<\d+>}/incass-o-matic', name: 'profile.incassomatic', methods: ['GET'])]
    public function incassomaticTab(Incassomatic $incassomatic, ?int $member_id = null): Response|RedirectResponse
    {
        if (isset($member_id))
            $iter = $this->model->get_iter($member_id);
        else
            $iter = $this->auth->getIdentity()->member();

        if (!$this->policy->userCanUpdate($iter))
            throw new UnauthorizedException();

        try {
            $contract = $incassomatic->getCurrentContract($iter);
            $debits = $incassomatic->getDebits($iter, 15);
        } catch (\Exception|\Error $exception) {
            \Sentry\captureException($exception);
            return $this->render('profile/incassomatic_tab_exception.html.twig', [
                'iter' => $iter,
                'exception' => $exception,
            ]);
        }

        if (empty($contract))
            return $this->redirectToRoute('profile.incassomatic.mandate', [
                'member_id' => $iter['id'],
            ]);

        // Group debits per batch
        $debitsPerBatch = [];
        foreach ($debits as $debit) {
            $key = (string) $debit['batch_id'];
            if (isset($debitsPerBatch[$key]))
                $debitsPerBatch[$key][] = $debit;
            else
                $debitsPerBatch[$key] = [$debit];
        }

        return $this->render('profile/incassomatic_tab.html.twig', [
            'iter' => $iter,
            'contract' => $contract,
            'debits_per_batch' => $debitsPerBatch,
        ]);
    }

    #[Route('/profile/{member_id<\d+>}/incass-o-matic/mandate', name: 'profile.incassomatic.mandate', methods: ['GET', 'POST'])]
    public function incassomaticTabMandate(Incassomatic $incassomatic, Request $request, int $member_id): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($member_id);

        if (!$this->policy->userCanUpdate($iter))
            throw new UnauthorizedException();

        // Validate the member has no contract and Incass-o-matic works.
        try {
            if ($incassomatic->getCurrentContract($iter))
                return $this->redirectToRoute('profile.incassomatic', [
                    'member_id' => $iter['id'],
                ]);
        } catch (\Exception|\Error $exception) {
            \Sentry\captureException($exception);
            return $this->render('profile/incassomatic_tab_exception.html.twig', [
                'iter' => $iter,
                'exception' => $exception,
            ]);
        }

        $form = $this->createFormBuilder()
            ->add('sepa_mandate', CheckboxType::class, [
                'label' => __('I hereby authorize Cover to automatically deduct the membership fee, costs for attending activities, and additional costs (e.g. food and drinks) from my bank account for the duration of my membership.'),
                'required' => true,
            ])
            ->add('submit', SubmitType::class, ['label' => 'Sign mandate'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $response = $incassomatic->createContract($iter);
            return $this->redirectToRoute('profile.incassomatic', [
                'member_id' => $iter['id'],
            ]);
        }

        return $this->render('profile/incassomatic_tab_mandate.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/profile/confirm_email', name: 'profile.confirm_email', methods: ['GET'])]
    public function confirmEmail(
        #[MapQueryParameter] string $token,
        Request $request,
        Secretary $secretary,
        UriSigner $uriSigner,
    ): Response
    {
        if (!$uriSigner->checkRequest($request))
            return $this->render('profile/confirm_email.html.twig', ['success' => false]);

        $model = $this->db->getModel('DataModelEmailConfirmationToken');

        try {
            $token = $model->get_iter($token);
        } catch (\Exception $e) {
            return $this->render('profile/confirm_email.html.twig', ['success' => false]);
        }

        // Update the member's email address
        $member = $token['member'];
        $old_email = $member['email'];
        $member['email'] = $token['email'];
        $this->model->update($member);

        // Report the changes to the secretary and to Secretary (the system...)
        $this->updateMember($secretary, $member);

        // Delete this and all other tokens for this user
        $model->invalidate_all($token['member']);

        return $this->render('profile/confirm_email.html.twig', ['success' => true]);
    }

    #[Route('/profile/{member_id<\d+>}/vcard', name: 'profile.vcard', methods: ['GET'])]
    public function vcard(int $member_id): Response
    {
        $iter = $this->model->get_iter($member_id);

        if (!$this->auth->getIdentity()->is_member())
            throw new UnauthorizedException('Only members can download vCards.');

        if (!$this->policy->userCanRead($iter))
            throw new UnauthorizedException('This person is no longer a member of Cover.');

        // Macro for checking whether a field is not private.
        $is_visible = function($field) use ($iter) {
            return in_array($this->model->get_privacy_for_field($iter, $field),
                [\DataModelMember::VISIBLE_TO_EVERYONE, \DataModelMember::VISIBLE_TO_MEMBERS]);
        };

        $card = new VCard();

        if ($is_visible('naam'))
            $card->addName($iter['achternaam'], $iter['voornaam'], $iter['tussenvoegsel']);

        if ($is_visible('email'))
            $card->addEmail($iter['email']);

        if ($is_visible('telefoonnummer'))
            $card->addPhoneNumber($iter['telefoonnummer'], 'PREF;CELL');

        if ($is_visible('adres') || $is_visible('postcode') || $is_visible('woonplaats'))
            $card->addAddress(null, null,
                $is_visible('adres') ? $iter['adres'] : null,
                $is_visible('woonplaats') ? $iter['woonplaats'] : null,
                null,
                $is_visible('postcode') ? $iter['postcode'] : null,
                null,
                'HOME;POSTAL'
            );

        if ($is_visible('geboortedatum'))
            $card->addBirthday($iter['geboortedatum']);

        // For some weird reason is 'http://' the default value for members their homepage.
        if (!empty($iter['homepage']) && $iter['homepage'] != 'http://')
            $card->addURL($iter['homepage']);

        if ($is_visible('foto') && $iter->get_profile_picture()) {
            // Ask ProfilePictureController for a photo
            $photo_response = $this->forward('App\Controller\ProfilePicturesController::member', [
                'member_id' => $iter['id'],
                'format' => 'square',
                'width' => 512,
            ]);
            $card->addPhotoContent($photo_response->getContent());
        }

        if (!is_array($card->getProperties()))
            throw $this->createNotFoundException('This member has no public fields in their profile.');


        return new Response(
            $card->getOutput(),
            Response::HTTP_OK,
            $card->getHeaders(true),
        );
    }
}
