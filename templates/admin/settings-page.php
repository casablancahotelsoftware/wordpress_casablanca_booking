<?php
defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals from include scope, not plugin globals.
/**
 * @var array<string,mixed>|null $config
 * @var string $tab
 * @var bool $connectionOk
 * @var string $envMode
 * @var string $siteId
 * @var array<string, string> $themeTokens
 * @var array{scheduled: bool, hook: string, recurrence: string, next_run: int|false, next_run_formatted: string} $schedulerStatus
 * @var array{roomTypes: array, rates: array, packages: array, companyId: string, spaceId: string, tenantId: string, empty: bool}|null $mappingData
 */
$repo = new Casablanca\Booking\Domain\Repository\SyncLogRepository();
$logs = is_array($config) ? $repo->findRecent($siteId) : [];

// Display-only notice flags from admin-post redirects (capability checked in SettingsPage::renderPage).
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$casablanca_booking_notice_saved = isset($_GET['saved']) && sanitize_text_field(wp_unslash((string) $_GET['saved'])) !== '';
$casablanca_booking_notice_cron = isset($_GET['cron']) && sanitize_key(wp_unslash((string) $_GET['cron'])) === 'scheduled';
$casablanca_booking_notice_connection = isset($_GET['connection'])
    ? sanitize_key(wp_unslash((string) $_GET['connection']))
    : '';
$casablanca_booking_notice_message = isset($_GET['message'])
    ? sanitize_text_field(wp_unslash((string) $_GET['message']))
    : '';
