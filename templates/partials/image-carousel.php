<?php
defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals from include scope, not plugin globals.
/**
 * @var array<int, array<string, mixed>> $carouselImages
 * @var string $carouselAlt
 */
/** @var \Casablanca\Booking\Frontend\LabelResolver $lr */
if (! isset($lr) || ! $lr instanceof \Casablanca\Booking\Frontend\LabelResolver) {
    if (isset($context) && ($context['lr'] ?? null) instanceof \Casablanca\Booking\Frontend\LabelResolver) {
        $lr = $context['lr'];
    } elseif (isset($calContext) && ($calContext['lr'] ?? null) instanceof \Casablanca\Booking\Frontend\LabelResolver) {
        $lr = $calContext['lr'];
    } else {
        $lr = new \Casablanca\Booking\Frontend\LabelResolver();
    }
}
if ($carouselImages === []) {
    return;
}

$count = count($carouselImages);
?>
<?php if ($count === 1) : ?>
    <figure class="cb-image-carousel__single">
        <img src="<?php echo esc_url((string) ($carouselImages[0]['url'] ?? '')); ?>"
             alt="<?php echo esc_attr($carouselAlt); ?>"
             loading="lazy" />
    </figure>
<?php else : ?>
    <div class="cb-image-carousel" data-cb-carousel tabindex="0" aria-label="<?php echo esc_attr($carouselAlt); ?>">
        <div class="cb-image-carousel__viewport">
            <?php foreach ($carouselImages as $index => $image) : ?>
                <figure class="cb-image-carousel__slide<?php echo $index === 0 ? ' cb-image-carousel__slide--active' : ''; ?>"
                        data-cb-carousel-slide
                        <?php echo $index === 0 ? '' : 'hidden="hidden"'; ?>>
                    <img src="<?php echo esc_url((string) ($image['url'] ?? '')); ?>"
                         alt="<?php echo esc_attr($carouselAlt); ?>"
                         loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>" />
                </figure>
            <?php endforeach; ?>
        </div>
        <div class="cb-image-carousel__controls">
            <button type="button"
                    class="cb-image-carousel__nav cb-image-carousel__nav--prev"
                    data-cb-carousel-prev
                    aria-label="<?php echo esc_attr($lr->resolve('common.previousImage', 'Previous image')); ?>">‹</button>
            <div class="cb-image-carousel__dots" role="tablist">
                <?php foreach ($carouselImages as $index => $image) : ?>
                    <button type="button"
                            class="cb-image-carousel__dot<?php echo $index === 0 ? ' cb-image-carousel__dot--active' : ''; ?>"
                            data-cb-carousel-dot="<?php echo (int) $index; ?>"
                            aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>"></button>
                <?php endforeach; ?>
            </div>
            <button type="button"
                    class="cb-image-carousel__nav cb-image-carousel__nav--next"
                    data-cb-carousel-next
                    aria-label="<?php echo esc_attr($lr->resolve('common.nextImage', 'Next image')); ?>">›</button>
        </div>
    </div>
<?php endif; ?>
