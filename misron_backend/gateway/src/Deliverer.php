<?php

namespace Gateway;

/** Durable at-least-once delivery. The installation de-duplicates on event_id, so retries are always safe. */
final class Deliverer
{
    public const MAX_ATTEMPTS = 10;

    /** @var callable(string,array,string):int  (url, headers, body) => http status; injectable for tests */
    private $http;

    public function __construct(private Store $store, ?callable $http = null)
    {
        $this->http = $http ?? function (string $url, array $headers, string $body): int {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $code;
        };
    }

    /** Store first (so a gateway crash loses nothing), then try once right away. */
    public function enqueue(string $installationId, string $platform, array $payload): ?int
    {
        $body = json_encode($payload);
        $eventId = 'ev_' . substr(hash('sha256', $installationId . $body), 0, 40);
        try {
            $this->store->run('INSERT INTO deliveries (installation_id,event_id,platform,payload,status,attempts,next_attempt_at,created_at) VALUES (?,?,?,?,?,?,?,?)', [$installationId, $eventId, $platform, $body, 'pending', 0, time(), time()]);
        } catch (\PDOException $e) {
            return null; // the provider redelivered an event we already hold
        }
        $id = (int) $this->store->db->lastInsertId();
        $this->attempt($id);

        return $id;
    }

    public function attempt(int $id): void
    {
        $d = $this->store->one("SELECT * FROM deliveries WHERE id=? AND status='pending'", [$id]);
        if (!$d) {
            return;
        }
        $inst = $this->store->installation($d['installation_id']);
        if (!$inst) {
            $this->store->run("UPDATE deliveries SET status='dead', last_error='installation revoked or unknown' WHERE id=?", [$id]);

            return;
        }
        $body = json_encode(['event_id' => $d['event_id'], 'platform' => $d['platform'], 'payload' => json_decode($d['payload'], true)]);
        $ts = time();
        $code = ($this->http)($inst['callback_url'], [
            'Content-Type: application/json', 'X-Softtech-Installation: ' . $inst['id'], 'X-Softtech-Timestamp: ' . $ts,
            'X-Softtech-Signature: ' . Signature::sign($inst['secrets'][0], $inst['id'], $ts, $body),
        ], $body);

        if ($code >= 200 && $code < 300) {
            $this->store->run("UPDATE deliveries SET status='done', attempts=attempts+1, last_error=NULL WHERE id=?", [$id]);

            return;
        }
        $n = (int) $d['attempts'] + 1;
        if ($n >= self::MAX_ATTEMPTS) {
            $this->store->run("UPDATE deliveries SET status='dead', attempts=?, last_error=? WHERE id=?", [$n, "HTTP $code after $n attempts", $id]);

            return;
        }
        // 30s, 1m, 2m … capped at 6h
        $this->store->run('UPDATE deliveries SET attempts=?, next_attempt_at=?, last_error=? WHERE id=?', [$n, time() + min(30 * (2 ** ($n - 1)), 21600), "HTTP $code", $id]);
    }

    /** Cron entry: every minute. */
    public function retryDue(int $limit = 100): int
    {
        $ids = $this->store->db->query("SELECT id FROM deliveries WHERE status='pending' AND next_attempt_at<=" . time() . ' ORDER BY id LIMIT ' . (int) $limit)->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            $this->attempt((int) $id);
        }

        return count($ids);
    }
}
