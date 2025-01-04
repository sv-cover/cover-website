<?php

namespace App\Controller;

use App\DataModel\DataModelCommissie;
use App\DataModel\DataModelMember;
use App\Exception\UnauthorizedException;
use App\Form\VacancyType;
use App\Legacy\Authentication\Authentication;
use App\Legacy\Policy\Policy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use ZipStream;

class AlmanakController extends AbstractController
{
    public function __construct(
        private Authentication $auth,
        private DataModelMember $model,
        private Policy $policy,
    ) {
    }

    public function almanakSearch(Request $request, string $search, ?int $limit = null): Response
    {
        $iters = $this->model->search_name($search, $limit);
        $iters = \array_filter($iters, [$this->policy, 'userCanRead']);

        // Filter out everyone that doesn't want to be found by their name
        if (!$this->auth->getIdentity()->member_in_committee(DataModelCommissie::BOARD)
            && !$this->auth->getIdentity()->member_in_committee(DataModelCommissie::CANDY))
            $iters = \array_filter($iters, fn($iter) => !$iter->is_private('naam'));

        if ($request->getPreferredFormat() === 'json')
            $response = $this->json(\array_map(
                fn($iter) => [
                    'id' => $iter->get_id(),
                    'starting_year' => $iter->get('beginjaar'),
                    'first_name' => $iter->get_first_name(),
                    'name' => $iter->get_full_name(),
                ],
                \array_values($iters)
            ));
        else
            $response = $this->render('almanak/almanak.html.twig', [
                'iters' => \array_values($iters),
            ]);
        $response->headers->set('Vary', 'Accept'); // Prevent caching issues
        return $response;
    }

    public function almanakYear($year): Response
    {
        $iters = $this->model->get_from_search_year($year);

        return $this->render('almanak/almanak.html.twig', [
            'iters' => array_filter($iters, [$this->policy, 'userCanRead']),
        ]);
    }

    public function almanakStatus($status): Response
    {
        if (!$this->auth->getIdentity()->member_in_committee(DataModelCommissie::BOARD)
            && !$this->auth->getIdentity()->member_in_committee(DataModelCommissie::CANDY))
            throw new UnauthorizedException('You are not allowed to search by status');

        $iters = $this->model->get_from_status($status);

        return $this->render('almanak/almanak.html.twig', [
            'iters' => array_filter($iters, [$this->policy, 'userCanRead']),
        ]);
    }

    #[Route('/almanak', name: 'almanak', methods: ['GET'])]
    public function almanak(
        Request $request,
        #[MapQueryParameter] ?string $search = null,
        #[MapQueryParameter] ?int $year = null,
        #[MapQueryParameter] ?int $status = null,
        #[MapQueryParameter] ?int $limit = null,
    ): Response
    {
        if (isset($search))
            return $this->almanakSearch($request, $search, $limit);
        elseif (isset($year))
            return $this->almanakYear($year);
        elseif (isset($status))
            return $this->almanakStatus($status);
        return $this->render('almanak/almanak.html.twig', [
            'iters' => null,
        ]);
    }

    private function streamCsv(array $iters): void
    {
        // Add Unicode byte order marker for Excel
        echo chr(239) . chr(187) . chr(191);

        $out = fopen('php://output', 'w');

        // column headers
        fputcsv($out, [
            'id',
            'voornaam',
            'tussenvoegsel',
            'achternaam',
            'naam',
            'adres',
            'postcode',
            'woonplaats',
            'email',
            'geboortedatum',
            'telefoonnummer',
            'studie',
            'beginjaar',
            'status',
        ]);

        flush();

        foreach ($iters as $item) {
            fputcsv($out, [
                $item['id'],
                $item['voornaam'],
                $item['tussenvoegsel'],
                $item['achternaam'],
                $item->get_full_name(ignorePrivacy: true),
                $item['adres'],
                $item['postcode'],
                $item['woonplaats'],
                $item['email'],
                $item['geboortedatum'],
                $item['telefoonnummer'], // TODO: Format
                $item['studie'],
                $item['beginjaar'],
                $item['status'],
            ]);
            flush();
        }
    }

