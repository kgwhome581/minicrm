<?php

declare(strict_types=1);

namespace OCA\MiniCRM\Service;

class PhoneNormalizer {
    /**
     * Normalize a phone number to standard E.164 format.
     * Special handling for North America (NANP: Canada / US: +1) if 10 digits provided.
     */
    public function normalize(?string $rawPhone): string {
        if ($rawPhone === null || trim($rawPhone) === '') {
            return '';
        }

        $trimmed = trim($rawPhone);
        // Strip everything except digits and leading '+'
        $hasLeadingPlus = str_starts_with($trimmed, '+');
        $digits = preg_replace('/[^\d]/', '', $trimmed);

        if (empty($digits)) {
            return '';
        }

        if ($hasLeadingPlus) {
            return '+' . $digits;
        }

        // Check if 10 digits (Standard Canada/US area code + number, e.g. 7801234567)
        if (strlen($digits) === 10) {
            return '+1' . $digits;
        }

        // Check if 11 digits starting with 1 (e.g. 17801234567)
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return '+' . $digits;
        }

        // Default fallback with +
        return '+' . $digits;
    }

    /**
     * Generates a safe file / folder name from a client name.
     */
    public function sanitizeName(string $name): string {
        $sanitized = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $name);
        $sanitized = preg_replace('/\s+/', ' ', trim((string)$sanitized));
        return empty($sanitized) ? 'Client' : $sanitized;
    }
}
