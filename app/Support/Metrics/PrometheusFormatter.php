<?php

declare(strict_types=1);

namespace App\Support\Metrics;

/**
 * Prometheus text exposition format helpers (W6-01).
 */
final class PrometheusFormatter
{
    /**
     * @param  array<string, string>  $labels
     */
    public function line(string $name, float|int|string $value, array $labels = []): string
    {
        if ($labels === []) {
            return "{$name} {$value}";
        }

        $parts = [];
        foreach ($labels as $key => $val) {
            $parts[] = "{$key}=\"".$this->escape((string) $val).'"';
        }

        return $name.'{'.implode(',', $parts)."} {$value}";
    }

    /**
     * @return list<string>
     */
    public function head(string $name, string $help, string $type): array
    {
        return ["# HELP {$name} {$help}", "# TYPE {$name} {$type}"];
    }

    /**
     * Format a histogram bucket boundary for the le= label.
     */
    public function bucket(float $bucket): string
    {
        if ($bucket === (float) (int) $bucket) {
            return (string) (int) $bucket;
        }

        return (string) $bucket;
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $value);
    }
}
