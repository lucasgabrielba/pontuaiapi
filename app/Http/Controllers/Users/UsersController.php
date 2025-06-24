<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\ChangePasswordRequest;
use App\Http\Requests\Users\CreateUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use Domains\Users\Services\UsersService;
use Illuminate\Http\Request;

class UsersController extends Controller
{
    protected UsersService $usersService;

    public function __construct(UsersService $usersService)
    {
        $this->usersService = $usersService;
    }

    public function index(Request $request)
    {
        $filters = $request->all();

        $users = $this->usersService->list($filters);

        return response()->json($users);
    }

    public function store(CreateUserRequest $request)
    {
        $data = $request->validated();
        $user = $this->usersService->create($data);

        return response()->json($user, 201);
    }

    public function show(string $userId)
    {
        $user = $this->usersService->get($userId);

        return response()->json($user);
    }

    public function update(UpdateUserRequest $request, string $userId)
    {
        $data = $request->validated();
        $response = $this->usersService->update($userId, $data);

        return response()->json($response, 200);
    }

    public function destroy(string $userId)
    {
        $this->usersService->destroy($userId);

        return response()->json([
            'message' => 'Usuário deletado com sucesso',
        ], 204);
    }

    public function changePassword(ChangePasswordRequest $request, string $userId)
    {
        $data = $request->validated();
        $this->usersService->changePassword($userId, $data);

        return response()->json([
            'message' => 'Senha alterada com sucesso',
        ]);
    }
}
