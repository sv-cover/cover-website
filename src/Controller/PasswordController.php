<?php

namespace App\Controller;

use App\DataModel\DataModelMember;
use App\DataModel\DataModelPasswordResetToken;
use App\Exception\NotFoundException;
use App\Exception\UnauthorizedException;
use App\Form\PasswordType;
use App\Service\Authentication;
use App\Service\Policy;
use App\Utils\UrlUtils;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class PasswordController extends AbstractController
{

    public function __construct(
        private DataModelMember $memberModel,
        private DataModelPasswordResetToken $tokenModel,
        private Policy $policy,
    ) {
    }

    #[Route('/password', name: 'password.request', methods: ['GET', 'POST'])]
    public function request(Request $request, MailerInterface $mailer, UriSigner $uriSigner): Response
    {
        $form = $this->createFormBuilder()
            ->add('email', EmailType::class, [
                'label' => __('Email'),
                'constraints' => [new Assert\NotBlank(), new Assert\Email()],
            ])
            ->add('submit', SubmitType::class, [
                'label' => __('Reset password'),
            ])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $member = $this->memberModel->get_from_email($form['email']->getData());

                $token = $this->tokenModel->create_token_for_member($member);

                $url = $this->generateUrl('password.reset', ['token' => $token['key']], UrlGeneratorInterface::ABSOLUTE_URL);
                $signed_url = $uriSigner->sign($url, new \DateInterval('PT24H')); // Valid for 24 hours

                $email = (new TemplatedEmail())
                    ->to($member['email'])
                    ->subject("[Cover] Password Reset")
                    ->htmlTemplate('emails/password_reset.html.twig')
                    ->textTemplate('emails/password_reset.txt.twig')
                    ->context([
                        'member' => $member,
                        'link' => $signed_url,
                    ])
                ;
                $mailer->send($email);
            } catch (NotFoundException $e) {
                // Do nothing, we don't want to give membership status based on email
            }
            return $this->render('password/request_success.html.twig', [
                'email' => $form['email']->getData(),
            ]);
        }

        return $this->render('password/request_form.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/password/reset', name: 'password.reset', methods: ['GET', 'POST'])]
    public function reset(
        Request $request,
        UriSigner $uriSigner,
        #[MapQueryParameter] string $token,
    ): Response|RedirectResponse
    {
        if (!$uriSigner->checkRequest($request))
            return $this->render('password/reset_error.html.twig');

        try {
            $tokenIter = $this->tokenModel->get_iter($token);
        } catch (NotFoundException $e) {
            return $this->render('password/reset_error.html.twig');
        }

        $form = $this->createForm(PasswordType::class, null, [
            'confirm_current' => false,
            'mapped' => false,
            'member' => $tokenIter['member'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->memberModel->set_password($tokenIter['member'], $form['password']->getData());

            $this->tokenModel->invalidate_all($tokenIter['member']);

            return $this->render('password/reset_success.html.twig');
        }

        return $this->render('password/reset_form.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/password/update', name: 'password.update', methods: ['GET', 'POST'])]
    public function update(
        Authentication $auth,
        Request $request,
        UrlUtils $urlUtils,
        #[MapQueryParameter] ?int $member_id,
    ): Response|RedirectResponse
    {
        if (!$auth->loggedIn)
            throw new UnauthorizedException();

        if (isset($member_id))
            $member = $this->memberModel->get_iter($member_id);
        else
            $member = $auth->getIdentity()->member();

        if (!$this->policy->userCanUpdate($member))
            throw new UnauthorizedException();

        $form = $this->createForm(PasswordType::class, null, [
            'confirm_current' => true,
            'mapped' => false,
            'member' => $member,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->memberModel->set_password($member, $form['password']->getData());
            $this->addFlash('notice', __('Your password has been changed.'));
            $referrer = $request->query->get(
                'referrer',
                $this->generateUrl('profile.profile', ['member_id' => $member->get_id()])
            );
            return $this->redirect($urlUtils->validateRedirect($referrer));
        }

        return $this->render('password/form.html.twig', [
            'form' => $form,
        ]);
    }
}
