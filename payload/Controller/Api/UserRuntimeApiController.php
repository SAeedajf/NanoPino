<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Controller\Api;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationDeniedException;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use App\com_pinoox_cms\Cms\Identity\PinooxIdentityRepository;
use App\com_pinoox_cms\Cms\Runtime\CmsApiResponse;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeServices;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeErrorReporter;
use Pinoox\Component\Http\JsonResponse;
use Pinoox\Component\Http\Request;
use Pinoox\Component\Kernel\Controller\ApiController;

final class UserRuntimeApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        try {
            CmsRuntimeServices::authorization()->authorize(new AuthorizationRequest('users.read', CmsRuntimeServices::actorId()));
            $query = trim((string)$request->query->get('q', ''));
            if (mb_strlen($query) > 190) {
                return CmsApiResponse::error('USER_QUERY_INVALID', 'Search query is too long.', 422);
            }
            $status = trim((string)$request->query->get('status', ''));
            if ($status !== '' && !in_array($status, ['active', 'inactive', 'suspend', 'pending'], true)) {
                return CmsApiResponse::error('USER_STATUS_INVALID', 'Invalid user status filter.', 422);
            }
            $role = trim((string)$request->query->get('role', ''));
            if ($role !== '' && preg_match('/^[a-z][a-z0-9._-]{0,126}$/', $role) !== 1) {
                return CmsApiResponse::error('USER_ROLE_INVALID', 'Invalid role filter.', 422);
            }
            $limit = max(1, min(100, (int)$request->query->get('limit', 50)));
            $offset = max(0, (int)$request->query->get('offset', 0));

            $repo = new PinooxIdentityRepository();
            $items = $repo->users($limit, $offset, $query !== '' ? $query : null, $status !== '' ? $status : null, $role !== '' ? $role : null);
            $total = $repo->count($query !== '' ? $query : null, $status !== '' ? $status : null, $role !== '' ? $role : null);
            $kernel = CmsRuntimeServices::kernel();
            $capabilities = array_map(static fn ($definition): array => [
                'key' => $definition->identifier(),
                'owner' => $definition->owner(),
                'description' => $definition->description,
            ], $kernel->capabilities->definitions());
            $roleTemplates = array_map(static fn ($definition): array => [
                'key' => $definition->identifier(),
                'owner' => $definition->owner(),
                'name' => $definition->name,
                'description' => $definition->description,
                'capabilities' => $definition->capabilities,
            ], $kernel->roleTemplates->definitions());

