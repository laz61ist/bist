<?php

declare(strict_types=1);

namespace Aidat\Controllers\Shared;

use Aidat\Core\Controller;
use Aidat\Core\Database;
use Aidat\Core\Response;

final class ProfileController extends Controller
{
    public function edit(): Response
    {
        $user = $this->user();
        $memberships = $this->app->gate()->memberships();
        $units = $this->db->fetchAll(
            'SELECT u.door_no, b.name AS building_name, o.role, bl.name AS block_name FROM occupancies o JOIN people p ON p.id = o.person_id JOIN units u ON u.id = o.unit_id JOIN buildings b ON b.id = u.building_id LEFT JOIN blocks bl ON bl.id = u.block_id WHERE p.user_id = ? AND (o.end_date IS NULL OR o.end_date >= ?)',
            [(int) $user['id'], date('Y-m-d')],
        );
        $this->layout = $this->app->gate()->hasManagementAccess() ? 'layouts.app' : 'layouts.app';
        return $this->view('shared.profile', ['title' => 'Profilim', 'user' => $user, 'memberships' => $memberships, 'units' => $units, 'area' => $this->app->gate()->hasManagementAccess() && $this->hasBuilding() ? 'manager' : 'portal']);
    }

    public function update(): Response
    {
        $user = $this->user();
        $d = $this->validate([
            'name' => 'required|string|min:2|max:120',
            'email' => 'required|email|max:190|unique:users,email,' . (int) $user['id'],
            'phone' => 'nullable|phone|max:30',
        ], ['name' => 'ad soyad', 'email' => 'e-posta', 'phone' => 'telefon']);
        $this->db->update('users', ['name' => $d['name'], 'email' => $d['email'], 'phone' => $d['phone'], 'updated_at' => Database::now()], 'id = ?', [(int) $user['id']]);
        $this->app->auth()->forgetUser();
        $this->audit('profile.update', 'user', (int) $user['id'], ['name' => $user['name'], 'email' => $user['email']], ['name' => $d['name'], 'email' => $d['email']], 'Profil güncellendi');
        $this->success('Profil bilgileriniz güncellendi.');
        return $this->redirectRoute('profile.edit');
    }

    public function password(): Response
    {
        $user = $this->user();
        $d = $this->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|max:72|confirmed',
        ], ['current_password' => 'mevcut şifre', 'password' => 'yeni şifre']);
        if (!password_verify((string) $d['current_password'], (string) $user['password_hash'])) {
            $this->error('Mevcut şifre hatalı.');
            return $this->redirectRoute('profile.edit');
        }
        $this->db->update('users', ['password_hash' => password_hash((string) $d['password'], PASSWORD_DEFAULT), 'must_change_password' => 0, 'updated_at' => Database::now()], 'id = ?', [(int) $user['id']]);
        $this->db->delete('user_tokens', 'user_id = ?', [(int) $user['id']]);
        $this->audit('profile.password', 'user', (int) $user['id'], null, null, 'Şifre değiştirildi');
        $this->success('Şifreniz değiştirildi.');
        return $this->redirectRoute('profile.edit');
    }
}
