<?php
/* Copyright (C) 2026 Vanyolai Krisztián <vanyolai@gmail.com> */

if (empty($conf) || !is_object($conf)) {
	print "Error, template page can't be called as URL";
	exit(1);
}

global $linkedObjectBlock, $noMoreLinkedObjectBlockAfter, $user, $db;

$langs->load('completioncertificate@completioncertificate');

$linkedObjectBlock = dol_sort_array($linkedObjectBlock, 'date_completion,ref', 'desc', 0, 0, 1);

$total = 0.0;
$ilink = 0;
foreach ($linkedObjectBlock as $key => $objectlink) {
	$ilink++;

	$trclass = 'oddeven';
	if ($ilink == count($linkedObjectBlock) && empty($noMoreLinkedObjectBlockAfter) && count($linkedObjectBlock) <= 1) {
		$trclass .= ' liste_sub_total';
	}

	print '<tr class="'.$trclass.'" data-element="'.$objectlink->element.'" data-id="'.((int) $objectlink->id).'">';
	print '<td class="linkedcol-element tdoverflowmax100">'.$langs->trans('CompletionCertificate').'</td>';
	print '<td class="linkedcol-name tdoverflowmax150">'.$objectlink->getNomUrl(1).'</td>';

	$thirdRef = !empty($objectlink->ref_customer) ? $objectlink->ref_customer : $objectlink->ref;
	print '<td class="linkedcol-ref tdoverflowmax150" title="'.dol_escape_htmltag($thirdRef).'">'.dol_escape_htmltag($thirdRef).'</td>';

	$dateCompletion = !empty($objectlink->date_completion) ? $db->jdate($objectlink->date_completion) : 0;
	print '<td class="linkedcol-date center">'.dol_print_date($dateCompletion, 'day').'</td>';

	print '<td class="linkedcol-amount right nowraponall">';
	if ((int) $objectlink->status === $objectlink::STATUS_CANCELED) {
		print '<strike>'.price($objectlink->total_ht).' '.dol_escape_htmltag($objectlink->currency_code).'</strike>';
	} else {
		$total += (float) $objectlink->total_ht;
		print price($objectlink->total_ht).' '.dol_escape_htmltag($objectlink->currency_code);
	}
	print '</td>';

	print '<td class="linkedcol-statut right">'.$objectlink->getLibStatut(3).'</td>';
	print '<td class="linkedcol-action right"></td>';
	print '</tr>';
}

$currencies = array();
foreach ($linkedObjectBlock as $tmpObjectLink) {
	if ((int) $tmpObjectLink->status !== $tmpObjectLink::STATUS_CANCELED && !empty($tmpObjectLink->currency_code)) {
		$currencies[$tmpObjectLink->currency_code] = true;
	}
}

if (count($linkedObjectBlock) > 1 && count($currencies) <= 1) {
	print '<tr class="liste_total '.(empty($noMoreLinkedObjectBlockAfter) ? 'liste_sub_total' : '').'">';
	print '<td>'.$langs->trans('Total').'</td>';
	print '<td></td>';
	print '<td></td>';
	print '<td></td>';
	$totalCurrency = count($currencies) === 1 ? (string) array_key_first($currencies) : '';
	print '<td class="right">'.price($total).($totalCurrency !== '' ? ' '.dol_escape_htmltag($totalCurrency) : '').'</td>';
	print '<td></td>';
	print '<td></td>';
	print '</tr>';
}
