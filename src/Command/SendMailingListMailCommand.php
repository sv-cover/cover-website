<?php

namespace App\Command;

require_once 'src/Legacy/mailing_list.php';

use function App\Legacy\Email\MailingList\get_error_message;
use function App\Legacy\Email\MailingList\send_mailinglist_mail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:send-mailing-list-mail')]
class SendMailingListMailCommand extends Command
{
    private SymfonyStyle $io;

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        // If testing this will get input added by `CommandTester::setInputs` method.
        $inputStream = ($input instanceof StreamableInputInterface) ? $input->getStream() : null;

        // If nothing from input stream use STDIN instead.
        $inputStream = $inputStream ?? \STDIN;

        $bufferStream = \fopen('php://temp', 'r+');
        \stream_copy_to_stream($inputStream, $bufferStream);

        $returnValue = send_mailinglist_mail($bufferStream);

        // Close the buffered message at last
        \fclose($bufferStream);

        if ($returnValue !== 0) {
            $io->getErrorStyle()->error(get_error_message($returnValue));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
