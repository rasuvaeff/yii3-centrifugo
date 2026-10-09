<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Console;

use Rasuvaeff\Yii3Centrifugo\Doctor\CentrifugoDoctor;
use Rasuvaeff\Yii3Centrifugo\Doctor\CheckStatus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `centrifugo:doctor` — prints one line per check and exits with a stable
 * code: 0 healthy, 2 config, 4 upstream (category of the first failure).
 * Needs `symfony/console` (registered for `yiisoft/yii-console`).
 *
 * @api
 */
#[AsCommand(name: 'centrifugo:doctor', description: 'Diagnose the Centrifugo configuration and server API')]
final class CentrifugoDoctorCommand extends Command
{
    public function __construct(
        private readonly CentrifugoDoctor $doctor,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $report = $this->doctor->diagnose();

        foreach ($report->checks as $check) {
            $output->writeln(sprintf(
                '%s %s: %s',
                match ($check->status) {
                    CheckStatus::Pass => '<info>[pass]</info>',
                    CheckStatus::Fail => '<error>[FAIL]</error>',
                    CheckStatus::Skip => '<comment>[skip]</comment>',
                },
                $check->name,
                $check->details,
            ));
        }

        return $report->exitCode();
    }
}
