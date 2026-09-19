<?php

declare(strict_types=1);

namespace Aidat\Core;

/**
 * Tutarlı form alanları. Eski girdi (old) ve hata mesajları otomatik bağlanır.
 * Örnek: echo Form::text('name', 'Ad', ['required' => true, 'col' => 'c6']);
 */
final class Form
{
    /** @param array<string, mixed> $o */
    public static function text(string $name, string $label, array $o = []): string
    {
        return self::wrap($name, $label, $o, self::input('text', $name, $o));
    }

    public static function email(string $name, string $label, array $o = []): string
    {
        return self::wrap($name, $label, $o, self::input('email', $name, $o));
    }

    public static function password(string $name, string $label, array $o = []): string
    {
        $o['value'] = '';
        $o['no_old'] = true;
        return self::wrap($name, $label, $o, self::input('password', $name, $o));
    }

    public static function number(string $name, string $label, array $o = []): string
    {
        $o['class'] = trim(($o['class'] ?? '') . ' num');
        return self::wrap($name, $label, $o, self::input('number', $name, $o));
    }

    public static function date(string $name, string $label, array $o = []): string
    {
        return self::wrap($name, $label, $o, self::input('date', $name, $o));
    }

    public static function month(string $name, string $label, array $o = []): string
    {
        return self::wrap($name, $label, $o, self::input('month', $name, $o));
    }

    public static function datetime(string $name, string $label, array $o = []): string
    {
        return self::wrap($name, $label, $o, self::input('datetime-local', $name, $o));
    }

    public static function time(string $name, string $label, array $o = []): string
    {
        return self::wrap($name, $label, $o, self::input('time', $name, $o));
    }

    /** Tutar alanı: Türkçe biçim, ₺ eki; value kuruş cinsinden verilir. */
    public static function money(string $name, string $label, array $o = []): string
    {
        if (isset($o['value']) && (is_int($o['value']) || (is_string($o['value']) && ctype_digit(ltrim($o['value'], '-'))) ) && !($o['raw'] ?? false)) {
            $o['value'] = Money::input((int) $o['value']);
        }
        $o['attrs'] = ($o['attrs'] ?? '') . ' data-money inputmode="decimal" autocomplete="off"';
        $o['class'] = trim(($o['class'] ?? '') . ' num');
        $o['placeholder'] ??= '0,00';
        $input = '<div class="input-group">' . self::input('text', $name, $o) . '<span class="addon">₺</span></div>';
        return self::wrap($name, $label, $o, $input);
    }

    public static function textarea(string $name, string $label, array $o = []): string
    {
        $value = self::value($name, $o);
        $attrs = self::attrs($name, $o, 'textarea');
        $html = '<textarea ' . $attrs . '>' . e($value) . '</textarea>';
        return self::wrap($name, $label, $o, $html);
    }

    /** @param array<string|int, string> $options */
    public static function select(string $name, string $label, array $options, array $o = []): string
    {
        $value = self::value($name, $o);
        $multiple = (bool) ($o['multiple'] ?? false);
        $selected = $multiple ? array_map('strval', (array) $value) : [(string) ($value ?? '')];
        $attrs = self::attrs($multiple ? $name . '[]' : $name, $o, 'select') . ($multiple ? ' multiple' : '');
        $html = '<select ' . $attrs . '>';
        if (!$multiple && array_key_exists('placeholder', $o)) {
            $html .= '<option value="">' . e($o['placeholder']) . '</option>';
        }
        foreach ($options as $k => $v) {
            if (is_array($v)) { // optgroup
                $html .= '<optgroup label="' . e($k) . '">';
                foreach ($v as $k2 => $v2) {
                    $html .= '<option value="' . e($k2) . '"' . (in_array((string) $k2, $selected, true) ? ' selected' : '') . '>' . e($v2) . '</option>';
                }
                $html .= '</optgroup>';
                continue;
            }
            $html .= '<option value="' . e($k) . '"' . (in_array((string) $k, $selected, true) ? ' selected' : '') . '>' . e($v) . '</option>';
        }
        $html .= '</select>';
        return self::wrap($name, $label, $o, $html);
    }

    public static function checkbox(string $name, string $label, array $o = []): string
    {
        $checked = (bool) self::value($name, ['value' => $o['checked'] ?? false, 'no_old' => $o['no_old'] ?? false]);
        $old = old($name, null);
        if ($old !== null && !($o['no_old'] ?? false)) {
            $checked = in_array($old, ['1', 1, 'on', true], true);
        }
        $col = $o['col'] ?? '';
        $html = '<div class="field ' . e($col) . '">';
        if (!empty($o['group_label'])) {
            $html .= '<span class="field-label">' . e($o['group_label']) . '</span>';
        }
        $html .= '<label class="check"><input type="hidden" name="' . e($name) . '" value="0"><input type="checkbox" name="' . e($name) . '" value="1"' . ($checked ? ' checked' : '') . ' ' . ($o['attrs'] ?? '') . '><span>' . e($label);
        if (!empty($o['help'])) {
            $html .= '<span class="h">' . e($o['help']) . '</span>';
        }
        $html .= '</span></label>';
        $err = error_for($name);
        if ($err) {
            $html .= '<div class="err"><i class="bi bi-exclamation-circle"></i>' . e($err) . '</div>';
        }
        return $html . '</div>';
    }

