<?php


/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2006 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 --------------------------------------------------------------------------
 */

// ----------------------------------------------------------------------
// Original Author of file:
// Purpose of file:
// ----------------------------------------------------------------------

// Update from 0.68.1 to 0.7
function update0681to07() {
	global $DB, $LANG, $CFG_GLPI,$LINK_ID_TABLE;

	if (!FieldExists("glpi_config", "cas_logout")) {
		$query = "ALTER TABLE `glpi_config` ADD `cas_logout` VARCHAR( 255 ) NULL AFTER `cas_uri`;";
		$DB->query($query) or die("0.7 add cas_logout in glpi_config " . $LANG["update"][90] . $DB->error());
	}

	if (!isIndex("glpi_computer_device", "specificity")) {
		$query = "ALTER TABLE `glpi_computer_device` ADD INDEX ( `specificity` )";
		$DB->query($query) or die("0.7 add index specificity in glpi_computer_device " . $LANG["update"][90] . $DB->error());
	}

	if (!FieldExists("glpi_docs", "comments")){
		$query = "ALTER TABLE `glpi_docs` CHANGE `comment` `comments` TEXT DEFAULT NULL ";
		$DB->query($query) or die("0.7 alter docs.comment to be comments" . $LANG["update"][90] . $DB->error());
		
	}
	// Update polish langage file
	$query = "UPDATE glpi_users SET language='pl_PL' WHERE language='po_PO'";
	$DB->query($query) or die("0.7 update polish lang file " . $LANG["update"][90] . $DB->error());

	// Add show_group_hardware
	if (!FieldExists("glpi_profiles", "show_group_hardware")){
		$query = "ALTER TABLE `glpi_profiles` ADD `show_group_hardware` CHAR( 1 ) NULL DEFAULT '0';";
		$DB->query($query) or die("0.7 alter glpi_profiles add show_group_hardware" . $LANG["update"][90] . $DB->error());
		$query="UPDATE glpi_profiles SET `show_group_hardware`=`show_group_ticket`";
		$DB->query($query) or die("0.7 alter glpi_profiles add show_group_hardware" . $LANG["update"][90] . $DB->error());
	}
	

	// Clean doc association
	$doc_links = array (COMPUTER_TYPE, NETWORKING_TYPE, PRINTER_TYPE, MONITOR_TYPE , PERIPHERAL_TYPE, SOFTWARE_TYPE, PHONE_TYPE, ENTERPRISE_TYPE , CARTRIDGE_TYPE, CONSUMABLE_TYPE, CONTRACT_TYPE);

	foreach ($doc_links as $type) {
		$table=$LINK_ID_TABLE[$type];
		$query = "SELECT glpi_doc_device.ID as linkID, $table.*
									FROM glpi_doc_device 
									LEFT JOIN $table ON (glpi_doc_device.FK_device = $table.ID AND glpi_doc_device.device_type='$type') WHERE glpi_doc_device.is_template='1'";
		$result = $DB->query($query) or die("0.7 search wrong data link doc device $table " . $LANG["update"][90] . $DB->error());
		if ($DB->numrows($result)) {
			while ($data = $DB->fetch_array($result)) {
				if (!isset ($data['is_template']) || $data['is_template'] == 0) {
					$query2 = "UPDATE glpi_doc_device SET is_template='0' WHERE ID='" . $data['linkID'] . "'";
					$DB->query($query) or die("0.7 update link doc device for $table " . $LANG["update"][90] . $DB->error());
				}
			}
		}

	}

	//// ENTITY MANAGEMENT

	if (!TableExists("glpi_entities")) {
		$query = "CREATE TABLE `glpi_entities` (
								`ID` int(11) NOT NULL auto_increment,
								`name` varchar(255) NOT NULL,
								`parentID` int(11) NOT NULL default '0',
								`completename` text NOT NULL,
								`comments` text,
								`level` int(11) default NULL,
								PRIMARY KEY  (`ID`),
								UNIQUE KEY `name` (`name`,`parentID`),
								KEY `parentID` (`parentID`)
								) ENGINE=MyISAM;";
		$DB->query($query) or die("0.7 create glpi_entities " . $LANG["update"][90] . $DB->error());
		// TODO : ADD other fields
	}

	if (!FieldExists("glpi_users_profiles", "FK_entities")) {
		$query = " ALTER TABLE `glpi_users_profiles` ADD `FK_entities` INT NOT NULL DEFAULT '0',
											ADD `recursive` TINYINT NOT NULL DEFAULT '1',
											ADD `active` TINYINT NOT NULL DEFAULT '1' ";
		$DB->query($query) or die("0.7 alter glpi_users_profiles " . $LANG["update"][90] . $DB->error());

		// Manage inactive users
		$query = "SELECT ID FROM glpi_users WHERE active='0'";
		$result = $DB->query($query);
		if ($DB->numrows($result)) {
			while ($data = $DB->fetch_array($result)) {
				$query2 = "UPDATE glpi_users_profiles SET active = '0' WHERE FK_users='" . $data['ID'] . "'";
				$DB->query($query2);
			}
		}

		$query = "ALTER TABLE `glpi_users` DROP `active` ";
		$DB->query($query) or die("0.7 drop active from glpi_users " . $LANG["update"][90] . $DB->error());

		$query = "DELETE FROM glpi_display WHERE type='" . USER_TYPE . "' AND num='8';";
		$DB->query($query) or die("0.7 delete active field items for user search " . $LANG["update"][90] . $DB->error());
	}

	// Add entity tags to tables
	$tables = array (
		"glpi_cartridges_type",
		"glpi_computers",
		"glpi_consumables_type",
		"glpi_contacts",
		"glpi_contracts",
		"glpi_docs",
		"glpi_dropdown_locations",
		"glpi_dropdown_netpoint",
		"glpi_enterprises",
		"glpi_groups",
		"glpi_monitors",
		"glpi_networking",
		"glpi_peripherals",
		"glpi_phones",
		"glpi_printers",
		"glpi_reminder",
		"glpi_software",
		"glpi_tracking"
	);
	// "glpi_kbitems","glpi_dropdown_kbcategories", -> easier to manage
	// "glpi_followups" -> always link to tracking ?
	// "glpi_licenses" -> always link to software ? 
	// "glpi_infocoms" -> always link to item ? PB on reports stats ?
	// "glpi_links" -> global items easier to manage
	// "glpi_reservation_item", "glpi_state_item" -> always link to item ? but info maybe needed
	foreach ($tables as $tbl) {
		if (!FieldExists($tbl, "FK_entities")) {
			$query = "ALTER TABLE `" . $tbl . "` ADD `FK_entities` INT NOT NULL DEFAULT '0' AFTER `ID`";
			$DB->query($query) or die("0.7 add FK_entities in $tbl " . $LANG["update"][90] . $DB->error());
		}

		if (!isIndex($tbl, "FK_entities")) {
			$query = "ALTER TABLE `" . $tbl . "` ADD INDEX (`FK_entities`)";
			$DB->query($query) or die("0.7 add index FK_entities in $tbl " . $LANG["update"][90] . $DB->error());
		}
	}

	// Regenerate Indexes :
	$tables = array (
		"glpi_dropdown_locations"
	);
	foreach ($tables as $tbl) {
		if (isIndex($tbl, "name")) {
			$query = "ALTER TABLE `$tbl` DROP INDEX `name`;";
			$DB->query($query) or die("0.7 drop index name in $tbl " . $LANG["update"][90] . $DB->error());
		}
		if (isIndex($tbl, "parentID_2")) {
			$query = "ALTER TABLE `$tbl` DROP INDEX `parentID_2`;";
			$DB->query($query) or die("0.7 drop index name in $tbl " . $LANG["update"][90] . $DB->error());
		}
		$query = "ALTER TABLE `$tbl` ADD UNIQUE(`name`,`parentID`,`FK_entities`);";
		$DB->query($query) or die("0.7 add index name in $tbl " . $LANG["update"][90] . $DB->error());

	}

	if (isIndex("glpi_users_profiles", "FK_users_profiles")) {
		$query = "ALTER TABLE `glpi_users_profiles` DROP INDEX `FK_users_profiles`;";
		$DB->query($query) or die("0.7 drop index FK_users_profiles in glpi_users_profiles " . $LANG["update"][90] . $DB->error());
	}

	if (!isIndex("glpi_users_profiles", "active")) {
		$query = "ALTER TABLE `glpi_users_profiles` ADD INDEX (`active`);";
		$DB->query($query) or die("0.7 add index active in glpi_users_profiles " . $LANG["update"][90] . $DB->error());
	}
	if (!isIndex("glpi_users_profiles", "FK_entities")) {
		$query = "ALTER TABLE `glpi_users_profiles` ADD INDEX (`FK_entities`);";
		$DB->query($query) or die("0.7 add index FK_entities in glpi_users_profiles " . $LANG["update"][90] . $DB->error());
	}

	//// MULTIAUTH MANAGEMENT

	if (!TableExists("glpi_auth_ldap")) {
		$query = "CREATE TABLE `glpi_auth_ldap` (
			 `ID` int(11) NOT NULL auto_increment,
			 `name` varchar(255) NOT NULL,
			 `ldap_host` varchar(200) default NULL,
			`ldap_basedn` varchar(200) default NULL,
			`ldap_rootdn` varchar(200) default NULL,
			`ldap_pass` varchar(200) default NULL,
			`ldap_port` varchar(200) NOT NULL default '389',
			`ldap_condition` varchar(255) default NULL,
			`ldap_login` varchar(200) NOT NULL default 'uid',	
			`ldap_use_tls` varchar(200) NOT NULL default '0',
			`ldap_field_group` varchar(255) default NULL,
			`ldap_group_condition` varchar(255) default NULL,
			`ldap_search_for_groups` tinyint(4) NOT NULL default '0',
			`ldap_field_group_member` varchar(255) default NULL,
			`ldap_field_email` varchar(200) default NULL,
			`ldap_field_location` varchar(200) default NULL,
			`ldap_field_realname` varchar(200) default NULL,
			`ldap_field_firstname` varchar(200) default NULL,
			`ldap_field_phone` varchar(200) default NULL,
			`ldap_field_phone2` varchar(200) default NULL,
			`ldap_field_mobile` varchar(200) default NULL,
			`ldap_field_comments` TEXT default NULL,		
			PRIMARY KEY  (`ID`)
		) ENGINE=MyISAM;";
		$DB->query($query) or die("0.7 create glpi_auth_ldap " . $LANG["update"][90] . $DB->error());
		// TODO : ADD other fields

		$query = "select * from glpi_config WHERE ID=1";
		$result = $DB->query($query);
		$config = $DB->fetch_array($result);

		if (!empty ($config["ldap_host"])) {

			//Transfer ldap informations into the new table

			$query = "INSERT INTO `glpi_auth_ldap` VALUES 
			(NULL, '" . $config["ldap_host"] . "', '" . $config["ldap_host"] . "', '" . $config["ldap_basedn"] . "', '" . $config["ldap_rootdn"] . "', '" . $config["ldap_pass"] . "', " . $config["ldap_port"] . ", '" . $config["ldap_condition"] . "', '" . $config["ldap_login"] . "', '" . $config["ldap_use_tls"] . "', '" . $config["ldap_field_group"] . "',
			'" . $config["ldap_condition"] . "', " . $config["ldap_search_for_groups"] . ", '" . $config["ldap_field_group_member"] . "',
			'" . $config["ldap_field_email"] . "', '" . $config["ldap_field_location"] . "', '" . $config["ldap_field_realname"] . "', '" . $config["ldap_field_firstname"] . "',
			'" . $config["ldap_field_phone"] . "', '" . $config["ldap_field_phone2"] . "', '" . $config["ldap_field_mobile"] . "',NULL);";
			$DB->query($query) or die("0.7 transfert of ldap parameters into glpi_auth_ldap " . $LANG["update"][90] . $DB->error());
		}

		$query = "ALTER TABLE `glpi_config`
			DROP `ldap_field_email`,
			DROP `ldap_port`,
			DROP `ldap_host`,
			DROP `ldap_basedn`,
			DROP `ldap_rootdn`,
			DROP `ldap_pass`,
			DROP `ldap_field_location`,
			DROP `ldap_field_realname`,
			DROP `ldap_field_firstname`,
			DROP `ldap_field_phone`,
			DROP `ldap_field_phone2`,
			DROP `ldap_field_mobile`,
			DROP `ldap_condition`,
			DROP `ldap_login`,
			DROP `ldap_use_tls`,
			DROP `ldap_field_group`,
			DROP `ldap_group_condition`,
			DROP `ldap_search_for_groups`,
			DROP `ldap_field_group_member`;";
		$DB->query($query) or die("0.7 drop ldap fields from glpi_config " . $LANG["update"][90] . $DB->error());


	}
	if (!FieldExists("glpi_users", "id_auth")) {
		$query = "ALTER TABLE glpi_users ADD `id_auth` INT NOT NULL DEFAULT '-1',
				ADD `auth_method` INT NOT NULL DEFAULT '-1',
				ADD `last_login` DATETIME NOT NULL,
				ADD `date_mod` DATETIME NOT NULL";
		$DB->query($query) or die("0.7 add auth_method & id_method in glpi_users " . $LANG["update"][90] . $DB->error());
	}

	if (!TableExists("glpi_auth_mail")) {
		$query = "CREATE TABLE `glpi_auth_mail` (
				`ID` int(11) NOT NULL auto_increment,
				`name` varchar(255) NOT NULL,
				`imap_auth_server` varchar(200) default NULL,
				`imap_host` varchar(200) default NULL,
				PRIMARY KEY  (`ID`)
				) ENGINE=MyISAM ;";

		$DB->query($query) or die("0.7 create glpi_auth_mail " . $LANG["update"][90] . $DB->error());
		// TODO : ADD other fields

		$query = "select * from glpi_config WHERE ID=1";
		$result = $DB->query($query);
		$config = $DB->fetch_array($result);

		if (!empty ($config["imap_host"])) {

			//Transfer ldap informations into the new table
			$query = "INSERT INTO `glpi_auth_mail` VALUES 
				(NULL, '" . $config["imap_host"] . "', '" . $config["imap_auth_server"] . "', '" . $config["imap_host"] . "');";
			$DB->query($query) or die("0.7 transfert of mail parameters into glpi_auth_mail " . $LANG["update"][90] . $DB->error());

		}

		$query = "ALTER TABLE `glpi_config`
		  		DROP `imap_auth_server`,
		  		DROP `imap_host`";
		$DB->query($query) or die("0.7 drop mail fields from glpi_config " . $LANG["update"][90] . $DB->error());

	}

	// Clean state_item -> add a field from tables
	if (TableExists("glpi_state_item")) {
		$state_type=array(SOFTWARE_TYPE,COMPUTER_TYPE,PRINTER_TYPE,MONITOR_TYPE,PERIPHERAL_TYPE,NETWORKING_TYPE,PHONE_TYPE);
		foreach ($state_type as $type){
			$table=$LINK_ID_TABLE[$type];
			if (!FieldExists($table, "state")) {
				$query ="ALTER TABLE `$table` ADD `state` INT NOT NULL DEFAULT '0';";
				$DB->query($query) or die("0.7 add state field to $table " . $LANG["update"][90] . $DB->error());
				$query2="SELECT * FROM glpi_state_item WHERE device_type='$type'";
				$result=$DB->query($query2);
				if ($DB->numrows($result)){
					while ($data=$DB->fetch_array($result)){
						$query3="UPDATE $table SET state='".$data["state"]."' WHERE ID ='".$data["id_device"]."'";
						$DB->query($query3) or die("0.7 update state field value to $table " . $LANG["update"][90] . $DB->error());
					}
				}
			}
		}
		$query="DROP TABLE `glpi_state_item` ";
		$DB->query($query) or die("0.7 drop table state_item " . $LANG["update"][90] . $DB->error());
		$query="INSERT INTO `glpi_display` (`type`, `num`, `rank`, `FK_users`) VALUES (22, 31, 1, 0);";
		$DB->query($query) or die("0.7 add default search for states " . $LANG["update"][90] . $DB->error());
		// Add for reservation
		$query="INSERT INTO `glpi_display` (`type`, `num`, `rank`, `FK_users`) VALUES ( 29, 4, 1, 0);";
		$DB->query($query) or die("0.7 add defaul search for reservation " . $LANG["update"][90] . $DB->error());
		$query="INSERT INTO `glpi_display` (`type`, `num`, `rank`, `FK_users`) VALUES ( 29, 3, 2, 0);";
		$DB->query($query) or die("0.7 add defaul search for reservation " . $LANG["update"][90] . $DB->error());
	}


	// Add ticket_tco for hardwares
	$tco_tbl=array(COMPUTER_TYPE, NETWORKING_TYPE, PRINTER_TYPE, MONITOR_TYPE, PERIPHERAL_TYPE, SOFTWARE_TYPE, PHONE_TYPE);
	include (GLPI_ROOT . "/inc/infocom.function.php");

	foreach ($tco_tbl as $type) {
		$table=$LINK_ID_TABLE[$type];
		if (!FieldExists($table, "ticket_tco")){
			$query = "ALTER TABLE `$table` ADD `ticket_tco` FLOAT DEFAULT '0';";
			$DB->query($query) or die("0.7 alter $table add ticket_tco" . $LANG["update"][90] . $DB->error());
			// Update values
			$query="SELECT DISTINCT device_type, computer 
				FROM glpi_tracking 
				WHERE device_type = '$type' AND (cost_time>0 
					OR cost_fixed>0
					OR cost_material>0)";
			$result=$DB->query($query) or die("0.7 update ticket_tco" . $LANG["update"][90] . $DB->error());
			if ($DB->numrows($result)){
				while ($data=$DB->fetch_array($result)){
					$query2="UPDATE $table SET ticket_tco='".computeTicketTco($type,$data["computer"])."' 
						WHERE ID='".$data["computer"]."';";
					$DB->query($query2) or die("0.7 update ticket_tco" . $LANG["update"][90] . $DB->error());
				}
			}
		}
	}	
	// TODO Enterprises -> dropdown manufacturer + update import OCS
	// TODO Split Config -> config general + config entity
	// TODO AUto assignment profile based on rules
	// TODO Add default profile to user + update data from preference

	// Alter INT fields to not null and default 0 :
	/* #819 -> clean CommonDBTM update(
	Need to update fields before.
	ALTER TABLE `glpi_computers` CHANGE `FK_users` `FK_users` INT( 11 ) NOT NULL DEFAULT '0',
	CHANGE `FK_groups` `FK_groups` INT( 11 ) NOT NULL DEFAULT '0'
	......
	*/
} // fin 0.7 #####################################################################################
?>
