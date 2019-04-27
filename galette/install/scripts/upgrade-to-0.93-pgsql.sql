-- sequence for searches
DROP SEQUENCE IF EXISTS galette_searches_id_seq;
CREATE SEQUENCE galette_searches_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

-- Table for saved searches
DROP TABLE IF EXISTS galette_searches;
CREATE TABLE galette_searches (
  search_id integer DEFAULT nextval('galette_searches_id_seq'::text) NOT NULL,
  name character varying(100) NOT NULL,
  private boolean DEFAULT TRUE,
  parameters text NOT NULL,
  id_adh integer REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE,
  creation_date timestamp NOT NULL,
  PRIMARY KEY (search_id)
);

UPDATE galette_database SET version = 0.93;
