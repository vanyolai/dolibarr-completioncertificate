<?php
/* Copyright (C) 2026 Vanyolai Krisztián <vanyolai@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Dolibarr business object for a completion certificate.
 */
class Certificate extends CommonObject
{
	public const STATUS_DRAFT = 0;
	public const STATUS_VALIDATED = 1;
	public const STATUS_CANCELED = 9;

	public const MODE_LINES = 0;
	public const MODE_PROGRESS = 1;

	public $module = 'completioncertificate';
	public $element = 'certificate';
	public $table_element = 'completioncertificate';
	public $picto = 'check-circle';
	public $ismultientitymanaged = 1;
	public $isextrafieldmanaged = 0;
	public $TRIGGER_PREFIX = 'COMPLETIONCERTIFICATE_CERTIFICATE';

	public $rowid;
	public $entity = 1;
	public $ref = '';
	public $socid = 0;
	public $fk_soc = 0;
	public $fk_commande = 0;
	public $date_completion = '';
	public $completion_mode = self::MODE_LINES;
	public $progress_percent = 0.0;
	public $order_total_ht = 0.0;
	public $total_ht = 0.0;
	public $note_public = '';
	public $status = self::STATUS_DRAFT;
	public $fk_user_author = 0;
	public $fk_user_valid = 0;
	public $date_creation = 0;
	public $order_ref = '';
	public $ref_customer = '';
	public $thirdparty_name = '';
	public $model_pdf = 'standard_certificate';
	public $lines = array();

	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Load certificate header and lines.
	 *
	 * @param int $id Certificate ID
	 * @param string|null $ref Certificate reference
	 * @return int 1 if found, 0 if not found, negative on error
	 */
	public function fetch($id, $ref = null)
	{
		global $conf;

		$sql = 'SELECT c.*, s.nom AS thirdparty_name, co.ref AS order_ref, co.ref_client AS ref_customer';
		$sql .= ' FROM '.$this->db->prefix().'completioncertificate AS c';
		$sql .= ' INNER JOIN '.$this->db->prefix().'societe AS s ON s.rowid = c.fk_soc';
		$sql .= ' INNER JOIN '.$this->db->prefix().'commande AS co ON co.rowid = c.fk_commande';
		$sql .= ' WHERE c.entity = '.((int) $conf->entity);
		if ($id > 0) {
			$sql .= ' AND c.rowid = '.((int) $id);
		} elseif ($ref !== null && $ref !== '') {
			$sql .= " AND c.ref = '".$this->db->escape($ref)."'";
		} else {
			return 0;
		}

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!$obj) {
			return 0;
		}

		$this->id = (int) $obj->rowid;
		$this->rowid = (int) $obj->rowid;
		$this->entity = (int) $obj->entity;
		$this->ref = (string) $obj->ref;
		$this->fk_soc = (int) $obj->fk_soc;
		$this->socid = (int) $obj->fk_soc;
		$this->fk_commande = (int) $obj->fk_commande;
		$this->date_completion = (string) $obj->date_completion;
		$this->completion_mode = (int) ($obj->completion_mode ?? self::MODE_LINES);
		$this->progress_percent = (float) ($obj->progress_percent ?? 0);
		$this->order_total_ht = (float) ($obj->order_total_ht ?? 0);
		$this->total_ht = (float) ($obj->total_ht ?? 0);
		$this->note_public = (string) ($obj->note_public ?? '');
		$this->status = (int) $obj->status;
		$this->fk_user_author = (int) ($obj->fk_user_author ?? 0);
		$this->fk_user_valid = (int) ($obj->fk_user_valid ?? 0);
		$this->date_creation = !empty($obj->datec) ? $this->db->jdate($obj->datec) : 0;
		$this->order_ref = (string) $obj->order_ref;
		$this->ref_customer = (string) ($obj->ref_customer ?? '');
		$this->thirdparty_name = (string) $obj->thirdparty_name;

