<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileImageRequest;
use App\Http\Resources\UserResource;
use App\Services\Profile\ProfileImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Troca e remoção da foto de perfil e do banner de quem está logado. */
class ProfileMediaController extends Controller
{
    public function __construct(private ProfileImage $images) {}

    public function update(UpdateProfileImageRequest $request, string $kind): JsonResponse
    {
        $user = $this->images->store($request->user(), $kind, $request->file('image'));

        // Banner enviado agora passa na frente do banner-prêmio: vale a última escolha.
        if ($kind === 'banner' && $user->equipped_banner_id !== null) {
            $user->forceFill(['equipped_banner_id' => null])->save();
        }

        return (new UserResource($user))->response()->setStatusCode(200);
    }

    public function destroy(Request $request, string $kind): JsonResponse
    {
        $user = $this->images->remove($request->user(), $kind);

        return (new UserResource($user))->response()->setStatusCode(200);
    }
}
