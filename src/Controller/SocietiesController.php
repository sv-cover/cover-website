<?php

namespace App\Controller;

use App\Exception\UnauthorizedException;
use App\Service\Authentication;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber as AssertPhoneNumber;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

class SocietiesController extends AbstractController
{
    #[Route('/societies', name: 'societies.list', methods: ['GET'])]
    public function societies(): Response
    {
        return $this->render('societies/list.html.twig');
    }

    #[Route('/societies/create', name: 'societies.create', methods: ['GET', 'POST'])]
    public function create(Authentication $auth, MailerInterface $mailer, Request $request): Response|RedirectResponse
    {
        if (!$auth->loggedIn)
            throw new UnauthorizedException();

        $member = $auth->identity->member();

        $data = [
            'email' => $member['email'],
            'phone' => $member['telefoonnummer'],
        ];

        $form = $this->createFormBuilder($data)
            ->add('society_name', TextType::class, [
                'label' => __('Society name'),
                'constraints' => new Assert\NotBlank(),
            ])
            ->add('society_purpose', TextareaType::class, [
                'label' => __('Purpose of the society'),
                'constraints' => new Assert\NotBlank(),
            ])
            ->add('founding_members', TextType::class, [
                'label' => __('Founding members'),
                'constraints' => new Assert\NotBlank(),
                'help' => __('Who are the founding members of the society?'),
            ])
            ->add('leader', TextType::class, [
                'label' => __('Leader'),
                'constraints' => new Assert\NotBlank()
            ])
            ->add('other_comments', TextareaType::class, [
                'label' => __('Other comments'),
                // allow it to be blank
                'required' => false,
                'help' => __('Anything else the Board should know when considering the request?'),
            ])
            ->add('email', EmailType::class, [
                'label' => __('Email'),
                'help' => __('We need to know how to contact you for questions!'),
                'constraints' => [new Assert\NotBlank(), new Assert\Email()],
            ])
            ->add('phone', TelType::class, [
                'label' => __('Phone number'),
                'help' => __('We need to know how to contact you for questions!'),
                'constraints' => [
                    new Assert\NotBlank(),
                    new AssertPhoneNumber(defaultRegion: 'NL'),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => __('Submit proposal'),
            ])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = (new TemplatedEmail())
                ->to($this->getParameter('app.email_board'))
                ->replyTo($form->get('email')->getData())
                ->subject("Society founding request")
                ->htmlTemplate('emails/society_request.html.twig')
                ->context([
                    'data' => $form->getData(),
                    'member' => $member,
                ])
            ;
            $mailer->send($email);
            $this->addFlash('notice', __('Society foundation requested! You should hear from the Board soon!'));
            return $this->redirectToRoute('societies.list');
        }

        return $this->render('societies/form.html.twig', [
            'form' => $form,
        ]);
    }
}
