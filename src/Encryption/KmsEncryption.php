<?php

namespace CredStash\Encryption;

use Aws\Kms\Exception\KmsException;
use Aws\Kms\KmsClient;
use CredStash\Credential;
use CredStash\Exception\DecryptionException;
use CredStash\Exception\EncryptionException;
use CredStash\Exception\IntegrityException;

/**
 * An encryption algorithm using AWS KMS service and AES Cipher.
 *
 * @author Carson Full <carsonfull@gmail.com>
 */
class KmsEncryption implements EncryptionInterface
{
    const DEFAULT_KMS_KEY = 'alias/credstash';

    /** @var KmsClient */
    protected $kms;
    /** @var string */
    protected $kmsKey;

    /**
     * Constructor.
     *
     * @param KmsClient $kms The KMS Client
     * @param string    $kmsKey The KMS key alias to use.
     */
    public function __construct(KmsClient $kms, $kmsKey = self::DEFAULT_KMS_KEY)
    {
        $this->kms = $kms;
        $this->kmsKey = $kmsKey;
    }

    /**
     * {@inheritdoc}
     */
    public function decrypt(Credential $credential, array $context)
    {
        try {
            $response = $this->kms->decrypt([
                'KeyId'             => $this->kmsKey,
                'CiphertextBlob'    => $credential->getKey(),
                'EncryptionContext' => $context,
            ]);
        } catch (\Exception $e) {
            $message = 'Failed to decrypt secret.';
            if ($e instanceof KmsException && $e->getAwsErrorCode() === 'InvalidCiphertextException') {
                $message .= empty($context) ?
                    "\nThe credential may require that an encryption context be provided to decrypt it." :
                    "\nThe encryption context provided may not match the context used when the credential was stored.";
            }

            throw new DecryptionException($message, $e);
        }

        $dataKey = substr($response['Plaintext'], 0, 32);
        $hmacKey = substr($response['Plaintext'], 32);

        $this->verifyHash($credential, $hmacKey);

        return $this->aesDecrypt($credential->getContents(), $dataKey);
    }

    /**
     * {@inheritdoc}
     */
    public function encrypt($secret, array $context)
    {
        $credential = new Credential();

        // Generate a 64 byte key
        // Half will be for data encryption, the other half for HMAC
        try {
            $response = $this->kms->generateDataKey([
                'KeyId'             => $this->kmsKey,
                'EncryptionContext' => $context,
                'NumberOfBytes'     => 64,
            ]);
        } catch (\Exception $e) {
            throw new EncryptionException(sprintf('Failed to generate data key using KMS key "%s"', $this->kmsKey), $e);
        }

        $credential->setKey($response['CiphertextBlob']);

        $dataKey = substr($response['Plaintext'], 0, 32);
        $hmacKey = substr($response['Plaintext'], 32);

        $contents = $this->aesEncrypt($secret, $dataKey);
        $credential->setContents($contents);

        $hmac = hash_hmac('sha256', $contents, $hmacKey);
        $credential->setHash($hmac);

        return $credential;
    }

    /**
     * Verifies the credential with the HMAC Key given.
     *
     * @param Credential $credential
     * @param string     $hmacKey
     *
     * @throws IntegrityException If the hashes do not match.
     */
    private function verifyHash(Credential $credential, $hmacKey)
    {
        $hmac = hash_hmac('sha256', $credential->getContents(), $hmacKey);

        if (!hash_equals($hmac, $credential->getHash())) {
            throw new IntegrityException(
                sprintf('Computed HMAC on %s does not match stored HMAC', $credential->getName())
            );
        }
    }

    /**
     * Encrypts secret with AES 256 cipher in CTR mode.
     *
     * @param string $secret
     * @param string $dataKey
     *
     * @throws EncryptionException If encryption fails.
     *
     * @return string The encrypted data.
     */
    private function aesEncrypt($secret, $dataKey)
    {
        $contents = openssl_encrypt($secret, 'aes-256-ctr', $dataKey, true, $this->getCounter());
        if ($contents === false) {
            throw new EncryptionException('Failed to encrypt secret.');
        }

        return $contents;
    }

    /**
     * Decrypts data with AES 256 cipher in CTR mode.
     *
     * @param string $contents The encrypted data.
     * @param string $dataKey
     *
     * @throws DecryptionException If decryption fails.
     *
     * @return string The secret data.
     */
    private function aesDecrypt($contents, $dataKey)
    {
        $secret = openssl_decrypt($contents, 'aes-256-ctr', $dataKey, true, $this->getCounter());
        if ($secret === false) {
            throw new DecryptionException('Failed to decrypt secret.');
        }

        return $secret;
    }

    /**
     * Creates a counter value for AES in CTR mode.
     *
     * Equivalent of python code: `Cyrpto.Util.Counter.new(128, initial_value = 1)`
     *
     * Taken from: {@see http://stackoverflow.com/a/32590050}
     *
     * @param int $initialValue
     *
     * @return string
     */
    private function getCounter($initialValue = 1)
    {
        // int to byte array
        $b = array_reverse(unpack('C*', pack('L', $initialValue)));
        // byte array to string
        $ctrStr = implode(array_map('chr', $b));
        // create 16 byte IV from counter
        $ctrVal = str_repeat("\x0", 12) . $ctrStr;

        return $ctrVal;
    }
}
