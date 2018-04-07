<?php defined('ABSPATH') || die(); ?>
<h3 id="hide-wp-login"><?=__('Hide wp-login.php', 'wwhwla'); ?></h3>

<p>
	<?=__('This option allows you to set a networkwide default, which can be overridden by individual sites.', 'wwhwla'); ?>
	<?=__('Simply go to to the site’s permalink settings to change the URL.', 'wwhwla'); ?>
</p>

<table class="form-table">
	<tbody>
		<tr>
			<th scope="row"><?=__('Networkwide default', 'wwhwla'); ?></th>
			<td><input type="text" name="<?=esc_attr($options['name']); ?>" value="<?=esc_attr($options['value']) ?>"/></td>
		</tr>
	</tbody>
</table>