		return $this->fetchLines();
	}

	public function fetchLines()
	{
		$this->lines = array();

		$sql = 'SELECT * FROM '.$this->db->prefix().'completioncertificate_line';
		$sql .= ' WHERE fk_completioncertificate = '.((int) $this->id);
		$sql .= ' ORDER BY rang, rowid';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$this->lines[] = $obj;
		}
		$this->db->free($resql);

		return 1;
	}

	/**
	 * Return the number of completion certificates attached to an order.
	 * Used by Dolibarr's native dynamic tab badge mechanism.
	 *
	 * @param int $orderId Customer order ID
	 * @param mixed $unused Compatibility argument supplied by complete_head_from_modules()
	 * @return int
	 */
	public function getOrderCertificateCount($orderId, $unused = null)
	{
		global $conf;

		$sql = 'SELECT COUNT(*) AS nb';
		$sql .= ' FROM '.$this->db->prefix().'completioncertificate';
		$sql .= ' WHERE entity = '.((int) $conf->entity);
		$sql .= ' AND fk_commande = '.((int) $orderId);

		$resql = $this->db->query($sql);
		if (!$resql) {
			return 0;
		}

		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);

		return (int) ($obj->nb ?? 0);
	}

	public function getUsedQuantitiesForOrder($orderId, $excludeCertificateId = 0)
	{
		global $conf;

		$result = array();
		$sql = 'SELECT l.fk_commandedet, SUM(l.qty_certified) AS qty_used';
		$sql .= ' FROM '.$this->db->prefix().'completioncertificate_line AS l';
		$sql .= ' INNER JOIN '.$this->db->prefix().'completioncertificate AS c ON c.rowid = l.fk_completioncertificate';
		$sql .= ' WHERE c.entity = '.((int) $conf->entity);
		$sql .= ' AND c.fk_commande = '.((int) $orderId);
		$sql .= ' AND c.status IN ('.self::STATUS_DRAFT.', '.self::STATUS_VALIDATED.')';
		if ($excludeCertificateId > 0) {
			$sql .= ' AND c.rowid <> '.((int) $excludeCertificateId);
		}
		$sql .= ' GROUP BY l.fk_commandedet';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return $result;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$result[(int) $obj->fk_commandedet] = (float) $obj->qty_used;
		}
		$this->db->free($resql);

		return $result;
	}


	/**
	 * Return the active completion mode already used on an order.
	 *
	 * @param int $orderId Customer order ID
	 * @param int $excludeCertificateId Certificate to ignore
	 * @return int|null MODE_* or null when no active certificate exists
	 */
	public function getActiveCompletionModeForOrder($orderId, $excludeCertificateId = 0)
	{
		global $conf;

		$sql = 'SELECT completion_mode';
		$sql .= ' FROM '.$this->db->prefix().'completioncertificate';
		$sql .= ' WHERE entity = '.((int) $conf->entity);
		$sql .= ' AND fk_commande = '.((int) $orderId);
		$sql .= ' AND status IN ('.self::STATUS_DRAFT.', '.self::STATUS_VALIDATED.')';
		if ($excludeCertificateId > 0) {
			$sql .= ' AND rowid <> '.((int) $excludeCertificateId);
		}
		$sql .= ' ORDER BY rowid ASC';
		$sql .= ' LIMIT 1';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return null;
		}

		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);

		return $obj ? (int) $obj->completion_mode : null;
	}

	/**
	 * Return active progress already certified on an order.
	 */
	public function getUsedProgressForOrder($orderId, $excludeCertificateId = 0)
	{
		global $conf;

		$sql = 'SELECT COALESCE(SUM(progress_percent), 0) AS progress_used';
		$sql .= ' FROM '.$this->db->prefix().'completioncertificate';
		$sql .= ' WHERE entity = '.((int) $conf->entity);
		$sql .= ' AND fk_commande = '.((int) $orderId);
		$sql .= ' AND completion_mode = '.self::MODE_PROGRESS;
		$sql .= ' AND status IN ('.self::STATUS_DRAFT.', '.self::STATUS_VALIDATED.')';
		if ($excludeCertificateId > 0) {
			$sql .= ' AND rowid <> '.((int) $excludeCertificateId);
		}

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return 0.0;
		}

		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);

		return (float) ($obj->progress_used ?? 0);
	}

	/**
	 * Return active certified net amount on an order.
	 */
	public function getUsedAmountForOrder($orderId, $excludeCertificateId = 0)
	{
		global $conf;

		$sql = 'SELECT COALESCE(SUM(total_ht), 0) AS amount_used';
		$sql .= ' FROM '.$this->db->prefix().'completioncertificate';
		$sql .= ' WHERE entity = '.((int) $conf->entity);
		$sql .= ' AND fk_commande = '.((int) $orderId);
		$sql .= ' AND status IN ('.self::STATUS_DRAFT.', '.self::STATUS_VALIDATED.')';
		if ($excludeCertificateId > 0) {
			$sql .= ' AND rowid <> '.((int) $excludeCertificateId);
		}

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return 0.0;
		}

		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);

		return (float) ($obj->amount_used ?? 0);
	}

	/**
	 * Return the net amount represented by a certified quantity of an order line.
	 */
	public static function calculateLineNetAmount($line, $certifiedQty)
	{
		$orderedQty = (float) ($line->qty ?? 0);
		if (abs($orderedQty) < 0.00000001) {
			return 0.0;
		}

		return round(((float) ($line->total_ht ?? 0)) * (((float) $certifiedQty) / $orderedQty), 8);
	}

	public function getCompletionModeLabel($outputlangs = null)
	{
		global $langs;
		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}

		return $this->completion_mode === self::MODE_PROGRESS
			? $outputlangs->trans('CompletionModeProgress')
			: $outputlangs->trans('CompletionModeLines');
	}


	public function createFromOrder($order, $user, $dateCompletion, $notePublic, array $requestedQty, $completionMode = self::MODE_LINES, $progressPercent = 0.0)
	{
		global $conf, $langs;

		if (empty($order->id) || empty($order->socid)) {
			$this->error = $langs->trans('CompletionCertificateInvalidSourceOrder');
			return -1;
		}
		if ((int) $order->status <= 0) {
			$this->error = $langs->trans('CompletionCertificateOrderMustBeValidated');
			return -1;
		}
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateCompletion)) {
			$this->error = $langs->trans('CompletionCertificateInvalidDate');
			return -1;
		}

		$completionMode = (int) $completionMode;
		if (!in_array($completionMode, array(self::MODE_LINES, self::MODE_PROGRESS), true)) {
			$completionMode = self::MODE_LINES;
		}

		$lockedMode = $this->getActiveCompletionModeForOrder((int) $order->id);
		if ($lockedMode !== null && $lockedMode !== $completionMode) {
			$this->error = $langs->trans('CompletionCertificateOrderModeLocked');
			return -2;
		}

		$order->getLinesArray();
		$orderTotalHt = (float) $order->total_ht;
		$totalHt = 0.0;
		$linesToCreate = array();

		if ($completionMode === self::MODE_PROGRESS) {
			if ($orderTotalHt <= 0) {
				$this->error = $langs->trans('CompletionCertificateProgressRequiresPositiveAmount');
				return -3;
			}

			$usedProgress = $this->getUsedProgressForOrder((int) $order->id);
			$remainingProgress = max(0.0, 100.0 - $usedProgress);
			$progressPercent = max(0.0, (float) $progressPercent);

			if ($progressPercent <= 0 || $progressPercent > $remainingProgress + 0.000001) {
				$this->error = $langs->trans('CompletionCertificateInvalidProgress', price($remainingProgress));
				return -4;
			}

			$totalHt = round($orderTotalHt * ($progressPercent / 100.0), 8);
		} else {
			$progressPercent = 0.0;
			$used = $this->getUsedQuantitiesForOrder((int) $order->id);

			foreach ($order->lines as $line) {
				$lineId = (int) $line->id;
				$orderedQty = (float) $line->qty;
				$usedQty = (float) ($used[$lineId] ?? 0.0);
				$availableQty = max(0.0, $orderedQty - $usedQty);
				$qty = max(0.0, (float) ($requestedQty[$lineId] ?? 0.0));

				if ($qty > $availableQty) {
					$qty = $availableQty;
				}
				if ($qty <= 0) {
					continue;
				}

				$lineTotalHt = self::calculateLineNetAmount($line, $qty);
				$totalHt += $lineTotalHt;
				$linesToCreate[] = array(
					'line' => $line,
					'qty' => $qty,
					'total_ht' => $lineTotalHt,
				);
			}

			if (empty($linesToCreate)) {
				$this->error = $langs->trans('CompletionCertificateNoQuantity');
				return -5;
			}
		}

		$this->db->begin();

		$ref = $this->getNextReference();
		if ($ref === '') {
			$this->db->rollback();
			return -6;
		}

		$sql = 'INSERT INTO '.$this->db->prefix().'completioncertificate (';
		$sql .= 'entity, ref, fk_soc, fk_commande, date_completion, completion_mode, progress_percent, order_total_ht, total_ht, note_public, status, fk_user_author, datec';
		$sql .= ') VALUES (';
		$sql .= ((int) $conf->entity).',';
		$sql .= "'".$this->db->escape($ref)."',";
		$sql .= ((int) $order->socid).',';
		$sql .= ((int) $order->id).',';
		$sql .= "'".$this->db->escape($dateCompletion)."',";
		$sql .= $completionMode.',';
		$sql .= ((float) $progressPercent).',';
		$sql .= ((float) $orderTotalHt).',';
		$sql .= ((float) $totalHt).',';
		$sql .= "'".$this->db->escape($notePublic)."',";
		$sql .= self::STATUS_DRAFT.',';
		$sql .= ((int) $user->id).',';
		$sql .= "'".$this->db->idate(dol_now())."')";

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -7;
		}

		$newId = (int) $this->db->last_insert_id($this->db->prefix().'completioncertificate');

		foreach ($linesToCreate as $item) {
			$line = $item['line'];
			$qty = (float) $item['qty'];
			$description = self::buildOrderLineDescription($line);

			$sql = 'INSERT INTO '.$this->db->prefix().'completioncertificate_line (';
			$sql .= 'fk_completioncertificate, fk_commandedet, fk_product, description, qty_ordered, qty_certified, total_ht, rang';
			$sql .= ') VALUES (';
			$sql .= $newId.',';
			$sql .= ((int) $line->id).',';
			$sql .= (!empty($line->fk_product) ? (int) $line->fk_product : 'NULL').',';
			$sql .= "'".$this->db->escape($description)."',";
			$sql .= ((float) $line->qty).',';
			$sql .= $qty.',';
			$sql .= ((float) $item['total_ht']).',';
			$sql .= ((int) $line->rang).')';

			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				$this->db->rollback();
				return -8;
			}
		}

		$this->db->commit();

		if ($this->fetch($newId) <= 0) {
			return -9;
		}

		$linkResult = $this->add_object_linked('commande', (int) $order->id, $user);
		if ($linkResult <= 0) {
			$this->warnings[] = $langs->trans('CompletionCertificateLinkWarning');
		}

		return $newId;
	}

	/**
	 * Update a draft certificate from its source order.
	 *
	 * @param Commande $order Source order
	 * @param User $user User making the change
	 * @param string $dateCompletion YYYY-MM-DD
	 * @param string $notePublic Public note
	 * @param array<int,float> $requestedQty Requested quantities by order-line ID
	 * @return int 1 on success, negative value on error
	 */
	public function updateDraftFromOrder($order, $user, $dateCompletion, $notePublic, array $requestedQty, $progressPercent = null)
	{
		global $langs;

		if ($this->id <= 0 || $this->status !== self::STATUS_DRAFT) {
			$this->error = $langs->trans('CompletionCertificateNotDraft');
			return -1;
		}
		if ((int) $order->id !== (int) $this->fk_commande || (int) $order->status <= 0) {
			$this->error = $langs->trans('CompletionCertificateInvalidSourceOrder');
			return -2;
		}
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateCompletion)) {
			$this->error = $langs->trans('CompletionCertificateInvalidDate');
			return -3;
		}

		$lockedMode = $this->getActiveCompletionModeForOrder((int) $order->id, (int) $this->id);
		if ($lockedMode !== null && $lockedMode !== (int) $this->completion_mode) {
			$this->error = $langs->trans('CompletionCertificateOrderModeLocked');
			return -4;
		}

		$order->getLinesArray();
		$orderTotalHt = (float) $order->total_ht;
		$totalHt = 0.0;
		$linesToCreate = array();

		if ($this->completion_mode === self::MODE_PROGRESS) {
			if ($orderTotalHt <= 0) {
				$this->error = $langs->trans('CompletionCertificateProgressRequiresPositiveAmount');
				return -5;
			}

			$usedProgress = $this->getUsedProgressForOrder((int) $order->id, (int) $this->id);
			$remainingProgress = max(0.0, 100.0 - $usedProgress);
			$progressPercent = max(0.0, (float) $progressPercent);

			if ($progressPercent <= 0 || $progressPercent > $remainingProgress + 0.000001) {
				$this->error = $langs->trans('CompletionCertificateInvalidProgress', price($remainingProgress));
				return -6;
			}

			$totalHt = round($orderTotalHt * ($progressPercent / 100.0), 8);
		} else {
			$progressPercent = 0.0;
			$used = $this->getUsedQuantitiesForOrder((int) $order->id, (int) $this->id);

			foreach ($order->lines as $line) {
				$lineId = (int) $line->id;
				$orderedQty = (float) $line->qty;
				$usedQty = (float) ($used[$lineId] ?? 0.0);
				$availableQty = max(0.0, $orderedQty - $usedQty);
				$qty = max(0.0, (float) ($requestedQty[$lineId] ?? 0.0));

				if ($qty > $availableQty) {
					$qty = $availableQty;
				}
				if ($qty <= 0) {
					continue;
				}

				$lineTotalHt = self::calculateLineNetAmount($line, $qty);
				$totalHt += $lineTotalHt;
				$linesToCreate[] = array(
					'line' => $line,
					'qty' => $qty,
					'total_ht' => $lineTotalHt,
				);
			}

			if (empty($linesToCreate)) {
				$this->error = $langs->trans('CompletionCertificateNoQuantity');
				return -7;
			}
		}

		$this->db->begin();

		$sql = 'UPDATE '.$this->db->prefix().'completioncertificate';
		$sql .= " SET date_completion = '".$this->db->escape($dateCompletion)."'";
		$sql .= ', progress_percent = '.((float) $progressPercent);
		$sql .= ', order_total_ht = '.((float) $orderTotalHt);
		$sql .= ', total_ht = '.((float) $totalHt);
		$sql .= ", note_public = '".$this->db->escape($notePublic)."'";
		$sql .= ' WHERE rowid = '.((int) $this->id);
		$sql .= ' AND status = '.self::STATUS_DRAFT;
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -8;
		}

		if (!$this->db->query('DELETE FROM '.$this->db->prefix().'completioncertificate_line WHERE fk_completioncertificate = '.((int) $this->id))) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -9;
		}

		foreach ($linesToCreate as $item) {
			$line = $item['line'];
			$qty = (float) $item['qty'];
			$description = self::buildOrderLineDescription($line);

			$sql = 'INSERT INTO '.$this->db->prefix().'completioncertificate_line (';
			$sql .= 'fk_completioncertificate, fk_commandedet, fk_product, description, qty_ordered, qty_certified, total_ht, rang';
			$sql .= ') VALUES (';
			$sql .= ((int) $this->id).',';
			$sql .= ((int) $line->id).',';
			$sql .= (!empty($line->fk_product) ? (int) $line->fk_product : 'NULL').',';
			$sql .= "'".$this->db->escape($description)."',";
			$sql .= ((float) $line->qty).',';
			$sql .= $qty.',';
			$sql .= ((float) $item['total_ht']).',';
			$sql .= ((int) $line->rang).')';

			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				$this->db->rollback();
				return -10;
			}
		}

		$this->db->commit();
		$this->fetch($this->id);
		return 1;
	}

	/**
	 * Check whether this certificate can reserve its current quantities.
	 * Canceled certificates do not reserve quantity, so this is required before reopening.
	 *
	 * @return bool
	 */
	private function canReserveCurrentQuantities()
	{
		$lockedMode = $this->getActiveCompletionModeForOrder((int) $this->fk_commande, (int) $this->id);
		if ($lockedMode !== null && $lockedMode !== (int) $this->completion_mode) {
			return false;
		}

		if ($this->completion_mode === self::MODE_PROGRESS) {
			$usedProgress = $this->getUsedProgressForOrder((int) $this->fk_commande, (int) $this->id);
			return ($usedProgress + (float) $this->progress_percent) <= 100.000001;
		}

		require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';

		$order = new Commande($this->db);
		if ($order->fetch((int) $this->fk_commande) <= 0) {
			return false;
		}
		$order->getLinesArray();

		$orderQty = array();
		foreach ($order->lines as $line) {
			$orderQty[(int) $line->id] = (float) $line->qty;
		}

		$used = $this->getUsedQuantitiesForOrder((int) $this->fk_commande, (int) $this->id);
		foreach ($this->lines as $line) {
			$lineId = (int) $line->fk_commandedet;
			$available = (float) ($orderQty[$lineId] ?? 0.0) - (float) ($used[$lineId] ?? 0.0);
			if ((float) $line->qty_certified > $available + 0.000001) {
				return false;
			}
		}

		return true;
	}

	public function validate($user)
	{
		global $conf, $langs;

		if ($this->id <= 0 || $this->status !== self::STATUS_DRAFT) {
			$this->error = $langs->trans('CompletionCertificateNotDraft');
			return -1;
		}
		if (!$this->canReserveCurrentQuantities()) {
			$this->error = $langs->trans('CompletionCertificateQuantityNoLongerAvailable');
			return -2;
		}

		$sql = 'UPDATE '.$this->db->prefix().'completioncertificate';
		$sql .= ' SET status = '.self::STATUS_VALIDATED;
		$sql .= ', fk_user_valid = '.((int) $user->id);
		$sql .= ' WHERE rowid = '.((int) $this->id);
		$sql .= ' AND entity = '.((int) $conf->entity);
		$sql .= ' AND status = '.self::STATUS_DRAFT;

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$this->status = self::STATUS_VALIDATED;
		$this->fk_user_valid = (int) $user->id;
		return 1;
	}

	/**
	 * Set the certificate back to draft.
	 */
	public function setDraft($user, $notrigger = 0)
	{
		if ($this->status !== self::STATUS_VALIDATED) {
			return 0;
		}
		$result = $this->setStatusCommon($user, self::STATUS_DRAFT, $notrigger, 'COMPLETIONCERTIFICATE_CERTIFICATE_UNVALIDATE');
		if ($result > 0) {
			$this->status = self::STATUS_DRAFT;
		}
		return $result;
	}

	/**
	 * Cancel/invalidate a validated certificate.
	 */
	public function cancel($user, $notrigger = 0)
	{
		if ($this->status !== self::STATUS_VALIDATED) {
			return 0;
		}
		$result = $this->setStatusCommon($user, self::STATUS_CANCELED, $notrigger, 'COMPLETIONCERTIFICATE_CERTIFICATE_CANCEL');
		if ($result > 0) {
			$this->status = self::STATUS_CANCELED;
		}
		return $result;
	}

	/**
	 * Reopen a canceled certificate as validated.
	 */
	public function reopen($user, $notrigger = 0)
	{
		global $langs;

		if ($this->status !== self::STATUS_CANCELED) {
			return 0;
		}
		if (!$this->canReserveCurrentQuantities()) {
			$this->error = $langs->trans('CompletionCertificateQuantityNoLongerAvailable');
			return -1;
		}
		$result = $this->setStatusCommon($user, self::STATUS_VALIDATED, $notrigger, 'COMPLETIONCERTIFICATE_CERTIFICATE_REOPEN');
		if ($result > 0) {
			$this->status = self::STATUS_VALIDATED;
		}
		return $result;
	}

	/**
	 * Delete a draft or canceled certificate, its lines, links and generated documents.
	 */
	public function delete($user, $notrigger = 0)
	{
		global $langs;

		if ($this->id <= 0 || !in_array($this->status, array(self::STATUS_DRAFT, self::STATUS_CANCELED), true)) {
			$this->error = $langs->trans('CompletionCertificateDeleteNotAllowed');
			return -1;
		}

		$this->db->begin();

		$this->deleteObjectLinked(null, '', null, '', 0, $user);

		if (!$this->db->query('DELETE FROM '.$this->db->prefix().'completioncertificate_line WHERE fk_completioncertificate = '.((int) $this->id))) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -2;
		}

		$sql = 'DELETE FROM '.$this->db->prefix().'completioncertificate';
		$sql .= ' WHERE rowid = '.((int) $this->id);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -3;
		}

		$this->db->commit();

		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
		$baseOutput = getMultidirOutput($this, $this->module);
		if (empty($baseOutput)) {
			$baseOutput = DOL_DATA_ROOT.'/completioncertificate';
		}
		$dir = $baseOutput.'/'.$this->element.'/'.dol_sanitizeFileName($this->ref);
		if (dol_is_dir($dir)) {
			$countDeleted = 0;
			dol_delete_dir_recursive($dir, 0, 1, 0, $countDeleted, 1);
		}

		return 1;
	}

	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '')
	{
		global $langs;

		$label = dol_escape_htmltag($this->ref);
		$url = dol_buildpath('/completioncertificate/card.php', 1).'?id='.((int) $this->id);
		$picto = $withpicto ? img_picto($langs->trans('CompletionCertificate'), $this->picto, 'class="pictofixedwidth"').' ' : '';

		return '<a class="'.dol_escape_htmltag($morecss).'" href="'.$url.'">'.$picto.$label.'</a>';
	}

	public function getLibStatut($mode = 0)
	{
		global $langs;

		if ($this->status === self::STATUS_VALIDATED) {
			return dolGetStatus($langs->trans('Validated'), '', '', 'status4', $mode);
		}
		if ($this->status === self::STATUS_CANCELED) {
			return dolGetStatus($langs->trans('Canceled'), '', '', 'status9', $mode);
		}
		return dolGetStatus($langs->trans('Draft'), '', '', 'status0', $mode);
	}

	/**
	 * Keep the selected document model in memory.
	 * This object currently has a single registered model.
	 */
	public function setDocModel($user, $modelpdf)
	{
		$this->model_pdf = $modelpdf;
		return 1;
	}

	public function generateDocument($modele, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0, $moreparams = null)
	{
		if (empty($modele)) {
			$modele = 'standard_certificate';
		}

		return $this->commonGenerateDocument(
			'core/modules/completioncertificate/doc/',
			$modele,
			$outputlangs,
			$hidedetails,
			$hidedesc,
			$hideref,
			$moreparams
		);
	}

	public static function buildOrderLineDescription($line)
	{
		$ref = trim((string) ($line->product_ref ?? $line->ref ?? ''));
		$customLabel = trim((string) ($line->label ?? ''));
		$productLabel = trim((string) ($line->product_label ?? $line->libelle ?? ''));
		$label = $customLabel !== '' ? $customLabel : $productLabel;
		$description = self::plainText($line->desc ?? $line->description ?? '');

		$main = $ref;
		if ($label !== '') {
			$main .= ($main !== '' ? ' - ' : '').$label;
		}

		if ($main === '') {
			return $description;
		}
		if ($description !== '' && $description !== $label && $description !== $main) {
			$main .= "\n".$description;
		}

		return $main;
	}

	private static function plainText($value)
	{
		$value = (string) $value;
		$value = preg_replace('/<br\s*\/?>/i', "\n", $value);
		$value = preg_replace('/<\/p>/i', "\n", $value);
		$value = strip_tags($value);
		$value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$value = str_replace("\r", '', $value);
		return trim($value);
	}

	private function getNextReference()
	{
		global $conf;

		$prefix = 'TI-'.date('Y').'-';
		$sql = 'SELECT MAX(CAST(SUBSTRING(ref, 9) AS UNSIGNED)) AS maxnum';
		$sql .= ' FROM '.$this->db->prefix().'completioncertificate';
		$sql .= ' WHERE entity = '.((int) $conf->entity);
		$sql .= " AND ref LIKE '".$this->db->escape($prefix)."%'";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return '';
		}

		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		$next = ((int) ($obj->maxnum ?? 0)) + 1;

		return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
	}
}
