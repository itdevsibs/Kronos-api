<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use PDOStatement;
use RuntimeException;

final class AnnouncementRelationshipRepository
{
    private const USER_FIELDS = 'u.gy_user_code, u.ghl_contact_id, u.gy_full_name, u.gy_username, '
        . 'u.gy_user_type, u.gy_user_function, u.gy_head_code, u.gy_script_code, u.gy_user_status';

    private const ANNOUNCEMENT_FIELDS = 'a.gy_ann_id, a.gy_ann_serial, a.gy_ann_type, a.gy_ann_date, '
        . 'a.gy_ann_end, a.gy_ann_caption, a.gy_ann_attachment';

    private const CONFIRMATION_FIELDS = 'c.gy_conf_id, c.gy_conf_date, c.gy_conf_by, c.gy_ann_id';

    private const NOTIFICATION_FIELDS = 'n.gy_notif_id, n.gy_notif_type, n.gy_user_code, '
        . 'n.gy_notif_text, n.gy_notif_date, n.gy_notif_ip';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    public function findAnnouncementsByUserCode(string $code, int $afterId): array
    {
        return $this->userCollection(
            $code,
            $afterId,
            'gy_announce a',
            'a.gy_ann_by = u.gy_user_id',
            self::ANNOUNCEMENT_FIELDS,
            'a.gy_ann_id',
            'gy_ann_id'
        );
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    public function findConfirmationsByUserCode(string $code, int $afterId): array
    {
        return $this->userCollection(
            $code,
            $afterId,
            'gy_confirm c',
            'TRIM(c.gy_conf_by) = TRIM(u.gy_user_code)',
            self::CONFIRMATION_FIELDS,
            'c.gy_conf_id',
            'gy_conf_id'
        );
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    public function findNotificationsByUserCode(string $code, int $afterId): array
    {
        return $this->userCollection(
            $code,
            $afterId,
            'gy_notification n',
            'TRIM(n.gy_user_code) = TRIM(u.gy_user_code)',
            self::NOTIFICATION_FIELDS,
            'n.gy_notif_id',
            'gy_notif_id'
        );
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    public function findConfirmationsByAnnouncementId(int $id, int $afterId): array
    {
        $sql = 'SELECT a.gy_ann_id AS _parent_id, ' . self::CONFIRMATION_FIELDS
            . ' FROM gy_announce a LEFT JOIN gy_confirm c ON c.gy_ann_id = a.gy_ann_id'
            . ' AND c.gy_conf_id > :after_id WHERE a.gy_ann_id = :announcement_id'
            . ' ORDER BY c.gy_conf_id ASC LIMIT 101';

        return $this->collection($sql, ':announcement_id', $id, PDO::PARAM_INT, $afterId, 'gy_conf_id');
    }

    /** @return array{parent_exists: bool, data: array<string, mixed>|null} */
    public function findCreatedByUserByAnnouncementId(int $id): array
    {
        return $this->single(
            'SELECT a.gy_ann_id AS _parent_id, u.gy_user_id AS _related_id, ' . self::USER_FIELDS
                . ' FROM gy_announce a LEFT JOIN gy_user u ON a.gy_ann_by = u.gy_user_id'
                . ' WHERE a.gy_ann_id = :announcement_id LIMIT 1',
            ':announcement_id',
            $id
        );
    }

    /** @return array{parent_exists: bool, data: array<string, mixed>|null} */
    public function findAnnouncementByConfirmationId(int $id): array
    {
        return $this->single(
            'SELECT c.gy_conf_id AS _parent_id, a.gy_ann_id AS _related_id, ' . self::ANNOUNCEMENT_FIELDS
                . ' FROM gy_confirm c LEFT JOIN gy_announce a ON c.gy_ann_id = a.gy_ann_id'
                . ' WHERE c.gy_conf_id = :confirmation_id LIMIT 1',
            ':confirmation_id',
            $id
        );
    }

    /** @return array{parent_exists: bool, data: array<string, mixed>|null} */
    public function findUserByConfirmationId(int $id): array
    {
        return $this->single(
            'SELECT c.gy_conf_id AS _parent_id, u.gy_user_id AS _related_id, ' . self::USER_FIELDS
                . ' FROM gy_confirm c LEFT JOIN gy_user u'
                . ' ON TRIM(c.gy_conf_by) = TRIM(u.gy_user_code)'
                . ' WHERE c.gy_conf_id = :confirmation_id LIMIT 1',
            ':confirmation_id',
            $id
        );
    }

    /** @return array{parent_exists: bool, data: array<string, mixed>|null} */
    public function findUserByNotificationId(int $id): array
    {
        return $this->single(
            'SELECT n.gy_notif_id AS _parent_id, u.gy_user_id AS _related_id, ' . self::USER_FIELDS
                . ' FROM gy_notification n LEFT JOIN gy_user u'
                . ' ON TRIM(n.gy_user_code) = TRIM(u.gy_user_code)'
                . ' WHERE n.gy_notif_id = :notification_id LIMIT 1',
            ':notification_id',
            $id
        );
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    private function userCollection(
        string $code,
        int $afterId,
        string $source,
        string $join,
        string $fields,
        string $key,
        string $cursor
    ): array {
        $sql = 'SELECT u.gy_user_id AS _parent_id, ' . $fields
            . ' FROM gy_user u LEFT JOIN ' . $source . ' ON ' . $join . ' AND ' . $key . ' > :after_id'
            . ' WHERE TRIM(u.gy_user_code) = TRIM(:employee_code) ORDER BY ' . $key . ' ASC LIMIT 101';

        return $this->collection($sql, ':employee_code', $code, PDO::PARAM_STR, $afterId, $cursor);
    }

    /** @return array{parent_exists: bool, data: array<string, mixed>|null} */
    private function single(string $sql, string $placeholder, int $id): array
    {
        $statement = $this->prepare($sql);
        $statement->bindValue($placeholder, $id, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return ['parent_exists' => false, 'data' => null];
        }

        $relatedExists = ($row['_related_id'] ?? null) !== null;
        unset($row['_parent_id'], $row['_related_id']);

        return ['parent_exists' => true, 'data' => $relatedExists ? $row : null];
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    private function collection(
        string $sql,
        string $placeholder,
        int|string $parent,
        int $type,
        int $afterId,
        string $cursor
    ): array {
        $statement = $this->prepare($sql);
        $statement->bindValue($placeholder, $parent, $type);
        $statement->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            return ['parent_exists' => false, 'data' => []];
        }

        $data = [];
        foreach ($rows as $row) {
            unset($row['_parent_id']);
            if (($row[$cursor] ?? null) !== null) {
                $data[] = $row;
            }
        }

        return ['parent_exists' => true, 'data' => $data];
    }

    private function prepare(string $sql): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        if ($statement === false) {
            throw new RuntimeException('Unable to prepare announcement relationship query.');
        }

        return $statement;
    }
}
