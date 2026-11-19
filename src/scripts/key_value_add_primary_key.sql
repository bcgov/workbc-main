DELETE FROM key_value WHERE collection = 'state' and name = 'system.private_key';
DELETE FROM key_value WHERE collection = 'state' and name = 'system.profile.files';
DELETE FROM key_value WHERE collection = 'state' and name = 'twig_extension_hash_prefix';

ALTER TABLE key_value ADD PRIMARY KEY (collection, name);