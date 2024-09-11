<?php

use Symfony\Component\Filesystem\Filesystem;

/**
* Creates a random unique temporary directory, with specified parameters,
* that does not already exist (like tempnam(), but for dirs).
*
* Created dir will begin with the specified prefix, followed by random
* numbers.
*
* @link https://php.net/manual/en/function.tempnam.php
*
* @param string|null $dir Base directory under which to create temp dir.
*     If null, the default system temp dir (sys_get_temp_dir()) will be
*     used.
* @param string $prefix String with which to prefix created dirs.
* @param int $mode Octal file permission mask for the newly-created dir.
*     Should begin with a 0.
* @param int $maxAttempts Maximum attempts before giving up (to prevent
*     endless loops).
* @return string|bool Full path to newly-created dir, or false on failure.
*/
function tempdir($dir = null, $prefix = 'tmp_', $mode = 0700, $maxAttempts = 1000)
{
  /* Use the system temp dir by default. */
  if (is_null($dir))
  {
    $dir = sys_get_temp_dir();
  }

  /* Trim trailing slashes from $dir. */
  $dir = rtrim($dir, DIRECTORY_SEPARATOR);

  /* If we don't have permission to create a directory, fail, otherwise we will
  * be stuck in an endless loop.
  */
  if (!is_dir($dir) || !is_writable($dir))
  {
    return false;
  }

  /* Make sure characters in prefix are safe. */
  if (strpbrk($prefix, '\\/:*?"<>|') !== false)
  {
    return false;
  }

  /* Attempt to create a random directory until it works. Abort if we reach
  * $maxAttempts. Something screwy could be happening with the filesystem
  * and our loop could otherwise become endless.
  */
  $attempts = 0;
  do
  {
    $path = sprintf('%s%s%s%s', $dir, DIRECTORY_SEPARATOR, $prefix, mt_rand(100000, mt_getrandmax()));
  } while (
    !mkdir($path, $mode) &&
    $attempts++ < $maxAttempts
  );

  return $path;
}

$git = new CzProject\GitPhp\Git;
$dir = tempdir(null, 'ssot_');
$repo = $git->cloneRepository('git@github.com:infojunkie/workbc-ssot.git', $dir, [
  '--branch=dev'
]);

$filename = $repo->getRepositoryPath() . '/test.txt';
file_put_contents($filename, "Lorem ipsum
	dolor
	sit amet
");

// commit
$repo->addFile($filename);
$repo->commit('Test commit');
$repo->push('origin');

echo "Done at $dir\n";

(new Filesystem)->remove($dir);
