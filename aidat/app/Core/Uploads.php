<?php

declare(strict_types=1);

namespace Aidat\Core;

use Aidat\Core\Exceptions\DomainException;
use finfo;

/** Özel dosya deposu: storage/uploads altında, web kökü dışında. Erişim yalnızca imzalı/yetkili rota üzerinden. */
final class Uploads
{
    /** @param list<string> $allowedMimes */
    public function __construct(
        private readonly string $dir,
        private readonly int $maxBytes,
        private readonly array $allowedMimes,
    ) {
    }

    /**
     * @param array<string, mixed> $file $_FILES girdisi
     * @return array{path: string, original_name: string, mime: string, size: int}
     */
    public function store(array $file, string $subdir, ?array $allowedMimes = null): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new DomainException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Dosya sunucu sınırından büyük.',
                UPLOAD_ERR_PARTIAL => 'Dosya eksik yüklendi, tekrar deneyin.',
                UPLOAD_ERR_NO_FILE => 'Dosya seçilmedi.',
                default => 'Dosya yüklenemedi.',
            });
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $this->maxBytes) {
            throw new DomainException(sprintf('Dosya boyutu en fazla %d MB olabilir.', intdiv($this->maxBytes, 1024 * 1024)));
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp) && !is_file($tmp)) {
            throw new DomainException('Geçersiz yükleme.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: 'application/octet-stream';
        $allowed = $allowedMimes ?? $this->allowedMimes;
        if (!in_array($mime, $allowed, true)) {
            throw new DomainException('Yalnızca JPG, PNG ve PDF dosyaları yüklenebilir.');
        }
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            'text/csv', 'text/plain' => 'csv',
            default => 'bin',
        };
        $subdir = trim(preg_replace('/[^a-z0-9\/_-]/i', '', $subdir) ?? '', '/');
        $target = $this->dir . '/' . $subdir;
        if (!is_dir($target) && !mkdir($target, 0o770, true) && !is_dir($target)) {
            throw new DomainException('Yükleme dizini oluşturulamadı.');
        }
        $name = uniqid('f', true) . '.' . $ext;
        $name = str_replace('.', '', substr($name, 0, -strlen($ext) - 1)) . '.' . $ext;
        $dest = $target . '/' . $name;
        $moved = is_uploaded_file($tmp) ? move_uploaded_file($tmp, $dest) : copy($tmp, $dest);
        if (!$moved) {
            throw new DomainException('Dosya kaydedilemedi.');
        }
        @chmod($dest, 0o640);
        return [
            'path' => $subdir . '/' . $name,
            'original_name' => mb_substr(basename((string) ($file['name'] ?? $name)), 0, 190),
            'mime' => $mime,
            'size' => $size,
        ];
    }

    public function absolute(string $relative): string
    {
        $clean = str_replace(['..', "\0"], '', $relative);
        return $this->dir . '/' . ltrim($clean, '/');
    }

    public function exists(string $relative): bool
    {
        return is_file($this->absolute($relative));
    }

    public function delete(string $relative): void
    {
        $abs = $this->absolute($relative);
        if (is_file($abs)) {
            @unlink($abs);
        }
    }
}
