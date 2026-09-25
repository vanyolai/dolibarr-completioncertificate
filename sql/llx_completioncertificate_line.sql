CREATE TABLE IF NOT EXISTS llx_completioncertificate_line (
 rowid integer AUTO_INCREMENT PRIMARY KEY,
 fk_completioncertificate integer NOT NULL,
 fk_commandedet integer NOT NULL,
 fk_product integer,
 description text,
 qty_ordered double(24,8) NOT NULL DEFAULT 0,
 qty_certified double(24,8) NOT NULL DEFAULT 0,
 total_ht double(24,8) NOT NULL DEFAULT 0,
 rang integer NOT NULL DEFAULT 0,
 INDEX idx_completioncertificate_line_parent (fk_completioncertificate),
 INDEX idx_completioncertificate_line_orderline (fk_commandedet)
) ENGINE=innodb;
