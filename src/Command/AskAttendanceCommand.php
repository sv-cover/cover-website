<?php

namespace App\Command;

use App\DataModel\DataModelAgenda;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;

#[AsCommand(name: 'app:ask-attendance')]
class AskAttendanceCommand extends Command
{
    public function __construct(
        private MailerInterface $mailer,
        private DataModelAgenda $model,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $events = $this->model->get(new \DateTime('-1 days'), new \DateTime(), true);

        foreach ($events as $event) {
            // Skip external activities
            if ($event['extern'])
                continue;

            $email = (new TemplatedEmail())
                ->to($event['committee']['email'])
                ->replyTo('intern@svcover.nl')
                ->subject(sprintf("Attendance of '%s'", $event['kop']))
                ->textTemplate('emails/event_attendance.txt.twig')
                ->context([
                    'event' => $event,
                ])
            ;
            $this->mailer->send($email);
        }

        return Command::SUCCESS;
    }
}
