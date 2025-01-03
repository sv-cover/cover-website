<?php

namespace App\Controller;

use App\DataIter\DataIterPhoto;
use App\DataIter\DataIterPhotobook;
use App\DataIter\DataIterRootPhotobook;
use App\DataModel\DataModelPhotobook;
use App\DataModel\DataModelPhotobookFace;
use App\DataModel\DataModelPhotobookReactie;
use App\Exception\UnauthorizedException;
use App\Form\PhotoBookType;
use App\Form\PhotoType;
use App\Legacy\Database\DatabasePDO;
use App\Service\Authentication;
use App\Service\Policy;
use App\Utils\PhotoBookUtils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use ZipStream;


#[Route('/photos', requirements: ['book_id' => '\d+|liked|member(_\d+)+'])]
class PhotoBooksController extends AbstractController
{
    private AsciiSlugger $slugger;

    public function __construct(
        private DatabasePDO $db,
        private DataModelPhotobook $model,
        private Policy $policy,
        private PhotoBookUtils $photoBookUtils,
    ) {
        // No autowiring for custom options
        $this->slugger = new AsciiSlugger('en', ['en' => ['/' => '_', '\\' => '_']]);
    }

    #[Route('/', name: 'photos', methods: ['GET'])]
    #[Route('/{book_id}', name: 'photo_books.single', methods: ['GET'])]
    public function single(
        Authentication $auth,
        DataModelPhotobookReactie $commentModel,
        ?string $book_id = null,
    ): Response
    {
        $book = $this->photoBookUtils->getBook($book_id);

        if (!$this->policy->userCanRead($book))
            throw new UnauthorizedException();

        $context = [
            'book' => $book,
        ];

        if ($book instanceof DataIterRootPhotobook) {
            $comments = $commentModel->get_latest(10);
            $recentComments = [];

            foreach ($comments as $comment) {
                if (!isset($recentComments[$comment['foto']]))
                    $recentComments[$comment['foto']] = [$comment];
                else
                    $recentComments[$comment['foto']][] = $comment;
            }

            $context['recent_comments'] = $recentComments;
        }

        $response = $this->render('photos/books/single.html.twig', $context);

        if ($auth->loggedIn && $this->policy->userCanMarkAsRead($book))
            $this->model->mark_read($auth->identity->get('id'), $book);

        return $response;
    }

    #[Route('/{book_id}/create', name: 'photo_books.create', methods: ['GET', 'POST'])]
    public function create(Request $request, string $book_id): Response|RedirectResponse
    {
        $book = $this->photoBookUtils->getBook($book_id)->new_book();

        if (!$this->policy->userCanCreate($book))
            throw new UnauthorizedException('You are not allowed to create new photo books inside this photo book.');

        $form = $this->createForm(PhotoBookType::class, $book, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $new_book_id = $this->model->insert_book($book);
            return $this->redirectToRoute('photo_books.single', ['book_id' => $new_book_id]);
        }

        return $this->render('photos/books/form.html.twig', [
            'book' => $book,
            'form' => $form,
        ]);
    }

    #[Route('/{book_id}/update', name: 'photo_books.update', methods: ['GET', 'POST'])]
    public function update(Request $request, string $book_id): Response|RedirectResponse
    {
        $book = $this->photoBookUtils->getBook($book_id);

        if (!$this->policy->userCanUpdate($book))
            throw new UnauthorizedException('You are not allowed to edit this photobook.');

        $form = $this->createForm(PhotoBookType::class, $book, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->model->update_book($book);
            return $this->redirectToRoute('photo_books.single', ['book_id' => $book->get_id()]);
        }

        return $this->render('photos/books/form.html.twig', [
            'book' => $book,
            'form' => $form,
        ]);
    }

    #[Route('/{book_id}/delete', name: 'photo_books.delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, string $book_id): Response|RedirectResponse
    {
        $book = $this->photoBookUtils->getBook($book_id);

        if (!$this->policy->userCanDelete($book))
            throw new UnauthorizedException('You are not allowed to delete this photobook.');

        $form = $this->createFormBuilder()
            ->add('titel', TextType::class, [
                'label' => __('To confirm, enter the name of the photo book you are about to entirely delete'),
                'constraints' => [
                    new Assert\Callback(function ($value, ExecutionContextInterface $context, $payload) use ($book) {
                        if ($book->get('titel') != $value)
                            $context->buildViolation(__('Name doesn’t match book name.'))
                                ->atPath('password')
                                ->addViolation();
                    }),
                ],
            ])
            ->add('submit', SubmitType::class, ['label' => __('Delete photo book')])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->model->delete_book($book);
            return $this->redirectToRoute('photo_books.single', ['book_id' => $book['parent_id']]);
        }

