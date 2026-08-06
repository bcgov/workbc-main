DELETE FROM key_value WHERE collection = 'state' and name = 'system.private_key';

ALTER TABLE key_value ADD PRIMARY KEY (collection, name);
