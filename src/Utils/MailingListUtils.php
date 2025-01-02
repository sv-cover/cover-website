<?php

namespace App\Utils;

use App\DataIter\DataIterCommissie;
use App\DataIter\DataIterMailinglist;
use App\DataModel\DataModelCommissie;
use App\DataModel\DataModelMailinglist;
use App\DataModel\DataModelMailinglistArchive;
use App\DataModel\DataModelMailinglistQueue;
use App\Markup\Markup;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message;
use ZBateson\MailMimeParser\Header\AddressHeader;
use ZBateson\MailMimeParser\Header\HeaderConsts;

final class MailingListUtils
{
    const COULD_NOT_DETERMINE_SENDER = 101;
    const COULD_NOT_DETERMINE_DESTINATION = 102;
    const COULD_NOT_DETERMINE_LIST = 103;
    const COULD_NOT_DETERMINE_COMMITTEE = 104;
    const COULD_NOT_PARSE_MESSAGE = 105;

    const NOT_ADDRESSED_TO_COMMITTEE_MAILINGLIST = 201;

    const NOT_ALLOWED_NOT_SUBSCRIBED = 401;
    const NOT_ALLOWED_NOT_COVER = 402;
    const NOT_ALLOWED_NOT_OWNER = 403;
    const NOT_ALLOWED_NOT_SUBSCRIBED_NOT_COVER = 404;
    const NOT_ALLOWED_UNKNOWN_POLICY = 405;

    const FAILURE_MESSAGE_EMPTY = 502;
    const MARKED_AS_SPAM = 503;
    const MAIL_LOOP_DETECTED = 504;

    public static function getErrorMessage(int $code): string
    {
        switch ($code) {
            case self::COULD_NOT_PARSE_MESSAGE:
                return "Error: Could not parse the message.";

            case self::COULD_NOT_DETERMINE_SENDER:
                return "Error: Could not determine sender.";

            case self::COULD_NOT_DETERMINE_DESTINATION:
                return "Error: Could not determine destination.";

            case self::COULD_NOT_DETERMINE_LIST:
                return "Error: Could not determine mailing list.";

            case self::NOT_ALLOWED_NOT_SUBSCRIBED:
                return "Not allowed: Sender not subscribed to list.";

            case self::NOT_ALLOWED_NOT_COVER:
                return "Not allowed: Sender does not match *@svcover.nl.";

            case self::NOT_ALLOWED_NOT_SUBSCRIBED_NOT_COVER:
                return "Not allowed: Sender not subscribed to list and does not match *@svcover.nl.";

            case self::NOT_ALLOWED_NOT_OWNER:
                return "Not allowed: Sender not the owner of the list.";

            case self::NOT_ALLOWED_UNKNOWN_POLICY:
                return "Not allowed: Unknown list policy.";

            case self::FAILURE_MESSAGE_EMPTY:
                return "Error: Message empty.";

            case self::MARKED_AS_SPAM:
                return "The message was marked as 'spammy' by the spamfilter.";

            case self::NOT_ADDRESSED_TO_COMMITTEE_MAILINGLIST:
                return "The message is not addressed to the committee mailing list.";

            default:
                return "(code $self::value)";
        }
    }

    public function __construct(
        private DataModelCommissie $committeeModel,
        private DataModelMailinglist $mailingListModel,
        private DataModelMailinglistArchive $archive,
        private DataModelMailinglistQueue $queue,
        private MailerInterface $mailer,
        private Markup $markup,
        private ContainerBagInterface $params,
    ) {
    }

    protected function inLoop(Message $message, string $loopId): bool
    {
        foreach ($message->getAllHeadersByName('X-Loop') as $header)
            if ($header->getValue() == $loopId)
                return true;
        return false;
    }

    // TODO SFY: support Message
    public function sendMessage(Message $message, string $email): void
    {
        $message->setRawHeader('X-Mailing-List-Destination', $email);


        $_email = new RawMessage(strval($message));

        $envelope = new Envelope(
            new Address($this->params->get('app.email_bounces')),
            [new Address($email)]
        );

        $this->mailer->send($_email, $envelope);
    }

