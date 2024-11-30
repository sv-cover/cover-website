<?php

namespace App\Command;

require_once 'src/Legacy/mailing_list.php';

use App\Legacy\Email\MessagePart;
use App\Service\Database;
use function App\Legacy\Email\personalize;
use function App\Legacy\Email\MailingList\send_mailinglist_mail;
use function App\Legacy\Email\MailingList\validate_message_to_all_committees;
use function App\Legacy\Email\MailingList\validate_message_to_committee;
use function App\Legacy\Email\MailingList\validate_message_to_mailinglist;
use function App\Legacy\Email\MailingList\parse_email_address;
use function App\Legacy\Email\MailingList\send_welcome_mail;
use function App\Legacy\Email\MailingList\send_message;
use function App\Legacy\Email\MailingList\get_error_message;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsCommand(name: 'app:process-mailing-list-queue')]
class ProcessMailingListQueueCommand extends Command
{
    const RETURN_UNKNOWN_LIST_TYPE = 601;
    const COOLDOWN = 10;

    private SymfonyStyle $io;

    public function __construct(
        private Database $db,
        private UrlGeneratorInterface $urlGenerator,
    ){
        parent::__construct();
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $model = $this->db->getModel('DataModelMailinglistQueue');

        $queue = $model->find(['status' => 'waiting']);

        // Array_shift returns NULL if no results
        while ($queuedMessage = \array_shift($queue)) {
            $queuedMessage->set('status', 'processing');
            $queuedMessage->set('processing_on', new \DateTime());
            $queuedMessage->update();

            $message = MessagePart::parse_text($queuedMessage->get('message'));
            $from = parse_email_address($message->header('From'));

            if ($queuedMessage->get('destination_type') === 'all_committees') {
                $result = $this->sendToAllCommittees($message, $queuedMessage->get('destination'), $from);
            } elseif ($queuedMessage->get('destination_type') === 'committee') {
                $result = $this->sendToCommittee($message, $queuedMessage->get('destination'));
            } elseif ($queuedMessage->get('destination_type') === 'mailinglist') {
                $mailinglist = $queuedMessage->get('mailinglist');
                $result = $this->sendToMailinglist($message, $queuedMessage->get('destination'), $from, $mailinglist);
            } else {
                $result = self::RETURN_UNKNOWN_LIST_TYPE;
            }

            if ($result === 0) {
               $model->delete($queuedMessage);
            } else {
                $message = $this->getErrorMessage($result);
                $queuedMessage->set('status', sprintf('error_%s', $message));
                $queuedMessage->update();
            }

            // Query every iteration to prevent race conditions
            $queue = $model->find(['status' => 'waiting']);
        }

        return Command::SUCCESS;
    }

    private function getErrorMessage(int $return_value): string
    {
        switch ($return_value) {
            case self::RETURN_UNKNOWN_LIST_TYPE:
                return "Error: Unknown list type.";

            default:
                return get_error_message($return_value);
        }
    }

    private function sendToAllCommittees(MessagePart $message, string $to, string $from): int
    {
        $model = $this->db->getModel('DataModelCommissie');

        $destinations = null;
        $loop_id = null;

        $result = validate_message_to_all_committees($message, $to, $from, $destinations, $loop_id);

        if ($result !== 0)
            return $result;

        $message->addHeader('X-Loop', $loop_id);

        $committees = $model->get($destinations[$to]); // Get all committees of that type, not including hidden committees (such as board)

        foreach ($committees as $committee) {
            $email = $committee['login'] . '@svcover.nl';

            // writing 1 line in 2 parts. This way, the logs show the message that caused the problem
            $this->io->write(date("Y-m-d H:i:s") . " - Sent mail for $to to {$committee['naam']} <$email>: ");

            $variables = array(
                '[COMMISSIE]' => $committee['naam'],
                '[COMMITTEE]' => $committee['naam']
            );

            $personalized_message = personalize($message, function($text) use ($variables) {
                return \str_ireplace(\array_keys($variables), \array_values($variables), $text);
            });

            $status = send_message($personalized_message, $email);

            $this->io->writeln($status);

            sleep(self::COOLDOWN);
        }

        return 0;
    }

