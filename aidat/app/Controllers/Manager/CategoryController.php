<?php

declare(strict_types=1);

namespace Aidat\Controllers\Manager;

use Aidat\Core\Controller;
use Aidat\Core\Database;
use Aidat\Core\Exceptions\DomainException;
use Aidat\Core\Exceptions\ValidationException;
use Aidat\Core\Response;

/** Gider ve gelir kategorileri: yapıya özel tanımlar + salt okunur genel (building_id NULL) set. */
final class CategoryController extends Controller
{
    public function index(): Response
    {
        $b = $this->buildingId();
        $rows = $this->db->fetchAll(
            'SELECT c.*, (SELECT COUNT(*) FROM expenses e WHERE e.category_id = c.id AND e.building_id = ?) AS expense_count,
                    (SELECT COUNT(*) FROM recurring_expenses r WHERE r.category_id = c.id AND r.building_id = ?) AS recurring_count,
                    (SELECT COUNT(*) FROM budget_lines l JOIN budgets bu ON bu.id = l.budget_id WHERE l.category_id = c.id AND l.kind = ? AND bu.building_id = ?) AS budget_count
             FROM expense_categories c WHERE c.building_id IS NULL OR c.building_id = ? ORDER BY c.sort_order, c.name',
            [$b, $b, 'gider', $b, $b],
        );
        $tree = [];
        $children = [];
        foreach ($rows as $r) {
            $r['usage'] = (int) $r['expense_count'] + (int) $r['recurring_count'] + (int) $r['budget_count'];
            $r['own'] = $r['building_id'] !== null;
            if ($r['parent_id'] === null) {
                $tree[(int) $r['id']] = $r + ['children' => []];
            } else {
                $children[(int) $r['parent_id']][] = $r;
            }
        }
        foreach ($children as $pid => $list) {
            if (isset($tree[$pid])) {
                $tree[$pid]['children'] = $list;
            } else {
                foreach ($list as $orphan) { // üst kategorisi silinmiş/pasif: kök gibi göster
                    $tree[(int) $orphan['id']] = $orphan + ['children' => []];
                }
            }
        }
        $incomeCats = $this->db->fetchAll(
            'SELECT c.*, (SELECT COUNT(*) FROM incomes i WHERE i.category_id = c.id AND i.building_id = ?) AS income_count,
                    (SELECT COUNT(*) FROM budget_lines l JOIN budgets bu ON bu.id = l.budget_id WHERE l.category_id = c.id AND l.kind = ? AND bu.building_id = ?) AS budget_count
             FROM income_categories c WHERE c.building_id IS NULL OR c.building_id = ? ORDER BY c.sort_order, c.name',
            [$b, 'gelir', $b, $b],
        );
        foreach ($incomeCats as &$ic) {
            $ic['usage'] = (int) $ic['income_count'] + (int) $ic['budget_count'];
            $ic['own'] = $ic['building_id'] !== null;
        }
        unset($ic);
        return $this->view('manager.categories.index', [
            'title' => 'Gider ve gelir kategorileri', 'tree' => $tree, 'incomeCats' => $incomeCats,
            'summary' => ['expense' => count($rows), 'income' => count($incomeCats), 'global' => count(array_filter($rows, static fn (array $r) => $r['building_id'] === null))],
        ]);
    }

    public function store(): Response
    {
        $d = $this->validate(['name' => 'required|max:100', 'kind' => 'required|in:gider,gelir', 'parent_id' => 'nullable|integer'], ['name' => 'kategori adı', 'kind' => 'tür', 'parent_id' => 'üst kategori']);
        $b = $this->buildingId();
        $table = $d['kind'] === 'gelir' ? 'income_categories' : 'expense_categories';
        $parent = null;
        if ($d['kind'] === 'gider' && !empty($d['parent_id'])) {
            $parent = $this->db->fetch('SELECT id, parent_id FROM expense_categories WHERE id = ? AND (building_id IS NULL OR building_id = ?)', [(int) $d['parent_id'], $b]);
            if ($parent === null || $parent['parent_id'] !== null) {
                throw new ValidationException(['parent_id' => 'Üst kategori yalnızca ana (kök) kategori olabilir.'], $this->request->all());
            }
        }
        $dupSql = "SELECT id FROM {$table} WHERE building_id = ? AND name = ?" . ($d['kind'] === 'gider' ? ' AND ' . ($parent ? 'parent_id = ?' : 'parent_id IS NULL') : '');
        $dupParams = $parent ? [$b, $d['name'], (int) $parent['id']] : [$b, $d['name']];
        if ($this->db->fetch($dupSql, $dupParams) !== null) {
            throw new ValidationException(['name' => 'Aynı adda kategori zaten var.'], $this->request->all());
        }
        $data = ['building_id' => $b, 'name' => $d['name'], 'is_active' => 1, 'sort_order' => $this->db->fetchInt("SELECT COALESCE(MAX(sort_order),0)+1 FROM {$table} WHERE building_id = ? OR building_id IS NULL", [$b]), 'created_at' => Database::now(), 'updated_at' => Database::now()];
        if ($d['kind'] === 'gider') {
            $data['parent_id'] = $parent ? (int) $parent['id'] : null;
        }
        $id = $this->db->insert($table, $data);
        $this->audit('category.create', $d['kind'] === 'gelir' ? 'income_category' : 'expense_category', $id, null, $data, ($d['kind'] === 'gelir' ? 'Gelir' : 'Gider') . ' kategorisi eklendi: ' . $d['name']);
        $this->success('Kategori eklendi.');
        return $this->redirectRoute('categories.index');
    }

