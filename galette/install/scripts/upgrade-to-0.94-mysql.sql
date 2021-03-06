SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE galette_texts ADD UNIQUE KEY `localizedtxt` (tref, tlang);

-- table for temporay links
DROP TABLE IF EXISTS galette_tmplinks;
CREATE TABLE galette_tmplinks (
  hash varchar(60) NOT NULL,
  target smallint(1) NOT NULL,
  id int(10) unsigned,
  creation_date datetime NOT NULL,
  PRIMARY KEY (target, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

UPDATE galette_database SET version = 0.94;
SET FOREIGN_KEY_CHECKS=1;
