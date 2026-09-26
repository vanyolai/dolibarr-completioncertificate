<?php
/* Copyright (C) 2026 Vanyolai Krisztián <vanyolai@gmail.com> */

// Use Dolibarr native CSRF protection for all state-changing actions.
define('CSRFCHECK_WITH_TOKEN', 1);

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
dol_include_once('/completioncertificate/class/certificate.class.php');

$langs->loadLangs(array('main', 'orders', 'mails', 'completioncertificate@completioncertificate'));

if (!$user->hasRight('completioncertificate', 'read')) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$orderId = GETPOSTINT('orderid');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'alpha');

$permissiontoadd = $user->hasRight('completioncertificate', 'write');
$permissiontodelete = $user->hasRight('completioncertificate', 'delete');

$certificate = new Certificate($db);
$object = $certificate;

if ($id > 0) {
	$result = $certificate->fetch($id);
	if ($result <= 0) {
		accessforbidden();
	}
	$certificate->fetch_thirdparty();
}

$form = new Form($db);

/**
 * Regenerate the current PDF using the native Dolibarr document model.
 *
 * @param Certificate $certificate Certificate object
 * @param Translate $langs Output language
 * @return void
 */
function completioncertificateRegeneratePdf($certificate, $langs)
{
	if (getDolGlobalString('MAIN_DISABLE_PDF_AUTOUPDATE')) {
		return;
	}

	$certificate->fetch($certificate->id);
	$certificate->fetch_thirdparty();
	$result = $certificate->generateDocument($certificate->model_pdf ?: 'standard_certificate', $langs);
	if ($result <= 0) {
		setEventMessages($certificate->error, $certificate->errors, 'warnings');
	}
}

/*
 * Actions
 */

// Create a draft from an order.
if ($action === 'save') {
	if (!$permissiontoadd) {
		accessforbidden();
	}

	$orderId = GETPOSTINT('orderid');
	$order = new Commande($db);
	if ($order->fetch($orderId) <= 0 || (int) $order->status <= 0) {
		accessforbidden();
	}

	$requestedQty = array();
	foreach ($order->lines as $line) {
		$requestedQty[(int) $line->id] = (float) price2num(GETPOST('qty_'.((int) $line->id), 'alphanohtml'));
	}

	$dateCompletion = GETPOST('date_completion', 'alpha');
	if ($dateCompletion === '') {
		$dateCompletion = dol_print_date(dol_now(), '%Y-%m-%d');
	}
	$notePublic = GETPOST('note_public', 'restricthtml');

	$completionMode = GETPOSTINT('completion_mode');
	$progressPercent = (float) price2num(GETPOST('progress_percent', 'alphanohtml'));

	$newId = $certificate->createFromOrder(
		$order,
		$user,
		$dateCompletion,
		$notePublic,
		$requestedQty,
		$completionMode,
		$progressPercent,
		GETPOST('issue_text', 'alphanohtml')
	);
	if ($newId > 0) {
		if (!empty($certificate->warnings)) {
			setEventMessages('', $certificate->warnings, 'warnings');
		}
		header('Location: '.dol_buildpath('/completioncertificate/card.php', 1).'?id='.$newId);
		exit;
	}

	setEventMessages($certificate->error, $certificate->errors, 'errors');
	$action = 'create';
}

