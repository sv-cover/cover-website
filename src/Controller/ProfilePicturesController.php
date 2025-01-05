<?php

namespace App\Controller;

use App\DataIter\DataIterMember;
use App\DataIter\DataIterProfilePicture;
use App\DataModel\DataModelMember;
use App\DataModel\DataModelProfilePicture;
use App\Exception\UnauthorizedException;
use App\Form\ProfilePictureType;
use App\Legacy\Authentication\Authentication;
use App\Legacy\Policy\Policy;
use App\Utils\ImageUtils;
use App\Utils\UrlUtils;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ProfilePicturesController extends AbstractController
{
    const FORMAT_ORIGINAL = 'original';
    const FORMAT_PORTRAIT = 'portrait';
    const FORMAT_SQUARE = 'square';

    const MAX_WIDTH = 2000;

    const CACHE_EXPIRES = 24*3600; // 24 hours

    public function __construct(
        private DataModelProfilePicture $model,
        private ImageUtils $imageUtils,
        private Policy $policy,
        private TagAwareCacheInterface $profilePicturesCache,
    ) {
    }

    /**
     * Serve an image with the correct headers to make caching possible.
     */
    private function sendImage(string $image, ?string $last_modified = null): Response
    {
        $response = new Response($image);

        $response->setPublic();
        $response->setMaxAge(self::CACHE_EXPIRES);

        // Set more headers
        $type = (new \finfo(\FILEINFO_MIME_TYPE))->buffer($image);
        if ($type !== null)
            $response->headers->set('Content-Type', $type);

        if ($last_modified !== null)
            $response->headers->set('Last-Modified', $last_modified);

        return $response;
    }

    /**
     * Inform the browser nothing has changed since last time.
     */
    private function sendNotModified(): response
    {
        $response = new Response($image);
        $response->setPublic();
        $response->setMaxAge(self::CACHE_EXPIRES);
        return $response;
    }

    private function _generateOriginal(DataIterProfilePicture $iter): string
    {
        $photo = $iter->get_stream();

        $imagick = new \Imagick();
        $imagick->readImageFile($photo['photo']);

        // Fix orientation, remove exif data
        $this->imageUtils->reorient($imagick);
        $this->imageUtils->stripExif($imagick);

        // Write the image as a progressive JPEG
        $imagick->setImageFormat('jpeg');
        $imagick->setInterlaceScheme(\Imagick::INTERLACE_PLANE);

        return $imagick->getImageBlob();
    }

    private function _generateScaled(DataIterProfilePicture $iter, string $format, int $width): string
    {
        $photo = $iter->get_stream();

        $imagick = new \Imagick();
        $imagick->readImageFile($photo['photo']);

        // Fix orientation, remove exif data
        $this->imageUtils->reorient($imagick);
        $this->imageUtils->stripExif($imagick);

        // Crop to square
        if ($format == self::FORMAT_SQUARE)
        {
            $y = intval(0.05 * $imagick->getImageHeight()); // Approximate location of face in PhotoCee portraits
            $size = min($imagick->getImageWidth(), $imagick->getImageHeight());

            if ($y + $size > $imagick->getImageHeight())
                $y = 0;

            $imagick->cropImage($size, $size, 0, $y);
        }

        // Scale to target width
        $imagick->scaleImage($width, 0);

        // Write the image as a progressive JPEG
        $imagick->setImageFormat('jpeg');
        $imagick->setInterlaceScheme(\Imagick::INTERLACE_PLANE);

        return $imagick->getImageBlob();
    }

    private function _generatePlaceholder(DataIterMember $member, string $format, int $width, bool $private = false): string
    {
        // Determine text
        if ($private)
            $text = '?';
        else
            $text = mb_strtoupper(sprintf('%s%s',
                mb_substr(trim($member->get('voornaam')), 0, 1),
                mb_substr(trim($member->get('achternaam')), 0, 1)
            ));

        // Determine dimensions
        switch ($format)
        {
            case self::FORMAT_SQUARE:
                $height = $width;
                break;

            case self::FORMAT_PORTRAIT:
            default:
                $height = 1.5 * $width;
                break;
        }

        $imagick = new \Imagick();
        $draw = new \ImagickDraw();

        // Get semi-random background colour. The magic numbers constrain the
        // colour to not be too light or dark.
        $hash = md5($member->get('voornaam') . $member->get('achternaam'));
        $random_r = hexdec(substr($hash, 0, 2));
        $random_g = hexdec(substr($hash, 2, 2));
        $random_b = hexdec(substr($hash, 4, 2));

        $s_r = 0.213 * $random_r;
        $s_g = 0.715 * $random_g;

        $random_b = max($random_b, (0.5 - ($s_r + $s_g)) / 0.072);

        $s_b = 0.072 * $random_b;

        // Apply colours
        $background = new \ImagickPixel(sprintf('#%02x%02x%02x', $random_r, $random_g, $random_b));
        $foreground = '#fff';

        // Create the background layer
        $imagick->newImage($width, $height, $background);

        // Create text layer
        $draw->setFillColor($foreground);
        $draw->setFont(realpath('assets/fonts/FiraSans-Regular.ttf'));
        $draw->setFontSize($width / 2);
        $draw->setTextAntialias(true);

        $metrics = $imagick->queryFontMetrics($draw, $text);

        $imagick->annotateImage($draw,
            ($width - $metrics['textWidth']) / 2, // x
            ($width - $metrics['boundingBox']['y2']) / 2 + $metrics['boundingBox']['y2'], // y
            0, // angle
            $text
        );

        // Write thje image as a PNG
        $imagick->setImageFormat('png');

        return $imagick->getImageBlob();
    }

    /**
     * Serve cached original version of the profile picture.
     */
    private function _serveCachedOriginal(Request $request, DataIterProfilePicture $iter): response
    {
        $cache = $this->profilePicturesCache;

        $key = sprintf('%d_original', $iter->get_id());

        // Return not modified if no changes since the client last checked
        $last_modified = gmdate(DATE_RFC1123, $iter->get_mtime());
        if ($cache->hasItem($key) && $request->headers->get('if-modified-since') == $last_modified)
            return $this->sendNotModified();

        // Get image and serve
        $image = $cache->get($key, function (ItemInterface $item) use ($iter): string {
            $item->tag(sprintf('member_%d_picture', $iter['member_id']));
            return $this->_generateOriginal($iter);
        });

        return $this->sendImage($image, $last_modified);
    }

    /**
     * Serve cached scaled version of the profile picture.
     */
    private function _serveCachedScaled(Request $request, DataIterProfilePicture $iter, string $format, int $width): response
    {
        $cache = $this->profilePicturesCache;

        $key = sprintf('%d_%s_%d', $iter->get_id(), $format, $width);

        // Return not modified if no changes since the client last checked
        $last_modified = gmdate(DATE_RFC1123, $iter->get_mtime());
        if ($cache->hasItem($key) && $request->headers->get('if-modified-since') == $last_modified)
            return $this->sendNotModified();

        // Get image and serve
        $image = $cache->get($key, function (ItemInterface $item) use ($iter, $format, $width): string {
            $item->tag(sprintf('member_%d_picture', $iter['member_id']));
            return $this->_generateScaled($iter, $format, $width);
        });

        return $this->sendImage($image, $last_modified);
    }

    /**
     * Serve cached placeholder.
     *
     * This needs to overcome two challenges unique to the placeholder:
     * 1. The placeholder needs to be updated when a member changes their name.
     * 2. The client needs a last modified date, but that's not available in
     *    this context as there's no upload date.
     *
     * To this end, we cache some metadata with a hash of the members name in
     * the key. The metadata contains a last upated date, and the hash in the
     * key helps us invalidate the cache on a name change.
     */
    private function _serveCachedPlaceholder(Request $request, DataIterMember $member, string $format, int $width): response
    {
        $cache = $this->profilePicturesCache;

        $private = $member->is_private('naam');
        $hash = md5($member->get_full_name(ignorePrivacy: true));

        // Determine keys and tags
        $key = sprintf('placeholder_%s_%d_%s_%d', ($private ? 'private' : 'public'), $member->get_id(), $format, $width);
        $meta_key = sprintf('placeholder_meta_%d_%s', $member->get_id(), $hash);
        $tag = sprintf('member_%d_placeholder', $member->get_id());

        // Invalidate the cache on name change
        if (!$cache->hasItem($meta_key))
            $cache->invalidateTags([$tag]);

        // Cache last modified as metadata
        $last_modified = $cache->get($meta_key, function (ItemInterface $item) use ($tag): string {
            $item->tag($tag);
            return gmdate(DATE_RFC1123);
        });

        // Return not modified if no changes since the client last checked
        if ($cache->hasItem($key) && $request->headers->get('if-modified-since') == $last_modified)
            return $this->sendNotModified();

        // Get image and serve
        $image = $cache->get($key, function (ItemInterface $item) use ($tag, $member, $format, $width, $private): string {
            $item->tag($tag);
            return $this->_generatePlaceholder($member, $format, $width, $private);
        });

        return $this->sendImage($image, $last_modified);
    }

    /**
     * Render profile picture review page. Semantically, this is a list of
     * profile pictures, and thus the index. But it doesn't need to exist for
     * most people.
     */
    #[Route('/profile_pictures', name: 'profile_pictures.list', methods: ['GET'])]
    public function list(): Response
    {
        $iter = $this->model->new_iter(['reviewed' => false]);

        if (!$this->policy->userCanReview($iter))
            throw $this->createNotFoundException('Page not found.');

        return $this->render('profile_pictures/list.html.twig', [
            'unreviewed' => $this->model->find(['reviewed' => false]),
            'all' => $this->model->find(['created_on__gt' => new \DateTime('6 months ago')]),
        ]);
    }

    /**
     * Replaces a member's profile picture, or creates one if none exist. Also
     * clears cache for this member's pictures.
     */
    #[Route('/profile_pictures/create', name: 'profile_pictures.create', methods: ['GET', 'POST'])]
    public function create(Authentication $auth, Request $request, UrlUtils $urlUtils): Response|RedirectResponse
    {
        $iter = $this->model->new_iter();
        $iter->set('member_id', $request->query->get('member_id', $auth->getIdentity()->get('id')));

        // Make sure the member we just set does exist…
        $member = $iter->get_member();
        if (!$member)
            throw $this->createNotFoundException('Member not found.');

        if (!$this->policy->userCanCreate($iter))
            throw new UnauthorizedException('You are not allowed to upload a profile picture.');

        $form = $this->createForm(ProfilePictureType::class, null, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form['photo']->getData();
            $this->profilePicturesCache->invalidateTags([sprintf('member_%d_picture', $iter['member_id'])]);

            $fh = fopen($file->getPathname(), 'rb');

            if (!$fh)
                throw new \RuntimeException(__('The uploaded file could not be opened.'));

            $this->model->set_for_member($member, $fh);

            fclose($fh);

            $referrer = $request->query->get(
                'referrer',
                $this->generateUrl('profile.profile', ['member_id' => $member->get_id()])
            );
            return $this->redirect($urlUtils->validateRedirect($referrer));
        }

        return $this->render('profile_pictures/form.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    /**
     * View a specific profile picture.
     * This view obeys the read policy for Profile Pictures. This policy should
     * mostly follow the member's photo privacy settings, but not for admins and
     * the member themselve. Use of this view should be limited to situations in
     * which the admin or member needs to see the photo. Refer to run_member for
     * all other uses.
     */
    #[Route(
        '/profile_pictures/{id<\d+>}/{format}/{width<\d+>}',
        name: 'profile_pictures.single',
        methods: ['GET'],
        requirements: ['format' => self::FORMAT_SQUARE . '|' . self::FORMAT_PORTRAIT . '|' . self::FORMAT_ORIGINAL],
        priority: -1 // Greedy, so needs to be matched last
    )]
    public function single(
        Authentication $auth,
        Request $request,
        int $id,
        string $format = self::FORMAT_SQUARE,
        int $width = self::MAX_WIDTH,
    ): Response
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanRead($iter))
            throw new UnauthorizedException('You are not allowed to see this profile picture.');

        $width = min($width, self::MAX_WIDTH);

        if ($format == self::FORMAT_ORIGINAL)
            return $this->_serveCachedOriginal($request, $iter);
        else
            return $this->_serveCachedScaled($request, $iter, $format, $width);
    }

    /**
     * View a member's profile picture.
     * This view obeys the member's photo privacy settings at all time, and is
     * therefore the preferred way to display a profile picture.
     */
    #[Route(
        '/profile/{member_id<\d+>}/picture/{format}/{width<\d+>}',
        name: 'profile_pictures.member',
        methods: ['GET'],
        requirements: ['format' => self::FORMAT_SQUARE . '|' . self::FORMAT_PORTRAIT . '|' . self::FORMAT_ORIGINAL]
    )]
    public function member(
        Authentication $auth,
        DataModelMember $memberModel,
        Request $request,
        int $member_id,
        string $format = self::FORMAT_SQUARE,
        int $width = self::MAX_WIDTH,
    ): Response
    {
        $member = $memberModel->get_iter($member_id); // Throws 404 if not exists

        $width = min($width, self::MAX_WIDTH);

        if ($format == self::FORMAT_ORIGINAL) {
            if ($memberModel->is_private($member, 'foto'))
                throw new UnauthorizedException('Photo is private');
            if (!$member->get_profile_picture())
                throw $this->createNotFoundException('Member has no photo.');
            return $this->_serveCachedOriginal($request, $member->get_profile_picture());
        } elseif ($memberModel->is_private($member, 'foto') || !$member->get_profile_picture()) {
            return $this->_serveCachedPlaceholder($request, $member, $format, $width);
        } else {
            return $this->_serveCachedScaled($request, $member->get_profile_picture(), $format, $width);
        }
    }

    /**
     * Deletes a profile picture. Sends a message to the member if the action
     * was performed by an admin.Also clears cache for this member's pictures.
     */
    #[Route('/profile_pictures/{id<\d+>}/delete', name: 'profile_pictures.delete', methods: ['GET', 'POST'])]
    public function delete(
        Authentication $auth,
        MailerInterface $mailer,
        Request $request,
        UrlUtils $urlUtils,
        int $id
    ): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanDelete($iter))
            throw new UnauthorizedException('You are not allowed to delete this profile picture.');

        $referrer = $request->query->get('referrer', $this->generateUrl('profile_pictures.list'));
        $data = [
            'referrer' => $referrer,
        ];

        $builder = $this->createFormBuilder($data, ['csrf_token_id' => 'profile_picture_delete_' . $iter->get_id()]);

        if ($iter['member_id'] != $auth->getIdentity()->get('id'))
            $builder->add('reason', TextareaType::class, [
                'label' => __('Reason for deletion'),
                'required' => true,
                'help' => __('You’re deleting someone else’s profile picture. They’ll be notified, so tell them why you deleted it.'),
            ]);


        $builder->add('referrer', HiddenType::class, ['required' => false]);
        $builder->add('submit', SubmitType::class, ['label' => __('Delete'), 'color' => 'danger']);

        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->model->delete($iter);
            $this->profilePicturesCache->invalidateTags([sprintf('member_%d_picture', $iter['member_id'])]);

            if ($iter['member_id'] != $auth->getIdentity()->get('id')) {
                $member = $iter->get_member();

                $email = (new TemplatedEmail())
                    ->to($member['email'])
                    ->subject("[Cover] Profile picture deleted")
                    ->htmlTemplate('emails/profile_picture_deleted.html.twig')
                    ->textTemplate('emails/profile_picture_deleted.txt.twig')
                    ->context([
                        'reason' => $form->get('reason')->getData(),
                    ])
                ;
                $mailer->send($email);
                $this->addFlash('notice',  sprintf(
                    __('%s has been notified their profile picture was deleted.'),
                    $member->get_full_Name()
                ));
            }

            return $this->redirect($urlUtils->validateRedirect(
                $form['referrer']->getData() ?? $this->generateUrl('profile_pictures.list')
            ));
        }

        return $this->render('profile_pictures/confirm_delete.html.twig', [
            'iter' => $iter,
            'form' => $form,
        ]);
    }

    /**
     * Mark profile picture as reviewed
     */
    #[Route('/profile_pictures/{id<\d+>}/review', name: 'profile_pictures.review', methods: ['POST'])]
    public function review(Authentication $auth, Request $request, int $id): RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanReview($iter))
            throw new UnauthorizedException('You are not allowed to review this profile picture.');

        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'profile_picture_review_' . $iter->get_id()])
            ->add('submit', SubmitType::class, ['label' => 'Review'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $iter['reviewed'] = true;
            $this->model->update($iter);
        }

        return $this->redirectToRoute('profile_pictures.list');
    }
}