    #[Route('/almanak/export/csv', name: 'almanak.export.csv', methods: ['GET'])]
    public function exportCsv(): StreamedResponse
    {
        if (!$this->auth->getIdentity()->member_in_committee(DataModelCommissie::YEARBOOKCEE))
            throw new UnauthorizedException('Only members of the YearbookCee committee are allowed to download these dumps.');

        // TODO: Refactor to not need this function - Martijn Luinstra 2024-11
        $iters = $this->model->get_from_search_first_last(null, null);

        $iters = \array_filter($iters, [$this->policy, 'userCanRead']);

        // Filter all hidden information (set the field to null)
        $privacy_fields = $this->model->get_privacy();

        foreach ($iters as $iter)
        {
            foreach ($iter->data as $field => $value)
                if (\array_key_exists($field, $privacy_fields))
                    if (($this->model->get_privacy_for_field($iter, $field) & 1) === 0)
                        $iter->data[$field] = null;

            $iter->data['status'] = $iter->get_status($iter);

            // TODO: Does this even work? - Martijn Luinstra 2024-11
            $iter->data['studie'] = \implode(', ', $iter->get('studie'));
        }

        $response = new StreamedResponse(function() use ($iters): void {
            $this->streamCsv($iters);
        });
        $response->headers->set('Content-Description:', 'File Transfer');
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="almanak-' . date('Y-m-d') . '.csv"');
        $response->headers->set('X-Accel-Buffering', 'no');
        return $response;
    }

    private function streamPhotoZip(array $iters): void
    {
        $zip = new ZipStream\ZipStream(
            defaultEnableZeroHeader: true,
            sendHttpHeaders: false,
            defaultCompressionMethod: ZipStream\CompressionMethod::STORE,
        );

        foreach ($iters as $iter) {
            // Skip all members that have hidden their photo
            if (($this->model->get_privacy_for_field($iter, 'foto') & $this->model::VISIBLE_TO_MEMBERS) === 0)
                continue;

            $profile_picture = $iter->get_profile_picture();

            if ($profile_picture === null)
                continue;

            $data = $profile_picture->get_stream();

            // Skip members that don't have a photo
            if ($data === null)
                continue;

            // And finally add the photo to the actual stream
            $zip->addFileFromStream(
                fileName: sprintf('%d.jpg', $iter->get_id()),
                stream: $data['photo'],
                lastModificationDateTime: new \DateTime($profile_picture['created_on']),
            );
        }

        $zip->finish();
    }

    #[Route('/almanak/export/photos', name: 'almanak.export.photos', methods: ['GET'])]
    public function exportPhotos(): StreamedResponse
    {
        if (!$this->auth->getIdentity()->member_in_committee(DataModelCommissie::YEARBOOKCEE))
            throw new UnauthorizedException('Only members of the YearbookCee committee are allowed to download these dumps.');

        // Disable PHP's time limit
        \set_time_limit(0);

        // Make sure we stop when the user is no longer listening
        \ignore_user_abort(false);

        // TODO: Refactor to not need this function - Martijn Luinstra 2024-11
        $iters = $this->model->get_from_search_first_last(null, null);

        $iters = \array_filter($iters, [$this->policy, 'userCanRead']);

        $response = new StreamedResponse(function() use ($iters): void {
            $this->streamPhotoZip($iters);
        });
        $response->headers->set('Content-Description:', 'File Transfer');
        $response->headers->set('Content-Type', 'application/x-zip');
        $response->headers->set('Content-Disposition', 'attachment; filename="almanak-' . date('Y-m-d') . '.zip"');
        $response->headers->set('Content-Transfer-Encodin', 'binary');
        $response->headers->set('X-Accel-Buffering', 'no');
        return $response;
    }
}
