<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Service;

use PDO;
use RuntimeException;
use Sibs\KronosApi\Exception\InvalidUserCredentialsException;

final class UserLoginService
{
    /** @param array<string, string|null> $encryptionConfig */
    public function __construct(
        private readonly PDO $pdo,
        private readonly array $encryptionConfig
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function authenticate(array $input): array
    {
        $identifier = $input['sibsId'] ?? null;
        $password = $input['password'] ?? null;
        if (!is_string($identifier) || trim($identifier) === '' || !is_string($password) || $password === '') {
            throw new InvalidUserCredentialsException('Invalid user credentials.');
        }

        $statement = $this->pdo->prepare(
            'SELECT u.gy_user_id, u.gy_user_code, u.ghl_contact_id, u.qds_contact_id, '
            . 'u.gy_full_name, u.gy_username, u.gy_password, u.gy_user_type, u.gy_user_function, '
            . 'u.gy_head_code, u.gy_script_code, u.gy_user_status, '
            . 'e.gy_emp_id, e.gy_acc_id AS accountId, e.gy_emp_fname, e.gy_emp_mname, '
            . 'e.gy_emp_lname, e.gy_emp_email, e.gy_dob, e.gy_gender, e.gy_civilstatus, '
            . 'e.gy_home_address, e.gy_emp_hiredate, e.gy_contact_num, '
            . 'a.gy_dept_id, a.gy_acc_name AS account, d.name_department AS department '
            . 'FROM gy_user u LEFT JOIN gy_employee e '
            . 'ON TRIM(e.gy_emp_code) = TRIM(u.gy_user_code) '
            . 'LEFT JOIN gy_accounts a ON e.gy_acc_id = a.gy_acc_id '
            . 'LEFT JOIN gy_department d ON a.gy_dept_id = d.id_department '
            . 'WHERE (TRIM(u.gy_user_code) = TRIM(:employee_code) '
            . 'OR TRIM(u.gy_username) = TRIM(:username)) AND u.gy_user_status = 0 LIMIT 1'
        );
        if ($statement === false) {
            throw new RuntimeException('Unable to prepare user login lookup.');
        }

        $identifier = trim($identifier);
        $statement->execute(['employee_code' => $identifier, 'username' => $identifier]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($user) || !is_string($user['gy_password'] ?? null)) {
            throw new InvalidUserCredentialsException('Invalid user credentials.');
        }

        $candidate = $this->encryptLegacyPassword($password);
        if (!hash_equals($user['gy_password'], $candidate)) {
            throw new InvalidUserCredentialsException('Invalid user credentials.');
        }

        unset($user['gy_password']);

        return $user;
    }

    private function encryptLegacyPassword(#[\SensitiveParameter] string $password): string
    {
        $method = $this->encryptionConfig['ENCRYPT_METHOD'] ?? null;
        $secretKey = $this->encryptionConfig['ENCRYPT_SECRET_KEY'] ?? null;
        $secretIv = $this->encryptionConfig['ENCRYPT_SECRET_IV'] ?? null;
        if (
            !is_string($method) || $method === ''
            || !is_string($secretKey) || $secretKey === ''
            || !is_string($secretIv) || $secretIv === ''
            || !in_array($method, openssl_get_cipher_methods(), true)
            || openssl_cipher_iv_length($method) !== 16
        ) {
            throw new RuntimeException('Invalid legacy password configuration.');
        }

        // Match the existing HRIS Node encryptPass() byte-for-byte.
        $key = substr(hash('sha256', $secretKey), 0, 32);
        $iv = substr(hash('sha256', $secretIv), 0, 16);
        $ciphertext = openssl_encrypt($password, $method, $key, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            throw new RuntimeException('Legacy password verification failed.');
        }

        return base64_encode(base64_encode($ciphertext));
    }
}
