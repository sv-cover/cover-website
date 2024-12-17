<?php

namespace App\DataModel;

use App\DataIter\DataIterMailinglist;
use App\DataIter\DataIterMember;
use App\DataModel\DataModelCommissie;
use App\DataModel\DataModelMailinglistArchive;
use App\DataModel\DataModelMailinglistArchiveAdapter;
use App\DataModel\DataModelMailinglistSubscription;
use App\DataModel\DataModelMember;
use App\Legacy\Database\DataModel;
use App\Markup\Markup;
use Symfony\Component\DependencyInjection\Attribute\Lazy;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class DataModelMailinglist extends DataModel
{
    const TOEGANG_IEDEREEN = 1;
    const TOEGANG_DEELNEMERS = 2;
    const TOEGANG_COVER = 3;
    const TOEGANG_EIGENAAR = 4;
    const TOEGANG_COVER_DEELNEMERS = 5;

    const TYPE_OPT_IN = 1;
    const TYPE_OPT_OUT = 2;

    public string $dataiter = DataIterMailinglist::class;
    public string $table = 'mailinglijsten';

    public function __construct(
        private DataModelCommissie $committeeModel,
        private DataModelMailinglistArchive $archiveModel,
        #[Lazy] private DataModelMailinglistSubscription $subscriptionModel,  // Lazy to prevent circular dependencies
        private MailerInterface $mailer,
        private Markup $markup,
    ) {
    }

    public function _row_to_iter($row, $dataiter = null, array $preseed = [])
    {
        // Stupid PGSQL boolean stuff...
        if ($row && isset($row['publiek']))
            $row['publiek'] = $row['publiek'] == 't';

        if ($row && isset($row['has_members']))
            $row['has_members'] = $row['has_members'] == 't';

        if ($row && isset($row['has_contributors']))
            $row['has_contributors'] = $row['has_contributors'] == 't';

        if ($row && isset($row['subscribed']))
            $row['subscribed'] = $row['subscribed'] == 't';

        return parent::_row_to_iter($row, $dataiter, $preseed);
    }

    public function get_for_member(DataIterMember $member, $public_only = true)
    {
        $rows = $this->db->query('
            SELECT
                l.*,
                CASE
                    WHEN l.type = ' . self::TYPE_OPT_IN . ' THEN COUNT(a.abonnement_id) > 0
                    WHEN l.type = ' . self::TYPE_OPT_OUT . ' THEN COUNT(o.id) = 0
                    ELSE FALSE
                END as subscribed
            FROM
                mailinglijsten l
            LEFT JOIN
                mailinglijsten_abonnementen a
                ON a.mailinglijst_id = l.id
                AND a.lid_id = ' . intval($member['id']) . '
                AND (a.opgezegd_op > NOW() OR a.opgezegd_op IS NULL)
            LEFT JOIN
                mailinglijsten_opt_out o
                ON o.mailinglijst_id = l.id
                AND o.lid_id = ' . intval($member['id']) . '
                AND o.opgezegd_op < NOW()
            where
                (l.has_starting_year IS NULL OR l.has_starting_year = ' . intval($member['beginjaar']) . ' )
            GROUP BY
                l.id,
                l.naam
            ORDER BY
                l.naam ASC');

        return $this->_rows_to_iters($rows);
    }

    public function get_iter_by_address($address)
    {
        return $this->find_one(['adres' => $address]);
    }

    public function send_subscription_mail(DataIterMailinglist $list, $name, $email)
    {
        if (!$list->sends_email_on_subscribing())
            return;

        $text = $list->get('on_subscription_message');

        $variables = [
            '[NAAM]' => $name,
            '[NAME]' => $name,
            '[MAILINGLIST]' => $list->get('naam'),
        ];

        // If you are allowed to unsubscribe, parse the placeholder correctly (different for opt-in and opt-out lists)
        /*
        if ($list->get('publiek'))
        {
            $url = $list->get('type')== DataModelMailinglijst::TYPE_OPT_IN
                ? ROOT_DIR_URI . sprintf('mailinglijsten.php?abonnement_id=%s', $aanmelding->get('abonnement_id'))
                : ROOT_DIR_URI . sprintf('mailinglijsten.php?lijst_id=%d', $list->get('id'));

            $variables['[UNSUBSCRIBE_URL]'] = htmlspecialchars($url, ENT_QUOTES, WEBSITE_ENCODING);

            $variables['[UNSUBSCRIBE]'] = sprintf('<a href="%s">Click here to unsubscribe from the %s mailinglist.</a>',
                htmlspecialchars($url, ENT_QUOTES, WEBSITE_ENCODING),
                htmlspecialchars($list->get('naam'), ENT_COMPAT, WEBSITE_ENCODING));
        }
        */

        $subject = $list->get('on_first_email_subject');

        $personalized_message = strtr($text, $variables);

        $email = (new Email())
            ->to(new Address($email, $name))
            ->replyTo(new Address('webcie@rug.nl', 'AC/DCee Cover'))
            ->subject($list->get('on_first_email_subject'))
            ->text($this->markup->strip($personalized_message))
            ->html($this->markup->parse($personalized_message))
        ;
        $this->mailer->send($email);
    }

    public function is_subscribed(DataIterMailinglist $list, DataIterMember $member)
    {
        return $this->subscriptionModel->is_subscribed($list, $member);
    }

    public function get_subscriptions(DataIterMailinglist $list)
    {
        return $this->subscriptionModel->get_subscriptions($list);
    }

    public function get_reach(DataIterMailinglist $list, ?string $partition_by = null)
    {
        return $this->subscriptionModel->get_reach($list, $partition_by);
    }

    public function get_archive(DataIterMailinglist $list)
    {
        return new DataModelMailinglistArchiveAdapter($this->archiveModel, $list);
    }

    // consistent naming with other models
    public function get_committee_for_iter(DataIterMailinglist $list)
    {
        return $this->committeeModel->get_iter($list['commissie']);
    }
}
