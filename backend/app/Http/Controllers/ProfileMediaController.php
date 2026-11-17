<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileImageRequest;
use App\Http\Resources\UserResource;
use App\Services\Profile\ProfileImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Troca e remoção da foto de perfil de quem está logado. Banner é só o do jogo (prêmio equipado):
 * não dá mais para enviar; a remoção continua para limpar algum enviado antes da regra.
 */
class ProfileMediaController extends Controller
{
    public function __construct(private ProfileImage $images) {}

    public function update(UpdateProfileImageRequest $request, string $kind): JsonResponse
    {
        $user = $this->images->store($request->user(), $kind, $request->file('image'));

        return (new UserResource($user))->response()->setStatusCode(200);
    }

    public function destroy(Request $request, string $kind): JsonResponse
    {
        $user = $this->images->remove($request->user(), $kind);

        return (new UserResource($user))->response()->setStatusCode(200);
    }
}
