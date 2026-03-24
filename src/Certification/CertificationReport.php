<?php
declare(strict_types=1);
namespace Moffhub\Cli\Certification;

final class CertificationReport
{
    public readonly int $total;
    public readonly int $passedCount;
    public readonly int $failedCount;
    public readonly bool $passed;

    public function __construct(
        public readonly array $results,
    ) {
        $this->total = count($results);
        $this->passedCount = count(array_filter($results, fn ($r) => $r['passed']));
        $this->failedCount = $this->total - $this->passedCount;
        $this->passed = $this->failedCount === 0;
    }
}
