<?php

declare(strict_types=1);

namespace App\Command;

use App\Database\ConnectionFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Reports which expected environment variables are present.
 *
 * This is the Zone B verification described in docs/plan.md Phase 1: running
 * it bare shows secrets absent, and running it under `infisical run` shows
 * them present, proving runtime injection works.
 *
 * It prints PRESENCE ONLY — never a value, never a length, never a prefix.
 * That restriction is a hard rule (CLAUDE.md Secrets Rules), and it is what
 * makes this command safe to run in a shared terminal or paste into an issue.
 */
#[AsCommand(
    name: 'env:check',
    description: 'Report which expected environment variables are set (names and presence only).',
)]
class EnvCheck extends Command
{
    /**
     * @var array<string, list<string>>
     */
    private const array EXPECTED = [
        'Application' => ['APP_DEBUG'],
        'Database' => ['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE'],
        'Zoho Books' => [
            'ZOHO_CLIENT_ID',
            'ZOHO_CLIENT_SECRET',
            'ZOHO_REFRESH_TOKEN',
            'ZOHO_ORGANIZATION_ID',
            'ZOHO_API_DOMAIN',
        ],
        'Backblaze B2' => ['B2_KEY_ID', 'B2_APPLICATION_KEY', 'B2_BUCKET', 'B2_REGION', 'B2_ENDPOINT'],
    ];

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Environment check');

        foreach (self::EXPECTED as $group => $names) {
            $rows = [];

            foreach ($names as $name) {
                $rows[] = [$name, $this->isSet($name) ? '<info>set</info>' : '<comment>unset</comment>'];
            }

            $io->section($group);
            $io->table(['Variable', 'Status'], $rows);
        }

        $io->section('Database connectivity');
        $reachable = ConnectionFactory::isReachable();
        $io->writeln($reachable
            ? '<info>Staging database reachable.</info>'
            : '<comment>Staging database unreachable.</comment>');

        $io->newLine();
        $io->writeln('Values are never printed — presence only.');

        return Command::SUCCESS;
    }

    private function isSet(string $name): bool
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        return is_string($value) && $value !== '';
    }
}
