<?php

declare(strict_types=1);

namespace Casablanca\Booking\Domain\Utility;

final class DescriptionPresenter
{
    /**
     * @param array<string, mixed> $data
     */
    public static function extractShortDescriptionFromApi(array $data): string
    {
        foreach (['shortDescription', 'shortText', 'teaser'] as $key) {
            $value = trim((string) ($data[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    public static function resolveOverviewText(string $shortDescription, string $fullDescription, string $mode): string
    {
        if ($mode === 'teaser') {
            return $shortDescription !== '' ? $shortDescription : $fullDescription;
        }

        return $fullDescription;
    }

    /**
     * @return array{mode: string, html: string, preview: string, needsExpand: bool}
     */
    public static function buildOverviewPresentation(
        string $shortDescription,
        string $fullDescription,
        string $mode,
        int $limit
    ): array {
        if ($mode === 'teaser') {
            $html = self::resolveOverviewText($shortDescription, $fullDescription, 'teaser');

            return [
                'mode' => 'teaser',
                'html' => $html,
                'preview' => '',
                'needsExpand' => false,
            ];
        }

        if ($fullDescription === '') {
            return ['mode' => 'full', 'html' => '', 'preview' => '', 'needsExpand' => false];
        }

        $plain = wp_strip_all_tags($fullDescription);
        $needsExpand = mb_strlen($plain) > $limit;

        return [
            'mode' => 'full',
            'html' => $fullDescription,
            'preview' => $needsExpand ? mb_substr($plain, 0, $limit) . '…' : $plain,
            'needsExpand' => $needsExpand,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $images
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeCarouselImages(array $images, string $fallbackUrl): array
    {
        if ($images === [] && $fallbackUrl !== '') {
            return [['url' => $fallbackUrl, 'sort' => 0]];
        }

        usort($images, static fn (array $a, array $b): int => ((int) ($a['sort'] ?? 0)) <=> ((int) ($b['sort'] ?? 0)));

        return $images;
    }
}
