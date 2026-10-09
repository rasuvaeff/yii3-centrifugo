<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Doctor;

/**
 * @api
 */
final readonly class DoctorReport
{
    /**
     * @param list<CheckResult> $checks in diagnosis order
     */
    public function __construct(
        public array $checks,
    ) {}

    public function healthy(): bool
    {
        return !$this->firstFailure() instanceof \Rasuvaeff\Yii3Centrifugo\Doctor\CheckResult;
    }

    /**
     * 0 when healthy, otherwise the category of the FIRST failing check
     * (2 config, 4 upstream): checks run in diagnosis order, so the first
     * failure is the root cause.
     */
    public function exitCode(): int
    {
        return $this->firstFailure()->category->value ?? 0;
    }

    private function firstFailure(): ?CheckResult
    {
        foreach ($this->checks as $check) {
            if ($check->status === CheckStatus::Fail) {
                return $check;
            }
        }

        return null;
    }
}
