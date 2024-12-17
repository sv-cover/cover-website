<?php

namespace App\Controller;

use App\DataModel\DataModelSticker;
use App\Exception\UnauthorizedException;
use App\Form\StickerType;
use App\Service\Authentication;
use App\Service\Policy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\Validator\Constraints as Assert;

class StickersController extends AbstractController
{
    const PHOTO_THUMBNAIL_WIDTH = 600;

    public function __construct(
        private DataModelSticker $model,
        private Policy $policy,
    ) {
    }

    #[Route('/stickers', name: 'stickers.list', methods: ['GET'])]
    public function list(): Response
    {
        $iters = $this->model->get();

        // Apply policy
        $iters = array_filter($iters, [$this->policy, 'userCanRead']);

        return $this->render('stickers/list.html.twig', [
            'iters' => $iters,
        ]);
    }

    #[Route('/stickers/geojson', name: 'stickers.geojson', methods: ['GET'])]
    public function geojson(): Response
    {
        $features = [];

        foreach ($this->model->get() as $sticker)
            if ($this->policy->userCanRead($sticker))
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [
                            $sticker['lng'],
                            $sticker['lat'],
                        ]
                    ],
                    'properties' => [
                        'id' => $sticker['id'],
                        'label' => $sticker['label'],
                        'description' => $sticker['omschrijving'],
                        'photo_url' => $sticker['foto']
                            ? $this->generateUrl('stickers.photo', ['id' => $sticker->get_id()])
                            : null,
                        'added_on' => $sticker['toegevoegd_op'],
                        'added_by_url' => $sticker['toegevoegd_door']
                            ? $this->generateUrl('profile.member', ['id' => $sticker['toegevoegd_door']])
                            : null,
                        'added_by_name' => $sticker['toegevoegd_door']
                            ? \member_full_name($sticker['member'], \BE_PERSONAL)
                            : null,
                        'editable' => $this->policy->userCanUpdate($sticker),
                        'add_photo_url' => $this->policy->userCanUpdate($sticker)
                            ? $this->generateUrl('stickers.add_photo', ['id' => $sticker->get_id()])
                            : null,
                        'delete_url' => $this->policy->userCanDelete($sticker)
                            ? $this->generateUrl('stickers.delete', ['id' => $sticker->get_id()])
                            : null,
                    ]
                ];

        return $this->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    #[Route('/stickers/create', name: 'stickers.create', methods: ['GET', 'POST'])]
    public function create(
        Authentication $auth,
        Request $request,
        #[MapQueryParameter] ?float $lng = null,
        #[MapQueryParameter] ?float $lat = null,
    ): Response|RedirectResponse
    {
        $sticker = $this->model->new_iter();

        if (!empty($lng))
            $sticker['lng'] = $lng;

        if (!empty($lat))
            $sticker['lat'] = $lat;

        $sticker['toegevoegd_op'] = date('Y-m-d');
        $sticker['toegevoegd_door'] = $auth->identity->get('id');

        if (!$this->policy->userCanCreate($sticker))
            throw new UnauthorizedException('You are not allowed to create stickers.');

        $form = $this->createForm(StickerType::class, $sticker, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $id = $this->model->insert($sticker);
            return $this->redirectToRoute('stickers.list', [
                // These parameter might not be used in the controller, but they are in the frontend.
                'point' => $sticker['id'],
                'lat' => $sticker['lat'],
                'lng' => $sticker['lng'],
            ]);
        }

        return $this->render('stickers/form.html.twig', [
            'sticker' => $sticker,
            'form' => $form,
        ]);
    }

    #[Route('/stickers/{id<\d+>}/delete', name: 'stickers.delete', methods: ['GET', 'POST'])]
    public function delete(TagAwareCacheInterface $stickerPicturesCache, Request $request, int $id): Response|RedirectResponse
    {
        $sticker = $this->model->get_iter($id);

        if (!$this->policy->userCanDelete($sticker))
            throw new UnauthorizedException('You are not allowed to delete this announcement.');

        $form = $this->createFormBuilder($sticker)
            ->add('submit', SubmitType::class, ['label' => __('Delete'), 'color' => 'danger'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->model->delete($sticker);
            $key = sprintf('sticker_%d', $sticker->get_id());
            $stickerPicturesCache->delete($key);
            return $this->redirectToRoute('stickers.list', [
                // These parameter might not be used in the controller, but they are in the frontend.
                'lat' => $request->query->get('lat', $sticker['lat']),
                'lng' => $request->query->get('lng', $sticker['lng']),
                'zoom' => $request->query->get('zoom', null),
            ]);
        }

        return $this->render('stickers/confirm_delete.html.twig', [
            'sticker' => $sticker,
            'form' => $form,
        ]);
    }

    private function _generatePhotoThumbnail($photo): string
    {
        $imagick = new \Imagick();
        $imagick->readImageFile($photo);

        // Fix orientation, remove exif data
        apply_image_orientation($imagick);
        strip_exif_data($imagick);

        // Scale to target width
        $imagick->scaleImage(self::PHOTO_THUMBNAIL_WIDTH, 0);

        // Write the image as a progressive JPEG
        $imagick->setImageFormat('jpeg');
        $imagick->setInterlaceScheme(\Imagick::INTERLACE_PLANE);

        return $imagick->getImageBlob();
    }

    #[Route('/stickers/{id<\d+>}/photo', name: 'stickers.photo', methods: ['GET'])]
    public function photoThumbnail(TagAwareCacheInterface $stickerPicturesCache, int $id): Response
    {
        $sticker = $this->model->get_iter($id);

        if (!$this->policy->userCanRead($sticker))
            throw new UnauthorizedException('You are not allowed to see this sticker.');

        $key = sprintf('sticker_%d', $sticker->get_id());
        $image = $stickerPicturesCache->get($key, function (ItemInterface $item) use ($sticker): string {
            return $this->_generatePhotoThumbnail($this->model->getPhoto($sticker));
        });

        $response = new Response($image);

        $cacheExpires = 24*3600;
        $response->setPublic();
        $response->setMaxAge($cacheExpires);

        $type = (new \finfo(\FILEINFO_MIME_TYPE))->buffer($image);
        if ($type !== null)
            $response->headers->set('Content-Type', $type);

        return $response;
    }

    // Currently not used. But let's keep it for courtesy :)
    #[Route('/stickers/{id<\d+>}/photo/original', name: 'stickers.photo_original', methods: ['GET'])]
    public function photoOriginal(int $id): Response
    {
        $sticker = $this->model->get_iter($id);

        if (!$this->policy->userCanRead($sticker))
            throw new UnauthorizedException('You are not allowed to see this sticker.');

        $image = \stream_get_contents($this->model->getPhoto($sticker));

        $response = new Response($image);

        $cacheExpires = 24*3600;
        $response->setPublic();
        $response->setMaxAge($cacheExpires);

        $type = (new \finfo(\FILEINFO_MIME_TYPE))->buffer($image);
        if ($type !== null)
            $response->headers->set('Content-Type', $type);

        return $response;
    }

    #[Route('/stickers/{id<\d+>}/add_photo', name: 'stickers.add_photo', methods: ['GET', 'POST'])]
    public function addPhoto(TagAwareCacheInterface $stickerPicturesCache, Request $request, int $id): Response|RedirectResponse
    {
        $sticker = $this->model->get_iter($id);

        if (!$this->policy->userCanUpdate($sticker))
            throw new UnauthorizedException('You are not allowed to see this sticker.');

        $form = $this->createFormBuilder()
            ->add('photo', FileType::class, [
                'label' => __('Photo'),
                'cta' => __('Select photo…'),
                'help' => __('Add a photo to this sticker. Only JPEG-images are allowed.'),
                'constraints' => [
                    new Assert\Image([
                        'maxSize' => \ini_get('upload_max_filesize'),
                        'mimeTypes' => [
                            'image/jpeg',
                        ],
                        'mimeTypesMessage' => __('Please upload a valid JPEG-image.'),
                        'sizeNotDetectedMessage' => __('The uploaded file doesn’t appear to be an image.'),
                    ])
                ],
                'attr' => [
                    'accept' => 'image/jpeg',
                ],
            ])
            ->add('submit', SubmitType::class)
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form['photo']->getData();

            // Set the new photo
            $this->model->setPhoto($sticker, \fopen($file->getPathname(), 'rb'));

            // Delete the old one from the cache (just in case, photo's can't be replaced so kinda useless)
            $key = sprintf('sticker_%d', $sticker->get_id());
            $stickerPicturesCache->delete($key);

            return $this->redirectToRoute('stickers.list', [
                // These parameter might not be used in the controller, but they are in the frontend.
                'point' => $sticker['id'],
                'lat' => $request->query->get('lat', $sticker['lat']),
                'lng' => $request->query->get('lng', $sticker['lng']),
                'zoom' => $request->query->get('zoom', null),
            ]);
        }

        return $this->render('stickers/add_photo.html.twig', [
            'sticker' => $sticker,
            'form' => $form,
        ]);
    }
}
