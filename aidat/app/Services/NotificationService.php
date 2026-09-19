<?php

declare(strict_types=1);

namespace Aidat\Services;

use Aidat\Core\Application;
use Aidat\Core\Database;
use Aidat\Core\Dates;
use Aidat\Core\Money;
use Throwable;

/**
 * Bildirimler: uygulama içi (her zaman), e-posta (log|mail|smtp), SMS (log|webhook).
 * Dış gönderim başarısız olsa da uygulama içi kayıt kalır; hata metni saklanır.
 */
final class NotificationService
{
    private Database $db;

    public function __construct(private readonly Application $app)
    {
        $this->db = $app->db();
    }

    /**
     * @param list<int> $personIds
     * @param list<string> $channels uygulama|eposta|sms
     */
    public function notifyPeople(int $buildingId, array $personIds, string $subject, string $body, array $channels = ['uygulama'], ?string $template = null, ?string $refType = null, ?int $refId = null): int
    {
        if ($personIds === []) {
            return 0;
        }
        $in = implode(',', array_fill(0, count($personIds), '?'));
        $people = $this->db->fetchAll("SELECT id, user_id, email, phone, contact_consent FROM people WHERE id IN ({$in})", $personIds);
        $n = 0;
        foreach ($people as $p) {
            foreach ($channels as $ch) {
                if ($ch === 'uygulama') {
                    if ($p['user_id'] === null) {
                        continue;
                    }
                    $this->insert($buildingId, (int) $p['user_id'], (int) $p['id'], 'uygulama', null, $subject, $body, 'gonderildi', null, $template, $refType, $refId);
                    $n++;
                } elseif ($ch === 'eposta' && $p['email']) {
                    $n += $this->sendEmail($buildingId, (int) $p['id'], $p['user_id'] ? (int) $p['user_id'] : null, (string) $p['email'], $subject, $body, $template, $refType, $refId) ? 1 : 0;
                } elseif ($ch === 'sms' && $p['phone'] && (int) $p['contact_consent'] === 1) {
                    $n += $this->sendSms($buildingId, (int) $p['id'], $p['user_id'] ? (int) $p['user_id'] : null, (string) $p['phone'], $subject . ' ' . strip_tags($body), $template, $refType, $refId) ? 1 : 0;
                }
            }
        }
        return $n;
    }

    /** Yapıdaki tüm kullanıcılara (yönetim dahil) uygulama içi bildirim. @param list<int> $userIds */
    public function notifyUsers(?int $buildingId, array $userIds, string $subject, string $body, ?string $template = null, ?string $refType = null, ?int $refId = null): int
    {
        $n = 0;
        foreach (array_unique($userIds) as $uid) {
            $this->insert($buildingId, (int) $uid, null, 'uygulama', null, $subject, $body, 'gonderildi', null, $template, $refType, $refId);
            $n++;
        }
        return $n;
    }

    /** Duyuru hedef kitlesine göre kişi listesi. @return list<int> person ids */
    public function audiencePeople(int $buildingId, string $target, ?string $targetRef): array
    {
        $today = Dates::today();
        $sql = 'SELECT DISTINCT p.id FROM people p JOIN occupancies o ON o.person_id = p.id JOIN units u ON u.id = o.unit_id WHERE p.building_id = ? AND (o.end_date IS NULL OR o.end_date >= ?)';
        $params = [$buildingId, $today];
        switch ($target) {
            case 'blok':
                $sql .= ' AND u.block_id = ?';
                $params[] = (int) $targetRef;
                break;
            case 'bolum':
                $sql .= ' AND u.id = ?';
                $params[] = (int) $targetRef;
                break;
            case 'rol':
                $sql .= ' AND o.role = ?';
                $params[] = (string) $targetRef;
                break;
            case 'kisi':
                $sql .= ' AND p.id = ?';
                $params[] = (int) $targetRef;
                break;
        }
        return array_map('intval', array_column($this->db->fetchAll($sql, $params), 'id'));
    }

    /** Vadesi geçmiş borcu olan bölümlerin sorumlularına hatırlatma. */
    public function sendDebtReminders(?int $buildingId = null, ?string $asOf = null): int
    {
        $asOf ??= Dates::today();
        $ids = $buildingId !== null ? [$buildingId] : array_map('intval', array_column($this->db->fetchAll('SELECT id FROM buildings WHERE is_active = 1'), 'id'));
        $charges = new ChargeService($this->app);
        $total = 0;
        foreach ($ids as $bid) {
            $bname = (string) $this->db->fetchColumn('SELECT name FROM buildings WHERE id = ?', [$bid]);
            foreach ($charges->debtors($bid, $asOf) as $d) {
                if ((int) $d['overdue'] <= 0) {
                    continue;
                }
                $personIds = array_map('intval', array_column($this->db->fetchAll(
                    "SELECT DISTINCT person_id FROM occupancies WHERE unit_id = ? AND role IN ('malik','kiraci') AND (end_date IS NULL OR end_date >= ?)",
                    [(int) $d['unit_id'], $asOf],
                ), 'person_id'));
                $subject = $bname . ' · Vadesi geçmiş aidat hatırlatması';
                $body = sprintf('Sayın sakinimiz, %s numaralı bağımsız bölüm için vadesi geçmiş %s borç bulunmaktadır (toplam açık borç %s). Ödemenizi kasa/banka yoluyla yapabilir, makbuzunuzu sakin alanından görüntüleyebilirsiniz.', $d['door_no'], Money::format((int) $d['overdue']), Money::format((int) $d['total_open']));
                $total += $this->notifyPeople($bid, $personIds, $subject, $body, ['uygulama', 'eposta'], 'debt_reminder', 'unit', (int) $d['unit_id']);
            }
        }
        return $total;
    }

