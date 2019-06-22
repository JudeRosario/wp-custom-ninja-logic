<?php
/**
 * Contains the HTML Table template used on the front end
 *
 * @package WP_Custom_Ninja_Logic_Plugin
 * @version 0.0.1
 */

$roles = ( new WP_Roles() )->get_names();
?>

<div id="wpcnl-action-wrapper" style="display: none;" >
	<div id="wpcnl-actions">
		<label for="wpcnl-filter"><?php esc_attr_e( 'Filter using role', 'wp-custom-ninja-logic' ); ?></label>
		<select id="wpcnl-filter" name="wpcnl-filter-action">
			<option value=""><?php esc_attr_e( 'All Roles', 'wp-custom-ninja-logic' ); ?></option>
			<?php
			if ( 0 < count( (array) $roles ) ) {
				foreach ( $roles as $role_slug => $role_name ) {
					echo '<option value="' . esc_html( $role_slug ) . '"> ' . esc_html( $role_name ) . ' </option>';
				}
			}
			?>
		</select>
	</div>
</div>

<div id="wpcnl-table-container" class="table-responsive">
	<table id="wpcnl-table" class="table compact hover stripe">
		<thead class="thead-light">
			<tr class="active">
				<th><?php esc_attr_e( 'Username', 'wp-custom-ninja-logic' ); ?></th>
				<th><?php esc_attr_e( 'Display Name', 'wp-custom-ninja-logic' ); ?></th>
				<th><?php esc_attr_e( 'User Role', 'wp-custom-ninja-logic' ); ?></th>
			</tr>
		</thead>
	</table>
</div>
