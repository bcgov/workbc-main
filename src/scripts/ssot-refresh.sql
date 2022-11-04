--
-- Send a signal to PostgREST to refresh its schema.
--
NOTIFY pgrst, 'reload schema';
