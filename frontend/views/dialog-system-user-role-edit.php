<?php
$SUBVIEW = 1;
require_once('../../loader.inc.php');
require_once('../session.php');
?>

<input type='hidden' id='txtEditSystemUserRoleId'></input>
<table class='fullwidth aligned'>
	<tr>
		<th><?php echo LANG('name'); ?></th>
		<td><input type='text' class='fullwidth' autocomplete='new-password' id='txtEditSystemUserRoleName' autofocus='true'></input></td>
	</tr>
	<tr>
		<th><?php echo LANG('permission_json'); ?></th>
		<td><textarea class='fullwidth monospace' autocomplete='new-password' id='txtEditSystemUserRolePermissions' rows='8'></textarea></td>
	</tr>
	<tr>
		<th></th>
		<td>
			<div class='alert warning' style='margin-top:0px;width:350px;min-width:100%'>
				<?php echo LANG('permission_json_docs'); ?>
			</div>
		</td>
	</tr>
</table>

<div class='controls right'>
	<button onclick='hideDialog();showLoader(false);showLoader2(false);'><img src='img/close.dyn.svg'>&nbsp;<?php echo LANG('close'); ?></button>
	<button id='btnUpdateSystemUserRole' class='primary' onclick='editSystemUserRole(
		txtEditSystemUserRoleId.value,
		txtEditSystemUserRoleName.value,
		txtEditSystemUserRolePermissions.value,
	)'><img src='img/send.white.svg'>&nbsp;<span id='spnBtnUpdateSystemUserRole'><?php echo LANG('change'); ?></span></button>
</div>