// Update a draft.
if ($action === 'update' && $id > 0) {
	if (!$permissiontoadd || $certificate->status !== Certificate::STATUS_DRAFT) {
		accessforbidden();
	}

	$order = new Commande($db);
	if ($order->fetch((int) $certificate->fk_commande) <= 0) {
		accessforbidden();
	}

	$requestedQty = array();
	foreach ($order->lines as $line) {
		$requestedQty[(int) $line->id] = (float) price2num(GETPOST('qty_'.((int) $line->id), 'alphanohtml'));
	}

	$result = $certificate->updateDraftFromOrder(
		$order,
		$user,
		GETPOST('date_completion', 'alpha'),
		GETPOST('note_public', 'restricthtml'),
		$requestedQty,
		(float) price2num(GETPOST('progress_percent', 'alphanohtml')),
		GETPOST('issue_text', 'alphanohtml')
	);

	if ($result > 0) {
		completioncertificateRegeneratePdf($certificate, $langs);
		setEventMessages($langs->trans('CompletionCertificateSaved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}

	setEventMessages($certificate->error, $certificate->errors, 'errors');
	$action = 'edit';
}

// Validate.
if ($action === 'confirm_validate' && $confirm === 'yes' && $id > 0) {
	if (!$permissiontoadd) {
		accessforbidden();
	}

	if ($certificate->validate($user) > 0) {
		completioncertificateRegeneratePdf($certificate, $langs);
		setEventMessages($langs->trans('CompletionCertificateValidated'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}
	setEventMessages($certificate->error, $certificate->errors, 'errors');
}

// Back to draft.
if ($action === 'confirm_setdraft' && $confirm === 'yes' && $id > 0) {
	if (!$permissiontoadd) {
		accessforbidden();
	}

	if ($certificate->setDraft($user) >= 0) {
		completioncertificateRegeneratePdf($certificate, $langs);
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}
	setEventMessages($certificate->error, $certificate->errors, 'errors');
}

// Invalidate/cancel.
if ($action === 'confirm_close' && $confirm === 'yes' && $id > 0) {
	if (!$permissiontoadd) {
		accessforbidden();
	}

	if ($certificate->cancel($user) >= 0) {
		completioncertificateRegeneratePdf($certificate, $langs);
		setEventMessages($langs->trans('CompletionCertificateCanceled'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}
	setEventMessages($certificate->error, $certificate->errors, 'errors');
}

// Reopen canceled certificate.
if ($action === 'confirm_reopen' && $confirm === 'yes' && $id > 0) {
	if (!$permissiontoadd) {
		accessforbidden();
	}

	if ($certificate->reopen($user) > 0) {
		completioncertificateRegeneratePdf($certificate, $langs);
		setEventMessages($langs->trans('CompletionCertificateReopened'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}
	setEventMessages($certificate->error, $certificate->errors, 'errors');
}

// Delete draft/canceled certificate after confirmation.
if ($action === 'confirm_delete' && $confirm === 'yes' && $id > 0) {
	if (!$permissiontodelete) {
		accessforbidden();
	}

	$sourceOrderId = (int) $certificate->fk_commande;
	if ($certificate->delete($user) > 0) {
		setEventMessages($langs->trans('CompletionCertificateDeleted'), null, 'mesgs');
		header('Location: '.dol_buildpath('/completioncertificate/order.php', 1).'?id='.$sourceOrderId);
		exit;
	}
	setEventMessages($certificate->error, $certificate->errors, 'errors');
}

/*
 * Native Dolibarr document actions.
 */
if ($id > 0 && ($action === 'builddoc' || $action === 'remove_file')) {
	$objref = dol_sanitizeFileName($certificate->ref);
	$baseOutput = getMultidirOutput($certificate, $certificate->module);
	if (empty($baseOutput)) {
		$baseOutput = DOL_DATA_ROOT.'/completioncertificate';
	}
	$upload_dir = $baseOutput.'/'.$certificate->element.'/'.$objref;
	$usercangeneratedoc = $permissiontoadd ? 1 : 0;

	include DOL_DOCUMENT_ROOT.'/core/actions_builddoc.inc.php';
}

/*
 * Native Dolibarr email actions.
 */
$emailActions = array('presend', 'send', 'relance');
if ($id > 0 && (in_array($action, $emailActions, true) || GETPOST('addfile', 'alpha') || GETPOST('removedfile') || GETPOST('removeAll', 'alpha') || GETPOST('modelselected'))) {
	if ($certificate->status !== Certificate::STATUS_VALIDATED) {
		accessforbidden();
	}

	$triggersendname = 'COMPLETIONCERTIFICATE_CERTIFICATE_SENTBYMAIL';
	$autocopy = 'MAIN_MAIL_AUTOCOPY_COMPLETIONCERTIFICATE_TO';
	$trackid = 'completioncertificate'.$certificate->id;
	include DOL_DOCUMENT_ROOT.'/core/actions_sendmails.inc.php';
}

/*
 * View
 */
llxHeader('', $langs->trans('CompletionCertificate'));
print load_fiche_titre($langs->trans('CompletionCertificate'), '', 'check-circle');

// Create form.
if ($orderId > 0 && $id <= 0) {
	$order = new Commande($db);
	if ($order->fetch($orderId) <= 0 || (int) $order->status <= 0) {
		accessforbidden();
	}
	$order->fetch_thirdparty();

	$lockedMode = $certificate->getActiveCompletionModeForOrder($orderId);
	$requestedMode = GETPOST('completion_mode', 'int');
	$selectedMode = $lockedMode !== null
		? $lockedMode
		: ($requestedMode === '' ? Certificate::MODE_LINES : (int) $requestedMode);
	if (!in_array($selectedMode, array(Certificate::MODE_LINES, Certificate::MODE_PROGRESS), true)) {
		$selectedMode = Certificate::MODE_LINES;
	}

	$usedQuantities = $certificate->getUsedQuantitiesForOrder($orderId);
	$usedProgress = $certificate->getUsedProgressForOrder($orderId);
	$orderCurrency = Certificate::getOrderCurrencyCode($order);
	$orderNetAmount = Certificate::getOrderNetAmount($order);
	$defaultIssueText = GETPOST('issue_text', 'alphanohtml');
	if ($defaultIssueText === '') {
		$defaultIssueText = Certificate::getDefaultIssueText($langs);
	}
	$remainingProgress = max(0.0, 100.0 - $usedProgress);

	print '<form method="post" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="save">';
	print '<input type="hidden" name="orderid" value="'.((int) $order->id).'">';

	print '<table class="border centpercent">';
	print '<tr><td class="titlefield">'.$langs->trans('Order').'</td><td>'.$order->getNomUrl(1).'</td></tr>';
	print '<tr><td>'.$langs->trans('ThirdParty').'</td><td>'.$order->thirdparty->getNomUrl(1).'</td></tr>';
	print '<tr><td>'.$langs->trans('CompletionDate').'</td><td><input type="date" name="date_completion" value="'.dol_print_date(dol_now(), '%Y-%m-%d').'"></td></tr>';
	print '<tr><td>'.$langs->trans('IssueText').'</td><td><input class="minwidth300" type="text" name="issue_text" maxlength="255" value="'.dol_escape_htmltag($defaultIssueText).'"></td></tr>';
	print '<tr><td>'.$langs->trans('CompletionMode').'</td><td>';
	if ($lockedMode !== null) {
		$tmpModeObject = new Certificate($db);
		$tmpModeObject->completion_mode = $lockedMode;
		print dol_escape_htmltag($tmpModeObject->getCompletionModeLabel($langs));
		print '<input type="hidden" name="completion_mode" value="'.((int) $lockedMode).'">';
		print ' <span class="opacitymedium">('.$langs->trans('CompletionModeLockedHint').')</span>';
	} else {
		print '<select name="completion_mode" id="completion_mode">';
		print '<option value="'.Certificate::MODE_LINES.'"'.($selectedMode === Certificate::MODE_LINES ? ' selected' : '').'>'.$langs->trans('CompletionModeLines').'</option>';
		print '<option value="'.Certificate::MODE_PROGRESS.'"'.($selectedMode === Certificate::MODE_PROGRESS ? ' selected' : '').'>'.$langs->trans('CompletionModeProgress').'</option>';
		print '</select>';
	}
	print '</td></tr>';
	print '</table><br>';

	print '<div id="completion_lines_section"'.($selectedMode === Certificate::MODE_LINES ? '' : ' style="display:none"').'>';
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('Description').'</td>';
	print '<td class="right">'.$langs->trans('OrderedQty').'</td>';
	print '<td class="right">'.$langs->trans('AlreadyCertifiedQty').'</td>';
	print '<td class="right">'.$langs->trans('RemainingQty').'</td>';
	print '<td class="right">'.$langs->trans('CertifiedQty').'</td>';
	print '</tr>';

	foreach ($order->lines as $line) {
		$lineId = (int) $line->id;
		$orderedQty = (float) $line->qty;
		$usedQty = (float) ($usedQuantities[$lineId] ?? 0.0);
		$remainingQty = max(0.0, $orderedQty - $usedQty);
		$description = Certificate::buildOrderLineDescription($line);

		print '<tr>';
		print '<td>'.dol_htmlentitiesbr($description).'</td>';
		print '<td class="right">'.price($orderedQty).'</td>';
		print '<td class="right">'.price($usedQty).'</td>';
		print '<td class="right">'.price($remainingQty).'</td>';
		print '<td class="right"><input class="width75 right" type="number" step="any" min="0" max="'.price2num($remainingQty).'" name="qty_'.$lineId.'" value="'.price2num($remainingQty).'"'.($remainingQty <= 0 ? ' disabled' : '').'></td>';
		print '</tr>';
	}
	print '</table></div>';
	print '</div>';

	print '<div id="completion_progress_section"'.($selectedMode === Certificate::MODE_PROGRESS ? '' : ' style="display:none"').'>';
	print '<table class="border centpercent">';
	print '<tr><td class="titlefield">'.$langs->trans('OrderNetAmount').'</td><td class="right">'.price($orderNetAmount).' '.dol_escape_htmltag($orderCurrency).'</td></tr>';
	print '<tr><td>'.$langs->trans('PreviouslyCertifiedProgress').'</td><td class="right">'.price($usedProgress).' %</td></tr>';
	print '<tr><td>'.$langs->trans('RemainingProgress').'</td><td class="right">'.price($remainingProgress).' %</td></tr>';
	print '<tr><td>'.$langs->trans('CurrentProgress').'</td><td class="right">';
	print '<input class="width75 right" type="number" step="0.01" min="0.01" max="'.price2num($remainingProgress).'" name="progress_percent" value="'.dol_escape_htmltag(GETPOST('progress_percent', 'alphanohtml')).'"> %';
	print '</td></tr>';
	print '</table>';
	print '<div class="opacitymedium">'.$langs->trans('ProgressIncrementHint').' '.$langs->trans('ProgressAmountHint').'</div>';
	print '</div>';

	print '<br><label for="note_public">'.$langs->trans('NotePublic').'</label><br>';
	print '<textarea id="note_public" class="quatrevingtpercent" rows="4" name="note_public">'.dol_escape_htmltag(GETPOST('note_public', 'restricthtml')).'</textarea>';

	print '<div class="center">';
	print '<input class="button button-save" type="submit" value="'.$langs->trans('Create').'">';
	print ' &nbsp; <a class="button button-cancel" href="'.DOL_URL_ROOT.'/commande/card.php?id='.((int) $order->id).'">'.$langs->trans('Cancel').'</a>';
	print '</div></form>';

	if ($lockedMode === null) {
		print '<script nonce="'.getNonce().'">
		jQuery(function($) {
			function toggleCompletionMode() {
				var mode = parseInt($("#completion_mode").val(), 10);
				$("#completion_lines_section").toggle(mode === '.Certificate::MODE_LINES.');
				$("#completion_progress_section").toggle(mode === '.Certificate::MODE_PROGRESS.');
			}
			$("#completion_mode").on("change", toggleCompletionMode);
			toggleCompletionMode();
		});
		</script>';
	}

// Edit draft.
} elseif ($id > 0 && $action === 'edit') {
	if (!$permissiontoadd || $certificate->status !== Certificate::STATUS_DRAFT) {
		accessforbidden();
	}

	$order = new Commande($db);
	if ($order->fetch((int) $certificate->fk_commande) <= 0) {
		accessforbidden();
	}
	$order->fetch_thirdparty();

	print '<form method="post" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="id" value="'.((int) $certificate->id).'">';

	print '<table class="border centpercent">';
	print '<tr><td class="titlefield">'.$langs->trans('Ref').'</td><td>'.dol_escape_htmltag($certificate->ref).'</td></tr>';
	print '<tr><td>'.$langs->trans('Order').'</td><td>'.$order->getNomUrl(1).'</td></tr>';
	print '<tr><td>'.$langs->trans('ThirdParty').'</td><td>'.$order->thirdparty->getNomUrl(1).'</td></tr>';
	print '<tr><td>'.$langs->trans('CompletionDate').'</td><td><input type="date" name="date_completion" value="'.dol_escape_htmltag($certificate->date_completion).'"></td></tr>';
	print '<tr><td>'.$langs->trans('IssueText').'</td><td><input class="minwidth300" type="text" name="issue_text" maxlength="255" value="'.dol_escape_htmltag($certificate->getIssueText($langs)).'"></td></tr>';
	print '<tr><td>'.$langs->trans('CompletionMode').'</td><td>'.dol_escape_htmltag($certificate->getCompletionModeLabel($langs)).'</td></tr>';
	print '</table><br>';

	if ($certificate->completion_mode === Certificate::MODE_PROGRESS) {
		$usedProgress = $certificate->getUsedProgressForOrder((int) $order->id, (int) $certificate->id);
		$availableProgress = max(0.0, 100.0 - $usedProgress);

		print '<table class="border centpercent">';
		print '<tr><td class="titlefield">'.$langs->trans('OrderNetAmount').'</td><td class="right">'.price(Certificate::getOrderNetAmount($order)).' '.dol_escape_htmltag(Certificate::getOrderCurrencyCode($order)).'</td></tr>';
		print '<tr><td>'.$langs->trans('PreviouslyCertifiedProgress').'</td><td class="right">'.price($usedProgress).' %</td></tr>';
		print '<tr><td>'.$langs->trans('AvailableProgress').'</td><td class="right">'.price($availableProgress).' %</td></tr>';
		print '<tr><td>'.$langs->trans('CurrentProgress').'</td><td class="right">';
		print '<input class="width75 right" type="number" step="0.01" min="0.01" max="'.price2num($availableProgress).'" name="progress_percent" value="'.price2num($certificate->progress_percent).'"> %';
		print '</td></tr>';
		print '</table>';
		print '<div class="opacitymedium">'.$langs->trans('ProgressIncrementHint').' '.$langs->trans('ProgressAmountHint').'</div>';
	} else {
		$usedQuantities = $certificate->getUsedQuantitiesForOrder((int) $order->id, (int) $certificate->id);
		$currentQty = array();
		foreach ($certificate->lines as $certificateLine) {
			$currentQty[(int) $certificateLine->fk_commandedet] = (float) $certificateLine->qty_certified;
		}

		print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans('Description').'</td>';
		print '<td class="right">'.$langs->trans('OrderedQty').'</td>';
		print '<td class="right">'.$langs->trans('AlreadyCertifiedQty').'</td>';
		print '<td class="right">'.$langs->trans('RemainingQty').'</td>';
		print '<td class="right">'.$langs->trans('CertifiedQty').'</td>';
		print '</tr>';

		foreach ($order->lines as $line) {
			$lineId = (int) $line->id;
			$orderedQty = (float) $line->qty;
			$usedQty = (float) ($usedQuantities[$lineId] ?? 0.0);
			$availableQty = max(0.0, $orderedQty - $usedQty);
			$value = (float) ($currentQty[$lineId] ?? 0.0);

			print '<tr>';
			print '<td>'.dol_htmlentitiesbr(Certificate::buildOrderLineDescription($line)).'</td>';
			print '<td class="right">'.price($orderedQty).'</td>';
			print '<td class="right">'.price($usedQty).'</td>';
			print '<td class="right">'.price($availableQty).'</td>';
			print '<td class="right"><input class="width75 right" type="number" step="any" min="0" max="'.price2num($availableQty).'" name="qty_'.$lineId.'" value="'.price2num($value).'"></td>';
			print '</tr>';
		}
		print '</table></div>';
	}

	print '<br><label for="note_public">'.$langs->trans('NotePublic').'</label><br>';
	print '<textarea id="note_public" class="quatrevingtpercent" rows="4" name="note_public">'.dol_escape_htmltag($certificate->note_public).'</textarea>';

	print '<div class="center">';
	print '<input class="button button-save" type="submit" value="'.$langs->trans('Save').'">';
	print ' &nbsp; <a class="button button-cancel" href="'.$_SERVER['PHP_SELF'].'?id='.$certificate->id.'">'.$langs->trans('Cancel').'</a>';
	print '</div></form>';

// Show saved certificate.
} elseif ($id > 0) {
	$formconfirm = '';

	// Delete confirmation: follow the native ModuleBuilder preloaded AJAX pattern.
	if ($action === 'delete' || ($conf->use_javascript_ajax && empty($conf->dol_use_jmobile))) {
		$formconfirm = $form->formconfirm(
			$_SERVER['PHP_SELF'].'?id='.$certificate->id,
			$langs->trans('Delete'),
			$langs->trans('ConfirmDeleteCompletionCertificate', $certificate->ref),
			'confirm_delete',
			'',
			0,
			'action-delete'
		);
	}

	// Invalidation is a status transition, not a delete action.
	// Use the normal Dolibarr confirmation flow instead of binding it to a delete-style AJAX button.
	if ($action === 'close') {
		$formconfirm .= $form->formconfirm(
			$_SERVER['PHP_SELF'].'?id='.$certificate->id,
			$langs->trans('InvalidateCompletionCertificate'),
			$langs->trans('ConfirmInvalidateCompletionCertificate', $certificate->ref),
			'confirm_close',
			'',
			0,
			1
		);
	}
	print $formconfirm;

	print '<table class="border centpercent">';
	print '<tr><td class="titlefield">'.$langs->trans('Ref').'</td><td>'.dol_escape_htmltag($certificate->ref).'</td></tr>';
	print '<tr><td>'.$langs->trans('ThirdParty').'</td><td>'.$certificate->thirdparty->getNomUrl(1).'</td></tr>';
	print '<tr><td>'.$langs->trans('Order').'</td><td><a href="'.DOL_URL_ROOT.'/commande/card.php?id='.((int) $certificate->fk_commande).'">'.dol_escape_htmltag($certificate->order_ref).'</a></td></tr>';
	print '<tr><td>'.$langs->trans('CompletionDate').'</td><td>'.dol_print_date($db->jdate($certificate->date_completion), 'day').'</td></tr>';
	print '<tr><td>'.$langs->trans('IssueText').'</td><td>'.dol_escape_htmltag($certificate->getIssueText($langs)).'</td></tr>';
	print '<tr><td>'.$langs->trans('CompletionMode').'</td><td>'.dol_escape_htmltag($certificate->getCompletionModeLabel($langs)).'</td></tr>';
	if ($certificate->completion_mode === Certificate::MODE_PROGRESS) {
		print '<tr><td>'.$langs->trans('CurrentProgress').'</td><td>'.price($certificate->progress_percent).' %</td></tr>';
	}
	print '<tr><td>'.$langs->trans('CertifiedNetAmount').'</td><td>'.price($certificate->total_ht).' '.dol_escape_htmltag($certificate->currency_code).'</td></tr>';
	print '<tr><td>'.$langs->trans('Status').'</td><td>'.$certificate->getLibStatut(5).'</td></tr>';
	print '</table><br>';

	if ($certificate->completion_mode === Certificate::MODE_PROGRESS) {
		$previousProgress = $certificate->getUsedProgressForOrder((int) $certificate->fk_commande, (int) $certificate->id);
		$cumulativeProgress = min(100.0, $previousProgress + (float) $certificate->progress_percent);
		$remainingProgress = max(0.0, 100.0 - $cumulativeProgress);

		print '<table class="border centpercent">';
		print '<tr><td class="titlefield">'.$langs->trans('OrderNetAmount').'</td><td class="right">'.price($certificate->order_total_ht).' '.dol_escape_htmltag($certificate->currency_code).'</td></tr>';
		print '<tr><td>'.$langs->trans('PreviouslyCertifiedProgress').'</td><td class="right">'.price($previousProgress).' %</td></tr>';
		print '<tr><td>'.$langs->trans('CurrentProgress').'</td><td class="right">'.price($certificate->progress_percent).' %</td></tr>';
		print '<tr><td>'.$langs->trans('CumulativeProgress').'</td><td class="right">'.price($cumulativeProgress).' %</td></tr>';
		print '<tr><td>'.$langs->trans('RemainingProgress').'</td><td class="right">'.price($remainingProgress).' %</td></tr>';
		print '<tr><td>'.$langs->trans('CertifiedNetAmount').'</td><td class="right"><strong>'.price($certificate->total_ht).' '.dol_escape_htmltag($certificate->currency_code).'</strong></td></tr>';
		print '</table>';
	} else {
		print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans('Description').'</td>';
		print '<td class="right">'.$langs->trans('OrderedQty').'</td>';
		print '<td class="right">'.$langs->trans('CertifiedQty').'</td>';
		print '<td class="right">'.$langs->trans('NetAmount').'</td>';
		print '</tr>';
		foreach ($certificate->lines as $line) {
			print '<tr>';
			print '<td>'.dol_htmlentitiesbr($line->description).'</td>';
			print '<td class="right">'.price($line->qty_ordered).'</td>';
			print '<td class="right">'.price($line->qty_certified).'</td>';
			print '<td class="right">'.price($line->total_ht).' '.dol_escape_htmltag($certificate->currency_code).'</td>';
			print '</tr>';
		}
		print '<tr class="liste_total">';
		print '<td>'.$langs->trans('Total').'</td><td></td><td></td>';
		print '<td class="right">'.price($certificate->total_ht).' '.dol_escape_htmltag($certificate->currency_code).'</td>';
		print '</tr>';
		print '</table></div>';
	}

	if ($certificate->note_public !== '') {
		print '<br><div class="opacitymedium">'.$langs->trans('NotePublic').'</div>';
		print '<div class="wordbreak">'.dol_htmlentitiesbr(strip_tags($certificate->note_public)).'</div>';
	}

	if ($action !== 'presend') {
		print '<div class="tabsAction">';

		// Native ModuleBuilder delete-button pattern.
		$deleteUrl = $_SERVER['PHP_SELF'].'?id='.$id.'&action=delete&token='.newToken();
		$deleteButtonId = 'action-delete-no-ajax';
		if ($conf->use_javascript_ajax && empty($conf->dol_use_jmobile)) {
			$deleteUrl = '';
			$deleteButtonId = 'action-delete';
		}

		if ($certificate->status === Certificate::STATUS_DRAFT) {
			print dolGetButtonAction('', $langs->trans('Modify'), 'default', $_SERVER['PHP_SELF'].'?id='.$id.'&action=edit&token='.newToken(), '', $permissiontoadd);
			print dolGetButtonAction('', $langs->trans('Validate'), 'default', $_SERVER['PHP_SELF'].'?id='.$id.'&action=confirm_validate&confirm=yes&token='.newToken(), '', $permissiontoadd);
			print dolGetButtonAction('', $langs->trans('Delete'), 'delete', $deleteUrl, $deleteButtonId, $permissiontodelete);
		} elseif ($certificate->status === Certificate::STATUS_VALIDATED) {
			if (empty($user->socid)) {
				print dolGetButtonAction('', $langs->trans('SendMail'), 'email', $_SERVER['PHP_SELF'].'?id='.$id.'&action=presend&token='.newToken().'&mode=init#formmailbeforetitle');
			}
			print dolGetButtonAction('', $langs->trans('SetToDraft'), 'default', $_SERVER['PHP_SELF'].'?id='.$id.'&action=confirm_setdraft&confirm=yes&token='.newToken(), '', $permissiontoadd);
			print dolGetButtonAction('', $langs->trans('InvalidateCompletionCertificate'), 'default', $_SERVER['PHP_SELF'].'?id='.$id.'&action=close&token='.newToken(), '', $permissiontoadd);
		} elseif ($certificate->status === Certificate::STATUS_CANCELED) {
			print dolGetButtonAction('', $langs->trans('ReOpen'), 'default', $_SERVER['PHP_SELF'].'?id='.$id.'&action=confirm_reopen&confirm=yes&token='.newToken(), '', $permissiontoadd);
			print dolGetButtonAction('', $langs->trans('Delete'), 'delete', $deleteUrl, $deleteButtonId, $permissiontodelete);
		}

		print '</div>';
	}

	// Native Dolibarr document block for every persisted status.
	$formfile = new FormFile($db);
	$objref = dol_sanitizeFileName($certificate->ref);
	$baseOutput = getMultidirOutput($certificate, $certificate->module);
	if (empty($baseOutput)) {
		$baseOutput = DOL_DATA_ROOT.'/completioncertificate';
	}
	$filedir = $baseOutput.'/'.$certificate->element.'/'.$objref;
	$urlsource = $_SERVER['PHP_SELF'].'?id='.$certificate->id;

	if ($action !== 'presend') {
		print '<br>';
		print $formfile->showdocuments(
			'completioncertificate:Certificate',
			$certificate->element.'/'.$objref,
			$filedir,
			$urlsource,
			1,
			(int) $permissiontoadd,
			$certificate->model_pdf,
			1,
			0,
			0,
			0,
			0,
			'',
			'',
			'',
			$langs->defaultlang,
			'',
			$certificate
		);
	}

	// Native Dolibarr email composition form.
	if (GETPOST('modelselected')) {
		$action = 'presend';
	}
	if ($action === 'presend') {
		$modelmail = 'completioncertificate';
		$defaulttopic = 'CompletionCertificateMailSubject';
		$defaulttopiclang = 'completioncertificate@completioncertificate';
		$diroutput = $baseOutput.'/'.$certificate->element;
		$trackid = 'completioncertificate'.$certificate->id;

		include DOL_DOCUMENT_ROOT.'/core/tpl/card_presend.tpl.php';
	}
}

llxFooter();
$db->close();
