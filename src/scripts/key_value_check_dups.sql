SELECT collection, name, count(collection) FROM key_value
GROUP BY collection, name
HAVING count(collection) > 1;