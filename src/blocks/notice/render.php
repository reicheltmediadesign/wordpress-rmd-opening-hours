<?php
/**
 * Server render of the "Opening Hours Notice" block.
 *
 * @var array $attributes Block attributes.
 *
 * @package RMD\OpeningHours
 */

defined( 'ABSPATH' ) || exit;

echo RMD\OpeningHours\Blocks::render_notice( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes.
