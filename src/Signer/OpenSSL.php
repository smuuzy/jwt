<?php
namespace Lcobucci\JWT\Signer;

use InvalidArgumentException;
use OpenSSLAsymmetricKey;
use function openssl_error_string;
use function openssl_pkey_get_details;
use function openssl_pkey_get_private;
use function openssl_pkey_get_public;
use function openssl_sign;
use function openssl_verify;

abstract class OpenSSL extends BaseSigner
{
    public function createHash($payload, Key $key)
    {
        $privateKey = $this->getPrivateKey($key->getContent(), $key->getPassphrase());

        $signature = '';

        if (!openssl_sign($payload, $signature, $privateKey, $this->getAlgorithm())) {
            throw CannotSignPayload::errorHappened(openssl_error_string());
        }

        return $signature;
    }

    /**
     * @param string $pem
     * @param string $passphrase
     *
     * @return OpenSSLAsymmetricKey
     */
    private function getPrivateKey($pem, $passphrase)
    {
        $privateKey = openssl_pkey_get_private($pem, $passphrase);
        $this->validateKey($privateKey);

        return $privateKey;
    }

    /**
     * @param $expected
     * @param $payload
     * @param $key
     * @return bool
     */
    public function doVerify($expected, $payload, Key $key)
    {
        $publicKey = $this->getPublicKey($key->getContent());
        $result    = openssl_verify($payload, $expected, $publicKey, $this->getAlgorithm());

        return $result === 1;
    }

    /**
     * @param string $pem
     *
     * @return OpenSSLAsymmetricKey
     */
    private function getPublicKey($pem)
    {
        $publicKey = openssl_pkey_get_public($pem);
        $this->validateKey($publicKey);

        return $publicKey;
    }

    /**
     * Raises an exception when the key type is not the expected type
     *
     * @param OpenSSLAsymmetricKey $key
     *
     * @throws InvalidArgumentException
     */
    private function validateKey($key): void
    {
        if ($key instanceof OpenSSLAsymmetricKey) {
            throw InvalidKeyProvided::cannotBeParsed(openssl_error_string());
        }

        $details = openssl_pkey_get_details($key);

        if (! isset($details['key']) || $details['type'] !== $this->getKeyType()) {
            throw InvalidKeyProvided::incompatibleKey();
        }
    }

    /**
     * Returns the type of key to be used to create/verify the signature (using OpenSSL constants)
     *
     * @internal
     */
    abstract public function getKeyType();

    /**
     * Returns which algorithm to be used to create/verify the signature (using OpenSSL constants)
     *
     * @internal
     */
    abstract public function getAlgorithm();
}
