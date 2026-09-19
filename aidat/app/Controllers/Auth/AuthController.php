<?php

declare(strict_types=1);

namespace Aidat\Controllers\Auth;

use Aidat\Core\Controller;
use Aidat\Core\Database;
use Aidat\Core\Response;
use Aidat\Services\NotificationService;

final class AuthController extends Controller
{
    protected string $layout = 'layouts.auth';

    public function showLogin(): Response
    {
        $demo = $this->db->fetch("SELECT email FROM users WHERE email LIKE 'demo.%' ORDER BY id LIMIT 1") !== null;
        return $this->view('auth.login', ['title' => 'Giriş', 'demo' => $demo]);
    }

    public function login(): Response
    {
        $d = $this->validate([
            'email' => 'required|email',
            'password' => 'required|min:1',
        ], ['email' => 'e-posta', 'password' => 'şifre'], $this->route('login'));
        $result = $this->app->auth()->attempt((string) $d['email'], (string) $this->request->str('password'), $this->request->bool('remember'), $this->request->ip());
        if (!$result['ok']) {
            $this->app->audit()->log('auth.fail', 'user', null, null, ['email' => $d['email']], null, 'Başarısız giriş');
            $this->error($result['error']);
            $this->app->session()->flash('old', ['email' => $d['email']]);
            return $this->redirectRoute('login');
        }
        $this->app->audit()->log('auth.login', 'user', (int) $result['user']['id'], null, null, null, 'Giriş yapıldı');
        $intended = $this->app->session()->get('intended');
        $this->app->session()->remove('intended');
        if (is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//')) {
            return $this->redirect($intended);
        }
        return $this->redirectRoute('home');
    }

    public function logout(): Response
    {
        $id = $this->app->auth()->id();
        if ($id !== null) {
            $this->app->audit()->log('auth.logout', 'user', $id, null, null, null, 'Çıkış yapıldı');
        }
        $this->app->auth()->logout();
        return $this->redirectRoute('login');
    }

    public function showForgot(): Response
    {
        return $this->view('auth.forgot', ['title' => 'Şifremi unuttum']);
    }

    public function sendReset(): Response
    {
        $d = $this->validate(['email' => 'required|email'], ['email' => 'e-posta'], $this->route('password.forgot'));
        $user = $this->db->fetch('SELECT * FROM users WHERE email = ? AND is_active = 1', [$d['email']]);
        if ($user !== null) {
            $token = bin2hex(random_bytes(32));
            $this->db->delete('password_resets', 'email = ?', [$d['email']]);
            $this->db->insert('password_resets', ['email' => $d['email'], 'token_hash' => hash('sha256', $token), 'expires_at' => date('Y-m-d H:i:s', time() + 3600), 'created_at' => Database::now()]);
            $url = rtrim((string) config('app.url'), '/') . $this->route('password.reset', ['token' => $token]);
            (new NotificationService($this->app))->sendEmail(null, null, (int) $user['id'], (string) $user['email'], 'Şifre sıfırlama bağlantınız', "Merhaba {$user['name']},\n\nŞifrenizi sıfırlamak için bağlantı (1 saat geçerli):\n{$url}\n\nBu isteği siz yapmadıysanız bu iletiyi yok sayın.", 'password_reset');
        }
        // Kullanıcı var/yok bilgisi sızdırılmaz
        $this->success('E-posta adresiniz kayıtlıysa sıfırlama bağlantısı gönderildi. Posta sürücüsü "log" ise bağlantı bildirim kaydında görünür.');
        return $this->redirectRoute('login');
    }

    public function showReset(string $token): Response
    {
        return $this->view('auth.reset', ['title' => 'Yeni şifre', 'token' => $token]);
    }

    public function reset(string $token): Response
    {
        $d = $this->validate(['email' => 'required|email', 'password' => 'required|min:8|max:72|confirmed'], ['email' => 'e-posta', 'password' => 'yeni şifre'], $this->route('password.reset', ['token' => $token]));
        $row = $this->db->fetch('SELECT * FROM password_resets WHERE email = ? AND token_hash = ? AND expires_at > ?', [$d['email'], hash('sha256', $token), Database::now()]);
        if ($row === null) {
            $this->error('Bağlantı geçersiz veya süresi dolmuş.');
            return $this->redirectRoute('password.forgot');
        }
        $this->db->update('users', ['password_hash' => password_hash((string) $d['password'], PASSWORD_DEFAULT), 'must_change_password' => 0, 'updated_at' => Database::now()], 'email = ?', [$d['email']]);
        $this->db->delete('password_resets', 'email = ?', [$d['email']]);
        $this->app->audit()->log('auth.password_reset', 'user', null, null, ['email' => $d['email']], null, 'Şifre sıfırlandı');
        $this->success('Şifreniz güncellendi, giriş yapabilirsiniz.');
        return $this->redirectRoute('login');
    }
}
