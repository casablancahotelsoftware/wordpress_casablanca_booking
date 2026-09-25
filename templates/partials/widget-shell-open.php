<?php
defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals from include scope, not plugin globals.
/** @var array<string, mixed> $context */
?>
<div class="<?php echo esc_attr((string) $context['widgetClasses']); ?>" style="<?php echo esc_attr((string) $context['themeStyle']); ?>">
<?php if (($context['themeCss'] ?? '') !== '') : ?>
<style><?php echo esc_html((string) $context['themeCss']); ?></style>
<?php endif; ?>
<script type="application/json" id="cb-labels-json"><?php echo esc_html((string) $context['cbLabelsJson']); ?></script>
<script>window.CB_LABELS = JSON.parse(document.getElementById('cb-labels-json').textContent);</script>
