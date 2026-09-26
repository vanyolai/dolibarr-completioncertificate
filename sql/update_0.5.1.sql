ALTER TABLE llx_completioncertificate
 ADD COLUMN IF NOT EXISTS currency_code varchar(3) NULL AFTER total_ht;

ALTER TABLE llx_completioncertificate
 ADD COLUMN IF NOT EXISTS issue_text varchar(255) NULL AFTER currency_code;

UPDATE llx_completioncertificate c
 INNER JOIN llx_commande co ON co.rowid = c.fk_commande
 SET c.currency_code = CASE
  WHEN co.multicurrency_code IS NOT NULL AND co.multicurrency_code <> '' THEN co.multicurrency_code
  ELSE c.currency_code
 END,
 c.order_total_ht = CASE
  WHEN co.multicurrency_tx IS NOT NULL AND co.multicurrency_tx <> 1 THEN co.multicurrency_total_ht
  ELSE co.total_ht
 END;

UPDATE llx_completioncertificate_line l
 INNER JOIN llx_completioncertificate c ON c.rowid = l.fk_completioncertificate
 INNER JOIN llx_commandedet cd ON cd.rowid = l.fk_commandedet
 INNER JOIN llx_commande co ON co.rowid = c.fk_commande
 SET l.total_ht = CASE
  WHEN cd.qty = 0 THEN 0
  WHEN co.multicurrency_tx IS NOT NULL AND co.multicurrency_tx <> 1 THEN cd.multicurrency_total_ht * (l.qty_certified / cd.qty)
  ELSE cd.total_ht * (l.qty_certified / cd.qty)
 END;

UPDATE llx_completioncertificate c
 SET c.total_ht = CASE
  WHEN c.completion_mode = 1 THEN c.order_total_ht * (c.progress_percent / 100)
  ELSE (
   SELECT COALESCE(SUM(l.total_ht), 0)
   FROM llx_completioncertificate_line l
   WHERE l.fk_completioncertificate = c.rowid
  )
 END;
