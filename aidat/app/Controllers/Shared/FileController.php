<?php

declare(strict_types=1);

namespace Aidat\Controllers\Shared;

use Aidat\Core\Controller;
use Aidat\Core\Exceptions\HttpException;
use Aidat\Core\Response;

/** Özel dosyalar: yalnızca yetkili kullanıcı veya süreli imzalı bağlantı ile. */
final class FileController extends Controller
{
    public function document(int $id): Response
    {
        $doc = $this->db->fetch('SELECT * FROM documents WHERE id = ?', [$id]) ?? throw new HttpException(404, 'Belge bulunamadı.');
        $buildingId = (int) $doc['building_id'];
        $gate = $this->app->gate();
        $allowed = $gate->allows('documents.view', $buildingId);
        if (!$allowed) {
            // Sakin: yapıda aktif oturumu olmalı ve belge görünürlüğü uygun olmalı
            $role = $this->db->fetchColumn(
                'SELECT o.role FROM occupancies o JOIN people p ON p.id = o.person_id WHERE p.user_id = ? AND o.building_id = ? AND (o.end_date IS NULL OR o.end_date >= ?) ORDER BY CASE o.role WHEN \'malik\' THEN 0 ELSE 1 END LIMIT 1',
                [$this->userId(), $buildingId, date('Y-m-d')],
            );
            $vis = (string) $doc['visibility'];
            $allowed = $role !== null && ($vis === 'sakinler' || ($vis === 'malikler' && $role === 'malik'));
            if (!$allowed && $doc['entity_type'] === 'expense' && $role !== null) {
                $allowed = $this->app->settings()->bool($buildingId, 'portal_show_expense_documents');
            }
        }
        if (!$allowed) {
            throw new HttpException(403, 'Bu belgeye erişim yetkiniz yok.');
        }
        return $this->serve((string) $doc['file_path'], (string) $doc['original_name'], (string) $doc['mime']);
    }

    /** İmzalı süreli bağlantı (paylaşım için). */
    public function signed(string $token): Response
    {
        $payload = $this->app->signer()->verify($token);
        if ($payload === null || !str_starts_with($payload, 'doc:')) {
            throw new HttpException(403, 'Bağlantı geçersiz veya süresi dolmuş.');
        }
        $id = (int) substr($payload, 4);
        $doc = $this->db->fetch('SELECT * FROM documents WHERE id = ?', [$id]) ?? throw new HttpException(404, 'Belge bulunamadı.');
        return $this->serve((string) $doc['file_path'], (string) $doc['original_name'], (string) $doc['mime']);
    }

    private function serve(string $relative, string $name, string $mime): Response
    {
        $uploads = $this->app->uploads();
        if (!$uploads->exists($relative)) {
            throw new HttpException(404, 'Dosya diskte bulunamadı.');
        }
        $inline = in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true);
        return Response::file($uploads->absolute($relative), $name, $mime, $inline);
    }
}
