<?php

declare(strict_types=1);

namespace Casablanca\Booking\Frontend;

/**
 * Pretty URLs for room/package detail pages: /{page-path}/{slug}/
 *
 * WordPress page rewrites often win over custom rules for paths like
 * /pakete/{slug}/, leaving query vars empty while still rendering the page.
 * We therefore also parse the request path on `request` and when resolving slugs.
 */
final class DetailRewrite
{
    public const QUERY_ROOM = 'casablanca_room_slug';
    public const QUERY_PACKAGE = 'casablanca_package_slug';
    public const OPTION_KEY = 'casablanca_booking_detail_pages';
    public const REWRITE_VERSION_OPTION = 'casablanca_booking_rewrite_version';
    public const REWRITE_VERSION = '1.0.0';

    public function register(): void
    {
        add_action('init', [$this, 'addRewrites'], 5);
        add_action('init', [$this, 'maybeFlushRewrites'], 20);
        add_filter('query_vars', [$this, 'registerQueryVars']);
        add_filter('request', [$this, 'filterRequest']);
        add_filter('redirect_canonical', [$this, 'filterRedirectCanonical'], 10, 2);
        add_action('save_post', [$this, 'onSavePost'], 20, 2);
    }

    /**
     * @param array<int, string> $vars
     * @return array<int, string>
     */
    public function registerQueryVars(array $vars): array
    {
        $vars[] = self::QUERY_ROOM;
        $vars[] = self::QUERY_PACKAGE;

        return $vars;
    }

    public function addRewrites(): void
    {
        $pages = $this->getRegisteredPages();

        foreach ($pages['room'] as $pageId) {
            $this->addPageRewrite((int) $pageId, self::QUERY_ROOM);
        }
        foreach ($pages['package'] as $pageId) {
            $this->addPageRewrite((int) $pageId, self::QUERY_PACKAGE);
        }
    }

    /**
     * One-shot flush after plugin updates so live sites pick up detail rules
     * without requiring a manual permalink save (still recommended once).
     */
    public function maybeFlushRewrites(): void
    {
        $stored = (string) get_option(self::REWRITE_VERSION_OPTION, '');
        if ($stored === self::REWRITE_VERSION) {
            return;
        }

        $this->syncRegisteredPagesFromContent();
        $this->addRewrites();
        flush_rewrite_rules(false);
        update_option(self::REWRITE_VERSION_OPTION, self::REWRITE_VERSION, false);
    }

    /**
     * When the path is {detail-page}/{slug}/, force the page query and slug var.
     *
     * @param array<string, mixed> $queryVars
     * @return array<string, mixed>
     */
    public function filterRequest(array $queryVars): array
    {
        $match = $this->matchDetailRequest();
        if ($match === null) {
            return $queryVars;
        }

        unset(
            $queryVars['error'],
            $queryVars['name'],
            $queryVars['pagename'],
            $queryVars['attachment'],
            $queryVars['category_name'],
            $queryVars['page'],
            $queryVars['year'],
            $queryVars['monthnum'],
            $queryVars['day']
        );

        $queryVars['page_id'] = $match['page_id'];
        $queryVars[$match['query_var']] = $match['slug'];

        return $queryVars;
    }

    /**
     * Prevent redirect_canonical from stripping /{slug}/ off detail URLs.
     *
     * @param string|false $redirectUrl
     * @return string|false
     */
    public function filterRedirectCanonical($redirectUrl, string $requestedUrl)
    {
        if ($this->matchDetailRequest() !== null) {
            return false;
        }

        return $redirectUrl;
    }

    public function onSavePost(int $postId, \WP_Post $post): void
    {
        if (wp_is_post_revision($postId) || $post->post_status === 'auto-draft') {
            return;
        }
        if (! in_array($post->post_type, ['page', 'post'], true)) {
            return;
        }

        $before = $this->getRegisteredPages();
        $this->syncRegisteredPagesFromContent();
        $after = $this->getRegisteredPages();

        if ($before !== $after) {
            flush_rewrite_rules(false);
        }
    }

    /**
     * Register a detail page id discovered from a block attribute (no flush if unchanged).
     */
    public function ensurePageRegistered(int $pageId, string $type): void
    {
        if ($pageId <= 0 || ! in_array($type, ['room', 'package'], true)) {
            return;
        }

        $pages = $this->getRegisteredPages();
        if (in_array($pageId, $pages[$type], true)) {
            return;
        }

        $pages[$type][] = $pageId;
        $pages[$type] = array_values(array_unique(array_map('intval', $pages[$type])));
        update_option(self::OPTION_KEY, $pages, false);
        flush_rewrite_rules(false);
    }

