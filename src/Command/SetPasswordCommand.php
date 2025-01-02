<?php

namespace App\Command;

use App\DataModel\DataModelMember;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:set-password')]
class SetPasswordCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private DataModelMember $model,
    ){
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Update password')
            ->setHelp('This command allows you to update a member’s password')
            ->addArgument('id', InputArgument::REQUIRED, 'The ID of the member')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->text('<info>Member ID</info>: ' . $input->getArgument('id'));

        $password = $this->io->askHidden('Password', function (string $password): string {
            if (empty($password)) {
                throw new \RuntimeException('Password cannot be empty.');
            }

            return $password;
        });

        $member = $this->model->get_iter(intval($input->getArgument('id')));
        $result = $this->model->set_password($member, $password);

        if ($result)
            $this->io->success(\sprintf('Password successfully updated for %s (%d).', $member->get_full_name(), $member->get_id()));
        else
            $this->io->error(\sprintf('Failed to update password updated for %s (%d).', $member->get_full_name(), $member->get_id()));

        return $result ? Command::SUCCESS : Command::FAILURE;
    }
}