    public function sendMailingListMail($bufferStream): int
    {
        $parser = new MailMimeParser();

        try {
            // Read the complete email from the stdin.
            rewind($bufferStream);
            $message = $parser->parse($bufferStream, false);
        } catch (\Exception $exception) {
            \Sentry\captureException($exception);
            return self::COULD_NOT_PARSE_MESSAGE;
        }

        $list = null;
        $committee = null;

        // Test at least the sender already
        $from = $message->getHeader(HeaderConsts::FROM)->getAddresses()[0]->getEmail();
        if (empty($from))
            return self::COULD_NOT_DETERMINE_SENDER;

        $destinations = (
            $message->getHeaderAs('Envelope-To', AddressHeader::class)
            ?? $message->getHeaderAs('X-Mailing-List-Destination', AddressHeader::class)
            ?? $message->getHeader(HeaderConsts::TO)
        );

        if (empty($destinations))
            return self::COULD_NOT_DETERMINE_DESTINATION;

        if ($message->getHeaderValue('X-Spam-Flag') == 'YES')
            return self::MARKED_AS_SPAM;

        $destinations = array_map(fn($a) => $a->getEmail(), $destinations->getAddresses());

        $returnCode = 0;

        foreach (array_unique($destinations) as $destination) {
            $committee = null;

            // First try if this message is addressed to committees@svcover.nl
            $returnCode = $this->processMessageToAllCommittees($message, $destination, $from);

            if ($returnCode === self::NOT_ADDRESSED_TO_COMMITTEE_MAILINGLIST) {
                // Then try sending the message to a committee
                $returnCode = $this->processMessageToCommittee($message, $destination, $committee);

                // If that didn't work, try sending it to a mailing list
                if ($returnCode === self::COULD_NOT_DETERMINE_COMMITTEE) {
                    // Process the message: parse it and send it to the list.
                    $returnCode = $this->processMessageToMailinglist($message, $destination, $from, $list);
                }
            }

            // Archive the message.
            rewind($bufferStream);
            $this->archive->archive($bufferStream, $from, $list, $committee, $returnCode);

            if ($returnCode !== 0)
                $this->procesReturnToSender($message, $from, $destination, $returnCode);
        }

        // Return the result of the processing step.
        return $returnCode;
    }

    public function sendWelcomeMail(DataIterMailinglist $list, string $to): void
    {
        $email = (new Email())
            ->to($to)
            ->from(new Address($list['adres'], $list['naam']))
            ->replyTo(new Address('webcie@rug.nl', 'AC/DCee Cover'))
            ->subject((string) $list['on_first_email_subject'])
            ->text($this->markup->strip($list['on_first_email_message']))
            ->html($this->markup->parse($list['on_first_email_message']))
        ;

        $this->mailer->send($email);
    }

    /**
     * Transforms the message's text body (plain and html) using a transformer
     * function. A shallow copy of the message is returned, with only the headers
     * and the body parts that have been touched by the transformer copied. The
     * transformer is also applied to the subject header of the mail.
     *
     * If any changes are actually made, this function also drops the DKIM-Signature
     * from the email.
     */
    public function personalize(Message $message, callable $transformer): Message
    {
        $changed = false;

        $changeChecker = function($text, $contentType) use ($transformer, &$changed) {
            $output = $transformer($text, $contentType);
            $changed = $changed || $output != $text;
            return $output;
        };

        $copy = clone $message;

        for ($idx = 0; $idx < $message->getPartCount(); $idx ++) {
            $part = $message->getPart($idx);
            $part->setContent(
                $changeChecker($part->getContent() ?? '', $part->getContentType())
            );
        }

        $copy->setRawHeader('Subject', $changeChecker($message->getSubject(), null));

        if ($changed)
            $copy->removeSingleHeader('DKIM-Signature');

        return $copy;
    }

