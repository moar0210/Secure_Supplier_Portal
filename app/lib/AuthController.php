<?php

declare(strict_types=1);

final class AuthController extends BaseController
{
    public function login(): void
    {
        if ($this->auth->isLoggedIn()) {
            $this->render('view_login', [
                'alreadyLoggedIn' => true,
                'username' => $this->auth->username(),
                'error' => null,
                'identifier' => '',
                'timedOut' => false,
            ], 200, 'Login');
            return;
        }

        $error = null;
        $identifier = '';
        $timedOut = isset($_GET['timeout']) && $_GET['timeout'] === '1';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            $identifier = (string)($_POST['identifier'] ?? '');
            $password = (string)($_POST['password'] ?? '');

            if ($this->auth->attemptLogin($identifier, $password)) {
                $this->redirect('?page=home');
            }

            $error = $this->auth->loginErrorMessage();
        }

        $this->render('view_login', [
            'alreadyLoggedIn' => false,
            'username' => null,
            'error' => $error,
            'identifier' => $identifier,
            'timedOut' => $timedOut,
        ], 200, 'Login');
    }

    public function logout(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('?page=404');
        }

        Csrf::verifyOrFail();
        $this->logActivity('User logged out', [
            'user_id' => $this->auth->userId(),
            'username' => $this->auth->username(),
        ]);
        $this->auth->logout();
        $this->redirect('?page=login');
    }

    public function resetRequest(): void
    {
        $error = null;
        $identifier = '';
        $submitted = false;
        $resetLink = null;
        $resetExpiresAt = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            $identifier = trim((string)($_POST['identifier'] ?? ''));
            $submitted = true;

            try {
                $tokenData = $this->auth->requestPasswordReset($identifier);
                if ($tokenData !== null) {
                    $this->logActivity('Password reset requested', [
                        'username' => (string)$tokenData['username'],
                        'expires_at' => (string)$tokenData['expires_at'],
                    ]);
                    $resetLink = '?page=reset_password&username='
                        . rawurlencode((string)$tokenData['username'])
                        . '&token='
                        . rawurlencode((string)$tokenData['token']);
                    $resetExpiresAt = (string)$tokenData['expires_at'];
                }
            } catch (Throwable $e) {
                $this->logUnexpected($e, 'Password reset request failed');
                $error = 'Unable to create a password reset request right now.';
            }
        }

        $this->render('view_reset_request', [
            'error' => $error,
            'identifier' => $identifier,
            'submitted' => $submitted,
            'resetLink' => $resetLink,
            'resetExpiresAt' => $resetExpiresAt,
        ], 200, 'Reset Password');
    }

    public function resetPassword(): void
    {
        $username = trim((string)($_GET['username'] ?? ''));
        $token = trim((string)($_GET['token'] ?? ''));
        $error = null;
        $success = false;

        $isValidToken = $this->auth->hasValidPasswordResetToken($username, $token);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isValidToken) {
            Csrf::verifyOrFail();
            $newPassword = (string)($_POST['new_password'] ?? '');
            $confirmPassword = (string)($_POST['confirm_password'] ?? '');

            try {
                $this->auth->resetPasswordWithToken(
                    $username,
                    $token,
                    $newPassword,
                    $confirmPassword
                );
                $this->logActivity('Password reset completed', [
                    'username' => $username,
                ]);
                $success = true;
                $isValidToken = false;
            } catch (Throwable $e) {
                $error = $this->presentError($e, 'Unable to update the password right now.');
            }
        }

        $this->render('view_reset_password', [
            'username' => $username,
            'token' => $token,
            'error' => $error,
            'success' => $success,
            'isValidToken' => $isValidToken,
        ], 200, 'Choose New Password');
    }
}
