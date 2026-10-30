<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Service;

/** Parse legacy labeled gy_gov_id/gy_gov_idnum without returning raw IDs. */
final class GovernmentIdPresence
{
    /** @return array{sss: bool, phic: bool, hdmf: bool, tin: bool} */
    public static function fromLegacyValues(mixed $first, mixed $second): array
    {
        $flags = ['sss' => false, 'phic' => false, 'hdmf' => false, 'tin' => false];
        $pattern = '/\b(GSIS|SSS|PHIC|PHIL\s*HEALTH|HDMF|PAG\s*[-–—]?\s*IBIG|TIN)\b\s*:?\s*/i';
        foreach ([$first, $second] as $raw) {
            $text = preg_replace('/<br\s*\/?\s*>/i', "\n", (string) ($raw ?? ''));
            $text = str_replace("\r", "\n", $text ?? '');
            if ($text === '') continue;
            if (!preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) continue;
            foreach ($matches[0] as $i => $marker) {
                $label = strtoupper(preg_replace('/\s+/', '', (string) $matches[1][$i][0]));
                $label = str_replace(['–', '—', '-'], '', $label);
                $key = match ($label) {
                    'SSS' => 'sss',
                    'PHIC', 'PHILHEALTH' => 'phic',
                    'HDMF', 'PAGIBIG' => 'hdmf',
                    'TIN' => 'tin',
                    default => null,
                };
                if ($key === null || $flags[$key]) continue;
                $start = $marker[1] + strlen($marker[0]);
                $end = isset($matches[0][$i + 1]) ? $matches[0][$i + 1][1] : strlen($text);
                $value = trim(substr($text, $start, $end - $start));
                $value = trim($value, " \t\n\r\0\x0B:;,|-");
                if ($value !== '' && !preg_match('/^(?:N\/?A|NONE|NULL|NOT APPLICABLE)$/i', $value)) {
                    $flags[$key] = true;
                }
            }
        }
        return $flags;
    }
}
