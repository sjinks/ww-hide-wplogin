<?php defined( 'ABSPATH' ) || die(); ?>
<h3 id="hide-wp-login"><?php esc_html_e( 'Hide wp-login.php', 'wwhwla' ); ?></h3>

<p>
	<?php esc_html_e( 'This option allows you to set a networkwide default, which can be overridden by individual sites.', 'wwhwla' ); ?>
	<?php esc_html_e( 'Simply go to to the site\'s permalink settings to change the URL.', 'wwhwla' ); ?>
</p>

<table class="form-table">
	<tbody>
		<tr>
			<th scope="row"><?php esc_html_e( 'Networkwide default', 'wwhwla' ); ?></th>
			<td><input type="text" name="<?php echo esc_attr( $options['name'] ); ?>" value="<?php echo esc_attr( $options['value'] ); ?>"/></td>
		</tr>
	</tbody>
</table>
