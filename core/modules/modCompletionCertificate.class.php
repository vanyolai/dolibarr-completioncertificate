<?php
/* Copyright (C) 2026 Krisztian Vanyolai
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modCompletionCertificate extends DolibarrModules
{
	public function __construct($db)
	{
		global $conf;

		$this->db = $db;
		$this->numero = 581200;
		$this->rights_class = 'completioncertificate';
		$this->family = 'crm';
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'ModuleCompletionCertificateDesc';
		$this->editor_name = 'Krisztian Vanyolai';
		$this->editor_url = 'https://github.com/vanyolai/dolibarr-completioncertificate';
		$this->version = '0.4.3';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'check-circle';

		$this->module_parts = array(
			'triggers' => 0,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 1,
			'printing' => 0,
			'theme' => 0,
			'css' => array(),
			'js' => array(),
			'hooks' => array('ordercard'),
			'moduleforexternal' => 0,
		);

		$this->dirs = array('/completioncertificate/temp', '/completioncertificate/certificate');
		$this->config_page_url = array();
		$this->hidden = false;
		$this->depends = array('modCommande');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array('completioncertificate@completioncertificate');
		$this->phpmin = array(8, 1);
		$this->need_dolibarr_version = array(23, 0);
		$this->need_javascript_ajax = 0;
		$this->const = array();

		if (!isModEnabled('completioncertificate')) {
			$conf->completioncertificate = new stdClass();
			$conf->completioncertificate->enabled = 0;
		}

		$this->tabs = array();
		$this->tabs[] = array(
			'data' => 'order:+completioncertificates:CompletionCertificates,Certificate,/completioncertificate/class/certificate.class.php,getOrderCertificateCount:completioncertificate@completioncertificate:$user->hasRight("completioncertificate", "read"):/completioncertificate/order.php?id=__ID__'
		);
		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array();

		$this->rights = array();
		$r = 0;

		$r++;
		$this->rights[$r][0] = 581201;
		$this->rights[$r][1] = 'Read completion certificates';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'read';

		$r++;
		$this->rights[$r][0] = 581202;
		$this->rights[$r][1] = 'Create/modify completion certificates';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'write';

		$r++;
		$this->rights[$r][0] = 581203;
		$this->rights[$r][1] = 'Delete completion certificates';
		$this->rights[$r][2] = 'd';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'delete';

		$this->menu = array();
	}

	public function init($options = '')
	{
		global $conf;

		$result = $this->_load_tables('/completioncertificate/sql/');
		if ($result <= 0) {
			return -1;
		}

		$sql = array();

		// Register the standard document model using the same mechanism as ModuleBuilder modules.
		$sql[] = "DELETE FROM ".$this->db->prefix()."document_model WHERE nom = 'standard_certificate' AND type = 'certificate' AND entity = ".((int) $conf->entity);
		$sql[] = "INSERT INTO ".$this->db->prefix()."document_model (nom, type, entity) VALUES ('standard_certificate', 'certificate', ".((int) $conf->entity).")";

		// Backfill native Dolibarr links for certificates created by earlier 0.x versions.
		$sql[] = "INSERT INTO ".$this->db->prefix()."element_element (fk_source, sourcetype, fk_target, targettype) "
			."SELECT c.fk_commande, 'commande', c.rowid, 'completioncertificate_certificate' "
			."FROM ".$this->db->prefix()."completioncertificate c "
			."WHERE c.entity = ".((int) $conf->entity)." "
			."AND NOT EXISTS (SELECT 1 FROM ".$this->db->prefix()."element_element ee "
			."WHERE ee.fk_source = c.fk_commande AND ee.sourcetype = 'commande' "
			."AND ee.fk_target = c.rowid AND ee.targettype = 'completioncertificate_certificate')";

		return $this->_init($sql, $options);
	}

	public function remove($options = '')
	{
		global $conf;

		$sql = array();
		$sql[] = "DELETE FROM ".$this->db->prefix()."document_model WHERE nom = 'standard_certificate' AND type = 'certificate' AND entity = ".((int) $conf->entity);
		return $this->_remove($sql, $options);
	}
}
