<?php

namespace App\Command;

use App\Utils\MailingListUtils;
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

    public function __construct(
        private MailingListUtils $mailingListUtils,
    ){
        parent::__construct();
    }

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

        $returnValue = $this->mailingListUtils->sendMailingListMail($bufferStream);

        // Close the buffered message at last
        \fclose($bufferStream);

        if ($returnValue !== 0) {
            $io->getErrorStyle()->error(MailingListUtils::getErrorMessage($returnValue));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
