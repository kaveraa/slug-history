<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Symfony\Command;

use Kaveraa\SlugHistory\PastSlug;
use Kaveraa\SlugHistory\SlugHistory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shows the past addresses of a content, and where they lead.
 *
 *     slugs:history "App\Entity\Article" 12
 */
#[AsCommand(
    name: 'slugs:history',
    description: 'Liste les anciennes adresses d\'un contenu.',
)]
final class HistoryCommand extends Command
{
    public function __construct(private readonly SlugHistory $history)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('type', InputArgument::REQUIRED, 'La classe du contenu, par exemple App\Entity\Article.')
            ->addArgument('id', InputArgument::REQUIRED, 'Son identifiant.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $type = (string) $input->getArgument('type');
        $id = (string) $input->getArgument('id');

        $past = $this->history->allFor($type, $id);

        if ($past === []) {
            $io->info(sprintf('Aucune ancienne adresse pour %s #%s.', $type, $id));

            return Command::SUCCESS;
        }

        $io->title(sprintf('Anciennes adresses de %s #%s', $type, $id));

        $io->table(
            ['Ancienne adresse', 'Adresse actuelle', 'Portée', 'Retenue le'],
            array_map(static fn (PastSlug $one): array => [
                $one->slug,
                $one->currentSlug,
                $one->scope === '' ? '-' : $one->scope,
                $one->rememberedAt?->format('Y-m-d H:i') ?? '-',
            ], $past),
        );

        return Command::SUCCESS;
    }
}