    private function insert(?int $buildingId, ?int $userId, ?int $personId, string $channel, ?string $recipient, string $subject, string $body, string $status, ?string $error, ?string $template, ?string $refType, ?int $refId): int
    {
        return $this->db->insert('notifications', [
            'building_id' => $buildingId, 'user_id' => $userId, 'person_id' => $personId, 'channel' => $channel, 'recipient' => $recipient,
            'subject' => mb_substr($subject, 0, 190), 'body' => $body, 'status' => $status, 'error' => $error, 'template' => $template,
            'ref_type' => $refType, 'ref_id' => $refId, 'sent_at' => $status === 'gonderildi' || $status === 'loglandi' ? Database::now() : null,
            'created_by' => $this->app->auth()->id(), 'created_at' => Database::now(),
        ]);
    }

    public function sendEmail(?int $buildingId, ?int $personId, ?int $userId, string $to, string $subject, string $body, ?string $template = null, ?string $refType = null, ?int $refId = null): bool
    {
        $cfg = $this->app->config()->get('app.mail', []);
        $driver = (string) ($cfg['driver'] ?? 'log');
        $status = 'loglandi';
        $error = null;
        try {
            if ($driver === 'mail') {
                $headers = 'From: ' . $cfg['from_name'] . ' <' . $cfg['from'] . ">\r\nContent-Type: text/plain; charset=UTF-8\r\n";
                $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
                $status = $ok ? 'gonderildi' : 'hata';
                $error = $ok ? null : 'mail() başarısız';
            } elseif ($driver === 'smtp') {
                $ok = $this->smtpSend($cfg, $to, $subject, $body);
                $status = $ok ? 'gonderildi' : 'hata';
                $error = $ok ? null : 'SMTP gönderimi başarısız';
            }
        } catch (Throwable $e) {
            $status = 'hata';
            $error = $e->getMessage();
        }
        $this->insert($buildingId, $userId, $personId, 'eposta', $to, $subject, $body, $status, $error, $template, $refType, $refId);
        return $status !== 'hata';
    }

    public function sendSms(?int $buildingId, ?int $personId, ?int $userId, string $to, string $text, ?string $template = null, ?string $refType = null, ?int $refId = null): bool
    {
        $cfg = $this->app->config()->get('app.sms', []);
        $driver = (string) ($cfg['driver'] ?? 'log');
        $status = 'loglandi';
        $error = null;
        if ($driver === 'webhook' && !empty($cfg['webhook_url'])) {
            try {
                $payload = json_encode(['to' => preg_replace('/\D/', '', $to), 'text' => mb_substr($text, 0, 480)], JSON_UNESCAPED_UNICODE);
                $ctx = stream_context_create(['http' => [
                    'method' => 'POST', 'timeout' => 10,
                    'header' => "Content-Type: application/json\r\n" . (!empty($cfg['webhook_token']) ? 'Authorization: Bearer ' . $cfg['webhook_token'] . "\r\n" : ''),
                    'content' => $payload, 'ignore_errors' => true,
                ]]);
                $res = @file_get_contents((string) $cfg['webhook_url'], false, $ctx);
                $code = 0;
                foreach ($http_response_header ?? [] as $h) {
                    if (preg_match('#HTTP/\S+\s+(\d{3})#', $h, $m)) {
                        $code = (int) $m[1];
                    }
                }
                $status = $res !== false && $code >= 200 && $code < 300 ? 'gonderildi' : 'hata';
                $error = $status === 'hata' ? 'HTTP ' . $code : null;
            } catch (Throwable $e) {
                $status = 'hata';
                $error = $e->getMessage();
            }
        }
        $this->insert($buildingId, $userId, $personId, 'sms', $to, mb_substr($text, 0, 60), $text, $status, $error, $template, $refType, $refId);
        return $status !== 'hata';
    }

    /** Minimal SMTP istemcisi (AUTH LOGIN, STARTTLS/SSL). Harici bağımlılık yok. */
    private function smtpSend(array $cfg, string $to, string $subject, string $body): bool
    {
        $host = (string) ($cfg['smtp_host'] ?? '');
        $port = (int) ($cfg['smtp_port'] ?? 587);
        $secure = (string) ($cfg['smtp_secure'] ?? 'tls');
        if ($host === '') {
            return false;
        }
        $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $fp = @stream_socket_client($remote, $errno, $errstr, 15);
        if (!$fp) {
            throw new \RuntimeException("SMTP bağlantısı kurulamadı: {$errstr}");
        }
        $read = static function () use ($fp): string {
            $data = '';
            while (($line = fgets($fp, 515)) !== false) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $cmd = static function (string $c, array $ok) use ($fp, $read): string {
            fwrite($fp, $c . "\r\n");
            $r = $read();
            if (!in_array((int) substr($r, 0, 3), $ok, true)) {
                throw new \RuntimeException('SMTP: ' . trim($r));
            }
            return $r;
        };
        $read();
        $cmd('EHLO aidat.local', [250]);
        if ($secure === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('STARTTLS başarısız');
            }
            $cmd('EHLO aidat.local', [250]);
        }
        if (!empty($cfg['smtp_user'])) {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode((string) $cfg['smtp_user']), [334]);
            $cmd(base64_encode((string) $cfg['smtp_pass']), [235]);
        }
        $cmd('MAIL FROM:<' . $cfg['from'] . '>', [250]);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', [354]);
        $headers = 'From: =?UTF-8?B?' . base64_encode((string) $cfg['from_name']) . '?= <' . $cfg['from'] . ">\r\nTo: <{$to}>\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\nDate: " . date('r') . "\r\n";
        $cmd($headers . "\r\n" . chunk_split(base64_encode($body)) . "\r\n.", [250]);
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return true;
    }
}