    public function parseEmailAddress(string $email): ?string
    {
        $email = trim($email);

        // 'jelmer@ikhoefgeen.nl'
        if (filter_var($email, FILTER_VALIDATE_EMAIL))
            return $email;

        // Jelmer van der Linde <jelmer@ikhoefgeen.nl>
        elseif (preg_match('/<(.+?)>$/', trim($email), $match)
            && filter_var($match[1], FILTER_VALIDATE_EMAIL))
            return $match[1];

        return null;
    }

    public function parseEmailAddresses(string $emails): array
    {
        return array_filter(array_map([$this, 'parseEmailAddress'], explode(',', $emails)));
    }

    public function validateMessageToAllCommittees(
        Message $message,
        string &$to,
        string $from,
        ?array &$destinations = null,
        ?string &$loopId = null,
    ): int
    {
        // Strip svcover.nl domain from $to, if it is there.
        if (preg_match('/@svcover\.nl$/i', $to))
            $to = substr($to, 0, -strlen('@svcover.nl'));

        $to = strtolower($to); // case insensitive please

        $destinations = [
            'committees' => DataModelCommissie::TYPE_COMMITTEE,
            'workingroups' => DataModelCommissie::TYPE_WORKING_GROUP,
        ];

        // Validate whether it is actually addressed to the committee (or working group) mailing list
        if (!array_key_exists($to, $destinations))
            return self::NOT_ADDRESSED_TO_COMMITTEE_MAILINGLIST;

        // Only @svcover.nl addresses can send to these mailing lists
        if (!preg_match('/@svcover\.nl$/i', $from))
            return self::NOT_ALLOWED_NOT_COVER;

        $loopId = sprintf('all-%s', $to);

        if ($this->inLoop($message, $loopId))
            return self::MAIL_LOOP_DETECTED;

        return 0;
    }

    /**
     * Sends mail to committees@svcover.nl and workinggroups@svcover.nl to all
     * committees or all working groups. [COMMISSIE] and [COMMITTEE] in the
     * plain message will be replaced with the name of the committee.
     *
     * @param $message the raw message body
     * @param $to destination address, ideally committees@svcover.nl
     *            or workinggroups@svcover.nl.
     * @param $from the email address of the sender. Must end in @svcover.nl or
     *              the function will return self::NOT_ALLOWED_NOT_COVER.
     *
     * @return self::NOT_ADDRESSED_TO_COMMITTEE_MAILINGLIST if the mail is not
     * addressed to one of those email addresses.
     * @return self::NOT_ALLOWED_NOT_COVER if the mail was not sent from an
     * address ending in @svcover.nl.
     */
    public function processMessageToAllCommittees(
        Message $message,
        string $to,
        string $from,
    ): int
    {
        $result = $this->validateMessageToAllCommittees($message, $to, $from);

        if ($result === 0)
            $this->queue->queue(strval($message), $to, 'all_committees');

        return $result;
    }

    public function validateMessageToCommittee(
        Message $message,
        string $to,
        ?DataIterCommissie &$committee = null,
        ?string &$loopId = null,
    ): int
    {
        // Find that committee
        if (!$committee)
            $committee = $this->committeeModel->get_from_email($to);

        // Error if still no committee
        if (!$committee)
            return self::COULD_NOT_DETERMINE_COMMITTEE;

        $loopId = sprintf('committee-%d', $committee['id']);

        if ($this->inLoop($message, $loopId))
            return self::MAIL_LOOP_DETECTED;

        return 0;
    }

    public function processMessageToCommittee(
        Message $message,
        string $to,
        ?DataIterCommissie &$committee = null,
    ): int
    {
        $result = $this->validateMessageToCommittee($message, $to, $committee);

        if ($result === 0)
            $this->queue->queue(strval($message), $to, 'committee');

        return $result;
    }

