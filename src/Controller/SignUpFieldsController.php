<?php

namespace App\Controller;

use App\Exception\UnauthorizedException;
use App\Form\SignUpFieldType;
use App\Service\Database;
use App\Service\Policy;
use App\SignUp\SignUpFormManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sign_up/{form_id<\d+>}/fields')]
class SignUpFieldsController extends AbstractController
{
    private \DataModelSignUpField $fieldModel;
    private \DataModelSignUpForm $formModel;

    public function __construct(
        private Database $db,
        private Policy $policy,
    ){
        $this->fieldModel = $db->getModel('DataModelSignUpField');
        $this->formModel = $db->getModel('DataModelSignUpForm');
    }

    #[Route('/create', name: 'sign_up_fields.create', methods: ['GET', 'POST'])]
    public function create(
        SignUpFormManager $manager,
        Request $request,
        int $form_id,
        #[MapQueryParameter] string $context = 'standalone',
    ): Response|RedirectResponse
    {
        $iter = $this->formModel->get_iter($form_id);

        if (!$this->policy->userCanUpdate($iter))
            throw new UnauthorizedException('You are not allowed to update this form.');

        $form = $this->createForm(SignUpFieldType::class, null, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // $field = $iter->new_field($form->get('field_type')->getData());
            $field = $manager->createField($iter, $form->get('field_type')->getData());
            $this->fieldModel->insert($field);

            if ($context === 'async')
                return $this->render('sign_ups/fields/single.html.twig', [
                    'field' => $field,
                    'form' => $iter
                ]);
        }

        return $this->redirectToRoute('sign_up_forms.update', [
            'id' => $iter->get_id(),
            '_fragment' => 'signup-form-fields',
        ]);
    }

    #[Route('/order', name: 'sign_up_fields.order', methods: ['GET', 'POST'])]
    public function order(Request $request, int $form_id): Response|RedirectResponse
    {
        $form = $this->formModel->get_iter($form_id);

        if (!$this->policy->userCanUpdate($form))
            throw new UnauthorizedException('You are not allowed to update this form.');

        $fields = $form['fields'];

        $order = $request->getPayload()->all('order');

        $indexes = \array_map(fn($f) => \array_search($f['id'], $order), $fields);

        array_multisort($indexes, $fields);

        $this->fieldModel->update_order($fields);

        return $this->redirectToRoute('sign_up_forms.update', [
            'id' => $form->get_id(),
            '_fragment' => 'signup-form-fields',
        ]);
    }

    #[Route('/{id<\d+>}/update', name: 'sign_up_fields.update', methods: ['GET', 'POST'])]
    public function update(
        Request $request,
        SignUpFormManager $manager,
        int $form_id,
        int $id,
        #[MapQueryParameter] string $context = 'standalone',
    ): Response|RedirectResponse
    {
        $iter = $this->formModel->get_iter($form_id);
        $field = $this->fieldModel->find_one([
            'id' => $id,
            'form_id' => $iter->get_id(),
        ]);

        if (!$field)
            throw $this->createNotFoundException('Field not found.');

        if (!$this->policy->userCanUpdate($iter))
            throw new UnauthorizedException('You are not allowed to update this form.');

        $form = $manager->getConfigurationForm($field);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $field['properties'] = $form->getData();
            $this->fieldModel->update($field);

            if ($context === 'async')
                return $this->render('sign_ups/fields/single.html.twig', [
                    'field' => $field,
                    'form' => $iter
                ]);
            else
                return $this->redirectToRoute('sign_up_forms.update', [
                    'id' => $iter->get_id(),
                    '_fragment' => 'signup-form-fields',
                ]);
        }

        return $this->render('sign_ups/fields/update.html.twig', [
            'field' => $field,
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/{id<\d+>}/delete', name: 'sign_up_fields.delete', methods: ['GET', 'POST'])]
    public function delete( Request $request, int $form_id, int $id): Response|RedirectResponse
    {
        $iter = $this->formModel->get_iter($form_id);
        $field = $this->fieldModel->find_one([
            'id' => $id,
            'form_id' => $iter->get_id(),
        ]);

        if (!$field)
            throw $this->createNotFoundException('Field not found.');

        if (!$this->policy->userCanUpdate($iter))
            throw new UnauthorizedException('You are not allowed to update this form.');

        $form = $this->createFormBuilder($field, ['csrf_token_id' => 'delete_sign_up_field_' . $field->get_id()])
            ->add('submit', SubmitType::class, ['label' => __('Delete'), 'color' => 'danger'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->fieldModel->delete($field);
            return $this->redirectToRoute('sign_up_fields.restore', [
                'form_id' => $iter->get_id(),
                'id' => $field->get_id(),
            ]);
        }

        return $this->render('sign_ups/fields/confirm_delete.html.twig', [
            'form' => $form,
            'iter' => $iter,
            'field' => $field,
        ]);
    }

    #[Route('/{id<\d+>}/restore', name: 'sign_up_fields.restore', methods: ['GET', 'POST'])]
    public function restore(Request $request, int $form_id, int $id): Response|RedirectResponse
    {
        $iter = $this->formModel->get_iter($form_id);
        $field = $this->fieldModel->find_one([
            'id' => $id,
            'form_id' => $iter->get_id(),
            'deleted' => true
        ]);

        if (!$field)
            throw $this->createNotFoundException('Field not found.');

        if (!$this->policy->userCanUpdate($iter))
            throw new UnauthorizedException('You are not allowed to update this form.');

        $form = $this->createFormBuilder($field)
            ->add('submit', SubmitType::class, ['label' => 'Restore'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->fieldModel->restore($field);
            return $this->redirectToRoute('sign_up_forms.update', [
                'id' => $iter->get_id(),
                '_fragment' => 'signup-form-fields',
            ]);
        }

        return $this->render('sign_ups/fields/restore.html.twig', [
            'form' => $form,
            'iter' => $iter,
            'field' => $field,
        ]);
    }
}
