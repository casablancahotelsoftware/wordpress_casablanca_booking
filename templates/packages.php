<?php
defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals from include scope, not plugin globals.
/**
 * Package overview.
 *
 * @var array<string, mixed> $context
 * @var array<string, mixed> $attrs
 * @var array<int, array<string, mixed>> $cards
 * @var DateTimeImmutable $from
 * @var int $stayNights
 */
$config = $context['config'];
$labels = $context['labels'];
/** @var \Casablanca\Booking\Frontend\LabelResolver $lr */
$lr = $context['lr'];
$layoutClass = $attrs['overviewLayout'] === 'list' ? ' cb-packages__grid--list' : '';
include CASABLANCA_BOOKING_PATH . 'templates/partials/widget-shell-open.php';
?>
<div class="cb-packages">
    <h2 id="cb-widget-heading" class="cb-packages__heading">
        <?php echo esc_html($lr->resolve('packages.heading', 'Packages & offers', (string) ($labels['heading'] ?? ''))); ?>
    </h2>

    <?php if ($cards === []) : ?>
        <p class="cb-packages__empty"><?php
            echo esc_html(
                ! empty($attrs['filterByStayNights'])
                    ? $lr->resolve('packages.empty', 'No packages match the selected stay length.')
                    : $lr->resolve('packages.emptyCatalog', 'No packages available yet. Run a sync first.')
            );
        ?></p>
    <?php else : ?>
        <div class="cb-packages__grid<?php echo esc_attr($layoutClass); ?>">
            <?php foreach ($cards as $card) :
                $package = $card['item'];
                $linkTarget = (string) $attrs['ibeLinkTarget'];
                $rel = $linkTarget === '_blank' ? 'noopener noreferrer' : '';
                ?>
                <article class="cb-package-card">
                    <?php if ($attrs['showImage'] && ($package['image_url'] ?? '') !== '') : ?>
                        <div class="cb-package-card__image">
                            <img src="<?php echo esc_url((string) $package['image_url']); ?>"
                                 alt="<?php echo esc_attr((string) $package['name']); ?>"
                                 loading="lazy" />
                        </div>
                    <?php endif; ?>

                    <div class="cb-package-card__body">
                        <?php if ($attrs['showName']) : ?>
                            <h3 class="cb-package-card__title"><?php echo esc_html((string) $package['name']); ?></h3>
                        <?php endif; ?>

                        <?php
                        $descriptionOverview = $card['descriptionOverview'];
                        $showDescription = (bool) $attrs['showDescription'];
                        include CASABLANCA_BOOKING_PATH . 'templates/partials/overview-description.php';
                        ?>

                        <?php if ($attrs['showPrice'] && $card['fromPrice'] !== null) : ?>
                            <p class="cb-package-card__price">
                                <span class="cb-package-card__price-label"><?php echo esc_html($lr->resolve('packages.fromPrice', 'from')); ?></span>
                                <span class="cb-package-card__price-value"><?php echo esc_html(number_format((float) $card['fromPrice'], 2) . ' ' . $card['currency']); ?></span>
                            </p>
                        <?php endif; ?>

                        <?php if ($attrs['cardLinkType'] === 'book') : ?>
                            <a href="<?php echo esc_url((string) $card['ibeUrl']); ?>"
                               class="cb-package-card__book cb-button"
                               target="<?php echo esc_attr($linkTarget); ?>"
                               <?php echo $rel !== '' ? 'rel="' . esc_attr($rel) . '"' : ''; ?>
                               data-cb-ibe-base="<?php echo esc_attr($config->ibeBaseUrl); ?>"
                               data-cb-tenant="<?php echo esc_attr($config->tenantId); ?>"
                               data-cb-ibe-context="<?php echo esc_attr($config->urlFriendlyIbeContextId); ?>"
                               data-cb-link-style="<?php echo esc_attr($config->ibeLinkStyle); ?>"
                               data-cb-link-target="<?php echo esc_attr($linkTarget); ?>"
                               data-cb-culture="<?php echo esc_attr((string) $context['culture']); ?>"
                               data-cb-rate-ids="<?php echo esc_attr((string) $package['rate_id']); ?>"
                               data-cb-adults="<?php echo esc_attr((string) $attrs['defaultAdults']); ?>"
                               data-cb-children="0"
                               data-cb-arrival="<?php echo esc_attr($from->format('Y-m-d')); ?>"
                               data-cb-stay-nights="<?php echo esc_attr((string) $stayNights); ?>">
                                <?php echo esc_html($lr->resolve('packages.book', 'Book package', (string) ($labels['book'] ?? ''))); ?>
                            </a>
                        <?php elseif ($attrs['cardLinkType'] === 'details' && ($card['detailUrl'] ?? '') !== '') : ?>
                            <a href="<?php echo esc_url((string) $card['detailUrl']); ?>"
                               class="cb-package-card__details cb-button cb-button--secondary">
                                <?php echo esc_html($lr->resolve('packages.details', 'Details', (string) ($labels['details'] ?? ''))); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php include CASABLANCA_BOOKING_PATH . 'templates/partials/widget-shell-close.php'; ?>