$casablanca_booking_notice_error = isset($_GET['error'])
    ? sanitize_text_field(wp_unslash((string) $_GET['error']))
    : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap cb-backend">
    <h1><?php esc_html_e('CASABLANCA Booking', 'casablanca-booking'); ?></h1>

    <?php if ($casablanca_booking_notice_saved) : ?>
        <div class="notice notice-success"><p><?php esc_html_e('Configuration saved.', 'casablanca-booking'); ?></p></div>
    <?php endif; ?>
    <?php if ($casablanca_booking_notice_cron) : ?>
        <div class="notice notice-success"><p><?php esc_html_e('Daily sync schedule updated.', 'casablanca-booking'); ?></p></div>
    <?php endif; ?>
    <?php if ($casablanca_booking_notice_message !== '') : ?>
        <div class="notice notice-<?php echo $casablanca_booking_notice_connection === 'ok' ? 'success' : 'error'; ?>"><p><?php echo esc_html($casablanca_booking_notice_message); ?></p></div>
    <?php endif; ?>
    <?php if ($casablanca_booking_notice_error !== '') : ?>
        <div class="notice notice-error"><p><?php echo esc_html($casablanca_booking_notice_error); ?></p></div>
    <?php endif; ?>

    <nav class="nav-tab-wrapper">
        <a href="<?php echo esc_url(add_query_arg(['page' => 'casablanca-booking', 'tab' => 'config'], admin_url('admin.php'))); ?>" class="nav-tab <?php echo $tab === 'config' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Configuration', 'casablanca-booking'); ?></a>
        <a href="<?php echo esc_url(add_query_arg(['page' => 'casablanca-booking', 'tab' => 'mapping'], admin_url('admin.php'))); ?>" class="nav-tab <?php echo $tab === 'mapping' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Mapping codes', 'casablanca-booking'); ?></a>
        <a href="<?php echo esc_url(add_query_arg(['page' => 'casablanca-booking', 'tab' => 'logs'], admin_url('admin.php'))); ?>" class="nav-tab <?php echo $tab === 'logs' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Sync logs', 'casablanca-booking'); ?></a>
    </nav>

    <?php if ($tab === 'config' || $tab === 'logs') : ?>
        <div class="cb-backend__scheduler">
            <h2><?php esc_html_e('Scheduled sync', 'casablanca-booking'); ?></h2>
            <dl class="cb-backend__scheduler-meta">
                <dt><?php esc_html_e('Hook', 'casablanca-booking'); ?></dt>
                <dd><code><?php echo esc_html($schedulerStatus['hook']); ?></code></dd>
                <dt><?php esc_html_e('Status', 'casablanca-booking'); ?></dt>
                <dd>
                    <?php if ($schedulerStatus['scheduled']) : ?>
                        <span class="cb-backend__status cb-backend__status--ok"><?php esc_html_e('Scheduled', 'casablanca-booking'); ?></span>
                    <?php else : ?>
                        <span class="cb-backend__status cb-backend__status--error"><?php esc_html_e('Not scheduled', 'casablanca-booking'); ?></span>
                    <?php endif; ?>
                </dd>
                <dt><?php esc_html_e('Recurrence', 'casablanca-booking'); ?></dt>
                <dd><?php echo esc_html($schedulerStatus['recurrence']); ?></dd>
                <dt><?php esc_html_e('Next run', 'casablanca-booking'); ?></dt>
                <dd>
                    <?php if ($schedulerStatus['scheduled'] && $schedulerStatus['next_run_formatted'] !== '') : ?>
                        <?php echo esc_html($schedulerStatus['next_run_formatted']); ?>
                    <?php else : ?>
                        —
                    <?php endif; ?>
                </dd>
            </dl>
            <p class="description">
                <?php esc_html_e('WordPress WP-Cron runs when the site receives traffic (or via a system cron hitting wp-cron.php). Saving configuration schedules this daily sync automatically.', 'casablanca-booking'); ?>
            </p>
            <?php if (! $schedulerStatus['scheduled']) : ?>
                <p class="cb-backend__scheduler-warning">
                    <?php esc_html_e('No daily sync event is registered. Click the button below to schedule it.', 'casablanca-booking'); ?>
                </p>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                <?php wp_nonce_field('casablanca_booking_reschedule_cron'); ?>
                <input type="hidden" name="action" value="casablanca_booking_reschedule_cron" />
                <input type="hidden" name="redirect_tab" value="<?php echo esc_attr($tab); ?>" />
                <?php
                submit_button(
                    $schedulerStatus['scheduled']
                        ? __('Reschedule daily sync', 'casablanca-booking')
                        : __('Schedule daily sync', 'casablanca-booking'),
                    'secondary',
                    'submit',
                    false
                );
                ?>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'config') : ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cb-backend__form">
            <?php wp_nonce_field('casablanca_booking_save'); ?>
            <input type="hidden" name="action" value="casablanca_booking_save" />

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Environment mode', 'casablanca-booking'); ?></th>
                    <td><code><?php echo esc_html($envMode); ?></code>
                        <p class="description"><?php esc_html_e('Set CASABLANCA_BOOKING_ENV in wp-config.php (production, staging, development). Not editable here.', 'casablanca-booking'); ?></p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="cb-tenant-id"><?php esc_html_e('Tenant ID', 'casablanca-booking'); ?></label></th>
                    <td><input name="tenant_id" id="cb-tenant-id" type="text" class="regular-text" required value="<?php echo esc_attr((string) ($config['tenant_id'] ?? '')); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="api_key"><?php esc_html_e('API key', 'casablanca-booking'); ?></label></th>
                    <td><input name="api_key" id="api_key" type="password" class="regular-text" <?php echo $config === null ? 'required' : ''; ?> autocomplete="new-password" placeholder="<?php echo $config ? esc_attr__('Leave blank to keep existing', 'casablanca-booking') : ''; ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Custom IBE domain', 'casablanca-booking'); ?></th>
                    <td><label><input type="checkbox" name="use_custom_ibe_domain" id="cb-use-custom-ibe" value="1" <?php checked(! empty($config['use_custom_ibe_domain'])); ?> /> <?php esc_html_e('Use custom booking engine domain', 'casablanca-booking'); ?></label></td>
                </tr>
                <tr>
                    <th scope="row"><label for="cb-ibe-base-url"><?php esc_html_e('IBE base URL', 'casablanca-booking'); ?></label></th>
                    <td><input name="ibe_base_url" id="cb-ibe-base-url" type="url" class="regular-text" value="<?php echo esc_attr((string) ($config['ibe_base_url'] ?? 'https://bookingengine.casablanca.at')); ?>" data-default-base="https://bookingengine.casablanca.at" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="cb-ibe-context"><?php esc_html_e('Space name', 'casablanca-booking'); ?></label></th>
                    <td><input name="url_friendly_ibe_context_id" id="cb-ibe-context" type="text" class="regular-text" value="<?php echo esc_attr((string) ($config['url_friendly_ibe_context_id'] ?? 'bookingengine')); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="cb-default-culture"><?php esc_html_e('Default culture', 'casablanca-booking'); ?></label></th>
                    <td><input name="default_culture" id="cb-default-culture" type="text" class="regular-text" value="<?php echo esc_attr((string) ($config['default_culture'] ?? 'de')); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="cb-ibe-link-style"><?php esc_html_e('Link style', 'casablanca-booking'); ?></label></th>
                    <td>
                        <select name="ibe_link_style" id="cb-ibe-link-style">
                            <?php foreach (Casablanca\Booking\Domain\IbeLinkStyle::all() as $style) : ?>
                                <option value="<?php echo esc_attr($style); ?>" <?php selected(($config['ibe_link_style'] ?? 'full_path') === $style); ?>><?php echo esc_html($style); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p id="cb-ibe-url-preview" class="description"></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="theme_css"><?php esc_html_e('Site CSS overrides', 'casablanca-booking'); ?></label></th>
                    <td>
                        <?php if ($themeTokens !== []) : ?>
                            <details class="cb-backend__token-reference">
                                <summary><?php esc_html_e('CSS design tokens (--cb-*)', 'casablanca-booking'); ?></summary>
                                <p class="description"><?php esc_html_e('Default values from widget.css. Override in the textarea below or in your theme stylesheet.', 'casablanca-booking'); ?></p>
                                <div class="cb-backend__token-table-wrap">
                                    <table class="widefat striped cb-backend__token-table">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('Token', 'casablanca-booking'); ?></th>
                                                <th><?php esc_html_e('Default', 'casablanca-booking'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($themeTokens as $tokenName => $defaultValue) : ?>
                                                <tr>
                                                    <td><code><?php echo esc_html($tokenName); ?></code></td>
                                                    <td><code><?php echo esc_html($defaultValue); ?></code></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </details>
                        <?php endif; ?>
                        <textarea name="theme_css" id="theme_css" class="large-text code cb-backend__theme-css" rows="8"><?php echo esc_textarea((string) ($config['theme_css'] ?? '')); ?></textarea>
                        <p class="description"><?php esc_html_e('Scope rules to .cb-widget. Injected after bundled CSS.', 'casablanca-booking'); ?></p>
                    </td>
                </tr>
                <?php if (is_array($config)) : ?>
                <tr>
                    <th scope="row"><?php esc_html_e('Connection status', 'casablanca-booking'); ?></th>
                    <td><span class="cb-backend__status cb-backend__status--<?php echo esc_attr((string) ($config['connection_status'] ?? 'unknown')); ?>"><?php echo esc_html((string) ($config['connection_status'] ?? 'unknown')); ?></span>
                        <?php if (! empty($config['connection_message'])) : ?><p><?php echo esc_html((string) $config['connection_message']); ?></p><?php endif; ?></td>
                </tr>
                <?php endif; ?>
            </table>

            <?php submit_button(__('Save configuration', 'casablanca-booking')); ?>
        </form>

        <?php if (is_array($config)) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                <?php wp_nonce_field('casablanca_booking_test'); ?>
                <input type="hidden" name="action" value="casablanca_booking_test" />
                <?php submit_button(__('Test connection', 'casablanca-booking'), 'secondary', 'submit', false); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;margin-left:8px">
                <?php wp_nonce_field('casablanca_booking_sync'); ?>
                <input type="hidden" name="action" value="casablanca_booking_sync" />
                <?php submit_button(__('Sync now', 'casablanca-booking'), 'secondary', 'submit', false); ?>
            </form>
        <?php endif; ?>

    <?php elseif ($tab === 'mapping') : ?>
        <?php if (! $connectionOk) : ?>
            <p><?php esc_html_e('Save valid credentials and test the connection first.', 'casablanca-booking'); ?></p>
        <?php elseif ($mappingData === null || ! empty($mappingData['empty'])) : ?>
            <div class="notice notice-info inline"><p><?php esc_html_e('No mapping codes synced yet. Save a valid configuration and run Sync now.', 'casablanca-booking'); ?></p></div>
        <?php else : ?>
            <div class="cb-backend__mapping">
                <div class="cb-backend__space-header">
                    <h2><?php esc_html_e('Space', 'casablanca-booking'); ?>: <?php echo esc_html($mappingData['spaceId']); ?></h2>
                    <dl class="cb-backend__space-meta">
                        <dt><?php esc_html_e('Tenant ID', 'casablanca-booking'); ?></dt>
                        <dd><?php echo esc_html($mappingData['tenantId']); ?></dd>
                        <dt><?php esc_html_e('Space ID', 'casablanca-booking'); ?></dt>
                        <dd><?php echo esc_html($mappingData['spaceId']); ?></dd>
                        <?php if ($mappingData['companyId'] !== '') : ?>
                            <dt><?php esc_html_e('Company ID', 'casablanca-booking'); ?></dt>
                            <dd><?php echo esc_html($mappingData['companyId']); ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>

                <?php if ($mappingData['roomTypes'] !== []) : ?>
                    <h3><?php esc_html_e('Room types', 'casablanca-booking'); ?></h3>
                    <table class="widefat striped cb-backend__table cb-backend__mapping-table cb-backend__mapping-table--grouped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Id', 'casablanca-booking'); ?></th>
                                <th><?php esc_html_e('Name', 'casablanca-booking'); ?></th>
                                <th><?php esc_html_e('Min. occupancy', 'casablanca-booking'); ?></th>
                                <th><?php esc_html_e('Std. occupancy', 'casablanca-booking'); ?></th>
                                <th><?php esc_html_e('Max. occupancy', 'casablanca-booking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mappingData['roomTypes'] as $roomType) : ?>
                                <tr class="cb-backend__mapping-room">
                                    <td><code><?php echo esc_html((string) $roomType['room_type_id']); ?></code></td>
                                    <td><?php echo esc_html((string) $roomType['name']); ?></td>
                                    <td><?php echo esc_html((string) $roomType['min_occupancy']); ?></td>
                                    <td><?php echo esc_html((string) $roomType['standard_occupancy']); ?></td>
                                    <td><?php echo esc_html((string) $roomType['max_occupancy']); ?></td>
                                </tr>
                                <?php if ($mappingData['rates'] !== []) : ?>
                                    <tr class="cb-backend__mapping-rates-row">
                                        <td colspan="5">
                                            <div class="cb-backend__mapping-nested">
                                                <strong><?php esc_html_e('Rates', 'casablanca-booking'); ?></strong>
                                                <table class="widefat striped cb-backend__mapping-table cb-backend__mapping-table--nested">
                                                    <thead>
                                                        <tr>
                                                            <th><?php esc_html_e('Type', 'casablanca-booking'); ?></th>
                                                            <th><?php esc_html_e('Name', 'casablanca-booking'); ?></th>
                                                            <th><?php esc_html_e('Code', 'casablanca-booking'); ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($mappingData['rates'] as $rate) : ?>
                                                            <tr>
                                                                <td><?php echo esc_html((string) $rate['display_type']); ?></td>
                                                                <td><?php echo esc_html((string) $rate['name']); ?></td>
                                                                <td><code><?php echo esc_html((string) $rate['rate_id']); ?></code></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php if ($mappingData['rates'] !== []) : ?>
                    <h3><?php esc_html_e('Rates (all)', 'casablanca-booking'); ?></h3>
                    <table class="widefat striped cb-backend__table cb-backend__mapping-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Type', 'casablanca-booking'); ?></th>
                                <th><?php esc_html_e('Name', 'casablanca-booking'); ?></th>
                                <th><?php esc_html_e('Code', 'casablanca-booking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mappingData['rates'] as $rate) : ?>
                                <tr>
                                    <td><?php echo esc_html((string) $rate['display_type']); ?></td>
                                    <td><?php echo esc_html((string) $rate['name']); ?></td>
                                    <td><code><?php echo esc_html((string) $rate['rate_id']); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php if ($mappingData['packages'] !== []) : ?>
                    <h3><?php esc_html_e('Packages', 'casablanca-booking'); ?></h3>
                    <table class="widefat striped cb-backend__table cb-backend__mapping-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Type', 'casablanca-booking'); ?></th>
                                <th><?php esc_html_e('Name', 'casablanca-booking'); ?></th>
                                <th><?php esc_html_e('Code', 'casablanca-booking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mappingData['packages'] as $package) : ?>
                                <tr>
                                    <td><?php echo esc_html((string) $package['display_type']); ?></td>
                                    <td><?php echo esc_html((string) $package['name']); ?></td>
                                    <td><code><?php echo esc_html((string) $package['rate_id']); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php else : ?>
        <div class="cb-backend__log-actions">
            <?php if (is_array($config)) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                <?php wp_nonce_field('casablanca_booking_sync'); ?>
                <input type="hidden" name="action" value="casablanca_booking_sync" />
                <?php submit_button(__('Sync now', 'casablanca-booking'), 'secondary', 'submit', false); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;margin-left:8px" onsubmit="return confirm('<?php echo esc_js(__('Delete mirrored catalog data for this site and run a fresh sync?', 'casablanca-booking')); ?>');">
                <?php wp_nonce_field('casablanca_booking_flush_sync'); ?>
                <input type="hidden" name="action" value="casablanca_booking_flush_sync" />
                <?php submit_button(__('Clear synced data and sync', 'casablanca-booking'), 'delete', 'submit', false); ?>
            </form>
            <?php endif; ?>
        </div>
        <table class="widefat striped"><thead><tr>
            <th><?php esc_html_e('Started', 'casablanca-booking'); ?></th>
            <th><?php esc_html_e('Duration', 'casablanca-booking'); ?></th>
            <th><?php esc_html_e('Status', 'casablanca-booking'); ?></th>
            <th><?php esc_html_e('Rows written', 'casablanca-booking'); ?></th>
            <th><?php esc_html_e('Rows changed', 'casablanca-booking'); ?></th>
            <th><?php esc_html_e('Message', 'casablanca-booking'); ?></th>
        </tr></thead><tbody>
        <?php if ($logs === []) : ?>
            <tr><td colspan="6"><?php esc_html_e('No sync runs yet.', 'casablanca-booking'); ?></td></tr>
        <?php else : foreach ($logs as $log) :
            $duration = ((int) ($log['finished_at'] ?? 0)) > 0 ? ((int) $log['finished_at'] - (int) $log['started_at']) . 's' : '—';
            ?>
            <tr>
                <td><?php echo esc_html(wp_date('Y-m-d H:i:s', (int) $log['started_at'])); ?></td>
                <td><?php echo esc_html($duration); ?></td>
                <td><?php echo esc_html((string) $log['status']); ?></td>
                <td><?php echo esc_html((string) $log['rows_written']); ?></td>
                <td><?php echo esc_html((string) $log['rows_changed']); ?></td>
                <td><?php echo esc_html((string) ($log['message'] ?? '')); ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody></table>
    <?php endif; ?>
</div>
