<?php

namespace App\Controller;

use App\DataModel\DataModelMember;
use App\DataModel\DataModelPasswordResetToken;
use App\DataModel\DataModelSession;
use App\Exception\InactiveMemberException;
use App\Exception\UnauthorizedException;
use App\Form\Type\CommitteeIdType;
use App\Form\Type\MemberIdType;
use App\Legacy\Authentication\Authentication;
use App\Legacy\Authentication\ImpersonatingIdentityProvider;
use App\Utils\UrlUtils;
use App\Validator\Member;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

class SessionsController extends AbstractController
{

    public function __construct(
        private UrlUtils $urlUtils,
    ){
    }

    #[Route('/sessions/{id}/delete', name: 'sessions.delete', methods: ['POST'])]
    public function delete(
        Authentication $auth,
        DataModelSession $model,
        Request $request,
        string $id,
    ): Response|RedirectResponse
    {
        if (!$auth->loggedIn)
            throw new UnauthorizedException('You need to log in to manage your sessions');

        $member = $auth->getIdentity()->member();

        $session = $model->get_iter($id);

        $form = $this->createFormBuilder($session, ['csrf_token_id' => 'session_delete_' . $session['id']])
            ->add('submit', SubmitType::class, ['label' => 'Delete'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Make sure we can only delete our own sessions
            if ($session && $session->get('member_id') == $member->get_id())
                $model->delete($session);

            // Extra warning after we end the current session of the user
            if ($session && $session->get_id() === $auth->getAuth()->get_session()->get_id())
                $this->addFlash('notice', __('Your session has been ended. You will need to log in again.'));
        }
        $referrer = $request->query->get(
            'referrer',
            $this->generateUrl('profile.sessions', ['member_id' => $member->get_id()])
        );
        return $this->redirect($this->urlUtils->validateRedirect($referrer));
    }

    #[Route('/impersonate', name: 'impersonate', methods: ['GET', 'POST'])]
    public function impersonate(
        Authentication $auth,
        DataModelMember $memberModel,
        Request $request
    ): Response|RedirectResponse
    {
        $identity = $auth->getIdentity();

        if (!($identity instanceof ImpersonatingIdentityProvider))
            throw new UnauthorizedException();

        $referrer = $request->query->get('referrer', $this->generateUrl('homepage'));

        $data = [
            'referrer' => $referrer,
            'override_committees' => $identity->get_override_committees() !== null,
            'override_committee_ids' => $identity->get_override_committees() ?? [],
            'override_member' => $identity->get_override_member() !== null,
            'override_member_id' => $identity->get_override_member() ? $identity->get_override_member()->get_id() : null,
        ];

        $form = $this->createFormBuilder($data)
            ->add('override_member', CheckboxType::class, [
                'label' => __('Override member'),
                'required' => false,
                'switch' => true,
            ])
            ->add('override_member_id', MemberIdType::class, [
                'label' => __('Member'),
                'required' => false,
                'constraints' => [
                    new Member(),
                ],
            ])
            ->add('override_committees', CheckboxType::class, [
                'label' => __('Override committee memberships'),
                'required' => false,
                'switch' => true,
            ])
            ->add('override_committee_ids', CommitteeIdType::class, [
                'required' => false,
                'show_all' => true,
                'show_own' => false,
                'multiple' => true,
                'expanded' => true,
                'chips' => true,
            ])
            ->add('referrer', HiddenType::class, [
                'required' => false,
            ])
            ->add('submit', SubmitType::class)
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form['override_member']->getData() && !empty($form['override_member_id']->getData())) {
                $override_member = $memberModel->get_iter($form['override_member_id']->getData());
                $identity->override_member($override_member);
            } else {
                $identity->reset_member();
            }

            if ($form['override_committees']->getData())
                $identity->override_committees($form['override_committee_ids']->getData());
            else
                $identity->reset_committees();

            return $this->redirect($this->urlUtils->validateRedirect(
                $form['referrer']->getData() ?? $this->generateUrl('sessions.impersonate')
            ));
        }

        return $this->render('sessions/impersonate.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(
        Authentication $auth,
        DataModelPasswordResetToken $passwordResetModel,
        Request $request
    ): Response|RedirectResponse
    {
        $referrer = $request->query->get('referrer', $this->generateUrl('homepage'));

        $referrer_host = parse_url($referrer, PHP_URL_HOST);
        if ($referrer_host && !$this->urlUtils->isSameDomain($referrer_host, $request->getHttpHost(), 3))
            $external_domain = parse_url($referrer, PHP_URL_HOST);
        else
            $external_domain = null;

        // Prevent returning to the logout link
        if ($external_domain === null && $referrer == $this->generateUrl('logout'))
            $referrer = null;

        if ($auth->loggedIn)
            return $this->redirect(
                $this->urlUtils->validateRedirect($referrer ?: $this->generateUrl('homepage'), true)
            );

        $data = [
            'referrer' => $referrer,
            'remember' => true,
        ];

        $form = $this->createFormBuilder($data)
            ->add('email', EmailType::class, [
                'label' => __('Email'),
                'constraints' => [new Assert\NotBlank(), new Assert\Email()],
                'attr' => [
                    'placeholder' => __('Email'),
                ],
            ])
            ->add('password', PasswordType::class, [
                'label' => __('Password'),
                'attr' => [
                    'placeholder' => __('Password'),
                ],
            ])
            ->add('remember', CheckboxType::class, [
                'label' => __('Keep me logged in'),
                'required' => false,
            ])
            ->add('referrer', HiddenType::class, [
                'required' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => __('Log in'),
            ])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $referrer = $form['referrer']->getData();
            try {
                if ($auth->getAuth()->login($form['email']->getData(), $form['password']->getData(), $form['remember']->getData(), $request->headers->get('user-agent'))) {
                    // User can apparently login, so invalidate all their password reset tokens
                    try {
                        $passwordResetModel->invalidate_all($auth->getIdentity()->member());
                    } catch (\Exception $exception) {
                        \Sentry\captureException($exception);
                    }

                    return $this->redirect(
                        $this->urlUtils->validateRedirect($referrer ?: $this->generateUrl('homepage'), true)
                    );
                } else {
                    $form->addError(new FormError(__('Wrong combination of e-mail address and password')));
                }
            } catch (InactiveMemberException $e) {
                return $this->render('sessions/inactive.html.twig');
            }
        }

        return $this->render('sessions/login.html.twig', [
            'form' => $form,
            'referrer' => $referrer,
            'external_domain' => $external_domain,
        ]);
    }

    #[Route('/logout', name: 'logout', methods: ['GET', 'POST'])]
    public function logout(Authentication $auth, Request $request): Response|RedirectResponse
    {
        if ($auth->loggedIn)
            $auth->getAuth()->logout();

        $referrer = $request->query->get('referrer');
        if (isset($referrer))
            return $this->redirect($this->urlUtils->validateRedirect($referrer, true));
        else
            return $this->render('sessions/logout.html.twig');
    }
}
