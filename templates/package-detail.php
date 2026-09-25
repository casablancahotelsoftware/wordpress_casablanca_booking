<?php
defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals from include scope, not plugin globals.
/**
 * Single package detail.
 *
 * @var array<string, mixed> $context
 * @var array<string, mixed> $attrs
 * @var array<int, array<string, mixed>> $cards
 * @var array<string, mixed> $detailCalendar
 * @var DateTimeImmutable $from
 * @var int $stayNights
 * @var string|null $error
 */
$config = $context['config'];
$labels = $context['labels'];
/** @var \Casablanca\Booking\Frontend\LabelResolver $lr */
$lr = $context['lr'];
include CASABLANCA_BOOKING_PATH . 'templates/partials/widget-shell-open.php';

if ($error === 'package_not_found') :
    ?>
    <div class="cb-widget__error" role="alert"><?php echo esc_html($lr->resolve('packages.notFound', 'Package not found.')); ?></div>
    <?php
else :
    if (! empty($detailCalendar['show']) && ($detailCalendar['position'] ?? '') === 'above') {
        $calendarEmbedContext = $detailCalendar['context'];
        $calendarEmbedAttrs = $detailCalendar['attrs'];
        include CASABLANCA_BOOKING_PATH . 'templates/partials/detail-calendar-embed.php';
    }

    foreach ($cards as $card) :
        $package = $card['item'];
        $linkTarget = (string) $attrs['ibeLinkTarget'];
        $rel = $linkTarget === '_blank' ? 'noopener noreferrer' : '';
        $carouselImages = $card['carouselImages'];
        $carouselAlt = (string) $package['name'];
        ?>
        <article class="cb-package-detail">
            <?php if ($attrs['showImage']) :
                include CASABLANCA_BOOKING_PATH . 'templates/partials/image-carousel.php';
            endif; ?>

            <div class="cb-package-detail__body">
                <?php if ($attrs['showName']) : ?>
                    <h2 class="cb-package-detail__title"><?php echo esc_html((string) $package['name']); ?></h2>
                <?php endif; ?>

                <?php if ($attrs['showDescription'] && ($package['description'] ?? '') !== '') : ?>
                    <div class="cb-package-detail__description"><?php echo wp_kses_post((string) $package['description']); ?></div>
                <?php endif; ?>

                <?php if (($package['catering_type'] ?? '') !== '') : ?>
                    <p class="cb-package-detail__meta">
                        <?php echo esc_html($lr->resolve('packages.catering', 'Catering')); ?>:
                        <?php echo esc_html((string) $package['catering_type']); ?>
                    </p>
                <?php endif; ?>

                <p class="cb-package-detail__nights">
                    <?php
                    $nightsKey = ((int) $stayNights === 1) ? 'packages.night' : 'packages.nights';
                    $nightsDefault = ((int) $stayNights === 1) ? '%s night' : '%s nights';
                    echo esc_html($lr->resolve($nightsKey, $nightsDefault, '', [(string) (int) $stayNights]));
                    ?>
                </p>

                <?php if ($attrs['showPrice'] && $card['fromPrice'] !== null) : ?>
                    <p class="cb-package-detail__price">
                        <span class="cb-package-detail__price-label"><?php echo esc_html($lr->resolve('packages.fromPrice', 'from')); ?></span>
                        <span class="cb-package-detail__price-value"><?php echo esc_html(number_format((float) $card['fromPrice'], 2) . ' ' . $card['currency']); ?></span>
                    </p>
                <?php endif; ?>

                <?php if (empty($detailCalendar['show'])) : ?>
                    <a href="<?php echo esc_url((string) $card['ibeUrl']); ?>"
                       class="cb-package-detail__book cb-button"
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
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach;

    if (! empty($detailCalendar['show']) && ($detailCalendar['position'] ?? '') === 'below') {
        $calendarEmbedContext = $detailCalendar['context'];
        $calendarEmbedAttrs = $detailCalendar['attrs'];
        include CASABLANCA_BOOKING_PATH . 'templates/partials/detail-calendar-embed.php';
    }
endif;

include CASABLANCA_BOOKING_PATH . 'templates/partials/widget-shell-close.php';
?>
