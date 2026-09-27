<?php
/**
 * Server render of the "Open Now Status" block.
 *
 * @var array $attributes Block attributes.
 *
 * @package RMD\OpeningHours
 */

defined( 'ABSPATH' ) || exit;

echo RMD\OpeningHours\Blocks::render_status( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes.
