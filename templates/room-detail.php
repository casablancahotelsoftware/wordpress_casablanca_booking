<?php
defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals from include scope, not plugin globals.
/**
 * Single room detail.
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

if ($error === 'room_not_found') :
    ?>
    <div class="cb-widget__error" role="alert"><?php echo esc_html($lr->resolve('roomtypes.notFound', 'Room not found.')); ?></div>
    <?php
else :
    if (! empty($detailCalendar['show']) && ($detailCalendar['position'] ?? '') === 'above') {
        $calendarEmbedContext = $detailCalendar['context'];
        $calendarEmbedAttrs = $detailCalendar['attrs'];
        include CASABLANCA_BOOKING_PATH . 'templates/partials/detail-calendar-embed.php';
    }

    foreach ($cards as $card) :
        $room = $card['item'];
        $linkTarget = (string) $attrs['ibeLinkTarget'];
        $rel = $linkTarget === '_blank' ? 'noopener noreferrer' : '';
        $carouselImages = $card['carouselImages'];
        $carouselAlt = (string) $room['name'];
        ?>
        <article class="cb-room-detail">
            <?php if ($attrs['showImage']) :
                include CASABLANCA_BOOKING_PATH . 'templates/partials/image-carousel.php';
            endif; ?>

            <div class="cb-room-detail__body">
                <?php if ($attrs['showName']) : ?>
                    <h2 class="cb-room-detail__title"><?php echo esc_html((string) $room['name']); ?></h2>
                <?php endif; ?>

                <?php if ($attrs['showDescription'] && ($room['description'] ?? '') !== '') : ?>
                    <div class="cb-room-detail__description"><?php echo wp_kses_post((string) $room['description']); ?></div>
                <?php endif; ?>

                <?php if ($attrs['showPrice'] && $card['fromPrice'] !== null) : ?>
                    <p class="cb-room-detail__price">
                        <span class="cb-room-detail__price-label"><?php echo esc_html($lr->resolve('roomtypes.fromPrice', 'from')); ?></span>
                        <span class="cb-room-detail__price-value"><?php echo esc_html(number_format((float) $card['fromPrice'], 2) . ' ' . $card['currency']); ?></span>
                    </p>
                <?php endif; ?>

                <?php if (empty($detailCalendar['show'])) : ?>
                    <a href="<?php echo esc_url((string) $card['ibeUrl']); ?>"
                       class="cb-room-detail__book cb-button"
                       target="<?php echo esc_attr($linkTarget); ?>"
                       <?php echo $rel !== '' ? 'rel="' . esc_attr($rel) . '"' : ''; ?>
                       data-cb-ibe-base="<?php echo esc_attr($config->ibeBaseUrl); ?>"
                       data-cb-tenant="<?php echo esc_attr($config->tenantId); ?>"
                       data-cb-ibe-context="<?php echo esc_attr($config->urlFriendlyIbeContextId); ?>"
                       data-cb-link-style="<?php echo esc_attr($config->ibeLinkStyle); ?>"
                       data-cb-link-target="<?php echo esc_attr($linkTarget); ?>"
                       data-cb-culture="<?php echo esc_attr((string) $context['culture']); ?>"
                       data-cb-room-type="<?php echo esc_attr((string) $room['room_type_id']); ?>"
                       data-cb-adults="<?php echo esc_attr((string) $attrs['defaultAdults']); ?>"
                       data-cb-children="<?php echo esc_attr((string) $attrs['defaultChildren']); ?>"
                       data-cb-arrival="<?php echo esc_attr($from->format('Y-m-d')); ?>"
                       data-cb-stay-nights="<?php echo esc_attr((string) $stayNights); ?>">
                        <?php echo esc_html($lr->resolve('roomtypes.book', 'Book now', (string) ($labels['book'] ?? ''))); ?>
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
