<?php
defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals from include scope, not plugin globals.
/**
 * Multi-room occupancy selector (1–5 rooms).
 *
 * @var int $defaultRooms
 * @var array<int, array{adults: int, children: int, childrenAges: int[], childrenAgesString: string}> $rooms
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
$defaultRooms = max(1, min(5, (int) ($defaultRooms ?? 1)));
$rooms = is_array($rooms ?? null) ? $rooms : [];
?>
<div class="cb-rooms" data-cb-rooms>
    <label class="cb-field cb-field--rooms-count">
        <span class="cb-field__label"><?php echo esc_html($lr->resolve('widget.rooms.count', 'Rooms')); ?></span>
        <input type="number"
               name="numberOfRooms"
               min="1"
               max="5"
               value="<?php echo esc_attr((string) $defaultRooms); ?>"
               data-cb-field="rooms-count" />
    </label>

    <div class="cb-rooms__list" data-cb-rooms-list>
        <?php foreach ($rooms as $index => $room) : ?>
            <?php
            $adults = (int) ($room['adults'] ?? 2);
            $children = (int) ($room['children'] ?? 0);
            $ages = is_array($room['childrenAges'] ?? null) ? $room['childrenAges'] : [];
            $agesString = (string) ($room['childrenAgesString'] ?? implode(', ', $ages));
            ?>
            <fieldset class="cb-room-block"
                      data-cb-room-block
                      data-cb-room-index="<?php echo esc_attr((string) $index); ?>">
                <legend class="cb-room-block__title">
                    <?php
                    echo esc_html(
                        $lr->resolve('widget.rooms.roomN', 'Room %s', '', [(string) ($index + 1)])
                    );
                    ?>
                </legend>

                <label class="cb-field">
                    <span class="cb-field__label"><?php echo esc_html($lr->resolve('widget.adults', 'Adults')); ?></span>
                    <input type="number"
                           name="rooms[<?php echo (int) $index; ?>][adults]"
                           min="1"
                           max="10"
                           value="<?php echo esc_attr((string) $adults); ?>"
                           data-cb-field="adults"
                           required />
                </label>

                <label class="cb-field">
                    <span class="cb-field__label"><?php echo esc_html($lr->resolve('widget.children', 'Children')); ?></span>
                    <input type="number"
                           name="rooms[<?php echo (int) $index; ?>][children]"
                           min="0"
                           max="10"
                           value="<?php echo esc_attr((string) $children); ?>"
                           data-cb-field="children-count"
                           required />
                </label>

                <input type="hidden"
                       name="rooms[<?php echo (int) $index; ?>][childrenAges]"
                       value="<?php echo esc_attr($agesString); ?>"
                       data-cb-children-ages-hidden />

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
        <?php endforeach; ?>
    </div>
</div>
