<?php

namespace App\Command;

use App\DataIter\DataIterCommissie;
use App\DataIter\DataIterMailinglist;
use App\DataIter\DataIterMailinglistQueue;
use App\DataModel\DataModelCommissie;
use App\DataModel\DataModelMailinglistQueue;
use App\Utils\MailingListUtils;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Header\HeaderConsts;

#[AsCommand(name: 'app:process-mailing-list-queue')]
class ProcessMailingListQueueCommand extends Command
{
    const RETURN_UNKNOWN_LIST_TYPE = 601;
    const COOLDOWN = 10;

    private SymfonyStyle $io;
    private MailMimeParser $parser;

    public function __construct(
        private DataModelCommissie $committeeModel,
        private DataModelMailinglistQueue $queueModel,
        private MailingListUtils $mailingListUtils,
        private UrlGeneratorInterface $urlGenerator,
    ){
        parent::__construct();
        $this->parser = new MailMimeParser();
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queue = $this->queueModel->find(['status' => 'waiting']);

        // Array_shift returns NULL if no results
        while ($queuedMessage = \array_shift($queue)) {
            $queuedMessage->set('status', 'processing');
            $queuedMessage->set('processing_on', new \DateTime());
            $queuedMessage->update();

            if ($queuedMessage->get('destination_type') === 'all_committees') {
                $result = $this->sendToAllCommittees($queuedMessage);
            } elseif ($queuedMessage->get('destination_type') === 'committee') {
                $result = $this->sendToCommittee($queuedMessage);
            } elseif ($queuedMessage->get('destination_type') === 'mailinglist') {
                $mailinglist = $queuedMessage->get('mailinglist');
                $result = $this->sendToMailinglist($queuedMessage, $mailinglist);
            } else {
                $result = self::RETURN_UNKNOWN_LIST_TYPE;
            }

            if ($result === 0) {
               $this->queueModel->delete($queuedMessage);
            } else {
                $message = MailingListUtils::getErrorMessage($result);
                $queuedMessage->set('status', sprintf('error_%s', $message));
                $queuedMessage->update();
            }

            // Query every iteration to prevent race conditions
            $queue = $this->queueModel->find(['status' => 'waiting']);
        }

        return Command::SUCCESS;
    }

    private function getErrorMessage(int $code): string
    {
        switch ($code) {
            case self::RETURN_UNKNOWN_LIST_TYPE:
                return "Error: Unknown list type.";

            default:
                return MailingListUtils::getErrorMessage($code);
        }
    }

    private function sendToAllCommittees(DataIterMailinglistQueue $queuedMessage): int
    {
        $message = $this->parser->parse($queuedMessage->get('message'), false);

        $to = $queuedMessage->get('destination');
        $from = $message->getHeader(HeaderConsts::FROM)->getAddresses()[0]->getEmail();

        $destinations = null;
        $loopId = null;

        $result = $this->mailingListUtils->validateMessageToAllCommittees($message, $to, $from, $destinations, $loopId);

        if ($result !== 0)
            return $result;

        $committees = $this->committeeModel->get($destinations[$to]); // Get all committees of that type, not including hidden committees (such as board)

        foreach ($committees as $committee) {
            // Reparse message as we can't deep clone.
            $message = $this->parser->parse($queuedMessage->get('message'), false);
            $message->setRawHeader('X-Loop', $loopId);

            $email = $committee['login'] . '@svcover.nl';

            // writing 1 line in 2 parts. This way, the logs show the message that caused the problem
            $this->io->write(date("Y-m-d H:i:s") . " - Sending mail for $to to {$committee['naam']} <$email>: ");

            $variables = array(
                '[COMMISSIE]' => $committee['naam'],
                '[COMMITTEE]' => $committee['naam']
            );

            $personalizedMessage = $this->mailingListUtils->personalize(
                $message,
                fn($text, $contentType) => \str_ireplace(\array_keys($variables), \array_values($variables), $text)
            );

            $this->mailingListUtils->sendMessage($personalizedMessage, $email);

            $this->io->writeln('success');

            sleep(self::COOLDOWN);
        }

        return 0;
    }

    private function sendToCommittee(DataIterMailinglistQueue $queuedMessage): int
    {
        $message = $this->parser->parse($queuedMessage->get('message'), false);

        $to = $queuedMessage->get('destination');

        $committee = null;
        $loopId = null;

        // Sets committee if committee is null
        $result = $this->mailingListUtils->validateMessageToCommittee($message, $to, $committee, $loopId);

        if ($result !== 0)
            return $result;

        $members = $committee->get_members();

        foreach ($members as $member) {
            // Reparse message as we can't deep clone.
            $message = $this->parser->parse($queuedMessage->get('message'), false);
            $message->setRawHeader('X-Loop', $loopId);

            $this->io->write(date("Y-m-d H:i:s") . " - Sending mail for $to to {$member['voornaam']} <{$member['email']}>: ");

            $variables = array(
                '[NAAM]' => $member['voornaam'],
                '[NAME]' => $member['voornaam'],
                '[COMMISSIE]' => $committee['naam'],
                '[COMMITTEE]' => $committee['naam']
            );

            $personalizedMessage = $this->mailingListUtils->personalize(
                $message,
                fn($text, $contentType) => \str_ireplace(\array_keys($variables), \array_values($variables), $text),
            );

            $this->mailingListUtils->sendMessage($personalizedMessage, $member['email']);

            $this->io->writeln('success');

            sleep(self::COOLDOWN);
        }

        return 0;
    }

