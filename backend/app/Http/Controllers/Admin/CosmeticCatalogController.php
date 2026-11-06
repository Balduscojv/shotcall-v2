<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CosmeticSlot;
use App\Http\Controllers\Controller;
use App\Http\Resources\CosmeticResource;
use App\Models\Cosmetic;
use App\Services\Profile\ProfileImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Catálogo de prêmios na moderação (só admin): criar, editar, ativar/desativar e apagar.
 * Prêmio novo: banner com imagem enviada; moldura e título com uma cor.
 */
class CosmeticCatalogController extends Controller
{
    private const RARITIES = ['COMMON', 'RARE', 'EPIC', 'LEGENDARY'];

    /** Banner até 15 MB: GIF animado no tamanho do cabeçalho pesa. O PHP precisa aceitar isso
     *  (upload_max_filesize e post_max_size acima disso; em dev, 16M e 20M). */
    private const MAX_IMAGE_KB = 15 * 1024;

    private const MESSAGES = [
        'name.required' => 'Dê um nome ao prêmio.',
        'name.max' => 'O nome pode ter até 40 caracteres.',
        'slot.required' => 'Escolha o tipo.',
        'rarity.in' => 'Raridade inválida.',
        'color.regex' => 'Use uma cor no formato #RRGGBB.',
        'color.required_unless' => 'Escolha uma cor.',
        'image.required_if' => 'Envie a imagem do banner.',
        'image.mimes' => 'Use JPG, PNG, WebP ou GIF.',
        'image.max' => 'O arquivo pode ter até 15 MB.',
        'image.uploaded' => 'O arquivo não chegou inteiro ao servidor. Use um arquivo de até 15 MB.',
    ];

    public function __construct(private ProfileImage $images) {}

    /** Todos, inclusive desativados, com quantos usuários têm e em quais missões estão. */
    public function index(): JsonResponse
    {
        $cosmetics = Cosmetic::showcaseOrder()->with('missions')->withCount('owners')->get();

        return response()->json(['data' => $cosmetics->map(fn (Cosmetic $c) => [
            ...(new CosmeticResource($c))->resolve(request()),
            'owners_count' => $c->owners_count,
            'missions' => $c->missions->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])->values(),
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:200'],
            'slot' => ['required', Rule::enum(CosmeticSlot::class)],
            'rarity' => ['required', Rule::in(self::RARITIES)],
            'color' => ['nullable', 'required_unless:slot,BANNER', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'image' => ['required_if:slot,BANNER', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:'.self::MAX_IMAGE_KB],
            'active' => ['boolean'],
        ], self::MESSAGES);

        $cosmetic = new Cosmetic([
            'key' => $this->uniqueKey($data['name']),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'slot' => $data['slot'],
            'rarity' => $data['rarity'],
            'source' => 'ADMIN',
            'trigger' => null,
            'color' => isset($data['color']) ? strtolower($data['color']) : null,
            'active' => $data['active'] ?? true,
        ]);
        if ($request->hasFile('image')) {
            $cosmetic->image_path = $this->storeImage($cosmetic->key, $request->file('image'));
        }
        $cosmetic->save();

        return response()->json(['data' => new CosmeticResource($cosmetic)], 201);
    }

    /** POST (multipart, por causa da imagem): só o que vier muda. O tipo não muda. */
    public function update(Request $request, Cosmetic $cosmetic): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:40'],
            'description' => ['sometimes', 'nullable', 'string', 'max:200'],
            'rarity' => ['sometimes', Rule::in(self::RARITIES)],
            'color' => ['sometimes', 'nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'image' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:'.self::MAX_IMAGE_KB],
            'active' => ['sometimes', 'boolean'],
        ], self::MESSAGES);

        unset($data['image']);
        if (array_key_exists('color', $data) && $data['color'] !== null) {
            $data['color'] = strtolower($data['color']);
        }
        $cosmetic->fill($data);

        if ($request->hasFile('image')) {
            $this->forgetImage($cosmetic);
            $cosmetic->image_path = $this->storeImage($cosmetic->key, $request->file('image'));
        }
        $cosmetic->save();

        return response()->json(['data' => new CosmeticResource($cosmetic->load('missions'))]);
    }

    /** Só apaga quem ninguém tem; senão, o caminho é desativar. */
    public function destroy(Cosmetic $cosmetic): JsonResponse
    {
        if ($cosmetic->owners()->exists()) {
            return response()->json([
                'message' => 'Tem gente com esse prêmio. Desative em vez de apagar: quem tem continua usando.',
            ], 422);
        }

        $this->forgetImage($cosmetic);
        $cosmetic->delete();

        return response()->json(['message' => "{$cosmetic->name} apagado."]);
    }

    private function uniqueKey(string $name): string
    {
        $base = Str::slug($name) ?: 'premio';
        $key = $base;
        for ($n = 2; Cosmetic::where('key', $key)->exists(); $n++) {
            $key = "{$base}-{$n}";
        }

        return $key;
    }

    /**
     * GIF fica como veio, para não perder a animação (o banner usa object-fit: cover, então o
     * recorte acontece na tela). O resto é recortado e salvo em WebP, como o banner do perfil.
     */
    private function storeImage(string $key, UploadedFile $file): string
    {
        $name = "cosmetics/{$key}-".Str::random(8);

        if ($file->getMimeType() === 'image/gif') {
            Storage::disk('public')->putFileAs('', $file, "{$name}.gif");

            return "/storage/{$name}.gif";
        }

        Storage::disk('public')->put("{$name}.webp", $this->images->webp($file, ProfileImage::BANNER));

        return "/storage/{$name}.webp";
    }

    private function forgetImage(Cosmetic $cosmetic): void
    {
        if ($cosmetic->image_path && str_starts_with($cosmetic->image_path, '/storage/')) {
            Storage::disk('public')->delete(Str::after($cosmetic->image_path, '/storage/'));
        }
    }
}
