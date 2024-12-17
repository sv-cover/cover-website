<?php

namespace App\Command;

use App\DataModel\DataModelAgenda;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:ask-attendance')]
class AskAttendanceCommand extends Command
{
    public function __construct(
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

            $data = [
                'commissie_naam' => $event['committee']['naam']
            ];

            $body = \parse_email('ask_attendance.txt', \array_merge($event->data, $data));

            $headers = [
                'From: Study Association Cover <noreply@svcover.nl>',
                'Reply-to: intern@svcover.nl'
            ];

            \mail(
                $event['committee']['email'],
                \sprintf("Attendance of '%s'", $event['kop']),
                $body,
                \implode("\r\n", $headers)
            );
        }

        return Command::SUCCESS;
    }
}