    private function sendToMailinglist(DataIterMailinglistQueue $queuedMessage, DataIterMailinglist &$list = null): int
    {
        $message = $this->parser->parse($queuedMessage->get('message'), false);

        $to = $queuedMessage->get('destination');
        $from = $message->getHeader(HeaderConsts::FROM)->getAddresses()[0]->getEmail();

        $list = null;
        $subscriptions = null;
        $loopId = null;

        $result = $this->mailingListUtils->validateMessageToMailinglist($message, $to, $from, $list, $subscriptions, $loopId);

        if ($result !== 0)
            return $result;

        // Append '[Cover]' or whatever tag is defined for this list to the subject
        // but do so only if it is set.
        if (!empty($list['tag']))
            $subject = \preg_replace(
                '/^(?!(?:Re:\s*)?\[' . \preg_quote($list['tag'], '/') . '\])(.+?)$/im',
                '[' . $list['tag'] . '] $1',
                $message->getSubject(),
                1
            );
        else
            $subject = $message->getSubject();

        if ($list->sends_email_on_first_email() && !$list['archive']->contains_email_from($from))
            $this->mailingListUtils->sendWelcomeMail($list, $from);

        foreach ($subscriptions as $subscription) {
            // Skip subscriptions without an e-mail address silently
            if (\trim($subscription['email']) == '')
                continue;

            // Reparse message as we can't deep clone.
            $message = $this->parser->parse($queuedMessage->get('message'), false);
            $message->setRawHeader('X-Loop', $loopId);
            $message->setRawHeader('Subject', $subject);

            $this->io->write(date("Y-m-d H:i:s") . " - Sending mail for $to to {$subscription['naam']} <{$subscription['email']}>: ");

            $unsubscribeUrl = $this->urlGenerator->generate(
                'mailing_lists.subscription.unsubscribe',
                ['id' => $subscription['abonnement_id']],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
            $archiveUrl = $this->urlGenerator->generate(
                'mailing_lists.archive.list',
                ['id'=> $list['id']],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            // Personize the message for the receiver
            $personalizedMessage = $this->mailingListUtils->personalize(
                $message,
                fn($text, $contentType) => $this->messageTransformer($text, $contentType, [
                    'subscription' => $subscription,
                    'list' => $list,
                    'unsubscribe_url' => $unsubscribeUrl,
                    'archive_url' => $archiveUrl,
                ]),
            );

            $personalizedMessage->setRawHeader('List-Unsubscribe', sprintf('<%s>', $unsubscribeUrl));
            $personalizedMessage->setRawHeader('List-Archive', sprintf('<%s>', $archiveUrl));

            $this->mailingListUtils->sendMessage($personalizedMessage, $subscription['email']);

            $this->io->writeln('success');

            sleep(self::COOLDOWN);
        }

        return 0;
    }

    private function messageTransformer(string $text, ?string $contentType, array $context): string
    {
        $use_html = (
            $contentType !== null
            && \preg_match('/^text\/html/', $contentType)
        );

        // Escape function depends on content type (text/html is treated differently)
        $escape = (
            $use_html
            ? (fn($text, $entities = \ENT_COMPAT) => \htmlspecialchars($text, $entities, 'utf-8'))
            : (fn($text, $entities = null) => $text)
        );


        $variables = [
            '[NAAM]' => $escape($context['subscription']['naam']),
            '[NAME]' => $escape($context['subscription']['naam']),
            '[MAILINGLIST]' => $escape($context['list']['naam'])
        ];

        if ($context['subscription']['lid_id'])
            $variables['[LID_ID]'] = $context['subscription']['lid_id'];

        $variables['[UNSUBSCRIBE_URL]'] = $escape($context['unsubscribe_url'], \ENT_QUOTES);

        if ($use_html)
            $unsubscribeMessage = '<a href="%s">Click here to unsubscribe from the %s mailinglist.</a>';
        else
            $unsubscribeMessage = 'To unsubscribe from the %2$s mailinglist, go to %1$s';

        $variables['[UNSUBSCRIBE]'] = \sprintf(
            $unsubscribeMessage,
            $escape($context['unsubscribe_url']),
            $escape($context['list']['naam']),
        );

        // Add an unsubscribe link to the footer when there isn't already a link in there, and
        // if users can unsubscribe from the list (i.e. public lists)
        if (
            $contentType !== null
            && $context['list']['publiek']
            && \strpos($text, '[UNSUBSCRIBE]') === false
            && \strpos($text, '[UNSUBSCRIBE_URL]') === false
        ) {
            if ($use_html)
                $unsubscribeMessage = "<div><hr style=\"border:0;border-top:1px solid #ccc\"><small>You are receiving this mail because you are subscribed to the %s mailinglist. [UNSUBSCRIBE]</small></div>";
            else
                $unsubscribeMessage = "\n\n---\nYou are receiving this mail because you are subscribed to the %s mailinglist. [UNSUBSCRIBE]";
            $text .= \sprintf($unsubscribeMessage, $escape($context['list']['naam']));
        }

        return \str_ireplace(\array_keys($variables), \array_values($variables), $text);
    }
}
