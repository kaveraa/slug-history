<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Symfony\Command;

use Kaveraa\SlugHistory\Doctrine\SchemaInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Creates the table of past addresses. Safe to run again: it does nothing
 * when the table is already there.
 */
#[AsCommand(
    name: 'slugs:install',
    description: 'Crée la table des anciennes adresses.',
)]
final class InstallCommand extends Command
{
    public function __construct(private readonly SchemaInstaller $installer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->installer->install()) {
            $io->success(sprintf('La table "%s" a été créée.', $this->installer->name()));

            return Command::SUCCESS;
        }

        $io->info(sprintf('La table "%s" existe déjà : rien à faire.', $this->installer->name()));

        return Command::SUCCESS;
    }
}
