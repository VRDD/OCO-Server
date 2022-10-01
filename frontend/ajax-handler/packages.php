<?php
$SUBVIEW = 1;
require_once('../../loader.inc.php');
require_once('../session.php');

try {

	if(!empty($_POST['get_package_names']) && is_array($_POST['get_package_names'])) {
		$finalArray = [];
		foreach($_POST['get_package_names'] as $id) {
			$p = $cl->getPackage($id);
			if(!empty($p)) $finalArray[$p->id] = $p->getFullName();
		}
		die(json_encode($finalArray));
	}

	if(!empty($_POST['edit_package_family_id'])
	&& isset($_POST['name'])
	&& isset($_POST['notes'])) {
		$cl->editPackageFamily($_POST['edit_package_family_id'], $_POST['name'], $_POST['notes']);
		die();
	}

	if(!empty($_POST['edit_package_family_id'])
	&& !empty($_FILES['icon']['tmp_name'])) {
		if(!exif_imagetype($_FILES['icon']['tmp_name'])) {
			throw new Exception(LANG('invalid_input'));
		}
		$cl->editPackageFamilyIcon($_POST['edit_package_family_id'], file_get_contents($_FILES['icon']['tmp_name']));
		die();
	}

	if(!empty($_POST['edit_package_family_id'])
	&& !empty($_POST['remove_icon'])) {
		$cl->editPackageFamilyIcon($_POST['edit_package_family_id'], null);
		die();
	}

	if(!empty($_POST['edit_package_id'])
	&& !empty($_POST['add_dependend_package_id'])) {
		$cl->addPackageDependency($_POST['edit_package_id'], $_POST['add_dependend_package_id']);
		die();
	}

	if(!empty($_POST['edit_package_id'])
	&& !empty($_POST['remove_dependency_package_id']) && is_array($_POST['remove_dependency_package_id'])) {
		foreach($_POST['remove_dependency_package_id'] as $dpid) {
			$cl->removePackageDependency($_POST['edit_package_id'], $dpid);
		}
		die();
	}

	if(!empty($_POST['edit_package_id'])
	&& !empty($_POST['remove_dependent_package_id']) && is_array($_POST['remove_dependent_package_id'])) {
		foreach($_POST['remove_dependent_package_id'] as $dpid) {
			$cl->removePackageDependency($dpid, $_POST['edit_package_id']);
		}
		die();
	}

	if(!empty($_POST['edit_package_id'])
	&& isset($_POST['package_family_id'])
	&& isset($_POST['version'])
	&& isset($_POST['compatible_os'])
	&& isset($_POST['compatible_os_version'])
	&& isset($_POST['notes'])
	&& isset($_POST['install_procedure'])
	&& isset($_POST['install_procedure_success_return_codes'])
	&& isset($_POST['install_procedure_post_action'])
	&& isset($_POST['uninstall_procedure'])
	&& isset($_POST['uninstall_procedure_success_return_codes'])
	&& isset($_POST['uninstall_procedure_post_action'])
	&& isset($_POST['download_for_uninstall'])) {
		$cl->editPackage($_POST['edit_package_id'],
			$_POST['package_family_id'],
			$_POST['version'],
			$_POST['compatible_os'],
			$_POST['compatible_os_version'],
			$_POST['notes'],
			$_POST['install_procedure'],
			$_POST['install_procedure_success_return_codes'],
			$_POST['install_procedure_post_action'],
			$_POST['uninstall_procedure'],
			$_POST['uninstall_procedure_success_return_codes'],
			$_POST['uninstall_procedure_post_action'],
			$_POST['download_for_uninstall']
		);
		die();
	}

	if(!empty($_POST['remove_package_family_id']) && is_array($_POST['remove_package_family_id'])) {
		foreach($_POST['remove_package_family_id'] as $id) {
			$cl->removePackageFamily($id);
		}
		die();
	}

	if(!empty($_POST['move_in_group_id']) && !empty($_POST['move_from_pos']) && !empty($_POST['move_to_pos'])) {
		$cl->reorderPackageInGroup($_POST['move_in_group_id'], $_POST['move_from_pos'], $_POST['move_to_pos']);
		die();
	}

	if(!empty($_POST['remove_id']) && is_array($_POST['remove_id'])) {
		foreach($_POST['remove_id'] as $id) {
			$cl->removePackage($id, !empty($_POST['force']));
		}
		die();
	}

	if(!empty($_POST['remove_group_id']) && is_array($_POST['remove_group_id'])) {
		foreach($_POST['remove_group_id'] as $id) {
			$cl->removePackageGroup($id, !empty($_POST['force']));
		}
		die();
	}

	if(!empty($_POST['remove_from_group_id']) && !empty($_POST['remove_from_group_package_id']) && is_array($_POST['remove_from_group_package_id'])) {
		foreach($_POST['remove_from_group_package_id'] as $pid) {
			$cl->removePackageFromGroup($pid, $_POST['remove_from_group_id']);
		}
		die();
	}

	if(isset($_POST['create_group'])) {
		die(strval(intval(
			$cl->createPackageGroup($_POST['create_group'], empty($_POST['parent_id']) ? null : intval($_POST['parent_id']))
		)));
	}

	if(!empty($_POST['rename_group_id']) && isset($_POST['new_name'])) {
		$cl->renamePackageGroup($_POST['rename_group_id'], $_POST['new_name']);
		die();
	}

	if(isset($_POST['add_to_group_id']) && is_array($_POST['add_to_group_id']) && isset($_POST['add_to_group_package_id']) && is_array($_POST['add_to_group_package_id'])) {
		foreach($_POST['add_to_group_package_id'] as $pid) {
			foreach($_POST['add_to_group_id'] as $gid) {
				$cl->addPackageToGroup($pid, $gid);
			}
		}
		die();
	}

	if(isset($_POST['create_package'])) {
		// no payload by default
		$tmpFilePath = null;
		$tmpFileName = null;
		if(!empty($_FILES['archive'])) {
			// use file from user upload
			$tmpFilePath = $_FILES['archive']['tmp_name'];
			$tmpFileName = $_FILES['archive']['name'];
		}
		// create package
		$insertId = $cl->createPackage($_POST['create_package'], $_POST['version'], $_POST['description'] ?? '', $currentSystemUser->username,
			$_POST['install_procedure'], $_POST['install_procedure_success_return_codes'] ?? '', $_POST['install_procedure_post_action'] ?? null,
			$_POST['uninstall_procedure'] ?? '', $_POST['uninstall_procedure_success_return_codes'] ?? '', $_POST['download_for_uninstall'], $_POST['uninstall_procedure_post_action'] ?? null,
			$_POST['compatible_os'] ?? null, $_POST['compatible_os_version'] ?? null, $tmpFilePath, $tmpFileName
		);
		die(strval(intval($insertId)));
	}

} catch(PermissionException $e) {
	header('HTTP/1.1 403 Forbidden');
	die(LANG('permission_denied'));
} catch(Exception $e) {
	header('HTTP/1.1 400 Invalid Request');
	die($e->getMessage());
}

header('HTTP/1.1 400 Invalid Request');
die(LANG('unknown_method'));
