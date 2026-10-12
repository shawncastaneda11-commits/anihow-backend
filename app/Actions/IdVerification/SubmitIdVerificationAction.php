<?php

namespace App\Actions\IdVerification;

use App\Enums\IdDocumentType;
use App\Enums\IdVerificationStatus;
use App\Models\IdVerification;
use App\Models\User;
use App\Support\ImageVariants;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SubmitIdVerificationAction
{
    public function __construct(private ImageVariants $images) {}

    public function handle(User $user, IdDocumentType $type, UploadedFile $front, ?UploadedFile $selfie): IdVerification
    {
        if ($user->id_verified_at !== null) {
            throw ValidationException::withMessages(['front' => 'Your ID is already verified.']);
        }

        if ($user->idVerifications()->pending()->exists()) {
            throw ValidationException::withMessages(['front' => 'Your ID is already being reviewed.']);
        }

        $disk = Storage::disk('local');
        $directory = 'id-verifications/'.$user->id;
        $frontPath = $this->images->store($front, $directory, $disk);
        $selfiePath = $selfie === null ? null : $this->images->store($selfie, $directory, $disk);

        return DB::transaction(fn (): IdVerification => $user->idVerifications()->create([
            'id_type' => $type,
            'front_path' => $frontPath,
            'selfie_path' => $selfiePath,
            'status' => IdVerificationStatus::Pending,
            'consented_at' => now(),
        ]));
    }
}