    public function flush(): void
    {
        $this->syncRegisteredPagesFromContent();
        $this->addRewrites();
        flush_rewrite_rules(false);
        update_option(self::REWRITE_VERSION_OPTION, self::REWRITE_VERSION, false);
    }

    public function resolveRoomSlug(): string
    {
        return $this->resolveSlug(self::QUERY_ROOM, 'room');
    }

    public function resolvePackageSlug(): string
    {
        return $this->resolveSlug(self::QUERY_PACKAGE, 'package');
    }

    public function buildDetailUrl(int $pageId, string $slug): string
    {
        if ($pageId <= 0 || $slug === '') {
            return '';
        }

        $permalink = get_permalink($pageId);
        if (! is_string($permalink) || $permalink === '') {
            return '';
        }

        $slug = sanitize_title(rawurldecode($slug));
        if ($slug === '') {
            return '';
        }

        return trailingslashit($permalink) . rawurlencode($slug) . '/';
    }

    /**
     * @return array{room: int[], package: int[]}
     */
    public function getRegisteredPages(): array
    {
        $raw = get_option(self::OPTION_KEY, ['room' => [], 'package' => []]);
        if (! is_array($raw)) {
            $raw = [];
        }

        return [
            'room' => array_values(array_unique(array_map('intval', (array) ($raw['room'] ?? [])))),
            'package' => array_values(array_unique(array_map('intval', (array) ($raw['package'] ?? [])))),
        ];
    }

    private function resolveSlug(string $queryVar, string $type): string
    {
        $slug = trim((string) get_query_var($queryVar));
        if ($slug !== '') {
            return sanitize_title(rawurldecode($slug));
        }

        $match = $this->matchDetailRequest();
        if ($match !== null && $match['query_var'] === $queryVar) {
            return $match['slug'];
        }

        // Last resort: last path segment when the current queried page is a known detail page.
        $pageId = (int) get_queried_object_id();
        if ($pageId > 0) {
            $pages = $this->getRegisteredPages();
            if (in_array($pageId, $pages[$type], true)) {
                $segment = $this->lastPathSegmentAfterPage($pageId);
                if ($segment !== '') {
                    return $segment;
                }
            }
        }

        return '';
    }

