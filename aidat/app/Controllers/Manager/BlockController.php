<?php

declare(strict_types=1);

namespace Aidat\Controllers\Manager;

use Aidat\Core\Controller;
use Aidat\Core\Database;
use Aidat\Core\Exceptions\DomainException;
use Aidat\Core\Response;

final class BlockController extends Controller
{
    public function index(): Response
    {
        $b = $this->buildingId();
        $blocks = $this->db->fetchAll('SELECT bl.*, (SELECT COUNT(*) FROM units u WHERE u.block_id = bl.id) AS unit_count FROM blocks bl WHERE bl.building_id = ? ORDER BY bl.sort_order, bl.name', [$b]);
        $groups = $this->db->fetchAll('SELECT g.*, (SELECT COUNT(*) FROM units u WHERE u.fee_group_id = g.id) AS unit_count FROM fee_groups g WHERE g.building_id = ? ORDER BY g.name', [$b]);
        $noBlock = $this->db->fetchInt('SELECT COUNT(*) FROM units WHERE building_id = ? AND block_id IS NULL', [$b]);
        return $this->view('manager.blocks.index', ['title' => 'Bloklar ve aidat grupları', 'blocks' => $blocks, 'groups' => $groups, 'noBlock' => $noBlock]);
    }

    public function store(): Response
    {
        $d = $this->validate(['name' => 'required|max:60', 'code' => 'nullable|max:20', 'floors' => 'nullable|integer|min:0|max:200', 'description' => 'nullable|max:500'], ['name' => 'blok adı', 'code' => 'kod', 'floors' => 'kat sayısı']);
        $id = $this->db->insert('blocks', $d + ['building_id' => $this->buildingId(), 'sort_order' => $this->db->fetchInt('SELECT COALESCE(MAX(sort_order),0)+1 FROM blocks WHERE building_id = ?', [$this->buildingId()]), 'created_at' => Database::now(), 'updated_at' => Database::now()]);
        $this->audit('block.create', 'block', $id, null, $d, 'Blok eklendi: ' . $d['name']);
        $this->success('Blok eklendi.');
        return $this->redirectRoute('blocks.index');
    }

    public function update(int $id): Response
    {
        $row = $this->findOwned('blocks', $id);
        $d = $this->validate(['name' => 'required|max:60', 'code' => 'nullable|max:20', 'floors' => 'nullable|integer|min:0|max:200', 'description' => 'nullable|max:500'], ['name' => 'blok adı']);
        $this->db->update('blocks', $d + ['updated_at' => Database::now()], 'id = ?', [$id]);
        $this->audit('block.update', 'block', $id, $row, $d, 'Blok güncellendi');
        $this->success('Blok güncellendi.');
        return $this->redirectRoute('blocks.index');
    }

    public function destroy(int $id): Response
    {
        $row = $this->findOwned('blocks', $id);
        if ($this->db->fetchInt('SELECT COUNT(*) FROM units WHERE block_id = ?', [$id]) > 0) {
            throw new DomainException('İçinde bağımsız bölüm olan blok silinemez. Önce bölümleri başka bloğa taşıyın.');
        }
        $this->db->delete('blocks', 'id = ?', [$id]);
        $this->audit('block.delete', 'block', $id, $row, null, 'Blok silindi: ' . $row['name']);
        $this->success('Blok silindi.');
        return $this->redirectRoute('blocks.index');
    }

    public function storeGroup(): Response
    {
        $d = $this->validate(['name' => 'required|max:80', 'description' => 'nullable|max:500'], ['name' => 'grup adı']);
        $id = $this->db->insert('fee_groups', $d + ['building_id' => $this->buildingId(), 'created_at' => Database::now(), 'updated_at' => Database::now()]);
        $this->audit('fee_group.create', 'fee_group', $id, null, $d, 'Aidat grubu eklendi: ' . $d['name']);
        $this->success('Aidat grubu eklendi.');
        return $this->redirectRoute('blocks.index');
    }

    public function destroyGroup(int $id): Response
    {
        $row = $this->findOwned('fee_groups', $id);
        $this->db->update('units', ['fee_group_id' => null], 'fee_group_id = ?', [$id]);
        $this->db->delete('fee_groups', 'id = ?', [$id]);
        $this->audit('fee_group.delete', 'fee_group', $id, $row, null, 'Aidat grubu silindi');
        $this->success('Aidat grubu silindi; bölümlerin grup bağı kaldırıldı.');
        return $this->redirectRoute('blocks.index');
    }
}
