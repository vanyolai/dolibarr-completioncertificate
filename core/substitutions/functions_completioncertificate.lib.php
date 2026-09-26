<?php
/* Copyright (C) 2026 Vanyolai Krisztián <vanyolai@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Add Completion Certificate variables to Dolibarr substitution arrays.
 *
 * When no object is supplied (for example in the email-template editor),
 * the values are translation keys used as descriptions in the
 * "Available variables" help. When a completion certificate object is
 * supplied, the placeholders are replaced with the actual certificate
 * values.
 *
 * @param array<string,mixed> $substitutionarray Substitution array to complete
 * @param Translate $langs Output language
 * @param mixed $object Current business object, if available
 * @param array<string,mixed> $parameters Optional caller parameters
 * @return void
 */
function completioncertificate_completesubstitutionarray(&$substitutionarray, $langs, $object, $parameters = array())
{
	$labels = array(
		'__COMPLETIONCERTIFICATE_REF__' => 'SubstCompletionCertificateRef',
		'__ORDER_REF__' => 'SubstCompletionCertificateOrderRef',
		'__COMPLETION_DATE__' => 'SubstCompletionCertificateDate',
		'__CERTIFIED_AMOUNT__' => 'SubstCompletionCertificateCertifiedAmount',
		'__CURRENCY__' => 'SubstCompletionCertificateCurrency',
		'__PROGRESS_PERCENT__' => 'SubstCompletionCertificateProgressPercent',
	);

	if (!is_object($object)
		|| (($object->module ?? '') !== 'completioncertificate' && ($object->element ?? '') !== 'certificate')) {
		foreach ($labels as $key => $label) {
			if (!array_key_exists($key, $substitutionarray)) {
				$substitutionarray[$key] = $label;
			}
		}
		return;
	}

	$completionDate = '';
	if (!empty($object->date_completion)) {
		if (isset($object->db) && is_object($object->db)) {
			$completionDate = dol_print_date($object->db->jdate($object->date_completion), 'day', false, $langs);
		} else {
			$timestamp = strtotime((string) $object->date_completion);
			$completionDate = $timestamp ? dol_print_date($timestamp, 'day', false, $langs) : (string) $object->date_completion;
		}
	}

	$substitutionarray['__COMPLETIONCERTIFICATE_REF__'] = (string) ($object->ref ?? '');
	$substitutionarray['__ORDER_REF__'] = (string) ($object->order_ref ?? '');
	$substitutionarray['__COMPLETION_DATE__'] = $completionDate;
	$substitutionarray['__CERTIFIED_AMOUNT__'] = price((float) ($object->total_ht ?? 0), 0, $langs);
	$substitutionarray['__CURRENCY__'] = (string) ($object->currency_code ?? '');

	// In progress mode the percentage is stored explicitly. For line-based
	// certificates derive the equivalent share from the certified net amount,
	// so the same email placeholder can be used for both completion modes.
	$progressPercent = (float) ($object->progress_percent ?? 0);
	if ((int) ($object->completion_mode ?? 0) !== 1) {
		$orderTotalHt = (float) ($object->order_total_ht ?? 0);
		$totalHt = (float) ($object->total_ht ?? 0);
		$progressPercent = abs($orderTotalHt) > 0.00000001
			? ($totalHt / $orderTotalHt) * 100
			: 0.0;
	}
	$substitutionarray['__PROGRESS_PERCENT__'] = price($progressPercent, 0, $langs);
}