        return $this->render('photos/books/confirm_delete_book.html.twig', [
            'book' => $book,
            'form' => $form,
        ]);
    }

    #[Route('/{book_id}/add_photos', name: 'photo_books.add_photos', methods: ['GET', 'POST'])]
    public function addPhotos(
        DataModelPhotobookFace $faceModel,
        Request $request,
        string $book_id,
    ): Response|RedirectResponse
    {
        $book = $this->photoBookUtils->getBook($book_id);

        if (!$this->policy->userCanUpdate($book))
            throw new UnauthorizedException('You are not allowed to add photos to this photobook.');

        $form = $this->createFormBuilder(null)
            ->add('photos', CollectionType::class, [
                'label' => __('Photos'),
                'entry_type' => PhotoType::class,
                'entry_options' => [
                    'add_photo' => true
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'delete_empty' =>  function ($value = []) {
                    return empty($value['add']);
                },
                'prototype_data' => [
                    'add' => true,
                ],
                'mapped' => false,
            ])
            ->add('submit', SubmitType::class, ['label' => __('Re-run face detection')])
            ->getForm();
        $form->handleRequest($request);

        $errors = [];

        if ($form->isSubmitted() && $form->isValid()) {
            $photos = [];

            foreach ($form['photos']->getData() as $photo) {
                try {
                    $iter = new DataIterPhoto($this->model, -1, [
                        'boek' => $book->get_id(),
                        'beschrijving' => $photo['beschrijving'],
                        'filepath' => $photo['filepath']
                    ]);

                    if (!$iter->file_exists())
                        throw new \Exception("File not found");

                    $id = $this->model->insert($iter);

                    $photos[] = new DataIterPhoto($this->model, $id, $iter->data);
                } catch (\Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }

            if (count($photos)) {
                // Update photo book last_update timestamp
                $book['last_update'] = new \DateTime();
                $this->model->update_book($book);

                // Update faces (but re-run on all photos to align clusters)
                $faceModel->refresh_faces($book->get_photos());
            }

            if (count($errors) == 0)
                return $this->redirectToRoute('photo_books.single', ['book_id' => $book->get_id()]);
        }

        return $this->render('photos/books/add_photos.html.twig', [
            'book' => $book,
            'errors' => $errors,
            'form' => $form,
        ]);
    }

    #[Route('/{book_id}/add_photos/list_dirs', name: 'photo_books.add_photos.list_dirs', methods: ['GET'])]
    public function addPhotosListDirs(
        string $book_id,
        #[MapQueryParameter] ?string $path = null,
    ): Response
    {
        $book = $this->photoBookUtils->getBook($book_id);

        if (!$this->policy->userCanUpdate($book))
            throw new UnauthorizedException();

        $photosDir = $this->getParameter('app.photos_dir');

        if (!empty($path))
            $fsPath = PhotoBookUtils::path_concat($photosDir, $path);
        else
            $fsPath = $photosDir;

        $entries = [];
        foreach (new \FilesystemIterator($fsPath) as $entry)
            if (is_dir($entry))
                $entries[] = PhotoBookUtils::path_subtract($entry, $photosDir);
        rsort($entries);

        return $this->json($entries);
    }

    #[Route('/{book_id}/add_photos/list_photos', name: 'photo_books.add_photos.list_photos', methods: ['GET'])]
    public function addPhotosListPhotos(
        string $book_id,
        #[MapQueryParameter] ?string $path = null,
    ): StreamedResponse
    {
        $book = $this->photoBookUtils->getBook($book_id);

        if (!$this->policy->userCanUpdate($book))
            throw new UnauthorizedException();

        $photosDir = $this->getParameter('app.photos_dir');
        $fsPath = PhotoBookUtils::path_concat($photosDir, $path);

        $iter = \is_dir($fsPath) ? new \FilesystemIterator($fsPath) : [];

        $event = function(string $event, ?string $data = ''): void {
            echo "event: $event\ndata: $data\n\n";
            flush();
        };

        $response = new StreamedResponse(function() use ($iter, $event, $photosDir, $book): void {
            $bookPhotos = $book->get_photos(); // Only query once
            foreach ($iter as $entry)
            {
                try {
                    if (!\preg_match('/\.(je?pg|gif)$/i', $entry))
                        continue;

                    $id = null;
                    $description = '';
                    $filePath = PhotoBookUtils::path_subtract($entry, $photosDir);

                    // Find existing photo
                    foreach ($bookPhotos as $photo) {
                        if ($photo->get('filepath') == $filePath) {
                            $id = $photo->get_id();
                            $description = $photo->get('beschrijving');
                            break;
                        }
                    }

                    $exif_data = @\exif_read_data($entry);

                    if ($exif_data === false)
                        $exif_data = ['FileDateTime' => \filemtime($entry)];

                    if ($exif_thumbnail = @\exif_thumbnail($entry, $th_width, $th_height, $th_image_type))
                        $thumbnail = sprintf(
                            'data:%s;base64,%s',
                            image_type_to_mime_type($th_image_type),
                            base64_encode($exif_thumbnail),
                        );
                    else
                        $thumbnail = null;

                    $event('photo', \json_encode([
                        'id' => $id,
                        'description' => (string) $description,
                        'path' => $filePath,
                        'created_on' => \date('Y-m-d H:i:s',
                            isset($exif_data['DateTimeOriginal'])
                                ? \strtotime($exif_data['DateTimeOriginal'])
                                : $exif_data['FileDateTime']),
                        'thumbnail' => $thumbnail,
                    ]));

                } catch (\Exception $e) {
                    $event('error', json_encode([
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTrace()
                    ]));
                }
            }
            $event('end');
        });
        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');
        return $response;
    }

    #[Route('/{book_id}/delete_photos', name: 'photo_books.delete_photos', methods: ['GET', 'POST'])]
    public function deletePhotos(
        Request $request,
        string $book_id,
        #[MapQueryParameter] array $photo_id,
    ): Response
    {
        $book = $this->photoBookUtils->getBook($book_id);

        if (!$this->policy->userCanUpdate($book))
            throw new UnauthorizedException('You are not allowed to delete photos from this photobook.');

        $photos = [];
        foreach ($photo_id as $id)
            if ($photo = $this->model->get_iter($id))
                $photos[] = $photo;

        $form = $this->createFormBuilder(null, ['action' => $request->getUri()])
            ->add('submit', SubmitType::class, ['label' => __('Delete photos')])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($photos as $photo)
                $this->model->delete($photo);
            return $this->redirectToRoute('photo_books.single', ['book_id' => $book->get_id()]);
        }

        return $this->render('photos/books/confirm_delete_photos.html.twig', [
            'book' => $book,
            'photos' => $photos,
            'form' => $form,
        ]);
    }


    #[Route('/{book_id}/update_photo_order', name: 'photo_books.update_photo_order', methods: ['POST'])]
    public function updatePhotoOrder(Request $request, string $book_id): Response
    {
        $order = $request->getPayload()->all('order');

        if (!$order)
            throw new BadRequestHttpException('Order parameter missing');

        $book = $this->photoBookUtils->getBook($book_id);

        if (!$this->policy->userCanUpdate($book))
            throw new UnauthorizedException('You are not allowed to edit this photobook.');

        foreach ($book->get_photos() as $photo) {
            $index = \array_search($photo->get_id(), $order);

            if ($index === false)
                continue;

            $photo->set('sort_index', $index);
            $this->model->update($photo);
        }

        return $response = new Response();
    }

    #[Route('/{book_id}/update_book_order', name: 'photo_books.update_book_order', methods: ['POST'])]
    public function updateBookOrder(Request $request, string $book_id): Response
    {
        $order = $request->getPayload()->all('order');

        if (!$order)
            throw new BadRequestHttpException('Order parameter missing');

        $parent = $this->photoBookUtils->getBook($book_id);

        if (!$this->policy->userCanUpdate($parent))
            throw new UnauthorizedException('You are not allowed to edit this photobook.');

        foreach ($parent->get_books() as $book) {
            $index = \array_search($book->get_id(), $order);

            if ($index === false)
                continue;

            $book->set('sort_index', $index);
            $this->model->update_book($book);
        }

        return $response = new Response();
    }

    #[Route('/{book_id}/face_detection', name: 'photo_books.face_detection', methods: ['GET', 'POST'])]
    public function faceDetection(
        Request $request,
        DataModelPhotobookFace $faceModel,
        ?string $book_id = null,
    ): Response
    {
        $book = $this->photoBookUtils->getBook($book_id);

        if (!$this->policy->userCanRead($book))
            throw new UnauthorizedException();

        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'cluster_photos_' . $book->get_id()])
            ->add('submit', SubmitType::class, ['label' => __('Re-run face detection')])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->policy->userCanUpdate($book))
                throw new UnauthorizedException();

            $photos = $book->get_photos();
            $faceModel->refresh_faces($photos);
        }

        $faces = $faceModel->get_for_book($book);

        $clusters = ['null' => []];
        foreach ($faces as $face) {
            $clusterId = $face['cluster_id'] ? strval($face['cluster_id']) : 'null';
            if (!isset($clusters[$clusterId]))
                $clusters[$clusterId] = [];

            $memberId = $face['lid_id'];
            if (!isset($clusters[$clusterId][$memberId]))
                $clusters[$clusterId][$memberId] = [];

            $clusters[$clusterId][$memberId][] = $face;
        }

        return $this->render('photos/books/face_detection.html.twig', [
            'book' => $book,
            'clusters' => $clusters,
            'form' => $form,
        ]);
    }

    #[Route('/{book_id}/mark_read', name: 'photo_books.mark_read', methods: ['POST'])]
    public function markRead(Authentication $auth, Request $request, string $book_id): RedirectResponse
    {
        $book = $this->photoBookUtils->getBook($book_id);

        if (!$this->policy->userCanMarkAsRead($book))
            throw new UnauthorizedException();

        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'mark_book_read_' . $book->get_id()])
            ->add('submit', SubmitType::class)
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $auth->loggedIn)
            $this->model->mark_read_recursively($auth->identity->get('id'), $book);

        return $this->redirectToRoute('photo_books.single', ['book_id' => $book->get_id()]);
    }

    private function streamZip(DataIterPhotobook $root): void
    {
        $books = [$root];

        // Make a list of all the books to be added to the zip
        // but filter out the books I can't read.
        for ($i = 0; $i < count($books); ++$i)
            foreach ($books[$i]['books_without_metadata'] as $child)
                if ($this->policy->userCanDownloadBook($child))
                    $books[] = $child;

        // Turn that list into a hashtable linking book id to book instance.
        $books = \array_combine(\array_column($books, 'id'), $books);

        $zip = new ZipStream\ZipStream(
            defaultEnableZeroHeader: true,
            sendHttpHeaders: false,
            defaultCompressionMethod: ZipStream\CompressionMethod::STORE,
        );

        // Now for each book find all photos and add them to the zip stream
        foreach ($books as $book)
        {
            // Create a path back to the root book to find a good file name
            $ancestors = [$book];

            while (
                end($ancestors)->get_id() != $root->get_id()
                && end($ancestors)->has_value('parent_id')
                && isset($books[end($ancestors)->get('parent_id')])
            )
                $ancestors[] = $books[end($ancestors)->get('parent_id')];

            $bookPath = \implode('/', \array_map(
                fn($b): string => (new \DateTime($b['date']))->format('Y-m-d') . '_' . $this->slugger->slug($b['titel']),
                \array_reverse($ancestors)
            ));

            foreach ($book->get_photos() as $photo)
            {
                // Skip originals we cannot find in this output. Very bad indeed, but not
                // something that should block downloading of the others.
                if (!$photo->file_exists())
                    continue;

                // Skip things that are not files. Apparently, there are some of those…
                if (!\is_file($photo->get_full_path()))
                    continue;

                // Skip photo's you cannot access
                if (!$this->policy->userCanRead($photo))
                    continue;

                // Let's just assume that the filename the photo already has is sane and safe
                $photoPath = $bookPath . '/' . \basename($photo->get('filepath'));

                // Calculate modification time
                if ($photo->has_value('created_on'))
                    $modificationTime = new \DateTime($photo->get('created_on'));
                else
                    $modificationTime = new \DateTime(sprintf('@%d', \filectime($photo->get_full_path())));

                // And finally add the photo to the actual stream
                $zip->addFileFromPath(
                    fileName: $photoPath,
                    path: $photo->get_full_path(),
                    lastModificationDateTime: $modificationTime,
                    comment: $photo->get('beschrijving') ?? '',
                );
            }
        }

        $zip->finish();
    }

    #[Route('/{book_id}/download', name: 'photo_books.download', methods: ['GET'])]
    public function download(Request $request, string $book_id): StreamedResponse
    {
        $book = $this->photoBookUtils->getBook($book_id);

        if (!$this->policy->userCanDownloadBook($book))
            throw new UnauthorizedException();

        // Disable PHP's time limit
        \set_time_limit(0);

        // Make sure we stop when the user is no longer listening
        \ignore_user_abort(false);

        $response = new StreamedResponse(function() use ($book): void {
            $this->streamZip($book);
        });
        $response->headers->set('Content-Description:', 'File Transfer');
        $response->headers->set('Content-Type', 'application/x-zip');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $this->slugger->slug($book->get('titel')) . '.zip"');
        $response->headers->set('Content-Transfer-Encodin', 'binary');
        $response->headers->set('X-Accel-Buffering', 'no');
        return $response;
    }


    #[Route('/{book_id}/download/confirm', name: 'photo_books.download.confirm', methods: ['GET'])]
    public function downloadConfirm(Request $request, string $book_id): Response
    {
        $book = $this->photoBookUtils->getBook($book_id);

        if (!$this->policy->userCanDownloadBook($book))
            throw new UnauthorizedException();

        // Disable PHP's time limit
        \set_time_limit(0);

        // Make sure we stop when the user is no longer listening
        \ignore_user_abort(false);


        $books = [$book];

        // Make a list of all the books to be added to the zip
        // but filter out the books I can't read.
        for ($i = 0; $i < count($books); ++$i)
            foreach ($books[$i]['books_without_metadata'] as $child)
                if ($this->policy->userCanDownloadBook($child))
                    $books[] = $child;

        $totalPhotos = 0;
        $totalFileSize = 0;

        foreach ($books as $child) {
            foreach ($child->get_photos() as $photo) {
                if ($photo->file_exists() && $this->policy->userCanRead($photo)) {
                    $totalPhotos += 1;
                    $totalFileSize += $photo->get_file_size();
                }
            }
        }

        return $this->render('photos/books/confirm_download.html.twig', [
            'book' => $book,
            'total_photos' => $totalPhotos,
            'total_file_size' => $totalFileSize,
        ]);
    }

    #[Route('/competition', name: 'photo_books.competition', methods: ['GET'])]
    public function competition(Authentication $auth): Response
    {
        if (!$auth->loggedIn)
            throw new UnauthorizedException();

        $taggers = $this->db->query(<<<SQL
            SELECT
                l.id,
                l.voornaam,
                COUNT(f_f.id) tags,
                (SELECT
                    fav_l.voornaam
                FROM
                    foto_faces fav_faces
                LEFT JOIN leden fav_l ON
                    fav_l.id = fav_faces.lid_id
                WHERE
                    fav_faces.tagged_by = l.id
                GROUP BY
                    fav_l.id
                ORDER BY
                    COUNT(fav_l.id) DESC
                LIMIT 1) favorite
            FROM
                foto_faces f_f
            LEFT JOIN leden l ON
                l.id = f_f.tagged_by
            WHERE
                f_f.lid_id IS NOT NULL
            GROUP BY
                l.id
            ORDER BY
                tags DESC;
        SQL);

        $tagged = $this->db->query(<<<SQL
            SELECT
                l.id,
                l.voornaam,
                COUNT(f_f.id) tags
            FROM
                foto_faces f_f
            LEFT JOIN leden l ON
                l.id = f_f.lid_id
            WHERE
                f_f.lid_id IS NOT NULL
            GROUP BY
                l.id
            HAVING
                COUNT(f_f.id) > 50
            ORDER BY
                tags DESC
        SQL);

        return $this->render('photos/books/competition.html.twig', [
            'taggers' => $taggers,
            'tagged' => $tagged,
        ]);
    }

    #[Route('/slide', name: 'photo_books.slide', methods: ['GET'])]
    public function slide(): Response
    {
        $book = $this->model->get_random_book();
        $photos = $this->model->get_photos($book);

        shuffle($photos);

        return $this->render('photos/books/slide.html.twig', [
            'book' => $book,
            'photos' => $photos,
        ]);
    }

}