    public function update(int $id): Response
    {
        $d = $this->validate(['kind' => 'required|in:gider,gelir', 'name' => 'nullable|max:100', 'is_active' => 'nullable|boolean'], ['name' => 'kategori adı', 'kind' => 'tür']);
        $table = $d['kind'] === 'gelir' ? 'income_categories' : 'expense_categories';
        $row = $this->findOwned($table, $id); // genel (building_id NULL) kategoriler salt okunur → 404
        $new = [];
        if (($d['name'] ?? null) !== null && $d['name'] !== '') {
            $new['name'] = $d['name'];
        }
        if (array_key_exists('is_active', $d) && $d['is_active'] !== null) {
            $new['is_active'] = (int) $d['is_active'];
        }
        if ($new === []) {
            throw new DomainException('Değişiklik yok.');
        }
        $this->db->update($table, $new + ['updated_at' => Database::now()], 'id = ?', [$id]);
        if (isset($new['is_active']) && $d['kind'] === 'gider' && $row['parent_id'] === null) {
            $this->db->update('expense_categories', ['is_active' => $new['is_active'], 'updated_at' => Database::now()], 'parent_id = ? AND building_id = ?', [$id, $this->buildingId()]);
        }
        [$o, $n] = \Aidat\Core\Audit::diff($row, $new);
        $this->audit('category.update', $d['kind'] === 'gelir' ? 'income_category' : 'expense_category', $id, $o, $n, 'Kategori güncellendi: ' . ($new['name'] ?? $row['name']));
        $this->success(isset($new['is_active']) && !isset($new['name']) ? ($new['is_active'] ? 'Kategori etkinleştirildi.' : 'Kategori pasife alındı; yeni kayıtlarda listelenmez.') : 'Kategori güncellendi.');
        return $this->redirectRoute('categories.index');
    }

    public function destroy(int $id): Response
    {
        $kind = $this->request->str('kind') === 'gelir' ? 'gelir' : 'gider';
        $table = $kind === 'gelir' ? 'income_categories' : 'expense_categories';
        $row = $this->findOwned($table, $id);
        $b = $this->buildingId();
        if ($kind === 'gider') {
            $used = $this->db->fetchInt('SELECT COUNT(*) FROM expenses WHERE category_id = ?', [$id])
                + $this->db->fetchInt('SELECT COUNT(*) FROM recurring_expenses WHERE category_id = ?', [$id])
                + $this->db->fetchInt("SELECT COUNT(*) FROM budget_lines WHERE category_id = ? AND kind = 'gider'", [$id])
                + $this->db->fetchInt('SELECT COUNT(*) FROM expense_categories WHERE parent_id = ?', [$id]);
        } else {
            $used = $this->db->fetchInt('SELECT COUNT(*) FROM incomes WHERE category_id = ?', [$id])
                + $this->db->fetchInt("SELECT COUNT(*) FROM budget_lines WHERE category_id = ? AND kind = 'gelir'", [$id]);
        }
        if ($used > 0) {
            throw new DomainException('Kullanımda olan kategori silinemez (' . $used . ' bağlı kayıt). Bunun yerine pasife alın.');
        }
        $this->db->delete($table, 'id = ? AND building_id = ?', [$id, $b]);
        $this->audit('category.delete', $kind === 'gelir' ? 'income_category' : 'expense_category', $id, $row, null, 'Kategori silindi: ' . $row['name']);
        $this->success('Kategori silindi.');
        return $this->redirectRoute('categories.index');
    }
}
