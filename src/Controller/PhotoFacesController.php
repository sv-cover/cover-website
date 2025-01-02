<?php

namespace App\Controller;

use App\DataIter\DataIterPhotobookFace;
use App\DataModel\DataModelPhotobook;
use App\DataModel\DataModelPhotobookFace;
use App\Exception\UnauthorizedException;
use App\Service\Authentication;
use App\Service\Policy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class PhotoFacesController extends AbstractController
{
    public function __construct(
        private DataModelPhotobookFace $faceModel,
        private DataModelPhotobook $photoModel,
        private Policy $policy,
    ) {
    }

    private function getLinksForIter(DataIterPhotobookFace $face)
    {
        $links = [];

        if ($this->policy->userCanRead($face))
            $links['read'] = $this->generateUrl('photo_faces.single', [
                'id' => $face['id'],
                'photo_id' => $face['foto_id'],
            ]);

        if ($this->policy->userCanUpdate($face))
            $links['update'] = $this->generateUrl('photo_faces.update', [
                'id' => $face['id'],
                'photo_id' => $face['foto_id'],
            ]);

        if ($this->policy->userCanDelete($face))
            $links['delete'] = $this->generateUrl('photo_faces.delete', [
                'id' => $face['id'],
                'photo_id' => $face['foto_id'],
            ]);

        return $links;
    }

    private function serializeFace(DataIterPhotobookFace $face)
    {
        if ($face['lid_id'])
            $suggested = null;
        else
            $suggested = $face['suggested_member'];

        if ($suggested && !$this->policy->userCanRead($suggested))
            $suggested = null;

        return [
            '__id' => $face['id'],
            '__links' => $this->getLinksForIter($face),
            'id' => $face['id'],
            'photo_id' => $face['foto_id'],
            'x' => $face['x'],
            'y' => $face['y'],
            'h' => $face['h'],
            'w' => $face['w'],
            'member_id' => $face['lid_id'],
            'member_full_name' => $face['lid'] ? $face['lid']->get_full_name(bePersonal: true) : null,
            'member_url' => $face['lid_id'] ? $this->generateUrl('profile.member', ['member_id' => $face['lid_id']]) : null,
            'custom_label' => $face['custom_label'],
            'suggested_id' => $suggested ? $suggested['id'] : null,
            'suggested_full_name' => $suggested ? $suggested->get_full_name(bePersonal: true) : null,
            'suggested_url' => $suggested ? $this->generateUrl('profile.member', ['member_id' => $suggested['id']]) : null,
        ];
    }

    #[Route('/photos/photo/{photo_id<\d+>}/faces', name: 'photo_faces.list', methods: ['GET'])]
    public function list(int $photo_id): Response
    {
        $photo = $this->photoModel->get_iter($photo_id);
        $iters = $this->faceModel->get_for_photo($photo);

        // Apply policy
        $iters = array_filter($iters, [$this->policy, 'userCanRead']);

        $links = [];

        if ($this->policy->userCanCreate('DataModelPhotobookFace'))
            $links['create'] = $this->generateUrl('photo_faces.create', ['photo_id' => $photo->get_id()]);

        return $this->json([
            'iters' => array_map([$this, 'serializeFace'], $iters),
            '__links' => $links
        ]);
    }


    #[Route('/photos/photo/{photo_id<\d+>}/faces/create', name: 'photo_faces.create', methods: ['POST'])]
    public function create(Authentication $auth, Request $request, int $photo_id): Response
    {
        $photo = $this->photoModel->get_iter($photo_id);
        $face = $this->faceModel->new_iter();

        if (!$this->policy->userCanCreate($face))
            throw new UnauthorizedException('You are not allowed to tag people.');

        $data = $request->toArray();

        $data['foto_id'] = $photo->get_id();
        $data['tagged_by'] = $auth->identity->get('id');
        $data['tagged_on'] = new \DateTime();

        $face->set_all($data);
        $this->faceModel->insert($face);

        return $this->json(['iter' => $this->serializeFace($face)]);
    }

    #[Route('/photos/faces/{id<\d+>}', name: 'photo_faces.single', methods: ['GET'])]
    public function single(int $id): Response
    {
        $face = $this->faceModel->get_iter($id);

        if (!$this->policy->userCanRead($face))
            throw new UnauthorizedException('You are not allowed to see this tag.');

        return $this->json([
            'iter' => $this->serializeFace($face)
        ]);
    }

    #[Route('/photos/faces/{id<\d+>}/update', name: 'photo_faces.update', methods: ['PATCH'])]
    public function update(Authentication $auth, Request $request, int $id): Response
    {
        $face = $this->faceModel->get_iter($id);

        if (!$this->policy->userCanUpdate($face))
            throw new UnauthorizedException('You are not allowed to edit this tag.');

        $data = $request->toArray();

        // Also update who changed it.
        $data['tagged_by'] = $auth->identity->get('id');
        $data['tagged_on'] = new \DateTime();

        // Only a custom label XOR a lid_id can be assigned to a tag
        if (isset($data['custom_label']))
            $data['lid_id'] = null;
        elseif (isset($data['lid_id']))
            $data['custom_label'] = null;

        foreach ($data as $key => $value)
            $face->set($key, $value);

        $this->faceModel->update($face);

        return $this->json(['iter' => $this->serializeFace($face)]);
    }

    #[Route('/photos/faces/{id<\d+>}/delete', name: 'photo_faces.delete', methods: ['DELETE'])]
    public function delete(Request $request, int $id): Response
    {
        $face = $this->faceModel->get_iter($id);

        if (!$this->policy->userCanDelete($face))
            throw new UnauthorizedException('You are not allowed to delete this tag.');

        $this->faceModel->delete($face);

        return $this->json([]);
    }
}
