<?php

namespace App\Command;

use App\DataModel\DataModelMember;
use App\DataModel\DataModelProfilePicture;
use App\Exception\NotFoundException;
use App\Legacy\Database\DatabasePDO;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[AsCommand(name: 'app:import-profile-pictures')]
class ImportProfilePicturesCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private DatabasePDO $db,
        private DataModelMember $memberModel,
        private DataModelProfilePicture $profilePictureModel,
        private TagAwareCacheInterface $profilePicturesCache,
    ){
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Import profile pictures')
            ->setHelp('This command allows you to set profile pictures for many members.')
            ->addArgument(
                'photos',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'File names separated with a space. The files have to be JPEG files (.jpg) and either have the member’s full name or ID as file name, e.g. `martijn_luinstra.jpg` or `939.jpg`. Use a wildcard (*) to run this command for all files in a directory.'
            )
            ->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'Directory, in case the provided file names are relative. For example: `-d $PWD 939.jpg`')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($input->getArgument('photos') as $filename) {
            if ($input->getOption('directory'))
                $this->setPhoto($input->getOption('directory') . DIRECTORY_SEPARATOR . $filename);
            else
                $this->setPhoto($filename);
        }

        return Command::SUCCESS;
    }

    private function setPhoto(string $file): void
    {
        $name = \basename($file, '.jpg');
        $name = \str_replace('_', ' ', $name);

        if (\ctype_digit($name))
            $memberId = \intval($name);
        else
            $memberId = $this->findMemberId($name);

        if ($memberId === null)
            return;

        try {
            $member = $this->memberModel->get_iter($memberId);
        } catch (NotFoundException $e) {
            $this->io->error("Unable to find find member with ID $memberId.");
            return;
        }

        $fh = \fopen($file, 'rb');

        if ($fh === false) {
            $this->io->error("Can't open file '$file'.");
            return;
        }

        $this->io->text("Update {$file} > {$member['full_name']}");

        $this->profilePicturesCache->invalidateTags([
            sprintf('member_%d_picture', $member->get_id()),
        ]);
        $this->profilePictureModel->set_for_member($member, $fh);

        // Set the photo to reviewed. Don't add an extra argument to
        // set_for_member to ensure proper process in all other situations.
        $picture = $this->profilePictureModel->get_for_member($member);
        $picture['reviewed'] = true;
        $this->profilePictureModel->update($picture);

        unlink($file);
    }

    private function findMemberId(string $name): ?int
    {
        $result = $this->db->query(<<<SQL
            SELECT id
              FROM leden
             WHERE LOWER(voornaam || CASE  WHEN char_length(tussenvoegsel) > 0 THEN ' ' || tussenvoegsel ELSE '' END || ' ' || achternaam) = LOWER(:name)
        SQL, false, [':name' => $name]);

        if (count($result) > 1) {
            $this->io->warning("Found multiple members named '$name'.");
            return null;
        } else if (count($result) == 0) {
            $this->io->warning("Found no members named '$name'.");
            return null;
        } else {
            return \intval($result[0]['id']);
        }
    }
}
