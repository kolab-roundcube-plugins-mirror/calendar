/**
 * Roundcube Calendar Kolab backend
 *
 * @author Sergey Sidlyarenko
 * @licence GNU AGPL
 **/

CREATE TABLE IF NOT EXISTS kolab_alarms (
  event_id character varying(255) NOT NULL,
  user_id integer NOT NULL
        REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  notifyat timestamp without time zone DEFAULT NULL,
  dismissed smallint NOT NULL DEFAULT 0,
  PRIMARY KEY(event_id)
);

CREATE TABLE IF NOT EXISTS itipinvitations (
  token character varying(64) NOT NULL,
  event_uid character varying(255) NOT NULL,
  user_id integer NOT NULL
        REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  event text NOT NULL,
  expires timestamp without time zone DEFAULT NULL,
  cancelled smallint NOT NULL DEFAULT 0,
  PRIMARY KEY(token)
);

CREATE INDEX itipinvitations_event_uid_user_id_idx ON itipinvitations (event_uid, user_id);

INSERT INTO system (name, value) VALUES ('calendar-kolab-version', '2013011000');
