<?php

namespace CredStash;

use CredStash\Exception\AutoIncrementException;
use CredStash\Exception\CredentialNotFoundException;
use CredStash\Exception\DecryptionException;
use CredStash\Exception\DuplicateCredentialVersionException;
use CredStash\Exception\EncryptionException;
use CredStash\Exception\IntegrityException;
use Iterator;
use Traversable;

/**
 * A CredStash.
 *
 * @author Carson Full <carsonfull@gmail.com>
 */
interface CredStashInterface
{
    /**
     * Fetches the names and version of every credential in the
     * store matching the pattern given.
     *
     * The pattern can contain "*" and "?" wildcard characters and "[]" grouping.
     *
     * @example "gr[ae]y"
     * @example "group*"
     *
     * @param string $pattern The pattern to search for.
     *
     * @return Iterator [name => version]
     */
    public function listCredentials($pattern = '*');

    /**
     * Fetches and decrypts all credentials.
     *
     * @param array|Traversable $context Encryption Context key value pairs.
     * @param int|string|null   $version Numeric version for all credentials or null for highest of each credential.
     *
     * @throws CredentialNotFoundException If the credential does not exist.
     * @throws IntegrityException If the HMAC does not match.
     * @throws DecryptionException If decryption fails.
     *
     * @return Iterator [name => secret]
     */
    public function getAll($context = [], $version = null);

    /**
     * Fetches and decrypts all credentials matching the pattern given.
     *
     * @param string            $pattern The pattern to search for. See {@see listCredentials} for details.
     * @param array|Traversable $context Encryption Context key value pairs.
     * @param int|string|null   $version Numeric version for all credentials or null for highest of each credential.
     *
     * @throws CredentialNotFoundException If the credential does not exist.
     * @throws IntegrityException If the HMAC does not match.
     * @throws DecryptionException If decryption fails.
     *
     * @return Iterator [name => secret]
     */
    public function search($pattern = '*', $context = [], $version = null);

    /**
     * Fetches and decrypts the credential.
     *
     * @param string            $name    The credential's name.
     * @param array|Traversable $context Encryption Context key value pairs.
     * @param int|string|null   $version Numeric version or null for highest.
     *
     * @throws CredentialNotFoundException If the credential does not exist.
     * @throws IntegrityException If the HMAC does not match.
     * @throws DecryptionException If decryption fails.
     *
     * @return string The secret.
     */
    public function get($name, $context = [], $version = null);

    /**
     * Put a credential into the store.
     *
     * @param string            $name    The credential's name.
     * @param string            $secret  The secret value.
     * @param array|Traversable $context Encryption Context key value pairs.
     * @param int|string|null   $version Numeric version or null for next auto-incremented version.
     *
     * @throws AutoIncrementException If current version cannot be auto incremented.
     * @throws DuplicateCredentialVersionException If the credential with the version already exists.
     * @throws EncryptionException If encryption fails.
     */
    public function put($name, $secret, $context = [], $version = null);

    /**
     * Delete a credential from the store (including all versions).
     *
     * @param string $name
     */
    public function delete($name);

    /**
     * Fetches the highest version of given credential in the store.
     *
     * @param string $name The credential's name.
     *
     * @return string The version or "0" if not found.
     */
    public function getHighestVersion($name);
}
