<?php

declare(strict_types=1);

namespace Aidat\Core;

use Aidat\Core\Exceptions\ValidationException;
use InvalidArgumentException;

/**
 * Form doğrulayıcı. Kurallar "required|max:120|email" biçiminde.
 * Hata mesajları Türkçe; :alan etiketi labels ile değiştirilir.
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];
    /** @var array<string, mixed> */
    private array $validated = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     * @param array<string, string> $labels
     */
    public function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $labels = [],
        private readonly ?Database $db = null,
    ) {
    }

    /** @param array<string, mixed> $data @param array<string, string|list<string>> $rules @param array<string, string> $labels */
    public static function make(array $data, array $rules, array $labels = [], ?Database $db = null): self
    {
        $v = new self($data, $rules, $labels, $db);
        $v->run();
        return $v;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string, mixed> */
    public function validated(): array
    {
        return $this->validated;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    /** @return array<string, mixed> */
    public function validate(?string $redirectTo = null): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors, $this->data, $redirectTo);
        }
        return $this->validated;
    }

    private function label(string $field): string
    {
        return $this->labels[$field] ?? str_replace('_', ' ', $field);
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);
            $value = $this->data[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }
            $isEmpty = $value === null || $value === '' || $value === [];
            $nullable = in_array('nullable', $rules, true);
            $required = in_array('required', $rules, true);

            if ($isEmpty) {
                if ($required) {
                    $this->addError($field, sprintf('%s alanı zorunludur.', ucfirst($this->label($field))));
                    continue;
                }
                $this->validated[$field] = $value === '' || $value === [] ? null : $value;
                if ($nullable || !$required) {
                    continue;
                }
            }

            foreach ($rules as $rule) {
                if ($rule === '' || $rule === 'required' || $rule === 'nullable') {
                    continue;
                }
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $value = $this->apply($field, $name, $arg, $value);
                if (isset($this->errors[$field])) {
                    break;
                }
            }
            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }
    }

    private function apply(string $field, string $rule, ?string $arg, mixed $value): mixed
    {
        $label = ucfirst($this->label($field));
        switch ($rule) {
            case 'string':
                if (!is_scalar($value)) {
                    $this->addError($field, "{$label} metin olmalıdır.");
                }
                return is_scalar($value) ? (string) $value : $value;
            case 'email':
                if (!filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "{$label} geçerli bir e-posta adresi olmalıdır.");
                }
                return mb_strtolower((string) $value);
            case 'min':
                $min = (int) $arg;
                if (is_numeric($value) && !is_string($value)) {
                    if ($value < $min) {
                        $this->addError($field, "{$label} en az {$min} olmalıdır.");
                    }
                } elseif (mb_strlen((string) $value) < $min) {
                    $this->addError($field, "{$label} en az {$min} karakter olmalıdır.");
                }
                return $value;
            case 'max':
                $max = (int) $arg;
                if (is_int($value) || is_float($value)) {
                    if ($value > $max) {
                        $this->addError($field, "{$label} en fazla {$max} olabilir.");
                    }
                } elseif (mb_strlen((string) $value) > $max) {
                    $this->addError($field, "{$label} en fazla {$max} karakter olabilir.");
                }
                return $value;
            case 'integer':
                if (!preg_match('/^-?\d+$/', (string) $value)) {
                    $this->addError($field, "{$label} tam sayı olmalıdır.");
                    return $value;
                }
                return (int) $value;
            case 'numeric':
                $norm = str_replace(',', '.', (string) $value);
                if (!is_numeric($norm)) {
                    $this->addError($field, "{$label} sayısal olmalıdır.");
                    return $value;
                }
                return (float) $norm;
            case 'money':
                try {
                    return Money::parse($value);
                } catch (InvalidArgumentException) {
                    $this->addError($field, "{$label} geçerli bir tutar olmalıdır (örn. 1.250,50).");
                    return $value;
                }
            case 'money_positive':
                try {
                    $k = Money::parse($value);
                } catch (InvalidArgumentException) {
                    $this->addError($field, "{$label} geçerli bir tutar olmalıdır.");
                    return $value;
                }
                if ($k <= 0) {
                    $this->addError($field, "{$label} sıfırdan büyük olmalıdır.");
                }
                return $k;
            case 'gte':
                $other = $this->data[$arg] ?? null;
                if (is_numeric($value) && is_numeric($other) && (float) $value < (float) $other) {
                    $this->addError($field, "{$label}, {$this->label((string) $arg)} değerinden küçük olamaz.");
                }
                return $value;
            case 'date':
                $parsed = Dates::parse((string) $value);
                if ($parsed === null) {
                    $this->addError($field, "{$label} geçerli bir tarih olmalıdır (GG.AA.YYYY).");
                    return $value;
                }
                return $parsed;
            case 'period':
                if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $value)) {
                    $this->addError($field, "{$label} YYYY-AA biçiminde bir dönem olmalıdır.");
                }
                return $value;
            case 'after_or_equal':
                $other = Dates::parse((string) ($this->data[$arg] ?? ''));
                $self = Dates::parse((string) $value);
                if ($other !== null && $self !== null && $self < $other) {
                    $this->addError($field, "{$label}, {$this->label((string) $arg)} tarihinden önce olamaz.");
                }
                return $self ?? $value;
            case 'in':
                $options = explode(',', (string) $arg);
                if (!in_array((string) $value, $options, true)) {
                    $this->addError($field, "{$label} için geçersiz seçim.");
                }
                return $value;
            case 'in_keys':
                // config listeleri: in_keys:lists.unit_types
                $list = app()->config()->get((string) $arg, []);
                if (!is_array($list) || !array_key_exists((string) $value, $list)) {
                    $this->addError($field, "{$label} için geçersiz seçim.");
                }
                return $value;
            case 'boolean':
                return in_array($value, [1, '1', true, 'true', 'on', 'evet'], true) ? 1 : 0;
            case 'confirmed':
                if (($this->data[$field . '_confirmation'] ?? null) !== $value) {
                    $this->addError($field, "{$label} tekrarı eşleşmiyor.");
                }
                return $value;
            case 'regex':
                if (!preg_match((string) $arg, (string) $value)) {
                    $this->addError($field, "{$label} biçimi geçersiz.");
                }
                return $value;
            case 'phone':
                $digits = preg_replace('/\D/', '', (string) $value) ?? '';
                if (strlen($digits) < 10 || strlen($digits) > 13) {
                    $this->addError($field, "{$label} geçerli bir telefon numarası olmalıdır.");
                }
                return $value;
            case 'iban':
                if (!Str::validIban((string) $value)) {
                    $this->addError($field, "{$label} geçerli bir IBAN olmalıdır.");
                }
                return strtoupper(str_replace(' ', '', (string) $value));
            case 'tckn':
                if (!Str::validTckn((string) $value)) {
                    $this->addError($field, "{$label} geçerli bir T.C. kimlik numarası olmalıdır.");
                }
                return $value;
            case 'tckn_or_vkn':
                $v = (string) $value;
                if (!(Str::validTckn($v) || preg_match('/^\d{10}$/', $v))) {
                    $this->addError($field, "{$label} geçerli bir TCKN (11 hane) veya VKN (10 hane) olmalıdır.");
                }
                return $value;
            case 'url':
                if (!filter_var((string) $value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, "{$label} geçerli bir bağlantı olmalıdır.");
                }
                return $value;
            case 'array':
                if (!is_array($value)) {
                    $this->addError($field, "{$label} liste olmalıdır.");
                }
                return $value;
            case 'unique':
                // unique:table,column,ignoreId[,scopeColumn=scopeValue]
                if ($this->db === null) {
                    return $value;
                }
                $parts = explode(',', (string) $arg);
                $table = $parts[0];
                $column = $parts[1] ?? $field;
                $ignore = isset($parts[2]) && $parts[2] !== '' ? (int) $parts[2] : null;
                $sql = "SELECT id FROM `{$table}` WHERE `{$column}` = ?";
                $params = [$value];
                if ($ignore !== null) {
                    $sql .= ' AND id <> ?';
                    $params[] = $ignore;
                }
                if (isset($parts[3]) && str_contains($parts[3], '=')) {
                    [$sc, $sv] = explode('=', $parts[3], 2);
                    $sql .= " AND `{$sc}` = ?";
                    $params[] = $sv;
                }
                if ($this->db->fetch($sql, $params) !== null) {
                    $this->addError($field, "{$label} zaten kayıtlı.");
                }
                return $value;
            case 'exists':
                if ($this->db === null) {
                    return $value;
                }
                $parts = explode(',', (string) $arg);
                $table = $parts[0];
                $column = $parts[1] ?? 'id';
                $sql = "SELECT 1 FROM `{$table}` WHERE `{$column}` = ?";
                $params = [$value];
                if (isset($parts[2]) && str_contains($parts[2], '=')) {
                    [$sc, $sv] = explode('=', $parts[2], 2);
                    $sql .= " AND `{$sc}` = ?";
                    $params[] = $sv;
                }
                if ($this->db->fetch($sql, $params) === null) {
                    $this->addError($field, "Seçilen {$this->label($field)} bulunamadı.");
                }
                return is_numeric($value) && $column === 'id' ? (int) $value : $value;
            default:
                return $value;
        }
    }
}
