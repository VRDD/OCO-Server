<?php

namespace SelfService;

class PermissionManager {

	const METHOD_CREATE   = 'create';
	const METHOD_READ     = 'read';
	const METHOD_WOL      = 'wol';
	const METHOD_WRITE    = 'write';
	const METHOD_DEPLOY   = 'deploy';
	const METHOD_DOWNLOAD = 'download';
	const METHOD_DELETE   = 'delete';

	private /*\DatabaseController*/ $db;
	private /*\Models\DomainUser*/ $domainUser;
	private /*Array*/ $permData;

	function __construct(\DatabaseController $db, \Models\DomainUser $domainUser) {
		$this->db = $db;
		$this->domainUser = $domainUser;
		$this->permData = json_decode($domainUser->domain_user_role_permissions, true);
		if(empty($this->permData)) { // json_decode returns false on error; it is intentional that we also throw an error if the permission list is empty
			throw new \Exception('Invalid or no permission definition data found for this system user!');
		}
	}

	public function getPermissionEntry($ressource, String $method=null) {
		if(is_object($ressource)) $ressource = get_class($ressource);
		if(!isset($this->permData[$ressource])) return false;
		return $this->permData[$ressource];
	}

	public function hasPermission($ressource, String $method): bool {
		// check special permissions defined in array root if no object was given
		if(empty($ressource)) {
			if(!isset($this->permData[$method])) return false;
			return ((bool) $this->permData[$method]);
		}

		// check specific ressource type permissions
		if($ressource instanceof \Models\Computer) {
			$groups = $this->db->selectAllComputerGroupByComputerId($ressource->id);
			$parentGroups = [];
			foreach($groups as $group) {
				$parentGroups = array_merge($parentGroups, $this->getParentGroupsRecursively($group));
			}
			return $this->checkRessourcePermission(
				get_class($ressource), get_class(new \Models\ComputerGroup()), $parentGroups, $ressource, $method
			);

		} else if($ressource instanceof \Models\Package) {
			// check permission in context of package groups
			$groups = $this->db->selectAllPackageGroupByPackageId($ressource->id);
			$parentGroups = [];
			foreach($groups as $group) {
				$parentGroups = array_merge($parentGroups, $this->getParentGroupsRecursively($group));
			}
			if($this->checkRessourcePermission(
				get_class($ressource), get_class(new \Models\PackageGroup()), $parentGroups, $ressource, $method
			)) return true;

			// check permission in context of package family
			$family = \Models\PackageFamily::__constructWithId($ressource->package_family_id);
			return $this->checkRessourcePermission(
				get_class($ressource), get_class(new \Models\PackageFamily()), [$family], $ressource, $method
			);

		} else {
			return $this->checkRessourcePermission(
				get_class($ressource), null, null, $ressource, $method
			);
		}
	}

	// as defined, all parent group access privileges also apply to sub groups
	// so we query all parent groups to also check the privileges of them
	private function getParentGroupsRecursively(Object $groupRessource) {
		$parentGroups = [$groupRessource];
		if($groupRessource instanceof \Models\ComputerGroup) {
			while($groupRessource->parent_computer_group_id != null) {
				$parentGroup = $this->db->selectComputerGroup($groupRessource->parent_computer_group_id);
				$parentGroups[] = $parentGroup;
				$groupRessource = $parentGroup;
			}

		} else if($groupRessource instanceof \Models\PackageGroup) {
			while($groupRessource->parent_package_group_id != null) {
				$parentGroup = $this->db->selectPackageGroup($groupRessource->parent_package_group_id);
				$parentGroups[] = $parentGroup;
				$groupRessource = $parentGroup;
			}

		} else if($groupRessource instanceof \Models\ReportGroup) {
			while($groupRessource->parent_report_group_id != null) {
				$parentGroup = $this->db->selectReportGroup($groupRessource->parent_report_group_id);
				$parentGroups[] = $parentGroup;
				$groupRessource = $parentGroup;
			}

		} else {
			throw new \InvalidArgumentException('Permission check for this ressource type is not implemented');
		}

		return $parentGroups;
	}

	// self service computer permissions can be granted by time frame, which means that the
	// domain user has only access if his last login on this computer was less than the defined value in seconds ago
	private function timeCheck(Object $ressource, $value): bool {
		if($ressource instanceof \Models\Computer) {
			if(is_bool($value)) {
				return $value;
			} else {
				$lastLogon = $this->db->selectLastDomainUserLogonByDomainUserIdAndComputerId($this->domainUser->id, $ressource->id);
				if(!$lastLogon) return false;
				$lastLogonUnixTime = strtotime($lastLogon->timestamp);
				return (time() - $lastLogonUnixTime < intval($value));
			}
		} else {
			return ((bool) $value);
		}
	}

	private function checkRessourcePermission(String $ressourceType, String $ressourceGroupType=null, Array $assignedGroups=null, Object $ressource, String $method): bool {
		if(isset($this->permData[$ressourceType])) {
			// 1st try: check permissions defined in array root if no specific object was given (e.g. create permissions)
			if(empty($ressource->id)) {
				if(!isset($this->permData[$ressourceType][$method])) return false;
				return ((bool) $this->permData[$ressourceType][$method]);
			}

			// 2nd try: check if specific ressource ID is defined in access list
			foreach($this->permData[$ressourceType] as $key => $item) {
				if($key === intval($ressource->id) && isset($item[$method]))
					return $this->timeCheck($ressource, $item[$method]);
			}

			// 3rd try: check if `own` rules are applicable (currently only implemented for job containers)
			if(isset($this->permData[$ressourceType]['own'][$method])
			&& property_exists($ressource, 'created_by_domain_user_id')
			&& $ressource->created_by_domain_user_id === $this->domainUser->id)
				return ((bool) $this->permData[$ressourceType]['own'][$method]);

			// 4th try: check general permissions for this ressource type
			if(isset($this->permData[$ressourceType]['*'][$method]))
				return $this->timeCheck($ressource, $this->permData[$ressourceType]['*'][$method]);
		}

		// 5th try: check inherited group permissions
		if(!empty($ressourceGroupType)
		&& isset($this->permData[$ressourceGroupType])
		&& !empty($assignedGroups)) {
			foreach($assignedGroups as $group) {
				foreach($this->permData[$ressourceGroupType] as $key => $item) {
					if($key !== intval($group->id)) continue;

					if($ressource instanceof \Models\ComputerGroup || $ressource instanceof \Models\PackageGroup || $ressource instanceof \Models\ReportGroup) {
						// if we are checking the permission of a group object, read from the permission method directly inside the $item
						if(isset($item[$method])) {
							return ((bool) $item[$method]);
						}
					} else {
						// otherwise, read from the permission method in the 'items' dict
						if(isset($item['items'][$method])) {
							return $this->timeCheck($ressource, $item['items'][$method]);
						}
					}
				}
			}
		}

		// otherwise: access denied
		return false;
	}

}
