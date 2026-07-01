<?php

namespace Syon\AuditSdk\Logs;

/**
 * Scans log files for personal data that shouldn't be sitting in them. Logs are
 * a data store too — usually with no retention policy, lawful basis, or notice —
 * so leaked emails, card numbers or credentials in them are unassessed processing
 * (and a breach waiting to happen).
 *
 * Reads line by line, so a multi-gigabyte log won't exhaust memory. Deliberately
 * conservative (a small set of high-signal patterns) to stay low-noise. Every
 * matched value is redacted in the result: the scanner reports *where* and *what
 * kind*, never the raw personal data — so its own output can't leak anything.
 */
class LogScanner
{
    private const REDACTION = '[redacted]';

    /** type => [pattern, human label]. Matched on the whole line. */
    private const PATTERNS = [
        'email' => [
            '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/',
            'Email address',
        ],
        'uk_nino' => [
            '/\b[ABCEGHJ-PRSTW-Z]{2}\s?\d{2}\s?\d{2}\s?\d{2}\s?[A-D]\b/',
            'UK National Insurance number',
        ],
        'credential' => [
            '/\b(?:password|passwd|pwd|secret|api[_\-]?key|access[_\-]?token|authorization|bearer)\b\s*["\':=]+\s*\S+/i',
            'Credential / secret',
        ],
        // A personal detail logged under a recognised *key* (structured/JSON context).
        // Matching the key — not free text — keeps it low-noise: `{"first_name":"Jane"}`
        // is flagged, but prose like "shipped to Jane Doe" is not.
        'personal_field' => [
            '/\b(?:name|first_?name|last_?name|full_?name|surname|forename|given_?name|dob|date_of_birth|phone|mobile|telephone|address|postcode|post_code)\b\s*["\':=]+\s*\S+/i',
            'Personal detail',
        ],
    ];

    /** Candidate card-shaped digit runs — confirmed with a Luhn check before flagging. */
    private const CARD_CANDIDATE = '/\b(?:\d[ \-]?){13,19}\b/';

    /**
     * @param  list<string>  $paths  Files, or directories whose *.log files are scanned.
     * @return list<array{type: string, label: string, file: string, line: int, context: string}>
     */
    public function scan(array $paths): array
    {
        $findings = [];

        foreach ($this->resolveFiles($paths) as $file) {
            foreach ($this->scanFile($file) as $finding) {
                $findings[] = $finding;
            }
        }

        return $findings;
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function resolveFiles(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                foreach (glob(rtrim($path, '/').'/*.log') ?: [] as $match) {
                    $files[] = $match;
                }
            } elseif (is_file($path)) {
                $files[] = $path;
            }
        }

        $files = array_values(array_unique($files));
        sort($files); // deterministic order

        return $files;
    }

    /**
     * @return list<array{type: string, label: string, file: string, line: int, context: string}>
     */
    private function scanFile(string $file): array
    {
        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            return [];
        }

        $findings = [];
        $lineNumber = 0;

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;

            $types = $this->detect($line);
            if ($types === []) {
                continue;
            }

            $context = $this->redact($line);

            foreach ($types as $type => $label) {
                $findings[] = [
                    'type' => $type,
                    'label' => $label,
                    'file' => $file,
                    'line' => $lineNumber,
                    'context' => $context,
                ];
            }
        }

        fclose($handle);

        return $findings;
    }

    /**
     * Which kinds of personal data a line contains — one entry per kind, so a line
     * dumping ten emails still counts once for that line.
     *
     * @return array<string, string>  type => label
     */
    private function detect(string $line): array
    {
        $found = [];

        foreach (self::PATTERNS as $type => [$pattern, $label]) {
            if (preg_match($pattern, $line) === 1) {
                $found[$type] = $label;
            }
        }

        if ($this->hasCardNumber($line)) {
            $found['credit_card'] = 'Payment card number';
        }

        return $found;
    }

    /** The line with every sensitive value replaced by a placeholder, trimmed for display. */
    private function redact(string $line): string
    {
        $line = rtrim($line, "\r\n");

        foreach (self::PATTERNS as [$pattern]) {
            $line = preg_replace($pattern, self::REDACTION, $line) ?? $line;
        }

        $line = $this->redactCardNumbers($line);
        $line = trim($line);

        if (mb_strlen($line) > 160) {
            $line = mb_substr($line, 0, 157).'…';
        }

        return $line;
    }

    private function hasCardNumber(string $line): bool
    {
        if (preg_match_all(self::CARD_CANDIDATE, $line, $matches) === 0) {
            return false;
        }

        foreach ($matches[0] as $candidate) {
            if ($this->isCardNumber($candidate)) {
                return true;
            }
        }

        return false;
    }

    private function redactCardNumbers(string $line): string
    {
        return preg_replace_callback(
            self::CARD_CANDIDATE,
            fn (array $m): string => $this->isCardNumber($m[0]) ? self::REDACTION : $m[0],
            $line,
        ) ?? $line;
    }

    private function isCardNumber(string $candidate): bool
    {
        $digits = preg_replace('/\D/', '', $candidate) ?? '';
        $length = strlen($digits);

        return $length >= 13 && $length <= 19 && $this->passesLuhn($digits);
    }

    private function passesLuhn(string $number): bool
    {
        $sum = 0;
        $double = false;

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $digit = (int) $number[$i];

            if ($double) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $double = ! $double;
        }

        return $sum % 10 === 0;
    }
}
