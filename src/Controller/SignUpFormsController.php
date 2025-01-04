<?php

namespace App\Controller;

use App\DataIter\DataIterSignupForm;
use App\DataModel\DataModelCommissie;
use App\DataModel\DataModelSignUpField;
use App\DataModel\DataModelSignUpForm;
use App\Exception\UnauthorizedException;
use App\Form\SignUpFormType;
use App\Form\SignUpFieldType;
use App\Service\Authentication;
use App\Service\Policy;
use App\SignUp\SignUpFormManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class SignUpFormsController extends AbstractController
{
    public function __construct(
        private DataModelSignUpForm $model,
        private DataModelSignUpField $fieldModel,
        private Policy $policy,
        private SignUpFormManager $manager,
    ) {
    }

    #[Route('/sign_up', name: 'sign_up_forms.list', methods: ['GET'])]
    public function list(Authentication $auth): Response
    {
        $identity = $auth->identity;

        if (!$identity->get('committees'))
            throw new UnauthorizedException('Only committee members may create and manage forms.');

        if (
            $identity->member_in_committee(DataModelCommissie::BOARD)
            || $identity->member_in_committee(DataModelCommissie::CANDY)
        )
            $forms = $this->model->get();
        else
            $forms = $this->model->find(['committee_id__in' => $identity->get('committees')]);

        // Apply policy
        $forms = array_filter($forms, [$this->policy, 'userCanRead']);

        return $this->render('sign_ups/forms/list.html.twig', [
            'forms' => $forms,
        ]);
    }

    #[Route('/sign_up/create', name: 'sign_up_forms.create', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        #[MapQueryParameter] ?int $event_id = null,
    ): Response|RedirectResponse
    {
        $iter = $this->model->new_iter();
        $iter['created_on'] = new \DateTime('now');

        if (!$this->policy->userCanCreate($iter))
            throw new UnauthorizedException('You are not allowed to create sign_up_forms.');

        if (isset($event_id)) {
            $iter['agenda_id'] = $event_id;
            // agenda_item will be automatically queried based on the previously set agenda_id
            $iter['committee_id'] = $iter['agenda_item']['committee_id'];
        }

        $form = $this->createForm(SignUpFormType::class, $iter, ['mapped' => false]);
        $form->add('template', ChoiceType::class, [
            'label' => __('Template'),
            'choices' => [
                __('Sign-up form for a paid activity') => 'paid_activity',
            ],
            'help' => __('Choose a template to start with a set of predefined fields.'),
            'placeholder' => __('Empty form'),
            'mapped' => false,
            'required' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $id = $this->model->insert($iter);
            if (!empty($form->get('template')->getData()))
                $this->populateFormFromTemplate($iter, $form->get('template')->getData());
            return $this->redirectToRoute('sign_up_forms.update', [
                'id' => $iter->get_id(),
                '_fragment' => 'signup-form-fields',
            ]);
        }

        return $this->render('sign_ups/forms/create.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/sign_up/{id<\d+>}', name: 'sign_up_forms.single', methods: ['GET'])]
    public function single(int $id): Response
    {
        return $this->redirectToRoute('sign_up_entries.create', ['form_id' => $id]);
    }

    #[Route('/sign_up/{id<\d+>}/update', name: 'sign_up_forms.update', methods: ['GET', 'POST'])]
    public function update(int $id, Request $request): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanUpdate($iter))
            throw new UnauthorizedException('You are not allowed to edit this form.');

        $form = $this->createForm(SignUpFormType::class, $iter, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->model->update($iter);
            $this->addFlash('notice', __('The form was successfully updated!'));
        }

        $fieldForm = $this->createForm(SignUpFieldType::class, null, ['mapped' => false]);
        $fieldForm->handleRequest($request);

        return $this->render('sign_ups/forms/update.html.twig', [
            'iter' => $iter,
            'form' => $form,
            'field_form' => $fieldForm->createView(),
        ]);
    }

    #[Route('/sign_up/{id<\d+>}/delete', name: 'sign_up_forms.delete', methods: ['GET', 'POST'])]
    public function delete(int $id, Request $request): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanDelete($iter))
            throw new UnauthorizedException('You are not allowed to delete this form.');

        $form = $this->createFormBuilder($iter)
            ->add('submit', SubmitType::class, ['label' => __('Delete'), 'color' => 'danger'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->model->delete($iter);
            return $this->redirectToRoute('sign_up_forms.list');
        }

        return $this->render('sign_ups/forms/confirm_delete.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    private function populateFormFromTemplate(DataIterSignupForm $form, string $template): void
    {
        if ($template == 'paid_activity') {
            $model = $this->fieldModel;

            $model->db->beginTransaction();

            $model->insert($this->manager->createField($form, 'editable', function($widget) {
                $widget->content = "[h2]Sign up now![/h2]\nShort description of why you need to sign up and what you will receive in return.";
            }));

            $model->insert($this->manager->createField($form, 'name', function($widget) {
                $widget->required = true;
            }));

            $model->insert($this->manager->createField($form, 'editable', function($widget) {
                $widget->content = "We also need your email address to contact you, and address and bank account details to make a direct debit for you.";
            }));

            $model->insert($this->manager->createField($form, 'email', function($widget) {
                $widget->required = true;
            }));

            $model->insert($this->manager->createField($form, 'address', function($widget) {
                $widget->required = true;
            }));

            $model->insert($this->manager->createField($form, 'bankaccount', function($widget) {
                $widget->required = true;
            }));

            $model->insert($this->manager->createField($form, 'checkbox', function($widget) {
                $widget->required = true;
                $widget->description = 'I allow Cover to deduct €x,xx from my bank account.';
            }));

            $model->insert($this->manager->createField($form, 'editable', function($widget) {
                $widget->content = sprintf(
                    "Please review Cover's [url=%s]Cancellation Policy[/url] before proceeding. By checking the box below, you confirm that you understand and agree to these terms.",
                    $this->generateUrl('slug', ['slug' => 'cancellation-policy'])
                );
            }));

            $model->insert($this->manager->createField($form, 'checkbox', function($widget) {
                $widget->required = true;
                $widget->description = 'I agree with the cancellation policy.';
            }));

            $model->db->commit();
        }
    }
}