    public static function hidden(string $name, mixed $value): string
    {
        return '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
    }

    /** @param array<string, array{0: string, 1?: string}> $options key => [label, help] */
    public static function radioCards(string $name, string $label, array $options, array $o = []): string
    {
        $value = (string) (self::value($name, $o) ?? '');
        $html = '<div class="radio-cards">';
        foreach ($options as $k => $opt) {
            [$t, $h] = array_pad((array) $opt, 2, '');
            $html .= '<label class="radio-card"><input type="radio" name="' . e($name) . '" value="' . e($k) . '"' . ($value === (string) $k ? ' checked' : '') . ' ' . ($o['attrs'] ?? '') . '><span><span class="t">' . e($t) . '</span>' . ($h !== '' ? '<span class="h">' . e($h) . '</span>' : '') . '</span></label>';
        }
        $html .= '</div>';
        return self::wrap($name, $label, $o, $html);
    }

    public static function file(string $name, string $label, array $o = []): string
    {
        $accept = $o['accept'] ?? '.jpg,.jpeg,.png,.pdf';
        $html = '<div class="file-drop"><i class="bi bi-paperclip"></i> ' . e($o['hint'] ?? 'JPG, PNG veya PDF · en fazla 5 MB') . '<input type="file" name="' . e($name) . '" accept="' . e($accept) . '" ' . ($o['attrs'] ?? '') . '></div>';
        return self::wrap($name, $label, $o, $html);
    }

    private static function value(string $name, array $o): mixed
    {
        if (!($o['no_old'] ?? false)) {
            $old = old($name, null);
            if ($old !== null) {
                return $old;
            }
        }
        return $o['value'] ?? null;
    }

    private static function input(string $type, string $name, array $o): string
    {
        $value = self::value($name, $o);
        return '<input type="' . $type . '" ' . self::attrs($name, $o, 'input') . ' value="' . e($value) . '">';
    }

    private static function attrs(string $name, array $o, string $baseClass): string
    {
        $id = $o['id'] ?? 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
        $class = $baseClass . ' ' . ($o['class'] ?? '') . (error_for(rtrim($name, '[]')) ? ' is-invalid' : '');
        $attrs = 'name="' . e($name) . '" id="' . e($id) . '" class="' . e(trim($class)) . '"';
        if (!empty($o['placeholder'])) {
            $attrs .= ' placeholder="' . e($o['placeholder']) . '"';
        }
        if (!empty($o['required'])) {
            $attrs .= ' required';
        }
        if (!empty($o['readonly'])) {
            $attrs .= ' readonly';
        }
        if (!empty($o['disabled'])) {
            $attrs .= ' disabled';
        }
        if (isset($o['min'])) {
            $attrs .= ' min="' . e($o['min']) . '"';
        }
        if (isset($o['max'])) {
            $attrs .= ' max="' . e($o['max']) . '"';
        }
        if (isset($o['step'])) {
            $attrs .= ' step="' . e($o['step']) . '"';
        }
        if (isset($o['maxlength'])) {
            $attrs .= ' maxlength="' . e($o['maxlength']) . '"';
        }
        if (isset($o['rows'])) {
            $attrs .= ' rows="' . e($o['rows']) . '"';
        }
        if (isset($o['autocomplete'])) {
            $attrs .= ' autocomplete="' . e($o['autocomplete']) . '"';
        }
        if (!empty($o['autofocus'])) {
            $attrs .= ' autofocus';
        }
        if (!empty($o['attrs'])) {
            $attrs .= ' ' . $o['attrs'];
        }
        return $attrs;
    }

    private static function wrap(string $name, string $label, array $o, string $control): string
    {
        $col = $o['col'] ?? '';
        $id = $o['id'] ?? 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
        $html = '<div class="field ' . e($col) . '">';
        if ($label !== '') {
            $html .= '<label for="' . e($id) . '">' . e($label) . (!empty($o['required']) ? ' <span class="req" aria-hidden="true">*</span>' : '') . '</label>';
        }
        $html .= $control;
        $err = error_for(rtrim($name, '[]'));
        if ($err) {
            $html .= '<div class="err"><i class="bi bi-exclamation-circle"></i>' . e($err) . '</div>';
        } elseif (!empty($o['help'])) {
            $html .= '<div class="help">' . e($o['help']) . '</div>';
        }
        return $html . '</div>';
    }
}
