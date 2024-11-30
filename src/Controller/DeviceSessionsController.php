<?php

namespace App\Controller;

use App\Exception\UnauthorizedException;
use App\Form\DeviceSessionType;
use App\Legacy\Authentication\DeviceIdentityProvider;
use App\Service\Authentication;
use App\Service\Database;
use App\Service\Policy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class DeviceSessionsController extends AbstractController
{
    private \DataModelSession $model;

    public function __construct(
        private Database $db,
        private Policy $policy,
    ){
        $this->model = $db->getModel('DataModelSession');
    }

    #[Route('/sessions/device', name: 'device_sessions.list', methods: ['GET'])]
    public function list(Authentication $auth): Response
    {
        if (!$auth->getIdentity()->member_in_committee(COMMISSIE_EASY))
            throw new UnauthorizedException();

        $iters = $this->model->find(['type' => 'device']);

        // Apply policy
        $iters = array_filter($iters, [$this->policy, 'userCanRead']);

        return $this->render('device_sessions/list.html.twig', [
            'iters' => $iters,
        ]);
    }

    #[Route('/sessions/device/create', name: 'device_sessions.create', methods: ['GET', 'POST'])]
    public function create(Authentication $auth, Request $request): Response
    {
        if (!$auth->loggedIn && !($auth->getIdentity() instanceof DeviceIdentityProvider))
            $auth->getAuth()->create_device_session($request->headers->get('user-agent'));

        return $this->render('device_sessions/create.html.twig');
    }

    #[Route('/sessions/device/logout', name: 'device_sessions.logout', methods: ['GET'])]
    public function logout(Authentication $auth, Request $request): RedirectResponse
    {
        if ($auth->getIdentity() instanceof DeviceIdentityProvider)
            $auth->getAuth()->logout();

        return $this->redirectToRoute('device_sessions.list');
    }

    #[Route('/sessions/device/{id}/update', name: 'device_sessions.update', methods: ['GET', 'POST'])]
    public function update(string $id, Request $request): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanUpdate($iter))
            throw new UnauthorizedException('You are not allowed to edit this announcement.');

        $form = $this->createForm(DeviceSessionType::class, $iter, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->model->update($iter);
            return $this->redirectToRoute('device_sessions.list');
        }

        return $this->render('device_sessions/form.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    #[Route('/sessions/device/{id}/delete', name: 'device_sessions.delete', methods: ['GET', 'POST'])]
    public function delete(string $id, Request $request): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanDelete($iter))
            throw new UnauthorizedException('You are not allowed to delete this announcement.');

        $form = $this->createFormBuilder($iter)
            ->add('submit', SubmitType::class, ['label' => __('Delete'), 'color' => 'danger'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->model->delete($iter);
            return $this->redirectToRoute('device_sessions.list');
        }

        return $this->render('device_sessions/confirm_delete.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }
}