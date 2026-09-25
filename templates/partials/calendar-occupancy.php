<?php
defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals from include scope, not plugin globals.
/**
 * Sidebar occupancy for the availability calendar.
 *
 * @var int $defaultRooms
 * @var array<int, array{adults: int, children: int, childrenAges: int[], childrenAgesString: string}> $rooms
 * @var int $roomMaxOccupancy
 * @var int $roomMinOccupancy
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
$roomMaxOccupancy = (int) ($roomMaxOccupancy ?? 0);
?>
<div class="cb-calendar-occupancy" data-cb-calendar-occupancy>
    <h3 class="cb-calendar-occupancy__title"><?php echo esc_html($lr->resolve('widget.calendar.occupancy', 'Room occupancy')); ?></h3>

    <?php if ($roomMaxOccupancy > 0) : ?>
        <p class="cb-calendar-occupancy__hint">
            <?php
            echo esc_html(
                $lr->resolve(
                    'widget.calendar.maxOccupancyHint',
                    'Up to %s guests per room',
                    '',
                    [(string) $roomMaxOccupancy]
                )
            );
            ?>
        </p>

        <input type="hidden"
               name="numberOfRooms"
               value="<?php echo esc_attr((string) count($rooms)); ?>"
               data-cb-field="rooms-count" />

        <div class="cb-calendar-occupancy__rooms" data-cb-rooms-list>
            <?php foreach ($rooms as $index => $room) : ?>
                <?php
                $roomNumber = $index + 1;
                include CASABLANCA_BOOKING_PATH . 'templates/partials/compact-room-block.php';
                ?>
            <?php endforeach; ?>
        </div>

        <button type="button"
                class="cb-button cb-button--secondary cb-calendar-occupancy__add-room"
                data-cb-add-room>
            <?php echo esc_html($lr->resolve('widget.calendar.addRoom', 'Add another room')); ?>
        </button>
    <?php elseif ($defaultRooms > 1) : ?>
        <?php include CASABLANCA_BOOKING_PATH . 'templates/partials/occupancy-rooms.php'; ?>
    <?php else : ?>
        <input type="hidden"
               name="numberOfRooms"
               value="1"
               data-cb-field="rooms-count" />

        <?php if ($rooms !== []) : ?>
            <?php
            $room = $rooms[0];
            $index = 0;
            $roomNumber = 1;
            include CASABLANCA_BOOKING_PATH . 'templates/partials/compact-room-block.php';
            ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