            return CmsApiResponse::ok([
                'items' => $items,
                'roles' => $repo->roles(),
                'role_templates' => $roleTemplates,
                'capabilities' => $capabilities,
                'summary' => $repo->summary(),
                'current' => $repo->currentUser(),
                'statuses' => ['active', 'inactive', 'suspend', 'pending'],
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'returned' => count($items),
                    'total' => $total,
                    'has_more' => $offset + count($items) < $total,
                ],
            ]);
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'User access is not permitted.', 403);
        } catch (\Throwable $e) {
            return CmsRuntimeErrorReporter::response(
                $e,
                'USER_LIST_FAILED',
                'Users could not be loaded.',
                500,
                ['operation' => 'users.list'],
            );
        }
    }

    public function create(Request $request): JsonResponse
    {
        try {
            $data = $this->requestPayload($request);
            $username = trim((string)($data['username'] ?? ''));
            $password = (string)($data['password'] ?? '');
            if ($username === '' || mb_strlen($username) > 190 || mb_strlen($password) < 8) {
                return CmsApiResponse::error('USER_VALIDATION_FAILED', 'Username and a password of at least 8 characters are required.', 422);
            }
            $id = CmsRuntimeServices::userAdministration()->create(CmsRuntimeServices::actorId(), $data);
            return CmsApiResponse::ok(['id' => $id], 201);
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'User creation is not permitted.', 403);
        } catch (\Throwable $e) {
            return $this->mutationError($e, 'USER_CREATE_FAILED', 'User could not be created.');
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $data = $this->requestPayload($request);
            unset($data['password'], $data['status'], $data['roles'], $data['abilities']);
            $ok = CmsRuntimeServices::userAdministration()->update(CmsRuntimeServices::actorId(), $this->id($id), $data);
            return CmsApiResponse::ok(['updated' => $ok]);
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'User update is not permitted.', 403);
        } catch (\Throwable $e) {
            return $this->mutationError($e, 'USER_UPDATE_FAILED', 'User could not be updated.');
        }
    }

    public function status(Request $request, string $id): JsonResponse
    {
        try {
            $status = trim((string)($this->requestPayload($request)['status'] ?? ''));
            if (!in_array($status, ['active', 'inactive', 'suspend', 'pending'], true)) {
                return CmsApiResponse::error('USER_STATUS_INVALID', 'Invalid user status.', 422);
            }
            $ok = CmsRuntimeServices::userAdministration()->setStatus(CmsRuntimeServices::actorId(), $this->id($id), $status);
            return CmsApiResponse::ok(['updated' => $ok, 'status' => $status]);
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'User status update is not permitted.', 403);
        } catch (\Throwable $e) {
            return $this->mutationError($e, 'USER_STATUS_FAILED', 'User status could not be changed.');
        }
    }

    public function assignRole(string $id, string $role): JsonResponse
    {
        try {
            $role = $this->role($role);
            $ok = CmsRuntimeServices::userAdministration()->assignRole(CmsRuntimeServices::actorId(), $this->id($id), $role);
            return CmsApiResponse::ok(['updated' => $ok, 'role' => $role]);
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'Role assignment is not permitted.', 403);
        } catch (\Throwable $e) {
            return $this->mutationError($e, 'USER_ROLE_ASSIGN_FAILED', 'Role could not be assigned.');
        }
    }

    public function detachRole(string $id, string $role): JsonResponse
    {
        try {
            $role = $this->role($role);
            $ok = CmsRuntimeServices::userAdministration()->detachRole(CmsRuntimeServices::actorId(), $this->id($id), $role);
            return CmsApiResponse::ok(['updated' => $ok, 'role' => $role]);
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'Role removal is not permitted.', 403);
        } catch (\Throwable $e) {
            return $this->mutationError($e, 'USER_ROLE_DETACH_FAILED', 'Role could not be removed.');
        }
    }

    public function revokeSessions(string $id): JsonResponse
    {
        try {
            $count = CmsRuntimeServices::userAdministration()->revokeSessions(CmsRuntimeServices::actorId(), $this->id($id));
            return CmsApiResponse::ok(['revoked' => $count]);
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'Session revocation is not permitted.', 403);
        } catch (\Throwable $e) {
            return $this->mutationError($e, 'USER_SESSION_REVOKE_FAILED', 'User sessions could not be revoked.');
        }
    }

    public function delete(string $id): JsonResponse
    {
        try {
            $ok = CmsRuntimeServices::userAdministration()->delete(CmsRuntimeServices::actorId(), $this->id($id));
            return CmsApiResponse::ok(['deleted' => $ok]);
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'User deletion is not permitted.', 403);
        } catch (\Throwable $e) {
            return $this->mutationError($e, 'USER_DELETE_FAILED', 'User could not be deleted.');
        }
    }

    private function id(string $value): int
    {
        if (preg_match('/^[1-9]\\d*$/', $value) !== 1) {
            throw new \InvalidArgumentException('Invalid user id.');
        }
        return (int)$value;
    }

    private function role(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^[a-z][a-z0-9._-]{0,126}$/', $value) !== 1) {
            throw new \InvalidArgumentException('Invalid role key.');
        }
        return $value;
    }

    /** @return array<string,mixed> */
    private function requestPayload(Request $request): array
    {
        try { $data = $request->toArray(); } catch (\Throwable) { $data = []; }
        return is_array($data) ? $data : [];
    }

    private function mutationError(\Throwable $e, string $code, string $message): JsonResponse
    {
        if ($e instanceof \InvalidArgumentException) {
            return CmsApiResponse::error($code, $e->getMessage(), 422);
        }
        return CmsRuntimeErrorReporter::response(
            $e,
            $code,
            $message,
            500,
            ['operation' => 'users.mutation'],
        );
    }
}
