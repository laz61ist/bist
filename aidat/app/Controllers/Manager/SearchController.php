<?php

declare(strict_types=1);

namespace Aidat\Controllers\Manager;

use Aidat\Core\Controller;
use Aidat\Core\Response;

/** Komut paleti araması: bölüm, kişi, makbuz, tedarikçi. */
final class SearchController extends Controller
{
    public function index(): Response
    {
        $q = trim($this->request->str('q'));
        $b = $this->buildingId();
        $out = [];
        if (mb_strlen($q) < 2) {
            return $this->json(['sonuclar' => []]);
        }
        $like = '%' . $q . '%';
        if ($this->can('units.view')) {
            foreach ($this->db->fetchAll('SELECT u.id, u.door_no, u.type, bl.name AS block_name FROM units u LEFT JOIN blocks bl ON bl.id = u.block_id WHERE u.building_id = ? AND (u.door_no LIKE ? OR bl.name LIKE ?) ORDER BY u.door_no LIMIT 6', [$b, $like, $like]) as $u) {
                $out[] = ['g' => 'Bağımsız bölümler', 't' => ($u['block_name'] ? $u['block_name'] . ' · ' : '') . 'No ' . $u['door_no'] . ' (' . list_label('unit_types', $u['type']) . ')', 'u' => route('units.show', ['id' => $u['id']]), 'i' => 'door-open'];
            }
        }
        if ($this->can('people.view')) {
            foreach ($this->db->fetchAll("SELECT id, first_name, last_name, company_name, phone FROM people WHERE building_id = ? AND (first_name LIKE ? OR last_name LIKE ? OR company_name LIKE ? OR phone LIKE ? OR (first_name || ' ' || COALESCE(last_name,'')) LIKE ?) LIMIT 6", [$b, $like, $like, $like, $like, $like]) as $p) {
                $out[] = ['g' => 'Kişiler', 't' => $p['company_name'] ?: trim($p['first_name'] . ' ' . $p['last_name']), 'u' => route('people.show', ['id' => $p['id']]), 'i' => 'person', 'k' => (string) $p['phone']];
            }
        }
        if ($this->can('payments.view')) {
            foreach ($this->db->fetchAll('SELECT id, receipt_no, amount, payment_date FROM payments WHERE building_id = ? AND (receipt_no LIKE ? OR reference_no LIKE ?) ORDER BY id DESC LIMIT 5', [$b, $like, $like]) as $p) {
                $out[] = ['g' => 'Makbuzlar', 't' => $p['receipt_no'] . ' · ' . money($p['amount']), 'u' => route('payments.show', ['id' => $p['id']]), 'i' => 'receipt', 'k' => tr_date($p['payment_date'])];
            }
        }
        if ($this->can('expenses.view')) {
            foreach ($this->db->fetchAll('SELECT id, name FROM vendors WHERE building_id = ? AND name LIKE ? LIMIT 4', [$b, $like]) as $v) {
                $out[] = ['g' => 'Tedarikçiler', 't' => $v['name'], 'u' => route('vendors.show', ['id' => $v['id']]), 'i' => 'truck'];
            }
        }
        return $this->json(['sonuclar' => $out]);
    }
}
