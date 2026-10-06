<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Symfony\Command;

use Kaveraa\SlugHistory\SlugHistory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Deletes the past addresses that are too old. With no duration, neither here
 * nor in the configuration, the command stops: deleting the whole history by
 * accident is not an option.
 */
#[AsCommand(
    name: 'slugs:purge',
    description: 'Efface les anciennes adresses trop vieilles.',
)]
final class PurgeCommand extends Command
{
    public function __construct(
        private readonly SlugHistory $history,
        /** The retention duration from the configuration. Null: forever. */
        private readonly ?int $keepForDays = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'older-than',
            null,
            InputOption::VALUE_REQUIRED,
            'Âge en jours à partir duquel une ancienne adresse est effacée.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = $this->days($io, $input->getOption('older-than'));

        if ($days === null) {
            return Command::FAILURE;
        }

        $gone = $this->history->purgeOlderThan($days);

        $io->success(sprintf(
            '%d ancienne(s) adresse(s) de plus de %d jour(s) ont été effacées.',
            $gone,
            $days,
        ));

        return Command::SUCCESS;
    }

    /**
     * The number of days asked for, or the one from the configuration. Null when
     * the value makes no sense: the message is already shown.
     */
    private function days(SymfonyStyle $io, mixed $given): ?int
    {
        if ($given === null || $given === '') {
            if ($this->keepForDays === null) {
                $io->error(
                    'Aucune durée de conservation : donnez --older-than=<jours>, '
                    . 'ou renseignez slug_history.keep_for_days.',
                );

                return null;
            }

            return $this->keepForDays;
        }

        if (!is_numeric($given) || (int) $given < 1 || (string) (int) $given !== (string) $given) {
            $io->error(sprintf(
                'Durée invalide : "%s". Donnez un nombre entier de jours supérieur à zéro.',
                is_scalar($given) ? (string) $given : gettype($given),
            ));

            return null;
        }

        return (int) $given;
    }
}
