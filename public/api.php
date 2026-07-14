<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\ProjectController;
use App\Controllers\ReportController;
use App\Controllers\SearchController;
use App\Controllers\TaskController;
use App\Controllers\UserController;

$bootstrap = require dirname(__DIR__) . '/bootstrap.php';
$router = $bootstrap['router'];
$config = $bootstrap['config'];

// CORS headers so frontend can call API without browser tantrums.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$auth = new AuthController($config);
$user = new UserController($config);
$projects = new ProjectController($config);
$tasks = new TaskController($config);
$search = new SearchController($config);
$reports = new ReportController($config);

// Auth routes.
$router->add('POST', '/api/v1/auth/register', static fn () => $auth->register());
$router->add('POST', '/api/v1/auth/verify-email', static fn () => $auth->verifyEmail());
$router->add('POST', '/api/v1/auth/login', static fn () => $auth->login());
$router->add('POST', '/api/v1/auth/password/request-reset', static fn () => $auth->requestPasswordReset());
$router->add('POST', '/api/v1/auth/password/reset', static fn () => $auth->resetPassword());

// Current user profile routes.
$router->add('GET', '/api/v1/users/me', static fn () => $user->me());
$router->add('PATCH', '/api/v1/users/me', static fn () => $user->updateProfile());

// Project and membership routes.
$router->add('GET', '/api/v1/projects', static fn () => $projects->index());
$router->add('POST', '/api/v1/projects', static fn () => $projects->create());
$router->add('PATCH', '/api/v1/projects/{id}', static fn ($params) => $projects->update($params));
$router->add('POST', '/api/v1/projects/{id}/archive', static fn ($params) => $projects->archive($params));
$router->add('POST', '/api/v1/projects/{id}/invite', static fn ($params) => $projects->invite($params));

// Task board routes.
$router->add('GET', '/api/v1/projects/{projectId}/tasks', static fn ($params) => $tasks->listByProject($params));
$router->add('POST', '/api/v1/projects/{projectId}/tasks', static fn ($params) => $tasks->create($params));

$router->add('PATCH', '/api/v1/tasks/{id}', static fn ($params) => $tasks->update($params));
$router->add('PATCH', '/api/v1/tasks/{id}/move', static fn ($params) => $tasks->move($params));
$router->add('DELETE', '/api/v1/tasks/{id}', static fn ($params) => $tasks->delete($params));
$router->add('POST', '/api/v1/tasks/{id}/comments', static fn ($params) => $tasks->addComment($params));
$router->add('GET', '/api/v1/tasks/{id}/comments', static fn ($params) => $tasks->comments($params));
$router->add('GET', '/api/v1/tasks/{id}/activity', static fn ($params) => $tasks->activity($params));

// Search + reporting routes.
$router->add('GET', '/api/v1/search/tasks', static fn () => $search->tasks());

$router->add('GET', '/api/v1/reports/dashboard', static fn () => $reports->dashboard());
$router->add('GET', '/api/v1/reports/overdue.csv', static fn () => $reports->overdueCsv());

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$basePath = '/TaskManagementSystem/public';
$normalized = str_starts_with($path, $basePath) ? substr($path, strlen($basePath)) : $path;

// Strip gateway filename so router only sees clean API paths.
if (str_starts_with($normalized, '/api.php')) {
    $normalized = substr($normalized, strlen('/api.php')) ?: '/';
}

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $normalized);
