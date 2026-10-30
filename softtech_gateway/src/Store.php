<?php

namespace Gateway;

use PDO;

/** SQLite or MySQL through PDO. Stores routing and delivery state only: no conversations, no customer profiles. */
final class Store
{
    public PDO $db;
    private string $key;

    public function __construct(PDO $db, string $key32)
    {
        $this->db = $db;
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (strlen($key32) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \InvalidArgumentException('GATEWAY_KEY must be exactly 32 bytes (use base64 of 32 random bytes).');
        }
        $this->key = $key32;
    }

    public function migrate(): void
    {
        $auto = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT PRIMARY KEY AUTO_INCREMENT';
        foreach ([
            "CREATE TABLE IF NOT EXISTS installations (id VARCHAR(40) PRIMARY KEY, name VARCHAR(190), domain VARCHAR(190), callback_url VARCHAR(500), secret_enc TEXT, prev_secret_enc TEXT NULL, prev_expires_at INT NULL, status VARCHAR(12) DEFAULT 'active', created_at INT)",
            "CREATE TABLE IF NOT EXISTS routes (platform VARCHAR(20), external_id VARCHAR(100), installation_id VARCHAR(40), created_at INT, PRIMARY KEY (platform, external_id))",
            "CREATE TABLE IF NOT EXISTS deliveries (id $auto, installation_id VARCHAR(40), event_id VARCHAR(80) UNIQUE, platform VARCHAR(20), payload MEDIUMTEXT, status VARCHAR(10) DEFAULT 'pending', attempts INT DEFAULT 0, next_attempt_at INT, last_error VARCHAR(300) NULL, created_at INT)",
            "CREATE TABLE IF NOT EXISTS grants (token_hash CHAR(64) PRIMARY KEY, installation_id VARCHAR(40), accounts_enc TEXT, expires_at INT, used INT DEFAULT 0)",
            "CREATE TABLE IF NOT EXISTS oauth_states (state_hash CHAR(64) PRIMARY KEY, installation_id VARCHAR(40), platform VARCHAR(20), return_url VARCHAR(500), expires_at INT)",
            "CREATE TABLE IF NOT EXISTS seen_requests (sig_hash CHAR(64) PRIMARY KEY, expires_at INT)",
        ] as $sql) {
            $this->db->exec($sql);
        }
    }

    public function seal(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $this->key));
    }

    public function open(?string $sealed): ?string
    {
        if (!$sealed) {
            return null;
        }
        $raw = base64_decode($sealed, true);
        $plain = $raw === false ? false : sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $this->key);

        return $plain === false ? null : $plain;
    }

    public function one(string $sql, array $args = []): ?array
    {
        $st = $this->db->prepare($sql);
        $st->execute($args);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function run(string $sql, array $args = []): int
    {
        $st = $this->db->prepare($sql);
        $st->execute($args);

        return $st->rowCount();
    }

    // ── installations ──
    /** @return array{id:string,secret:string} the secret is shown once */
    public function createInstallation(string $name, string $domain, string $callbackUrl): array
    {
        if (!preg_match('#^https://#', $callbackUrl)) {
            throw new \InvalidArgumentException('The installation callback URL must be https.');
        }
        $id = 'inst_' . bin2hex(random_bytes(8));
        $secret = bin2hex(random_bytes(32));
        $this->run('INSERT INTO installations (id,name,domain,callback_url,secret_enc,status,created_at) VALUES (?,?,?,?,?,?,?)', [$id, $name, $domain, $callbackUrl, $this->seal($secret), 'active', time()]);

        return ['id' => $id, 'secret' => $secret];
    }

    /** New secret now; the old one still verifies requests for $graceSeconds so the installation can switch over. */
    public function rotate(string $id, int $graceSeconds = 3600): string
    {
        $row = $this->one('SELECT secret_enc FROM installations WHERE id=?', [$id]) ?? throw new \RuntimeException('Unknown installation.');
        $secret = bin2hex(random_bytes(32));
        $this->run('UPDATE installations SET prev_secret_enc=?, prev_expires_at=?, secret_enc=? WHERE id=?', [$row['secret_enc'], time() + $graceSeconds, $this->seal($secret), $id]);

        return $secret;
    }

    public function revoke(string $id): void
    {
        $this->run("UPDATE installations SET status='revoked' WHERE id=?", [$id]);
        $this->run('DELETE FROM routes WHERE installation_id=?', [$id]);
        $this->run("UPDATE deliveries SET status='dead', last_error='installation revoked' WHERE installation_id=? AND status='pending'", [$id]);
    }

    public function installation(string $id): ?array
    {
        $r = $this->one("SELECT * FROM installations WHERE id=? AND status='active'", [$id]);
        if (!$r) {
            return null;
        }
        $r['secrets'] = array_filter([$this->open($r['secret_enc']), $r['prev_expires_at'] && $r['prev_expires_at'] > time() ? $this->open($r['prev_secret_enc']) : null]);

        return $r;
    }

    /** First claim wins; an account can never be re-pointed at another installation without being unlinked first. */
    public function claim(string $platform, string $externalId, string $installationId): bool
    {
        $owner = $this->one('SELECT installation_id FROM routes WHERE platform=? AND external_id=?', [$platform, $externalId]);
        if ($owner) {
            return $owner['installation_id'] === $installationId;
        }
        try {
            $this->run('INSERT INTO routes (platform,external_id,installation_id,created_at) VALUES (?,?,?,?)', [$platform, $externalId, $installationId, time()]);

            return true;
        } catch (\PDOException $e) {
            $owner = $this->one('SELECT installation_id FROM routes WHERE platform=? AND external_id=?', [$platform, $externalId]);

            return $owner && $owner['installation_id'] === $installationId;
        }
    }

    public function owner(string $platform, string $externalId): ?string
    {
        return ($this->one('SELECT installation_id FROM routes WHERE platform=? AND external_id=?', [$platform, $externalId]) ?? [])['installation_id'] ?? null;
    }
}
