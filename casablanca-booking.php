<?php
/**
 * Plugin Name:       CASABLANCA Booking Engine
 * Plugin URI:        https://www.casablanca.at/
 * Description:       CASABLANCA Booking Engine integration for WordPress — background ARI sync, SSR widgets, PCI-safe redirect to IBE v2.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Martin Hairer <martin.hairer@casablanca.at>
 * Author URI:        https://profiles.wordpress.org/mhairer/
 * License:           AGPL-3.0-only
 * License URI:       https://www.gnu.org/licenses/agpl-3.0.html
 * Text Domain:       casablanca-booking
 *
 * @package CasablancaBooking
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('CASABLANCA_BOOKING_VERSION', '1.0.0');
define('CASABLANCA_BOOKING_FILE', __FILE__);
define('CASABLANCA_BOOKING_PATH', plugin_dir_path(__FILE__));
define('CASABLANCA_BOOKING_URL', plugin_dir_url(__FILE__));

require_once CASABLANCA_BOOKING_PATH . 'src/Autoloader.php';

Casablanca\Booking\Autoloader::register(CASABLANCA_BOOKING_PATH . 'src');

register_activation_hook(__FILE__, [Casablanca\Booking\Infrastructure\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [Casablanca\Booking\Infrastructure\Activator::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
    Casablanca\Booking\Plugin::instance()->boot();
});