    public function validateMessageToMailinglist(
        Message $message,
        string $to,
        string $from,
        ?DataIterMailinglist &$list = null,
        ?array &$subscriptions = null,
        ?string &$loopId = null,
    ): int
    {
        // Find that mailing list
        if (!$list)
            $list = $this->mailingListModel->get_iter_by_address($to);

        // Error if still no list
        if (!$list)
            return self::COULD_NOT_DETERMINE_LIST;

        $loopId = sprintf('mailinglist-%d', $list['id']);

        if ($this->inLoop($message, $loopId))
            return self::MAIL_LOOP_DETECTED;

        // Find everyone who is subscribed to that list
        $subscriptions = $list['subscriptions'];

        switch ($list['toegang']) {
            // Everyone can send mail to this list
            case DataModelMailinglist::TOEGANG_IEDEREEN:
                // No problem, you can mail
                break;

            // Only people on the list can send mail to the list
            case DataModelMailinglist::TOEGANG_DEELNEMERS:
                foreach ($subscriptions as $subscription)
                    if (strcasecmp($subscription['email'], $from) === 0)
                        break 2;

                // Also test whether the owner is sending mail, he should also be accepted.
                if (in_array($from, $list['committee']['email_addresses']))
                    break;

                // Nope, access denied
                return self::NOT_ALLOWED_NOT_SUBSCRIBED;

            // Only people who sent mail from an *@svcover.nl address can send to the list
            case DataModelMailinglist::TOEGANG_COVER:
                if (!preg_match('/\@svcover.nl$/i', $from))
                    return self::NOT_ALLOWED_NOT_COVER;
                break;

            // Only the owning committee can send mail to this list.
            case DataModelMailinglist::TOEGANG_EIGENAAR:
                if (!in_array($from, $list['committee']['email_addresses']))
                    return self::NOT_ALLOWED_NOT_OWNER;
                break;

            // Only the owning committee can send mail to this list.
            case DataModelMailinglist::TOEGANG_COVER_DEELNEMERS:
                foreach ($subscriptions as $subscription)
                    if (strcasecmp($subscription['email'], $from) === 0)
                        break 2;

                if (preg_match('/\@svcover.nl$/i', $from))
                    break;

                return self::NOT_ALLOWED_NOT_SUBSCRIBED;

            default:
                return self::NOT_ALLOWED_UNKNOWN_POLICY;
        }

        return 0;
    }

    public function processMessageToMailinglist(
        Message $message,
        string $to,
        string $from,
        ?DataIterMailinglist &$list = null,
    ): int
    {
        $list = null;
        $result = $this->validateMessageToMailinglist($message, $to, $from, $list);

        if ($result === 0)
            $this->queue->queue(strval($message), $to, 'mailinglist', $list);

        return $result;
    }

    public function procesReturnToSender(
        Message $message,
        string $from,
        string $destination,
        int $returnCode,
    ): void
    {
        $notice = 'Sorry, but your message';
        $notice .= ($destination ? ' to ' . $destination : '');
        $notice .= " could not be delivered:\n";
        $notice .= self::getErrorMessage($returnCode);

        echo "Return message to sender $from\n";

        $email = (new Email())
            ->to($from)
            ->replyTo(new Address('webcie@rug.nl', 'AC/DCee Cover'))
            ->subject('Message could not be delivered: ' . $message->getSubject())
        ;

        $messageId = $message->getHeaderValue('Message-ID');
        if ($messageId) {
            $email->getHeaders()->addIdHeader('In-Reply-To', $messageId);

            $references = $message->getHeaderValue('References');

            if ($references)
                $email->getHeaders()->addTextHeader('References', $messageId . "\n" . $references);
            else
                $email->getHeaders()->addIdHeader('References', $messageId);
        }

        if ($textBody = $message->getTextContent())
            $email->text($notice . "\n\n" . $textBody);
        else
            $email->text($notice);

        if ($htmlBody = $message->getHtmlContent()) {
            $email->html(
                sprintf('<p>%s</p><blockquote style="margin:0 0 0 0.8ex; border-left: 1px #ccc solid; padding-left: 1ex">%s</blockquote>',
                nl2br(htmlspecialchars($notice)),
                $htmlBody
            ));
        }

        $this->mailer->send($email);
    }
}