    private function sendToCommittee(MessagePart $message, string $to, \DataIterCommissie &$committee=null): int
    {
        $committee = null;
        $loop_id = null;

        // Sets committee if committee is null
        $result = validate_message_to_committee($message, $to, $committee, $loop_id);

        if ($result !== 0)
            return $result;

        $message->addHeader('X-Loop', $loop_id);

        $members = $committee->get_members();

        foreach ($members as $member) {
            $this->io->write(date("Y-m-d H:i:s") . " - Sent mail for $to to {$member['voornaam']} <{$member['email']}>: ");

            $variables = array(
                '[NAAM]' => $member['voornaam'],
                '[NAME]' => $member['voornaam'],
                '[COMMISSIE]' => $committee['naam'],
                '[COMMITTEE]' => $committee['naam']
            );

            $personalized_message = personalize($message, function($text) use ($variables) {
                return \str_ireplace(\array_keys($variables), \array_values($variables), $text);
            });

            $status = send_message($personalized_message, $member['email']);

            $this->io->writeln($status);

            sleep(self::COOLDOWN);
        }

        return 0;
    }

    private function sendToMailinglist(MessagePart $message, string $to, string $from, \DataIterMailinglist &$list=null): int
    {
        $list = null;
        $subscriptions = null;
        $loop_id = null;

        $result = validate_message_to_mailinglist($message, $to, $from, $list, $subscriptions, $loop_id);

        if ($result !== 0)
            return $result;

        $message->addHeader('X-Loop', $loop_id);

        // Append '[Cover]' or whatever tag is defined for this list to the subject
        // but do so only if it is set.
        if (!empty($list['tag']))
            $message->setHeader('Subject', \preg_replace(
                '/^(?!(?:Re:\s*)?\[' . \preg_quote($list['tag'], '/') . '\])(.+?)$/im',
                '[' . $list['tag'] . '] $1',
                $message->header('Subject'),
                1
            ));

        if ($list->sends_email_on_first_email() && !$list['archive']->contains_email_from($from))
            send_welcome_mail($list, $from);

        foreach ($subscriptions as $subscription) {
            // Skip subscriptions without an e-mail address silently
            if (\trim($subscription['email']) == '')
                continue;

            $this->io->write(date("Y-m-d H:i:s") . " - Sent mail for $to to {$subscription['naam']} <{$subscription['email']}>: ");

            $unsubscribeUrl = $this->urlGenerator->generate(
                'mailing_lists.subscription.unsubscribe',
                ['id' => $subscription['abonnement_id']],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
            $archiveUrl =  $this->urlGenerator->generate(
                'mailing_lists.archive.list',
                ['id'=> $list['id']],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            // Personize the message for the receiver
            $personalizedMessage = personalize($message, function($text, $content_type) use ($subscription, $list, $unsubscribeUrl, $archiveUrl) {
                return $this->messageTransformer($text, $content_type, [
                    'subscription' => $subscription,
                    'list' => $list,
                    'unsubscribe_url' => $unsubscribeUrl,
                    'archive_url' => $archiveUrl,
                ]);
            });

            $personalizedMessage->setHeader('List-Unsubscribe', sprintf('<%s>', $unsubscribeUrl));
            $personalizedMessage->setHeader('List-Archive', sprintf('<%s>', $archiveUrl));

            $status = send_message($personalizedMessage, $subscription['email']);

            $this->io->writeln($status);

            sleep(self::COOLDOWN);
        }

        return 0;
    }

    private function messageTransformer(string $text, ?string $content_type, array $context): string
    {
        $use_html = (
            $content_type !== null
            && \preg_match('/^text\/html/', $content_type)
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
            $content_type !== null
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
