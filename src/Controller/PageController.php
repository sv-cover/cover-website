<?php

namespace App\Controller;

use App\DataIter\DataIterPage;
use App\DataModel\DataModelBesturen;
use App\DataModel\DataModelCommissie;
use App\DataModel\DataModelPage;
use App\Exception\UnauthorizedException;
use App\Form\PageType;
use App\Service\Authentication;
use App\Markup\Markup;
use App\Service\Policy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class PageController extends AbstractController
{
    public function __construct(
        private DataModelBesturen $boardModel,
        private DataModelCommissie $committeeModel,
        private DataModelPage $model,
        private Policy $policy,
    ){
    }

    #[Route('/page', name: 'page.list', methods: ['GET'])]
    public function list(Authentication $auth): Response|RedirectResponse
    {
        $identity = $auth->getIdentity();
        if (
            !$identity->member_in_committee(COMMISSIE_BESTUUR)
            && !$identity->member_in_committee(COMMISSIE_KANDIBESTUUR)
            && !$identity->member_in_committee(COMMISSIE_EASY)
        )
            return $this->redirectToRoute('homepage'); // we don't have a public index/sitemap

        $iters = $this->model->get();

        // Apply policy
        $iters = array_filter($iters, [$this->policy, 'userCanRead']);

        return $this->render('page/list.html.twig', ['iters' => $iters]);
    }

    #[Route('/page/preview', name: 'page.preview', methods: ['POST'])]
    public function preview(Authentication $auth, Request $request, Markup $markup): Response
    {
        if (!$auth->loggedIn)
            throw new UnauthorizedException();

        return new Response(
            $markup->parse($request->getPayload()->get('content'))
        );
    }

    #[Route('/page/create', name: 'page.create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response|RedirectResponse
    {
        $iter = $this->model->new_iter([
            'committee_id' => COMMISSIE_BESTUUR,
        ]);

        if (!$this->policy->userCanCreate($iter))
            throw new UnauthorizedException('You are not allowed to create pages.');

        $form = $this->createForm(PageType::class, $iter, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $id = $this->model->insert($iter);

            if (!empty($iter['slug']))
                return $this->redirectToRoute('slug', ['slug' => $iter['slug']]);
            return $this->redirectToRoute('page.single', ['id' => $iter->get_id()]);
        }

        return $this->render('page/form.html.twig', [
            'iter' => $iter,
            'form' => $form
        ]);
    }

    private function _render_single(DataIterPage $iter): Response|RedirectResponse
    {
        $committee = $this->committeeModel->get_from_page($iter['id']);
        if (isset($committee))
            return $this->redirectToRoute('committees.single', ['slug' => $committee->get('login')], Response::HTTP_MOVED_PERMANENTLY);

        $board = $this->boardModel->get_from_page($iter['id']);
        if (isset($board))
            return $this->redirectToRoute('boards', ['_fragment' => $board->get('login')], Response::HTTP_MOVED_PERMANENTLY);

        if (!$this->policy->userCanRead($iter))
            throw new UnauthorizedException('You are not allowed to read this page.');

        return $this->render('page/single.html.twig', ['iter' => $iter]);
    }

    #[Route('/page/{id<\d+>}', name: 'page.single', methods: ['GET'])]
    public function single(int $id): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        return $this->_render_single($iter);
    }

    // Extremely low priority, need to be matched last
    #[Route('/{slug}', name: 'slug', methods: ['GET'], priority: -9999)]
    public function slug(string $slug): Response|RedirectResponse
    {
        $iter = $this->model->get_iter_from_slug($slug);

        if (!$iter)
            throw $this->createNotFoundException('Page not found');

        return $this->_render_single($iter);
    }

    private function _prepare_mail(Authentication $auth, DataIterPage $iter, string $difference): array
    {
        $identity = $auth->getIdentity();

        $data = $iter->data;
        $data['member_naam'] = \member_full_name($identity->member(), IGNORE_PRIVACY);
        $data['page'] = $difference;

        $isInBoard = $identity->member_in_committee(COMMISSIE_BESTUUR);
        $isInCommittee = $identity->member_in_committee($iter['committee_id']);

        if (!$isInCommittee && $isInBoard) {
            /* Bestuur changed something, notify commissie */
            $data['commissie_naam'] = $this->committeeModel->get_naam(COMMISSIE_BESTUUR);
            $data['email'] = [$this->committeeModel->get_email($iter['committee_id'])];
        } elseif (!$isInBoard && $isInCommittee) {
            /* Commissie changed something, notify bestuur */
            $data['commissie_naam'] = $this->committeeModel->get_naam($iter['committee_id']);
            $data['email'] = [$this->committeeModel->get_email(COMMISSIE_BESTUUR)];
        } else {
            /* AC/DCee changed something, notify bestuur and commissie */
            $data['commissie_naam'] = $this->committeeModel->get_naam(COMMISSIE_EASY);
            $data['email'] = [
                $this->committeeModel->get_email($iter['committee_id']),
                $this->committeeModel->get_email(COMMISSIE_BESTUUR)
            ];
        }

        return $data;
    }

    #[Route('/page/{id<\d+>}/update', name: 'page.update', methods: ['GET', 'POST'])]
    public function update(int $id, Request $request, Authentication $auth): Response|RedirectResponse
    {
        $iter = $this->model->get_iter($id);

        if (!$this->policy->userCanUpdate($iter))
            throw new UnauthorizedException('You are not allowed to edit this page.');

        $form = $this->createForm(PageType::class, $iter, ['mapped' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $content_fields = [
                'cover_image_url' => 'photo',
                'content_en' => 'content',
            ];

            // Retrieve old data for diffing ($iter has already been updated by the form)
            $old_iter = $this->model->get_iter($iter['id']);

            // Update as usual
            $result = $this->model->update($iter);

            // If the update succeeded (i.e. _validate came through positive)
            // send a notification email to those who are interested.
            if ($result > 0) {
                $updates = [];
                $subjects = [];

                foreach ($content_fields as $field => $name) {
                    // Only notify about changed content, skip equal stuff
                    if ($iter->data[$field] == $old_iter->data[$field])
                        continue;

                    $updates[] = sprintf(
                        "New %1\$s:\n%2\$s\n\nOld %1\$s:\n%3\$s",
                        $name,
                        $iter->data[$field],
                        $old_iter->data[$field],
                    );
                    $subjects[] = sprintf('Page %s', $name);
                }

                // Collect all
                $mail_data = $this->_prepare_mail($auth, $iter, implode("\n\n---\n\n", $updates));

                if (!empty($mail_data['email'])) {
                    $body = \parse_email('editable_edit.txt', $mail_data);

                    $subject = sprintf(
                        '[Cover website] %s updated: %s',
                        count($subjects) == 1 ? $subjects[0] : 'Page',
                        $mail_data['titel']
                    );

                    foreach ($mail_data['email'] as $email)
                        @mail($email, $subject, $body, "From: acdcee@svcover.nl\r\n");
                }
            }

            if (!empty($iter['slug']))
                return $this->redirectToRoute('slug', ['slug' => $iter['slug']]);
            return $this->redirectToRoute('page.single', ['id' => $iter->get_id()]);
        }

        return $this->render('page/form.html.twig', [
            'iter' => $iter,
            'form' => $form
        ]);
    }
}
