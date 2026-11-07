<?php

namespace App\Services\Profile;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Foto de perfil e banner: recorta, reduz e salva em WebP no disco público.
 * O banco guarda o caminho relativo (/storage/...), servido pelo Next via rewrite.
 */
class ProfileImage
{
    public const AVATAR = 'avatar';

    public const BANNER = 'banner';

    /** Tamanho final. Banner na proporção do cabeçalho web (1200 × 280), em 2x. */
    private const SIZES = [
        self::AVATAR => [512, 512],
        self::BANNER => [2400, 560],
    ];

    public function store(User $user, string $kind, UploadedFile $file): User
    {
        $bytes = $this->webp($file, $kind);

        $path = "{$kind}s/{$user->id}-".Str::random(10).'.webp';
        Storage::disk('public')->put($path, $bytes);

        $this->forgetFile($user, $kind);
        $user->forceFill([$this->column($kind) => '/storage/'.$path])->save();

        return $user;
    }

    /** Endireita, recorta no tamanho do tipo (avatar/banner) e devolve os bytes em WebP. */
    public function webp(UploadedFile $file, string $kind): string
    {
        [$width, $height] = self::SIZES[$kind];

        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw ValidationException::withMessages(['image' => 'Não deu para ler essa imagem. Tente JPG, PNG ou WebP.']);
        }

        $source = $this->upright($source, $file);
        $output = $this->cover($source, $width, $height);
        ob_start();
        imagewebp($output, null, 85);
        $bytes = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($output);

        return $bytes;
    }

    public function remove(User $user, string $kind): User
    {
        $this->forgetFile($user, $kind);
        $user->forceFill([$this->column($kind) => null])->save();

        return $user;
    }

    private function column(string $kind): string
    {
        return $kind === self::AVATAR ? 'avatar_url' : 'banner_url';
    }

    /** Apaga o arquivo atual, se foi enviado por aqui (avatar de login social fica de fora). */
    public function forgetFile(User $user, string $kind): void
    {
        $current = $user->{$this->column($kind)};
        if ($current && str_starts_with($current, '/storage/')) {
            Storage::disk('public')->delete(Str::after($current, '/storage/'));
        }
    }

    /** Foto de celular vem deitada com a orientação no EXIF; endireita quando a extensão exif existe. */
    private function upright(\GdImage $image, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data') || $file->getMimeType() !== 'image/jpeg') {
            return $image;
        }

        $angle = match ((int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : imagerotate($image, $angle, 0);
    }

    /** Recorte central que preenche o quadro, como object-fit: cover. */
    private function cover(\GdImage $source, int $width, int $height): \GdImage
    {
        $sw = imagesx($source);
        $sh = imagesy($source);
        $scale = max($width / $sw, $height / $sh);
        $cropW = (int) round($width / $scale);
        $cropH = (int) round($height / $scale);
        $x = (int) floor(($sw - $cropW) / 2);
        $y = (int) floor(($sh - $cropH) / 2);

        $out = imagecreatetruecolor($width, $height);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagecopyresampled($out, $source, 0, 0, $x, $y, $width, $height, $cropW, $cropH);

        return $out;
    }
}
