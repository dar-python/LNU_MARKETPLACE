<?php

namespace App\Http\Requests;

use App\Models\Listing;
use App\Support\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

class StoreListingImageRequest extends FormRequest
{
    public const MAX_FILE_SIZE_KB = 10240;

    public const MAX_IMAGES_PER_LISTING = 10;

    public function authorize(): bool
    {
        $listing = $this->route('listing');
        $userId = $this->user()?->id;

        return $listing instanceof Listing
            && $userId !== null
            && (int) $listing->user_id === (int) $userId;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_FILE_SIZE_KB, 'required_without:images'],
            'images' => ['nullable', 'array', 'max:1', 'required_without:image'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_FILE_SIZE_KB],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $listing = $this->route('listing');

            if (! $listing instanceof Listing) {
                return;
            }

            if ((int) $listing->listingImages()->count() >= self::MAX_IMAGES_PER_LISTING) {
                $validator->errors()->add(
                    'image',
                    'A listing may not have more than '.self::MAX_IMAGES_PER_LISTING.' images.'
                );
            }
        });
    }

    public function uploadedImage(): ?UploadedFile
    {
        $image = $this->file('image');
        if ($image instanceof UploadedFile) {
            return $image;
        }

        $images = $this->file('images');

        return is_array($images) && $images !== [] && $images[0] instanceof UploadedFile
            ? $images[0]
            : null;
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            ApiResponse::error('Forbidden.', null, 403)
        );
    }
}
