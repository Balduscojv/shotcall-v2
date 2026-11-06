<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRoleRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Gestão de papéis — só admin (rota protegida por role:admin). */
class UserRoleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'q' => ['nullable', 'string', 'max:64'],
        ], [
            'role.enum' => 'Papel inválido. Use admin, moderador ou jogador.',
            'q.max' => 'A busca pode ter até 64 caracteres.',
        ]);

        $users = User::query()
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.mb_strtolower($request->string('q')).'%';
                $query->where(fn ($q) => $q->whereRaw('lower(name) like ?', [$term])->orWhere('handle', 'like', $term));
            })
            ->orderBy('id')
            ->paginate(50);

        return UserResource::collection($users);
    }

    public function update(UpdateUserRoleRequest $request, User $user): UserResource
    {
        $user->role = $request->role();
        $user->save();

        return new UserResource($user);
    }
}
