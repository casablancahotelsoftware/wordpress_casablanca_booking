<?php

declare(strict_types=1);

namespace Casablanca\Booking;

use Casablanca\Booking\Admin\SettingsPage;
use Casablanca\Booking\Cli\SyncCommand;
use Casablanca\Booking\Frontend\AssetManager;
use Casablanca\Booking\Frontend\BlockRegistrar;
use Casablanca\Booking\Frontend\DetailRewrite;
use Casablanca\Booking\Frontend\RedirectHandler;
use Casablanca\Booking\Frontend\RestController;
use Casablanca\Booking\Frontend\Shortcodes;
use Casablanca\Booking\Sync\CronScheduler;

final class Plugin
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function boot(): void
    {
        // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Off-.org AGPL plugin; bundled MO files in /languages require explicit load.
        load_plugin_textdomain('casablanca-booking', false, dirname(plugin_basename(CASABLANCA_BOOKING_FILE)) . '/languages');

        (new SettingsPage())->register();
        (new AssetManager())->register();
        (new Shortcodes())->register();
        (new BlockRegistrar())->register();
        (new DetailRewrite())->register();
        (new RestController())->register();
        (new RedirectHandler())->register();
        (new CronScheduler())->register();

        if (defined('WP_CLI') && constant('WP_CLI')) {
            SyncCommand::register();
        }
    }
}
