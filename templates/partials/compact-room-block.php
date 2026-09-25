<?php
defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals from include scope, not plugin globals.
/**
 * Compact calendar room block with steppers.
 *
 * @var array{adults: int, children: int, childrenAges: int[], childrenAgesString: string} $room
 * @var int $index
 * @var int $roomNumber
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
$index = (int) ($index ?? 0);
$roomNumber = (int) ($roomNumber ?? ($index + 1));
$adults = (int) ($room['adults'] ?? 2);
$children = (int) ($room['children'] ?? 0);
$ages = is_array($room['childrenAges'] ?? null) ? $room['childrenAges'] : [];
$agesString = (string) ($room['childrenAgesString'] ?? implode(', ', $ages));
?>
<fieldset class="cb-room-block cb-room-block--compact"
          data-cb-room-block
          data-cb-room-index="<?php echo esc_attr((string) $index); ?>">
    <?php if ($index > 0) : ?>
        <div class="cb-room-block__header">
            <legend class="cb-room-block__title">
                <?php
                echo esc_html(
                    $lr->resolve('widget.rooms.roomN', 'Room %s', '', [(string) $roomNumber])
                );
                ?>
            </legend>
            <button type="button"
                    class="cb-room-block__remove"
                    data-cb-remove-room
                    aria-label="<?php echo esc_attr($lr->resolve('widget.calendar.removeRoom', 'Remove room')); ?>">
                <?php echo esc_html($lr->resolve('widget.calendar.removeRoom', 'Remove room')); ?>
            </button>
        </div>
    <?php endif; ?>

    <input type="hidden"
           name="rooms[<?php echo (int) $index; ?>][adults]"
           value="<?php echo esc_attr((string) $adults); ?>"
           data-cb-field="adults" />
    <input type="hidden"
           name="rooms[<?php echo (int) $index; ?>][children]"
           value="<?php echo esc_attr((string) $children); ?>"
           data-cb-field="children-count" />
    <input type="hidden"
           name="rooms[<?php echo (int) $index; ?>][childrenAges]"
           value="<?php echo esc_attr($agesString); ?>"
           data-cb-children-ages-hidden />

    <div class="cb-stepper" data-cb-stepper="adults">
        <span class="cb-stepper__label">
            <?php echo esc_html($lr->resolve('widget.adults', 'Adults')); ?>
            <span class="cb-stepper__hint"><?php echo esc_html($lr->resolve('widget.calendar.adultsHint', 'aged 15 and over')); ?></span>
        </span>
        <span class="cb-stepper__control">
            <button type="button" class="cb-stepper__btn" data-cb-step="-1" aria-label="-">−</button>
            <span class="cb-stepper__value" data-cb-stepper-value><?php echo esc_html((string) $adults); ?></span>
            <button type="button" class="cb-stepper__btn" data-cb-step="1" aria-label="+">+</button>
        </span>
    </div>

    <div class="cb-stepper" data-cb-stepper="children-count">
        <span class="cb-stepper__label">
            <?php echo esc_html($lr->resolve('widget.children', 'Children')); ?>
            <span class="cb-stepper__hint"><?php echo esc_html($lr->resolve('widget.calendar.childrenHint', 'aged 0 to 14')); ?></span>
        </span>
        <span class="cb-stepper__control">
            <button type="button" class="cb-stepper__btn" data-cb-step="-1" aria-label="-">−</button>
            <span class="cb-stepper__value" data-cb-stepper-value><?php echo esc_html((string) $children); ?></span>
            <button type="button" class="cb-stepper__btn" data-cb-step="1" aria-label="+">+</button>
        </span>
    </div>

    <div class="cb-field cb-field--ages"
         data-cb-ages-container
         <?php echo $children > 0 ? '' : 'hidden="hidden"'; ?>>
        <span class="cb-field__label cb-field__label--group"><?php echo esc_html($lr->resolve('widget.children.ages', 'Children ages')); ?></span>
        <div class="cb-ages" data-cb-ages-list>
            <?php foreach ($ages as $ageIndex => $age) : ?>
                <label class="cb-ages__item">
                    <span class="cb-ages__label">
                        <?php
                        echo esc_html(
                            $lr->resolve('widget.children.ageN', 'Child %s', '', [(string) ($ageIndex + 1)])
                        );
                        ?>
                    </span>
                    <input type="number"
                           name="rooms[<?php echo (int) $index; ?>][childAge][]"
                           min="0"
                           max="17"
                           value="<?php echo esc_attr((string) (int) $age); ?>"
                           required
                           data-cb-child-age />
                </label>
            <?php endforeach; ?>
        </div>
    </div>
</fieldset>
