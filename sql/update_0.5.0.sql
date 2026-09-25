ALTER TABLE llx_completioncertificate
 ADD COLUMN IF NOT EXISTS completion_mode smallint NOT NULL DEFAULT 0 AFTER date_completion;

ALTER TABLE llx_completioncertificate
 ADD COLUMN IF NOT EXISTS progress_percent double(24,8) NOT NULL DEFAULT 0 AFTER completion_mode;

ALTER TABLE llx_completioncertificate
 ADD COLUMN IF NOT EXISTS order_total_ht double(24,8) NOT NULL DEFAULT 0 AFTER progress_percent;

ALTER TABLE llx_completioncertificate
 ADD COLUMN IF NOT EXISTS total_ht double(24,8) NOT NULL DEFAULT 0 AFTER order_total_ht;

ALTER TABLE llx_completioncertificate_line
 ADD COLUMN IF NOT EXISTS total_ht double(24,8) NOT NULL DEFAULT 0 AFTER qty_certified;

UPDATE llx_completioncertificate c
 INNER JOIN llx_commande co ON co.rowid = c.fk_commande
 SET c.order_total_ht = co.total_ht
 WHERE c.order_total_ht = 0;

UPDATE llx_completioncertificate_line l
 INNER JOIN llx_commandedet cd ON cd.rowid = l.fk_commandedet
 SET l.total_ht = CASE
  WHEN cd.qty <> 0 THEN cd.total_ht * (l.qty_certified / cd.qty)
  ELSE 0
 END
 WHERE l.total_ht = 0;

UPDATE llx_completioncertificate c
 SET c.total_ht = (
  SELECT COALESCE(SUM(l.total_ht), 0)
  FROM llx_completioncertificate_line l
  WHERE l.fk_completioncertificate = c.rowid
 )
 WHERE c.total_ht = 0;
