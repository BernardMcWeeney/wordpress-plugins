<?php
/**
 * Shared admin UI helpers.
 *
 * Provides the consistent page shell, header, and toggle controls used by
 * every Greenberry module settings screen so they look and behave the same.
 *
 * @package Greenberry
 */

namespace Greenberry;

defined( 'ABSPATH' ) || exit;

/**
 * Renders shared admin chrome for Greenberry settings pages.
 */
class Admin_UI {
	/**
	 * Opens a Greenberry settings page: the `.wrap` element and page header.
	 *
	 * Pair every call with {@see Admin_UI::close()}.
	 *
	 * @param string $title       Module title (rendered as the page H1).
	 * @param string $description One-line description shown under the title.
	 * @param string $extra_class Optional extra class for the wrap element.
	 * @return void
	 */
	public static function open( $title, $description = '', $extra_class = '' ) {
		$classes = trim( 'wrap greenberry-admin ' . $extra_class );
		?>
		<div class="<?php echo esc_attr( $classes ); ?>">
			<header class="greenberry-header">
				<span class="greenberry-header__mark" aria-hidden="true"><?php self::brand_mark(); ?></span>
				<div class="greenberry-header__text">
					<p class="greenberry-header__eyebrow"><?php esc_html_e( 'Greenberry', 'greenberry' ); ?></p>
					<h1 class="greenberry-header__title"><?php echo esc_html( $title ); ?></h1>
					<?php if ( '' !== $description ) : ?>
						<p class="greenberry-header__desc"><?php echo esc_html( $description ); ?></p>
					<?php endif; ?>
				</div>
			</header>
			<hr class="wp-header-end">
		<?php
	}

	/**
	 * Closes a settings page opened with {@see Admin_UI::open()}.
	 *
	 * @return void
	 */
	public static function close() {
		echo '</div>';
	}

	/**
	 * Renders a labelled on/off toggle switch.
	 *
	 * Use for single boolean settings. Multi-select lists should stay as
	 * checkbox grids.
	 *
	 * @param array $args {
	 *     Toggle arguments.
	 *
	 *     @type string $name    Input name attribute. Required.
	 *     @type bool   $checked Whether the toggle is on.
	 *     @type string $label   Bold label text. Required.
	 *     @type string $help    Optional helper line under the label.
	 *     @type string $value   Input value attribute. Default '1'.
	 *     @type bool   $compact Render the switch only, using $label as an
	 *                           accessible label. Default false.
	 * }
	 * @return void
	 */
	public static function toggle( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'name'    => '',
				'checked' => false,
				'label'   => '',
				'help'    => '',
				'value'   => '1',
				'compact' => false,
			)
		);

		$input  = sprintf(
			'<input type="checkbox" name="%1$s" value="%2$s" %3$s%4$s>',
			esc_attr( $args['name'] ),
			esc_attr( $args['value'] ),
			checked( ! empty( $args['checked'] ), true, false ),
			$args['compact'] ? ' aria-label="' . esc_attr( $args['label'] ) . '"' : ''
		);
		$track  = '<span class="greenberry-toggle__track" aria-hidden="true"></span>';

		if ( $args['compact'] ) {
			echo '<label class="greenberry-toggle greenberry-toggle--compact">' . $input . $track . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Parts escaped above.
			return;
		}
		?>
		<label class="greenberry-toggle">
			<?php echo $input . $track; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Parts escaped above. ?>
			<span class="greenberry-toggle__text">
				<strong><?php echo esc_html( $args['label'] ); ?></strong>
				<?php if ( '' !== $args['help'] ) : ?>
					<small><?php echo esc_html( $args['help'] ); ?></small>
				<?php endif; ?>
			</span>
		</label>
		<?php
	}

	/**
	 * Outputs the Greenberry brand glyph (a leaf) used in the page header.
	 *
	 * @return void
	 */
	public static function brand_mark() {
		echo '<svg viewBox="0 0 24 24" focusable="false" role="img" aria-hidden="true"><path fill="currentColor" d="M20 3c-7.5.4-12.4 3.7-14.2 8.4-1 2.6-.8 5.4.3 7.6L4 21.3 5.4 22l2.1-2.7c2 1 4.4 1.2 6.6.3C19 17.6 20.6 11.4 20 3Zm-3.3 4.3c-3 3-6 6-8.4 9.6 1.9-3.9 4.8-7 8.4-9.6Z"/></svg>';
	}
}