    /**
     * @return array{page_id: int, slug: string, query_var: string}|null
     */
    private function matchDetailRequest(): ?array
    {
        $path = $this->requestPath();
        if ($path === '') {
            return null;
        }

        $pages = $this->getRegisteredPages();
        if ($pages['room'] === [] && $pages['package'] === []) {
            $this->syncRegisteredPagesFromContent();
            $pages = $this->getRegisteredPages();
        }

        foreach ($pages['package'] as $pageId) {
            $match = $this->matchPageSlug($path, (int) $pageId, self::QUERY_PACKAGE);
            if ($match !== null) {
                return $match;
            }
        }
        foreach ($pages['room'] as $pageId) {
            $match = $this->matchPageSlug($path, (int) $pageId, self::QUERY_ROOM);
            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }

    /**
     * @return array{page_id: int, slug: string, query_var: string}|null
     */
    private function matchPageSlug(string $path, int $pageId, string $queryVar): ?array
    {
        if ($pageId <= 0) {
            return null;
        }

        $uri = get_page_uri($pageId);
        if (! is_string($uri) || $uri === '') {
            return null;
        }

        $uri = trim($uri, '/');
        $prefix = $uri . '/';
        if ($path !== $uri && ! str_starts_with($path, $prefix)) {
            return null;
        }

        if ($path === $uri) {
            return null;
        }

        $rest = substr($path, strlen($prefix));
        if ($rest === false || $rest === '' || str_contains($rest, '/')) {
            return null;
        }

        $slug = sanitize_title(rawurldecode($rest));
        if ($slug === '') {
            return null;
        }

        return [
            'page_id' => $pageId,
            'slug' => $slug,
            'query_var' => $queryVar,
        ];
    }

    private function lastPathSegmentAfterPage(int $pageId): string
    {
        $path = $this->requestPath();
        $match = $this->matchPageSlug($path, $pageId, 'tmp');

        return $match['slug'] ?? '';
    }

    private function requestPath(): string
    {
        $raw = isset($_SERVER['REQUEST_URI'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_URI']))
            : '';
        if ($raw === '') {
            return '';
        }

        $path = (string) (wp_parse_url($raw, PHP_URL_PATH) ?? '');
        $homePath = (string) (wp_parse_url(home_url('/'), PHP_URL_PATH) ?? '/');
        $homePath = trim($homePath, '/');
        $path = trim($path, '/');

        if ($homePath !== '' && str_starts_with($path, $homePath . '/')) {
            $path = substr($path, strlen($homePath) + 1);
        } elseif ($homePath !== '' && $path === $homePath) {
            $path = '';
        }

        return trim((string) $path, '/');
    }

    private function addPageRewrite(int $pageId, string $queryVar): void
    {
        if ($pageId <= 0) {
            return;
        }

        $uri = get_page_uri($pageId);
        if (! is_string($uri) || $uri === '') {
            return;
        }

        $pattern = '^' . preg_quote($uri, '#') . '/([^/]+)/?$';
        add_rewrite_rule(
            $pattern,
            'index.php?page_id=' . $pageId . '&' . $queryVar . '=$matches[1]',
            'top'
        );
    }

    private function syncRegisteredPagesFromContent(): void
    {
        $roomPages = [];
        $packagePages = [];

        $posts = get_posts([
            'post_type' => ['page', 'post'],
            'post_status' => ['publish', 'private', 'draft'],
            'numberposts' => 200,
            'suppress_filters' => false,
        ]);

        foreach ($posts as $post) {
            if (! $post instanceof \WP_Post) {
                continue;
            }
            $content = (string) $post->post_content;
            $this->collectFromBlocks(
                parse_blocks($content),
                (int) $post->ID,
                $roomPages,
                $packagePages
            );
            $this->collectFromShortcodes($content, (int) $post->ID, $roomPages, $packagePages);
        }

        update_option(self::OPTION_KEY, [
            'room' => array_values(array_unique($roomPages)),
            'package' => array_values(array_unique($packagePages)),
        ], false);
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @param int[] $roomPages
     * @param int[] $packagePages
     */
    private function collectFromBlocks(array $blocks, int $currentPostId, array &$roomPages, array &$packagePages): void
    {
        foreach ($blocks as $block) {
            $name = (string) ($block['blockName'] ?? '');
            $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];

            if ($name === 'casablanca/room-detail') {
                $roomPages[] = $currentPostId;
            }
            if ($name === 'casablanca/package-detail') {
                $packagePages[] = $currentPostId;
            }
            if (in_array($name, ['casablanca/room-types', 'casablanca/room-detail'], true)) {
                $detailPageId = (int) ($attrs['detailPageId'] ?? 0);
                if ($detailPageId > 0) {
                    $roomPages[] = $detailPageId;
                }
            }
            if (in_array($name, ['casablanca/packages', 'casablanca/package-detail'], true)) {
                $detailPageId = (int) ($attrs['detailPageId'] ?? 0);
                if ($detailPageId > 0) {
                    $packagePages[] = $detailPageId;
                }
            }

            $inner = $block['innerBlocks'] ?? [];
            if (is_array($inner) && $inner !== []) {
                $this->collectFromBlocks($inner, $currentPostId, $roomPages, $packagePages);
            }
        }
    }

    /**
     * @param int[] $roomPages
     * @param int[] $packagePages
     */
    private function collectFromShortcodes(string $content, int $currentPostId, array &$roomPages, array &$packagePages): void
    {
        if ($content === '' || ! str_contains($content, '[')) {
            return;
        }

        if (has_shortcode($content, 'casablanca_room_detail')) {
            $roomPages[] = $currentPostId;
        }
        if (has_shortcode($content, 'casablanca_package_detail')) {
            $packagePages[] = $currentPostId;
        }

        foreach (['casablanca_room_types' => 'room', 'casablanca_packages' => 'package'] as $tag => $type) {
            if (! has_shortcode($content, $tag)) {
                continue;
            }
            if (! preg_match_all('/\[' . preg_quote($tag, '/') . '\b([^\]]*)\]/i', $content, $matches)) {
                continue;
            }
            foreach ($matches[1] as $attrString) {
                $atts = shortcode_parse_atts((string) $attrString);
                if (! is_array($atts)) {
                    continue;
                }
                $detailPageId = (int) ($atts['detailPageId'] ?? $atts['detailpageid'] ?? 0);
                if ($detailPageId <= 0) {
                    continue;
                }
                if ($type === 'room') {
                    $roomPages[] = $detailPageId;
                } else {
                    $packagePages[] = $detailPageId;
                }
            }
        }
    }
}
